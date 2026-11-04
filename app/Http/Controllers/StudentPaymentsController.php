<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\MidtransSnapService;
use App\Services\PaymentGatewaySettings;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class StudentPaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->role === 'mahasiswa', 403);

        $studentId = (string) $user->legacy_id;
        $ukt = DB::connection('simak_main')->table('s1m4k_pembayaranukt as payment')
            ->leftJoin('s1m4k_periode as period', function ($join): void {
                $join->on('payment.tahun', '=', 'period.tahun')->where('period.jenis', '=', 'ukt');
            })
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('payment.id_mahasiswa', $studentId)
            ->select([
                'payment.id as payment_id',
                'payment.tahun',
                'payment.nominal',
                'payment.lunas',
                'payment.nama_bank',
                'payment.trx_id',
                'period.nama_periode',
                'period.tanggal_mulai',
                'period.tanggal_akhir',
                'student.nim',
                'student.nama_mahasiswa',
                'program.nama_program_studi',
                'faculty.nama_fakultas',
                DB::raw("'ukt' as jenis"),
            ]);
        $ipi = DB::connection('simak_main')->table('s1m4k_pembayaranipi as payment')
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('payment.id_mahasiswa', $studentId)
            ->select([
                'payment.id as payment_id',
                DB::raw('NULL as tahun'),
                'payment.nominal',
                'payment.lunas',
                'payment.nama_bank',
                'payment.trx_id',
                DB::raw("'Iuran Pengembangan Institusi' as nama_periode"),
                DB::raw('NULL as tanggal_mulai'),
                DB::raw('NULL as tanggal_akhir'),
                'student.nim',
                'student.nama_mahasiswa',
                'program.nama_program_studi',
                'faculty.nama_fakultas',
                DB::raw("'ipi' as jenis"),
            ]);
        $records = DB::connection('simak_main')->query()
            ->fromSub($ukt->unionAll($ipi), 'student_payments')
            ->orderByDesc('tahun')
            ->orderBy('jenis')
            ->paginate(25)
            ->withQueryString();
        $connection = DB::connection('simak_main');
        $gatewaySettings = Schema::connection('simak_main')->hasTable('simak_payment_gateways')
            ? app(PaymentGatewaySettings::class)->get('midtrans')
            : null;
        $midtransEnabled = (bool) ($gatewaySettings['enabled'] ?? false)
            && Schema::connection('simak_main')->hasTable('simak_payment_transactions');
        $configuredServiceFee = $midtransEnabled ? (int) $gatewaySettings['service_fee'] : 0;
        $latestMidtrans = collect();
        $paymentsByType = $records->getCollection()->groupBy('jenis');
        $hasTransactionTable = Schema::connection('simak_main')->hasTable('simak_payment_transactions');
        $hasTransactionServiceFee = $hasTransactionTable
            && Schema::connection('simak_main')->hasColumn('simak_payment_transactions', 'service_fee');
        if ($hasTransactionTable && $paymentsByType->isNotEmpty()) {
            $transactionColumns = ['payment_type', 'payment_id', 'order_id', 'transaction_status', 'redirect_url'];
            if ($hasTransactionServiceFee) {
                $transactionColumns[] = 'service_fee';
            }
            $latestMidtrans = $connection->table('simak_payment_transactions')
                ->where('gateway', 'midtrans')
                ->where('student_id', $studentId)
                ->where(function ($query) use ($paymentsByType): void {
                    foreach ($paymentsByType as $type => $payments) {
                        $query->orWhere(function ($subQuery) use ($type, $payments): void {
                            $subQuery->where('payment_type', $type)
                                ->whereIn('payment_id', $payments->pluck('payment_id')->all());
                        });
                    }
                })
                ->orderByDesc('created_at')
                ->orderByDesc('updated_at')
                ->get($transactionColumns)
                ->unique(static fn (object $transaction): string => $transaction->payment_type.':'.$transaction->payment_id)
                ->keyBy(static fn (object $transaction): string => $transaction->payment_type.':'.$transaction->payment_id);
        }
        $records->getCollection()->each(function (object $payment) use ($midtransEnabled, $configuredServiceFee, $latestMidtrans): void {
            $transactionKey = $payment->jenis.':'.$payment->payment_id;
            $transaction = $latestMidtrans->get($transactionKey);
            $payment->midtrans_redirect_url = $transaction?->redirect_url;
            $payment->midtrans_order_id = $transaction?->order_id;
            $payment->midtrans_status = $transaction?->transaction_status;
            $isManualPayment = (int) $payment->lunas === 1
                && in_array(strtoupper((string) $payment->nama_bank), ['BEASISWA', 'PEMDA', 'MANUAL'], true);
            $payment->payment_reference = $isManualPayment ? $payment->trx_id : $payment->midtrans_order_id;
            $payment->payment_method = $isManualPayment
                ? ([
                    'BEASISWA' => 'Beasiswa',
                    'PEMDA' => 'Bantuan pemerintah daerah',
                    'MANUAL' => 'Pembayaran manual',
                ][strtoupper((string) $payment->nama_bank)])
                : ($payment->midtrans_order_id !== null ? 'Midtrans' : null);
            $pending = in_array($transaction?->transaction_status, ['pending', 'capture'], true);
            $payment->can_pay_midtrans = $midtransEnabled
                && (int) $payment->lunas !== 1
                && ($payment->jenis === 'ipi' || $this->isUktPaymentPeriodOpen($payment->tanggal_mulai, $payment->tanggal_akhir))
                && ! $pending;
            $payment->can_resume_midtrans = $midtransEnabled
                && (int) $payment->lunas !== 1
                && $pending
                && is_string($payment->midtrans_redirect_url)
                && $payment->midtrans_redirect_url !== '';
            $payment->can_check_midtrans = (int) $payment->lunas !== 1
                && in_array($payment->midtrans_status, ['pending', 'capture'], true);
            $payment->service_fee = $payment->payment_method === 'Midtrans'
                ? (int) ($transaction?->service_fee ?? 0)
                : ($payment->can_pay_midtrans ? $configuredServiceFee : 0);
            $payment->total_amount = (int) $payment->nominal + $payment->service_fee;
        });

        return view('student-payments.index', [
            'title' => 'Tagihan Uang Kuliah & Iuran Pengembangan Institusi',
            'records' => $records,
        ]);
    }

    public function printBilling(Request $request, string $type, string $payment): View
    {
        $user = $request->user();
        abort_unless($user?->role === 'mahasiswa', 403);
        abort_unless(in_array($type, ['ukt', 'ipi'], true), 404);

        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $query = DB::connection('simak_main')->table($table.' as payment')
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'program.id_fakultas', '=', 'faculty.id_fakultas')
            ->where('payment.id', $payment)
            ->where('payment.id_mahasiswa', (string) $user->legacy_id)
            ->select([
                'payment.id_mahasiswa', 'payment.nominal', 'payment.lunas', 'payment.tanggal_bayar',
                'payment.nama_bank', 'payment.trx_id',
                'student.nim', 'student.nama_mahasiswa',
                'program.nama_program_studi', 'faculty.nama_fakultas',
            ]);
        if ($type === 'ukt') {
            $query->leftJoin('s1m4k_periode as period', function ($join): void {
                $join->on('payment.tahun', '=', 'period.tahun')->where('period.jenis', '=', 'ukt');
            })->addSelect('payment.tahun', 'period.nama_periode');
        }

        $record = $query->first();
        abort_if($record === null, 404);
        $isPaid = (int) $record->lunas === 1;
        abort_unless($isPaid, 404);
        $transaction = null;
        $hasTransactionTable = Schema::connection('simak_main')->hasTable('simak_payment_transactions');
        if ($hasTransactionTable) {
            $transactionColumns = ['order_id', 'transaction_id', 'gross_amount'];
            if (Schema::connection('simak_main')->hasColumn('simak_payment_transactions', 'service_fee')) {
                $transactionColumns[] = 'service_fee';
            }
            $candidate = DB::connection('simak_main')->table('simak_payment_transactions')
                ->where('gateway', 'midtrans')
                ->where('payment_type', $type)
                ->where('payment_id', $payment)
                ->where('student_id', (string) $record->id_mahasiswa)
                ->whereIn('transaction_status', ['settlement', 'capture'])
                ->orderByDesc('created_at')
                ->first($transactionColumns);
            if ($candidate !== null
                && (int) $candidate->gross_amount === (int) $record->nominal + (int) ($candidate->service_fee ?? 0)) {
                $transaction = $candidate;
            }
        }
        $paymentMethod = $transaction !== null
            ? 'midtrans'
            : strtolower((string) $record->nama_bank);
        abort_unless($transaction !== null || in_array($paymentMethod, ['beasiswa', 'pemda', 'manual'], true), 404);

        return view('finance.billing-print', [
            'billing' => [
                'name' => (string) $record->nama_mahasiswa,
                'nim' => (string) $record->nim,
                'faculty' => $record->nama_fakultas,
                'program' => $record->nama_program_studi,
                'description' => $type === 'ukt'
                    ? (string) ($record->nama_periode ?: 'Uang Kuliah').' · '.$record->tahun
                    : 'Iuran Pengembangan Institusi',
                'amount' => (int) $record->nominal,
                'service_fee' => $paymentMethod === 'midtrans' ? (int) ($transaction->service_fee ?? 0) : 0,
                'total_amount' => (int) $record->nominal + ($paymentMethod === 'midtrans' ? (int) ($transaction->service_fee ?? 0) : 0),
                'payment_method' => $paymentMethod,
                'order_id' => $transaction?->order_id ?? ($record->trx_id ?: '-'),
                'transaction_id' => filled($transaction?->transaction_id) ? (string) $transaction->transaction_id : '-',
                'is_paid' => $isPaid,
                'paid_at' => $record->tanggal_bayar,
            ],
        ]);
    }

    public function createMidtransCheckout(
        Request $request,
        string $payment,
        MidtransSnapService $midtrans,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user?->role === 'mahasiswa', 403);
        $validated = $request->validate(['jenis' => ['required', 'in:ukt,ipi']]);
        $type = $validated['jenis'];
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $connection = DB::connection('simak_main');
        $record = $connection->table($table.' as payment')
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->where('payment.id', $payment)
            ->where('payment.id_mahasiswa', (string) $user->legacy_id)
            ->first([
                'payment.*',
                'student.nama_mahasiswa',
            ]);
        abort_if($record === null, 404);
        if ((int) $record->lunas === 1) {
            throw ValidationException::withMessages(['payment' => 'Tagihan ini sudah lunas.']);
        }
        if ($type === 'ukt') {
            $period = $connection->table('s1m4k_periode')
                ->where('tahun', $record->tahun)
                ->where('jenis', 'ukt')
                ->first(['tanggal_mulai', 'tanggal_akhir']);
            if ($period === null || ! $this->isUktPaymentPeriodOpen($period->tanggal_mulai, $period->tanggal_akhir)) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran uang kuliah hanya dapat dilakukan selama periode pembayaran.']);
            }
        }

        if (Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
            $pending = $connection->table('simak_payment_transactions')
                ->where('gateway', 'midtrans')
                ->where('payment_type', $type)
                ->where('payment_id', $payment)
                ->where('student_id', (string) $user->legacy_id)
                ->whereIn('transaction_status', ['pending', 'capture'])
                ->orderByDesc('created_at')
                ->first(['redirect_url']);
            if ($pending !== null) {
                try {
                    $currentStatus = $midtrans->checkStatusForPayment($type, $payment, (string) $user->legacy_id);
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
                }
                if (in_array($currentStatus, ['pending', 'capture'], true)
                    && is_string($pending->redirect_url)
                    && (
                        str_starts_with($pending->redirect_url, 'https://app.midtrans.com/')
                        || str_starts_with($pending->redirect_url, 'https://app.sandbox.midtrans.com/')
                    )) {
                    return redirect()->away($pending->redirect_url);
                }
                $record = $connection->table($table)->where('id', $payment)->first(['lunas']);
                if ((int) $record->lunas === 1) {
                    throw ValidationException::withMessages(['payment' => 'Tagihan ini sudah lunas.']);
                }
            }
        }

        try {
            $checkout = $midtrans->createCheckout(
                $type,
                (string) $record->id,
                (string) $user->legacy_id,
                (string) $record->nama_mahasiswa,
                (int) $record->nominal,
                ($type === 'ukt' ? 'Uang Kuliah '.$record->tahun : 'Iuran Pengembangan Institusi').' - '.$record->nama_mahasiswa,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        $activityLogger->record($user, 'Pembayaran Mahasiswa', 'Membuat checkout Midtrans '.$type, [
            'nominal' => (int) $record->nominal,
            'order_id' => $checkout['order_id'],
        ], $payment);

        return redirect()->away($checkout['redirect_url']);
    }

    public function checkMidtransStatus(
        Request $request,
        string $payment,
        MidtransSnapService $midtrans,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user?->role === 'mahasiswa', 403);
        $validated = $request->validate(['jenis' => ['required', 'in:ukt,ipi']]);
        $type = $validated['jenis'];
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $record = DB::connection('simak_main')->table($table)
            ->where('id', $payment)
            ->where('id_mahasiswa', (string) $user->legacy_id)
            ->first(['lunas']);
        abort_if($record === null, 404);
        if ((int) $record->lunas === 1) {
            return back()->with('status', 'Tagihan ini sudah tercatat lunas.');
        }

        try {
            $status = $midtrans->checkStatusForPayment($type, $payment, (string) $user->legacy_id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        $activityLogger->record($user, 'Pembayaran Mahasiswa', 'Memeriksa status Midtrans '.$type, [
            'status' => $status,
        ], $payment);

        return back()->with('status', 'Status transaksi Midtrans diperbarui: '.$status.'.');
    }

    private function isUktPaymentPeriodOpen(?string $startDate, ?string $endDate): bool
    {
        if (! filled($startDate) || ! filled($endDate)) {
            return false;
        }

        try {
            $today = today();

            return $today->betweenIncluded(
                Carbon::parse($startDate),
                Carbon::parse($endDate),
            );
        } catch (InvalidFormatException) {
            return false;
        }
    }
}
