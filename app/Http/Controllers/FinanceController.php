<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\MidtransSnapService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    private const ROLES = ['admin', 'keuangan'];

    private const ELIGIBLE_STUDENT_STATUSES = ['A', 'C', 'G', 'M', 'N', 'Z'];

    private const UKT_CATEGORY_STUDENT_STATUSES = ['A', 'C', 'N', 'M'];

    public function periods(Request $request): View
    {
        $this->authorizeFinance($request);
        $connection = DB::connection('simak_main');
        $periods = $connection->table('s1m4k_periode')
            ->where('jenis', 'ukt')
            ->orderByDesc('tahun')
            ->paginate(25)
            ->withQueryString();
        $calendarYears = $connection->table('s1m4k_kalender')
            ->whereNotNull('tahun')
            ->where('tahun', '<>', '')
            ->select('tahun', 'namatahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->get();

        return view('finance.index', [
            'module' => 'periods',
            'title' => 'Periode Uang Kuliah',
            'records' => $periods,
            'calendarYears' => $calendarYears,
        ]);
    }

    public function dashboard(Request $request): View
    {
        $this->authorizeFinanceDashboard($request);
        $user = $request->user();
        $facultyId = $user->role === 'pimfak' ? trim((string) data_get($user, 'id_fakultas')) : null;
        abort_if($user->role === 'pimfak' && $facultyId === '', 403);
        $periods = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('jenis', 'ukt')->orderByDesc('tahun')->get(['tahun', 'nama_periode']);
        $selectedYear = (string) $request->query('tahun', $periods->first()?->tahun ?? '');
        if ($selectedYear !== '') {
            validator(['tahun' => $selectedYear], ['tahun' => ['regex:/^\d{5}$/']])->validate();
        }

        $ukt = $this->dashboardPaymentSummary('ukt', $selectedYear, $facultyId);
        $ipi = $this->dashboardPaymentSummary('ipi', null, $facultyId);
        $facultySummary = $facultyId === null ? $this->financeTotalsByFaculty($selectedYear) : collect();

        return view('finance.dashboard', [
            'title' => 'Dashboard Keuangan',
            'periods' => $periods,
            'selectedYear' => $selectedYear,
            'ukt' => $ukt,
            'ipi' => $ipi,
            'facultySummary' => $facultySummary,
            'facultyName' => $facultyId === null ? null : DB::connection('simak_main')->table('s1m4k_masterfakultas')
                ->where('id_fakultas', $facultyId)->value('nama_fakultas'),
            'scopeLabel' => $facultyId === null ? 'Seluruh universitas' : 'Fakultas '.(DB::connection('simak_main')->table('s1m4k_masterfakultas')
                ->where('id_fakultas', $facultyId)->value('nama_fakultas') ?? 'sesuai akun'),
        ]);
    }

    private function dashboardPaymentSummary(string $type, ?string $year, ?string $facultyId): array
    {
        $query = $this->financeAggregateQuery($type);
        if ($type === 'ukt' && filled($year)) {
            $query->where('payment.tahun', $year);
        } elseif ($type === 'ukt') {
            $query->whereRaw('1 = 0');
        }
        if ($facultyId !== null) {
            $query->where('faculty.id_fakultas', $facultyId);
        }
        $summary = $query->selectRaw(
            'COUNT(*) as jumlah_tagihan, '.
            'SUM(CASE WHEN payment.lunas = 1 THEN 1 ELSE 0 END) as jumlah_lunas, '.
            'SUM(CASE WHEN payment.lunas = 0 THEN 1 ELSE 0 END) as jumlah_belum_lunas, '.
            'SUM(CASE WHEN payment.lunas = 1 THEN payment.nominal ELSE 0 END) as nominal_lunas, '.
            'SUM(CASE WHEN payment.lunas = 0 THEN payment.nominal ELSE 0 END) as nominal_belum_lunas'
        )->first();

        return [
            'jumlah_tagihan' => (int) ($summary->jumlah_tagihan ?? 0),
            'jumlah_lunas' => (int) ($summary->jumlah_lunas ?? 0),
            'jumlah_belum_lunas' => (int) ($summary->jumlah_belum_lunas ?? 0),
            'nominal_lunas' => (int) ($summary->nominal_lunas ?? 0),
            'nominal_belum_lunas' => (int) ($summary->nominal_belum_lunas ?? 0),
        ];
    }

    private function financeTotalsByFaculty(?string $year): Collection
    {
        $rows = collect();
        foreach (['ukt', 'ipi'] as $type) {
            $query = $this->financeAggregateQuery($type);
            if ($type === 'ukt') {
                if (filled($year)) {
                    $query->where('payment.tahun', $year);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
            $rows = $rows->merge($query->selectRaw(
                'faculty.id_fakultas, faculty.nama_fakultas, '.
                'SUM(CASE WHEN payment.lunas = 1 THEN payment.nominal ELSE 0 END) as nominal_lunas, '.
                'SUM(CASE WHEN payment.lunas = 0 THEN payment.nominal ELSE 0 END) as nominal_belum_lunas, '.
                'SUM(CASE WHEN payment.lunas = 1 THEN 1 ELSE 0 END) as jumlah_lunas, '.
                'SUM(CASE WHEN payment.lunas = 0 THEN 1 ELSE 0 END) as jumlah_belum_lunas'
            )->groupBy('faculty.id_fakultas', 'faculty.nama_fakultas')->get()->map(function (object $row) use ($type): array {
                return [
                    'id_fakultas' => $row->id_fakultas,
                    'nama_fakultas' => $row->nama_fakultas ?: 'Fakultas belum ditetapkan',
                    $type.'_nominal_lunas' => (int) $row->nominal_lunas,
                    $type.'_nominal_belum_lunas' => (int) $row->nominal_belum_lunas,
                    $type.'_jumlah_lunas' => (int) $row->jumlah_lunas,
                    $type.'_jumlah_belum_lunas' => (int) $row->jumlah_belum_lunas,
                ];
            }));
        }

        return $rows->groupBy('id_fakultas')->map(function (Collection $items): array {
            $ukt = $items->first(fn (array $item): bool => array_key_exists('ukt_nominal_lunas', $item)) ?? [];
            $ipi = $items->first(fn (array $item): bool => array_key_exists('ipi_nominal_lunas', $item)) ?? [];

            return [
                'nama_fakultas' => $items->first()['nama_fakultas'],
                'ukt_nominal_lunas' => $ukt['ukt_nominal_lunas'] ?? 0,
                'ukt_nominal_belum_lunas' => $ukt['ukt_nominal_belum_lunas'] ?? 0,
                'ipi_nominal_lunas' => $ipi['ipi_nominal_lunas'] ?? 0,
                'ipi_nominal_belum_lunas' => $ipi['ipi_nominal_belum_lunas'] ?? 0,
                'jumlah_tagihan' => ($ukt['ukt_jumlah_lunas'] ?? 0) + ($ukt['ukt_jumlah_belum_lunas'] ?? 0)
                    + ($ipi['ipi_jumlah_lunas'] ?? 0) + ($ipi['ipi_jumlah_belum_lunas'] ?? 0),
            ];
        })->sortBy('nama_fakultas')->values();
    }

    private function financeAggregateQuery(string $type): Builder
    {
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';

        return DB::connection('simak_main')->table($table.' as payment')
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'faculty.id_fakultas', '=', DB::raw("COALESCE(NULLIF(student.id_fakultas, ''), program.id_fakultas)"));
    }

    private function cohortFromNim(mixed $nim): string
    {
        if ((! is_string($nim) && ! is_int($nim)) || preg_match('/^\d{11}$/', (string) $nim) !== 1) {
            return '-';
        }

        return '20'.substr((string) $nim, 4, 2);
    }

    public function storePeriod(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate([
            'tahun' => ['required', 'regex:/^\d{5}$/'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);
        $calendar = DB::connection('simak_main')->table('s1m4k_kalender')
            ->where('tahun', $validated['tahun'])
            ->orderBy('id_prodi')
            ->first(['tahun', 'namatahun']);
        abort_if($calendar === null, 422, 'Tahun akademik tidak ditemukan.');

        $record = [
            'id_periode' => (string) Str::uuid(),
            'nama_periode' => (string) $calendar->namatahun,
            'tahun' => (string) $calendar->tahun,
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_akhir' => $validated['tanggal_akhir'],
            'jenis' => 'ukt',
            'aktif' => 'N',
        ];
        DB::connection('simak_main')->table('s1m4k_periode')->insert($record);
        $activityLogger->record($request->user(), 'Keuangan', 'Menambahkan periode uang kuliah', $record, $record['id_periode']);

        return redirect()->route('finance.periods.index')->with('status', 'Periode uang kuliah berhasil ditambahkan.');
    }

    public function updatePeriod(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate([
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);
        $query = DB::connection('simak_main')->table('s1m4k_periode')
            ->where('id_periode', $period)
            ->where('jenis', 'ukt');
        $record = $query->first();
        abort_if($record === null, 404);
        $query->update($validated);
        $activityLogger->record($request->user(), 'Keuangan', 'Mengubah tanggal periode uang kuliah', [
            'sebelum' => ['tanggal_mulai' => $record->tanggal_mulai, 'tanggal_akhir' => $record->tanggal_akhir],
            'sesudah' => $validated,
        ], $period);

        return redirect()->route('finance.periods.index')->with('status', 'Tanggal periode uang kuliah berhasil diperbarui.');
    }

    public function activatePeriod(Request $request, string $period, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $period, $activityLogger, $request): void {
            $record = $connection->table('s1m4k_periode')
                ->where('id_periode', $period)
                ->where('jenis', 'ukt')
                ->lockForUpdate()
                ->first();
            abort_if($record === null, 404);
            $previous = $connection->table('s1m4k_periode')->where('jenis', 'ukt')->where('aktif', 'Y')
                ->pluck('id_periode')->all();
            $connection->table('s1m4k_periode')->where('jenis', 'ukt')->update(['aktif' => 'N']);
            $connection->table('s1m4k_periode')->where('id_periode', $period)->update(['aktif' => 'Y']);
            $activityLogger->record($request->user(), 'Keuangan', 'Mengaktifkan periode uang kuliah', [
                'sebelumnya' => $previous,
                'aktif' => $period,
            ], $period);
        });

        return redirect()->route('finance.periods.index')->with('status', 'Periode uang kuliah berhasil diaktifkan.');
    }

    public function categories(Request $request): View
    {
        $this->authorizeFinance($request);
        $validated = $request->validate(['prodi' => ['nullable', 'string', 'max:50']]);

        return view('finance.index', [
            'module' => 'categories',
            'title' => 'Kategori Uang Kuliah',
            'records' => null,
            'programs' => DB::connection('simak_main')->table('s1m4k_masterprodi')
                ->orderBy('nama_program_studi')->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan']),
            'selectedProgram' => $validated['prodi'] ?? '',
        ]);
    }

    public function categoryData(Request $request): JsonResponse
    {
        $this->authorizeFinance($request);
        $request->validate([
            'prodi' => ['nullable', 'string', 'max:50'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:6'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $this->categoryQuery((string) $request->input('prodi', ''));
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('m.nim', 'like', '%'.$search.'%')
                    ->orWhere('m.nama_mahasiswa', 'like', '%'.$search.'%');
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = ['m.nim', 'm.nim', 'm.nama_mahasiswa', 'p.nama_program_studi', 'm.nama_status_mahasiswa', 'm.kategori_ukt', 'm.nominal_ukt'];
        $sortIndex = (int) $request->input('order.0.column', 0);
        $sortDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->orderBy($sortFields[$sortIndex], $sortDirection)
            ->offset($start)->limit($length)->get();

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $records->map(fn (object $record): array => [
                'nim' => $record->nim,
                'angkatan' => $this->cohortFromNim($record->nim),
                'nama_mahasiswa' => $record->nama_mahasiswa,
                'nama_program_studi' => $record->nama_program_studi,
                'nama_status_mahasiswa' => $record->nama_status_mahasiswa,
                'kategori_ukt' => $record->kategori_ukt,
                'nominal_ukt' => $record->nominal_ukt,
            ]),
        ]);
    }

    public function categoryUploadTemplate(Request $request): StreamedResponse
    {
        $this->authorizeFinanceWrite($request);

        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Uang Kuliah');
            $sheet->fromArray([['No', 'NPM', 'Kategori Uang Kuliah', 'Nominal Uang Kuliah']]);
            $sheet->getStyle('B1:B1000')->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            foreach (range('A', 'D') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');

            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Petunjuk');
            $instructions->fromArray([
                ['Kolom', 'Keterangan'],
                ['NPM', 'Wajib: NPM yang terdaftar pada master mahasiswa SIMAK. Kolom ini berformat teks.'],
                ['Kategori Uang Kuliah', 'Wajib: kode kategori uang kuliah, maksimal 2 karakter.'],
                ['Nominal Uang Kuliah', 'Wajib: nominal tagihan berupa angka.'],
                ['Catatan', 'Isi satu mahasiswa per baris pada sheet Uang Kuliah. Baris kosong dilewati.'],
            ]);
            $instructions->getStyle('A1:B1')->getFont()->setBold(true);
            $instructions->getColumnDimension('A')->setAutoSize(true);
            $instructions->getColumnDimension('B')->setAutoSize(true);
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-kategori-uang-kuliah.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function uploadCategories(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240']]);
        $spreadsheet = IOFactory::load($validated['file']->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $connection = DB::connection('simak_main');
        $updated = 0;
        $notFound = 0;
        $skipped = 0;
        $connection->transaction(function () use ($sheet, $connection, &$updated, &$notFound, &$skipped): void {
            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $nim = trim((string) $sheet->getCell("B{$row}")->getValue());
                $category = trim((string) $sheet->getCell("C{$row}")->getValue());
                $amountRaw = trim((string) $sheet->getCell("D{$row}")->getFormattedValue());
                $amountText = preg_replace('/\D+/', '', $amountRaw) ?? '';
                if ($nim === '' && $category === '' && $amountText === '') {
                    $skipped++;

                    continue;
                }
                if ($nim === '' || $category === '' || $amountText === '' || strlen($category) > 2) {
                    $skipped++;

                    continue;
                }
                if (ctype_digit($nim)) {
                    $nim = str_pad($nim, 11, '0', STR_PAD_LEFT);
                }
                $student = $connection->table('s1m4k_mastermhs')->where('nim', $nim)->first(['id_mahasiswa']);
                if ($student === null) {
                    $notFound++;

                    continue;
                }
                $updated += $connection->table('s1m4k_mastermhs')->where('id_mahasiswa', $student->id_mahasiswa)
                    ->update(['kategori_ukt' => $category, 'nominal_ukt' => (int) $amountText]);
            }
        });
        $activityLogger->record($request->user(), 'Keuangan', 'Mengunggah kategori uang kuliah', [
            'berhasil' => $updated,
            'tidak_ditemukan' => $notFound,
            'dilewati' => $skipped,
        ]);

        return redirect()->route('finance.categories.index')->with(
            'status',
            "Upload selesai: {$updated} data diperbarui, {$notFound} NIM tidak ditemukan, {$skipped} baris dilewati."
        );
    }

    public function ukt(Request $request): View
    {
        return $this->payments($request, 'ukt');
    }

    public function ipi(Request $request): View
    {
        return $this->payments($request, 'ipi');
    }

    public function midtransTransactions(Request $request): View
    {
        $this->authorizeFinance($request);
        abort_unless(Schema::connection('simak_main')->hasTable('simak_payment_transactions'), 503,
            'Tabel riwayat transaksi Midtrans belum tersedia. Jalankan SQL konfigurasi pembayaran terlebih dahulu.');

        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_akhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $startDate = $validated['tanggal_mulai'] ?? today()->subDays(29)->toDateString();
        $endDate = $validated['tanggal_akhir'] ?? today()->toDateString();
        $search = trim((string) ($validated['cari'] ?? ''));
        $schema = Schema::connection('simak_main');
        $hasManualBills = $schema->hasTable('simak_manual_bills');
        $hasUktPayments = $schema->hasTable('s1m4k_pembayaranukt');
        $hasIpiPayments = $schema->hasTable('s1m4k_pembayaranipi');
        $paidConditions = ["payment_transaction.transaction_status = 'settlement'"];
        if ($hasManualBills) {
            $paidConditions[] = "manual_bill.checkout_status = 'paid'";
        }
        if ($hasUktPayments) {
            $paidConditions[] = "(payment_transaction.payment_type = 'ukt' AND ukt_payment.lunas = 1)";
        }
        if ($hasIpiPayments) {
            $paidConditions[] = "(payment_transaction.payment_type = 'ipi' AND ipi_payment.lunas = 1)";
        }
        $query = DB::connection('simak_main')->table('simak_payment_transactions as payment_transaction')
            ->leftJoin('s1m4k_mastermhs as student', 'payment_transaction.student_id', '=', 'student.id_mahasiswa')
            ->when($hasManualBills, fn (Builder $builder) => $builder->leftJoin(
                'simak_manual_bills as manual_bill',
                'payment_transaction.payment_id',
                '=',
                'manual_bill.id'
            ))
            ->when($hasUktPayments, fn (Builder $builder) => $builder->leftJoin(
                's1m4k_pembayaranukt as ukt_payment',
                function (Builder $join): void {
                    $join->on('payment_transaction.payment_id', '=', 'ukt_payment.id')
                        ->whereColumn('payment_transaction.student_id', 'ukt_payment.id_mahasiswa')
                        ->where('payment_transaction.payment_type', '=', 'ukt');
                }
            ))
            ->when($hasIpiPayments, fn (Builder $builder) => $builder->leftJoin(
                's1m4k_pembayaranipi as ipi_payment',
                function (Builder $join): void {
                    $join->on('payment_transaction.payment_id', '=', 'ipi_payment.id')
                        ->whereColumn('payment_transaction.student_id', 'ipi_payment.id_mahasiswa')
                        ->where('payment_transaction.payment_type', '=', 'ipi');
                }
            ))
            ->where('payment_transaction.gateway', 'midtrans')
            ->where('payment_transaction.created_at', '>=', $startDate.' 00:00:00')
            ->where('payment_transaction.created_at', '<', Carbon::parse($endDate)->addDay()->toDateString().' 00:00:00')
            ->when($search !== '', function (Builder $builder) use ($search, $hasManualBills): void {
                $like = '%'.$search.'%';
                $builder->where(function (Builder $matches) use ($like, $hasManualBills): void {
                    $matches->where('student.nim', 'like', $like)
                        ->orWhere('student.nama_mahasiswa', 'like', $like)
                        ->orWhere('payment_transaction.order_id', 'like', $like)
                        ->orWhere('payment_transaction.transaction_id', 'like', $like);
                    if ($hasManualBills) {
                        $matches->orWhere('manual_bill.payer_name', 'like', $like)
                            ->orWhere('manual_bill.payer_email', 'like', $like)
                            ->orWhere('manual_bill.description', 'like', $like);
                    }
                });
            })
            ->select([
                'payment_transaction.id', 'payment_transaction.payment_type',
                'payment_transaction.student_id', 'payment_transaction.order_id', 'payment_transaction.transaction_id',
                'payment_transaction.gross_amount', 'payment_transaction.transaction_status',
                'payment_transaction.created_at',
                'student.nim', 'student.nama_mahasiswa',
            ])
            ->addSelect(DB::raw('CASE WHEN '.implode(' OR ', $paidConditions).' THEN 1 ELSE 0 END AS is_paid'))
            ->when($hasManualBills, fn (Builder $builder) => $builder->addSelect([
                'manual_bill.description as manual_description',
                'manual_bill.payer_name',
                'manual_bill.payer_email',
            ]))
            ->orderByDesc('payment_transaction.created_at')
            ->orderByDesc('payment_transaction.id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.midtrans-transactions', [
            'title' => 'Riwayat Transaksi Midtrans',
            'transactions' => $query,
            'filters' => [
                'tanggal_mulai' => $startDate,
                'tanggal_akhir' => $endDate,
                'cari' => $search,
            ],
        ]);
    }

    public function manualBills(Request $request): View
    {
        $this->authorizeFinance($request);
        $this->assertManualBillingSchema();

        $bills = DB::connection('simak_main')->table('simak_manual_bills as manual_bill')
            ->leftJoin('simak_payment_transactions as payment_transaction', function (Builder $join): void {
                $join->on('payment_transaction.payment_id', '=', 'manual_bill.id')
                    ->where('payment_transaction.payment_type', '=', 'manual')
                    ->where('payment_transaction.gateway', '=', 'midtrans');
            })
            ->select([
                'manual_bill.id', 'manual_bill.description', 'manual_bill.payer_name',
                'manual_bill.payer_email', 'manual_bill.payer_phone', 'manual_bill.amount',
                'manual_bill.expires_at', 'manual_bill.checkout_status', 'manual_bill.created_at',
                'payment_transaction.order_id', 'payment_transaction.redirect_url',
                'payment_transaction.gross_amount', 'payment_transaction.service_fee',
                'payment_transaction.transaction_status',
            ])
            ->selectRaw(
                "CASE WHEN manual_bill.checkout_status = 'paid' OR payment_transaction.transaction_status = 'settlement' THEN 1 ELSE 0 END AS is_paid"
            )
            ->orderByDesc('manual_bill.created_at')
            ->paginate(25)
            ->withQueryString();

        return view('finance.manual-bills', [
            'title' => 'Billing Midtrans',
            'bills' => $bills,
        ]);
    }

    public function createManualBill(
        Request $request,
        MidtransSnapService $midtrans,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $this->authorizeFinanceWrite($request);
        $this->assertManualBillingSchema();

        $validated = $request->validate([
            'payer_name' => ['required', 'string', 'max:100'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'expires_in_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);
        $billId = (string) Str::uuid();
        $now = now();
        $payerEmail = filled($validated['payer_email'] ?? null) ? trim($validated['payer_email']) : null;
        $payerPhone = filled($validated['payer_phone'] ?? null) ? trim($validated['payer_phone']) : null;
        $connection = DB::connection('simak_main');
        $connection->table('simak_manual_bills')->insert([
            'id' => $billId,
            'description' => trim($validated['description']),
            'payer_name' => trim($validated['payer_name']),
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
            'amount' => (int) $validated['amount'],
            'expires_at' => $now->copy()->addHours((int) $validated['expires_in_hours']),
            'checkout_status' => 'creating',
            'created_by' => (string) (data_get($request->user(), 'legacy_id') ?: $request->user()->id),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            $checkout = $midtrans->createCheckout(
                'manual',
                $billId,
                'MANUAL',
                trim($validated['payer_name']),
                (int) $validated['amount'],
                trim($validated['description']),
                (int) $validated['expires_in_hours'],
                $payerEmail,
                $payerPhone,
                route('public.home')
            );
        } catch (\RuntimeException $exception) {
            $connection->table('simak_manual_bills')->where('id', $billId)->delete();
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        $connection->table('simak_manual_bills')->where('id', $billId)->update([
            'expires_at' => $checkout['expires_at'],
            'checkout_status' => 'created',
            'updated_at' => now(),
        ]);
        $activityLogger->record($request->user(), 'Keuangan', 'Membuat billing manual Midtrans', [
            'deskripsi' => trim($validated['description']),
            'nominal' => (int) $validated['amount'],
            'masa_berlaku_jam' => (int) $validated['expires_in_hours'],
            'order_id' => $checkout['order_id'],
        ], $billId);

        return redirect()->route('finance.manual-bills.index')
            ->with('status', 'Billing manual berhasil dibuat. Salin tautan pembayaran untuk dibagikan.')
            ->with('checkout_url', $checkout['redirect_url']);
    }

    public function checkManualBillStatus(
        Request $request,
        string $bill,
        MidtransSnapService $midtrans,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $this->authorizeFinanceWrite($request);
        $this->assertManualBillingSchema();

        $record = DB::connection('simak_main')->table('simak_manual_bills')
            ->where('id', $bill)
            ->first(['checkout_status']);
        abort_if($record === null, 404);
        if ($record->checkout_status === 'paid') {
            return back()->with('status', 'Billing manual ini sudah tercatat lunas.');
        }

        try {
            $status = $midtrans->checkStatusForPayment('manual', $bill, 'MANUAL');
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        $activityLogger->record($request->user(), 'Keuangan', 'Memeriksa status billing manual Midtrans', [
            'status' => $status,
        ], $bill);

        return redirect()->route('finance.manual-bills.index')
            ->with('status', 'Status pembayaran Midtrans diperbarui: '.$status.'.');
    }

    public function printManualBill(Request $request, string $bill): View
    {
        $this->authorizeFinance($request);
        $this->assertManualBillingSchema();
        $record = DB::connection('simak_main')->table('simak_manual_bills as manual_bill')
            ->leftJoin('simak_payment_transactions as payment_transaction', function (Builder $join): void {
                $join->on('payment_transaction.payment_id', '=', 'manual_bill.id')
                    ->where('payment_transaction.payment_type', '=', 'manual')
                    ->where('payment_transaction.gateway', '=', 'midtrans');
            })
            ->where('manual_bill.id', $bill)
            ->first([
                'manual_bill.description', 'manual_bill.payer_name', 'manual_bill.payer_email',
                'manual_bill.payer_phone', 'manual_bill.amount', 'manual_bill.expires_at',
                'manual_bill.checkout_status', 'payment_transaction.order_id',
                'payment_transaction.redirect_url', 'payment_transaction.gross_amount',
                'payment_transaction.service_fee', 'payment_transaction.transaction_id',
                'payment_transaction.transaction_status',
            ]);
        abort_if($record === null, 404);
        abort_if($record->order_id === null, 404);

        $isPaid = $record->checkout_status === 'paid' || $record->transaction_status === 'settlement';

        return view('finance.manual-billing-print', [
            'billing' => [
                'name' => (string) $record->payer_name,
                'email' => $record->payer_email,
                'phone' => $record->payer_phone,
                'description' => (string) $record->description,
                'amount' => (int) $record->amount,
                'service_fee' => (int) ($record->service_fee ?? 0),
                'total_amount' => (int) ($record->gross_amount ?? $record->amount),
                'expires_at' => $record->expires_at,
                'order_id' => (string) $record->order_id,
                'redirect_url' => $record->redirect_url,
                'transaction_id' => $record->transaction_id,
                'transaction_status' => $record->transaction_status,
                'is_paid' => $isPaid,
            ],
        ]);
    }

    private function assertManualBillingSchema(): void
    {
        $schema = Schema::connection('simak_main');
        abort_unless(
            $schema->hasTable('simak_manual_bills') && $schema->hasTable('simak_payment_transactions'),
            503,
            'Tabel billing manual belum tersedia. Jalankan SQL upgrade database/manual/midtrans_manual_billing.sql terlebih dahulu.'
        );
    }

    public function generateUkt(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate([
            'tahun' => ['required', 'regex:/^\d{5}$/'],
            'id_fakultas' => ['nullable', 'string', 'max:50'],
        ]);
        $connection = DB::connection('simak_main');
        $period = $connection->table('s1m4k_periode')
            ->where('jenis', 'ukt')->where('tahun', $validated['tahun'])->first();
        abort_if($period === null, 422, 'Periode pembayaran uang kuliah tidak ditemukan.');
        $students = $connection->table('s1m4k_mastermhs as m')
            ->join('s1m4k_masterprodi as p', 'm.id_prodi', '=', 'p.id_prodi')
            ->whereIn('m.id_status_mahasiswa', self::ELIGIBLE_STUDENT_STATUSES)
            ->whereNotNull('m.kategori_ukt')->where('m.kategori_ukt', '<>', '')
            ->select(['m.id_mahasiswa', 'm.nominal_ukt'])
            ->get();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $connection->transaction(function () use ($connection, $students, $validated, &$created, &$updated, &$skipped, $request): void {
            foreach ($students as $student) {
                $existing = $connection->table('s1m4k_pembayaranukt')
                    ->where('id_mahasiswa', $student->id_mahasiswa)->where('tahun', $validated['tahun'])
                    ->lockForUpdate()->first();
                if ($existing === null) {
                    $connection->table('s1m4k_pembayaranukt')->insert([
                        'id' => (string) Str::uuid(),
                        'id_mahasiswa' => $student->id_mahasiswa,
                        'tahun' => $validated['tahun'],
                        'nominal' => (int) ($student->nominal_ukt ?? 0),
                        'lunas' => 0,
                        'petugas' => (string) data_get($request->user(), 'legacy_id', ''),
                    ]);
                    $created++;

                    continue;
                }
                if ((int) $existing->lunas !== 0
                    || $this->hasActiveMidtransTransaction('ukt', (string) $existing->id)) {
                    $skipped++;

                    continue;
                }
                $connection->table('s1m4k_pembayaranukt')->where('id', $existing->id)->update([
                    'nominal' => (int) ($student->nominal_ukt ?? 0),
                    'lunas' => 0,
                    'petugas' => (string) data_get($request->user(), 'legacy_id', ''),
                ]);
                $updated++;
            }
        });
        $activityLogger->record($request->user(), 'Keuangan', 'Membuat tagihan uang kuliah', [
            'tahun' => $validated['tahun'],
            'cakupan' => 'seluruh fakultas',
            'dibuat' => $created,
            'diperbarui' => $updated,
            'dilewati' => $skipped,
        ]);

        return redirect()->route('finance.ukt.index', array_filter([
            'tahun' => $validated['tahun'],
            'id_fakultas' => $validated['id_fakultas'] ?? null,
        ], fn (?string $value): bool => filled($value)))
            ->with('status', "Generate selesai: {$created} tagihan dibuat, {$updated} diperbarui, {$skipped} dilewati.");
    }

    public function updateUktAmount(Request $request, string $payment, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->updateAmount($request, 'ukt', $payment, $activityLogger);
    }

    public function updateIpiAmount(Request $request, string $payment, ActivityLogger $activityLogger): RedirectResponse
    {
        return $this->updateAmount($request, 'ipi', $payment, $activityLogger);
    }

    public function checkMidtransStatus(
        Request $request,
        string $type,
        string $payment,
        MidtransSnapService $midtrans,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $this->authorizeFinanceWrite($request);
        abort_unless(in_array($type, ['ukt', 'ipi'], true), 404);
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $record = DB::connection('simak_main')->table($table)->where('id', $payment)->first(['lunas']);
        abort_if($record === null, 404);
        if ((int) $record->lunas === 1) {
            return back()->with('status', 'Tagihan ini sudah tercatat lunas.');
        }

        try {
            $status = $midtrans->checkStatusForPayment($type, $payment);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }
        $activityLogger->record($request->user(), 'Keuangan', 'Memeriksa status transaksi Midtrans '.$type, [
            'status' => $status,
        ], $payment);

        return redirect()->route('finance.'.$type.'.index', $request->only('tahun', 'id_fakultas'))
            ->with('status', 'Status transaksi Midtrans diperbarui: '.$status.'.');
    }

    public function markPaymentPaidManually(
        Request $request,
        string $type,
        string $payment,
        ActivityLogger $activityLogger
    ): RedirectResponse {
        $this->authorizeFinanceWrite($request);
        abort_unless(in_array($type, ['ukt', 'ipi'], true), 404);
        $validated = $request->validate([
            'method' => ['required', 'in:beasiswa,pemda,manual'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $connection = DB::connection('simak_main');
        $connection->transaction(function () use ($connection, $table, $type, $payment, $validated, $request, $activityLogger): void {
            $record = $connection->table($table)->where('id', $payment)->lockForUpdate()->first();
            abort_if($record === null, 404);
            if ((int) $record->lunas === 1) {
                throw ValidationException::withMessages(['payment' => 'Tagihan ini sudah tercatat lunas.']);
            }
            if ($this->hasActiveMidtransTransaction($type, $payment)) {
                throw ValidationException::withMessages([
                    'payment' => 'Periksa atau selesaikan transaksi Midtrans yang masih berjalan atau sudah berhasil sebelum mencatat pembayaran manual.',
                ]);
            }

            $method = [
                'beasiswa' => 'BEASISWA',
                'pemda' => 'PEMDA',
                'manual' => 'MANUAL',
            ][$validated['method']];
            $connection->table($table)->where('id', $payment)->update([
                'lunas' => 1,
                'tanggal_bayar' => now()->toDateTimeString(),
                'nama_bank' => $method,
                'trx_id' => filled($validated['reference']) ? trim($validated['reference']) : null,
                'petugas' => (string) data_get($request->user(), 'legacy_id', ''),
            ]);
            $activityLogger->record($request->user(), 'Keuangan', 'Mencatat pembayaran manual '.$type, [
                'metode' => $validated['method'],
                'referensi' => filled($validated['reference']) ? trim($validated['reference']) : null,
                'nominal' => (int) $record->nominal,
            ], $payment);
        });

        return redirect()->route('finance.'.$type.'.index', $request->only('tahun', 'id_fakultas'))
            ->with('status', 'Pembayaran manual berhasil dicatat lunas.');
    }

    public function uploadIpi(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeIpiUpload($request);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:10240']]);
        $sheet = IOFactory::load($validated['file']->getRealPath())->getActiveSheet();
        $connection = DB::connection('simak_main');
        $created = 0;
        $updated = 0;
        $notFound = 0;
        $skipped = 0;
        $connection->transaction(function () use ($sheet, $connection, &$created, &$updated, &$notFound, &$skipped): void {
            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $nim = trim((string) $sheet->getCell("B{$row}")->getValue());
                $amountRaw = trim((string) $sheet->getCell("C{$row}")->getFormattedValue());
                $amountText = preg_replace('/\D+/', '', $amountRaw) ?? '';
                if ($nim === '' && $amountText === '') {
                    $skipped++;

                    continue;
                }
                if ($nim === '' || $amountText === '') {
                    $skipped++;

                    continue;
                }
                $student = $connection->table('s1m4k_mastermhs')->where('nim', $nim)->first(['id_mahasiswa']);
                if ($student === null) {
                    $notFound++;

                    continue;
                }
                $existing = $connection->table('s1m4k_pembayaranipi')
                    ->where('id_mahasiswa', $student->id_mahasiswa)->lockForUpdate()->first();
                if ($existing === null) {
                    $connection->table('s1m4k_pembayaranipi')->insert([
                        'id' => (string) Str::uuid(),
                        'id_mahasiswa' => $student->id_mahasiswa,
                        'nominal' => (int) $amountText,
                        'lunas' => 0,
                    ]);
                    $created++;

                    continue;
                }
                if ((int) $existing->lunas === 1 || $this->hasActiveMidtransTransaction('ipi', (string) $existing->id)) {
                    $skipped++;

                    continue;
                }
                $connection->table('s1m4k_pembayaranipi')->where('id', $existing->id)
                    ->update(['nominal' => (int) $amountText]);
                $updated++;
            }
        });
        $activityLogger->record($request->user(), 'Keuangan', 'Mengunggah tagihan Iuran Pengembangan Institusi', [
            'dibuat' => $created,
            'diperbarui' => $updated,
            'tidak_ditemukan' => $notFound,
            'dilewati' => $skipped,
        ]);

        return redirect()->route('finance.ipi.index')->with(
            'status',
            "Upload selesai: {$created} dibuat, {$updated} nominal diperbarui, {$notFound} NIM tidak ditemukan, {$skipped} baris dilewati."
        );
    }

    public function ipiUploadTemplate(Request $request): StreamedResponse
    {
        $this->authorizeIpiUpload($request);

        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Iuran Pengembangan Institusi');
            $sheet->fromArray([['No', 'NPM', 'Nominal Iuran Pengembangan Institusi']]);
            $sheet->getStyle('B1:B1000')->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle('A1:C1')->getFont()->setBold(true);
            foreach (range('A', 'C') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->freezePane('A2');

            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Petunjuk');
            $instructions->fromArray([
                ['Kolom', 'Keterangan'],
                ['NPM', 'Wajib: NPM yang terdaftar pada master mahasiswa SIMAK. Kolom ini berformat teks.'],
                ['Nominal Iuran Pengembangan Institusi', 'Wajib: nominal tagihan berupa angka tanpa teks mata uang.'],
                ['Nama Mahasiswa', 'Tidak perlu diisi pada Excel. Nama otomatis mengikuti data master mahasiswa berdasarkan NPM.'],
                ['Catatan', 'Isi satu mahasiswa per baris pada sheet Iuran Pengembangan Institusi. Baris kosong dilewati.'],
            ]);
            $instructions->getStyle('A1:B1')->getFont()->setBold(true);
            $instructions->getColumnDimension('A')->setAutoSize(true);
            $instructions->getColumnDimension('B')->setAutoSize(true);
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template-iuran-pengembangan-institusi.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportUkt(Request $request): StreamedResponse
    {
        return $this->exportStudentPayments($request, 'ukt');
    }

    public function exportIpi(Request $request): StreamedResponse
    {
        return $this->exportStudentPayments($request, 'ipi');
    }

    private function exportStudentPayments(Request $request, string $type): StreamedResponse
    {
        $this->authorizeFinance($request);
        $filters = $request->validate([
            'lunas' => ['required', 'in:0,1'],
            'tahun' => [$type === 'ukt' ? 'required' : 'nullable', 'regex:/^\d{5}$/'],
            'id_fakultas' => ['nullable', 'string', 'max:50'],
        ]);
        $query = $this->paymentQuery($type)->where('payment.lunas', (int) $filters['lunas']);
        if ($type === 'ukt') {
            $query->where('payment.tahun', $filters['tahun']);
        }
        if (filled($filters['id_fakultas'] ?? null)) {
            $query->where('faculty.id_fakultas', $filters['id_fakultas']);
        }
        $rows = $query->orderBy('student.nim')->get();
        $transactions = $this->latestMidtransTransactions($type, $rows->pluck('id')->all());
        $status = $filters['lunas'] === '1' ? 'lunas' : 'belum-lunas';
        $headers = ['Angkatan', 'NIM', 'Nama mahasiswa', 'Fakultas', 'Program studi (jenjang)'];
        if ($type === 'ukt') {
            $headers = [...$headers, 'Tahun akademik', 'Kategori Uang Kuliah'];
        }
        $headers = [...$headers, 'ID transaksi / referensi', 'Metode pembayaran', 'Nominal tagihan UKT / IPI', 'Biaya layanan Midtrans', 'Total pembayaran Midtrans', 'Status lunas', 'Tanggal bayar', 'Status Midtrans'];

        return $this->spreadsheetDownload(
            'pembayaran-'.($type === 'ukt' ? 'uang-kuliah' : 'uang-pembangunan').'-'.$status.($type === 'ukt' ? '-'.$filters['tahun'] : '').'.xlsx',
            $headers,
            $rows->map(function (object $payment) use ($type, $transactions): array {
                $row = [
                    $this->cohortFromNim($payment->nim),
                    $payment->nim,
                    $payment->nama_mahasiswa,
                    $payment->nama_fakultas,
                    trim(($payment->nama_program_studi ?? '').(filled($payment->nama_jenjang_pendidikan) ? ' ('.$payment->nama_jenjang_pendidikan.')' : '')),
                ];
                if ($type === 'ukt') {
                    $row[] = $payment->tahun;
                    $row[] = $payment->kategori_ukt;
                }
                $transaction = $transactions->get((string) $payment->id);
                $isManualPayment = $this->isManualPayment($payment);
                $serviceFee = $transaction !== null && ! $isManualPayment
                    ? (int) ($transaction->service_fee ?? 0)
                    : 0;

                return [
                    ...$row,
                    $isManualPayment ? ($payment->trx_id ?: null) : ($transaction?->order_id),
                    $transaction ? 'Midtrans' : ($isManualPayment ? strtoupper((string) $payment->nama_bank) : null),
                    (int) $payment->nominal,
                    $serviceFee,
                    (int) $payment->nominal + $serviceFee,
                    (int) $payment->lunas === 1 ? 'Lunas' : 'Belum lunas',
                    (int) $payment->lunas === 1 ? ($payment->tanggal_bayar ?: null) : null,
                    $transaction?->transaction_status,
                ];
            })
        );
    }

    public function printPaymentBilling(Request $request, string $type, string $payment): View
    {
        $this->authorizeFinanceWrite($request);
        abort_unless(in_array($type, ['ukt', 'ipi'], true), 404);

        $record = $this->paymentQuery($type)->where('payment.id', $payment)->first();
        abort_if($record === null, 404);
        $isPaid = (int) $record->lunas === 1;
        abort_unless($isPaid, 404);
        $transaction = null;
        if (Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
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
                'description' => $type === 'ukt' ? 'Uang Kuliah · '.$record->tahun : 'Iuran Pengembangan Institusi',
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

    public function paymentDataUkt(Request $request): JsonResponse
    {
        return $this->paymentData($request, 'ukt');
    }

    public function paymentDataIpi(Request $request): JsonResponse
    {
        return $this->paymentData($request, 'ipi');
    }

    private function payments(Request $request, string $type): View
    {
        $this->authorizeFinance($request);
        $filters = $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{5}$/'],
            'id_fakultas' => ['nullable', 'string', 'max:50'],
        ]);
        $records = null;

        return view('finance.index', [
            'module' => $type,
            'title' => $type === 'ukt' ? 'Pembayaran Uang Kuliah' : 'Iuran Pengembangan Institusi',
            'records' => $records,
            'filters' => $filters,
            'periods' => DB::connection('simak_main')->table('s1m4k_periode')
                ->where('jenis', 'ukt')->orderByDesc('tahun')->get(['tahun', 'nama_periode']),
            'faculties' => DB::connection('simak_main')->table('s1m4k_masterfakultas')
                ->orderBy('nama_fakultas')->get(['id_fakultas', 'nama_fakultas']),
        ]);
    }

    private function paymentData(Request $request, string $type): JsonResponse
    {
        $this->authorizeFinance($request);
        $request->validate([
            'tahun' => ['nullable', 'regex:/^\d{5}$/'],
            'id_fakultas' => ['nullable', 'string', 'max:50'],
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order.0.column' => ['nullable', 'integer', 'min:0', 'max:'.($type === 'ukt' ? '10' : '8')],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);
        $draw = max(0, (int) $request->input('draw', 0));
        if ($type === 'ukt' && blank($request->input('tahun'))) {
            return response()->json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }

        $query = $this->paymentQuery($type);
        if ($type === 'ukt') {
            $query->where('payment.tahun', $request->input('tahun'));
        }
        if (filled($request->input('id_fakultas'))) {
            $query->where('faculty.id_fakultas', $request->input('id_fakultas'));
        }
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('student.nim', 'like', '%'.$search.'%')
                    ->orWhere('student.nama_mahasiswa', 'like', '%'.$search.'%');
            });
        }
        $recordsFiltered = (clone $query)->count();
        $sortFields = $type === 'ukt'
            ? ['student.nim', 'student.nim', 'faculty.nama_fakultas', 'payment.tahun', 'student.kategori_ukt', 'payment.trx_id', 'payment.nominal', 'payment.lunas', 'payment.tanggal_bayar', 'payment.trx_id']
            : ['student.nim', 'student.nim', 'faculty.nama_fakultas', 'payment.trx_id', 'payment.nominal', 'payment.lunas', 'payment.tanggal_bayar', 'payment.trx_id'];
        $sortIndex = (int) $request->input('order.0.column', 0);
        $sortDirection = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->orderBy($sortFields[$sortIndex] ?? 'student.nim', $sortDirection)
            ->offset($start)->limit($length)->get();
        $transactions = $this->latestMidtransTransactions($type, $records->pluck('id')->all());
        $data = $records->map(function (object $payment) use ($type, $request, $transactions): array {
            $transaction = $transactions->get((string) $payment->id);
            $payment->midtrans_status = $transaction?->transaction_status;
            $payment->midtrans_order_id = $transaction?->order_id;
            $payment->can_edit_amount = (int) $payment->lunas !== 1
                && ! in_array($payment->midtrans_status, ['pending', 'capture', 'settlement'], true);

            return [
                'id' => $payment->id,
                'nim' => $payment->nim,
                'angkatan' => $this->cohortFromNim($payment->nim),
                'nama_mahasiswa' => $payment->nama_mahasiswa,
                'nama_fakultas' => $payment->nama_fakultas,
                'nama_program_studi' => $payment->nama_program_studi,
                'nama_jenjang_pendidikan' => $payment->nama_jenjang_pendidikan,
                'tahun' => $payment->tahun ?? null,
                'kategori_ukt' => $payment->kategori_ukt,
                'midtrans_order_id' => $payment->midtrans_order_id,
                'payment_reference' => $this->isManualPayment($payment)
                    ? $payment->trx_id
                    : $payment->midtrans_order_id,
                'payment_method' => $this->isManualPayment($payment)
                    ? strtoupper((string) $payment->nama_bank)
                    : ($payment->midtrans_order_id !== null ? 'Midtrans' : null),
                'midtrans_status' => $payment->midtrans_status,
                'nominal' => (int) $payment->nominal,
                'service_fee' => $transaction !== null && ! $this->isManualPayment($payment)
                    ? (int) ($transaction->service_fee ?? 0)
                    : 0,
                'total_amount' => (int) $payment->nominal
                    + ($transaction !== null && ! $this->isManualPayment($payment) ? (int) ($transaction->service_fee ?? 0) : 0),
                'lunas' => (int) $payment->lunas,
                'tanggal_bayar' => $payment->tanggal_bayar ?? null,
                'actions' => $request->user()->role === 'keuangan' ? $this->paymentActions($payment, $type) : '',
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    private function paymentActions(object $payment, string $type): string
    {
        $actions = '';
        $paid = (int) $payment->lunas === 1;
        if ($paid) {
            $actions .= '<a class="table-icon-button" href="'.e(route('finance.payments.billing', [
                'type' => $type,
                'payment' => $payment->id,
            ])).'" target="_blank" rel="noopener" title="Cetak billing" aria-label="Cetak billing untuk '
                .e($payment->nama_mahasiswa).'" data-page-loader-skip><span data-lucide="printer"></span></a>';
        }
        if ($payment->can_edit_amount) {
            $actions .= '<button class="table-icon-button" type="button" data-dialog-open="finance-payment-edit" data-finance-edit'
                .' data-update-url="'.e(route('finance.'.$type.'.amount', $payment->id)).'"'
                .' data-student-name="'.e($payment->nama_mahasiswa).'" data-student-nim="'.e($payment->nim).'"'
                .' data-amount="'.e((string) $payment->nominal).'" title="Ubah nominal" aria-label="Ubah nominal untuk '.e($payment->nama_mahasiswa).'">'
                .'<span data-lucide="pencil"></span></button>';
        }
        if (! $paid && in_array($payment->midtrans_status, ['pending', 'capture', 'settlement'], true)) {
            $actions .= '<form method="POST" action="'.e(route('finance.payments.midtrans-status', [
                'type' => $type,
                'payment' => $payment->id,
            ])).'">'
                .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
                .'<input type="hidden" name="tahun" value="'.e((string) request()->query('tahun', '')).'">'
                .'<input type="hidden" name="id_fakultas" value="'.e((string) request()->query('id_fakultas', '')).'">'
                .'<button class="table-icon-button" type="submit" title="Cek status Midtrans"'
                .' aria-label="Cek status Midtrans untuk '.e($payment->nama_mahasiswa).'">'
                .'<span data-lucide="refresh-cw"></span></button></form>';
        }
        if (! $paid && ! in_array($payment->midtrans_status, ['pending', 'capture', 'settlement'], true)) {
            $actions .= '<button class="table-icon-button" type="button" data-dialog-open="finance-payment-manual"'
                .' data-finance-manual-paid data-update-url="'.e(route('finance.payments.manual-paid', [
                    'type' => $type,
                    'payment' => $payment->id,
                ])).'" data-student-name="'.e($payment->nama_mahasiswa).'" data-student-nim="'.e($payment->nim).'"'
                .' title="Catat pembayaran manual" aria-label="Catat pembayaran manual untuk '.e($payment->nama_mahasiswa).'">'
                .'<span data-lucide="hand-coins"></span></button>';
        }

        return '<div class="finance-table-actions">'.$actions.'</div>';
    }

    private function paymentQuery(string $type): Builder
    {
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';

        return DB::connection('simak_main')->table($table.' as payment')
            ->join('s1m4k_mastermhs as student', 'payment.id_mahasiswa', '=', 'student.id_mahasiswa')
            ->leftJoin('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
            ->leftJoin('s1m4k_masterfakultas as faculty', 'faculty.id_fakultas', '=', DB::raw("COALESCE(NULLIF(student.id_fakultas, ''), program.id_fakultas)"))
            ->select([
                'payment.*', 'student.nim', 'student.nama_mahasiswa', 'student.kategori_ukt',
                'student.id_prodi', 'student.id_fakultas', 'student.id_perguruan_tinggi',
                'program.nama_program_studi', 'program.nama_jenjang_pendidikan', 'faculty.nama_fakultas',
            ]);
    }

    private function updateAmount(Request $request, string $type, string $payment, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizeFinanceWrite($request);
        $validated = $request->validate(['nominal' => ['required', 'integer', 'min:1', 'max:2147483647']]);
        $connection = DB::connection('simak_main');
        $table = $type === 'ukt' ? 's1m4k_pembayaranukt' : 's1m4k_pembayaranipi';
        $connection->transaction(function () use ($connection, $table, $payment, $type, $validated, $activityLogger, $request): void {
            $record = $connection->table($table)->where('id', $payment)->lockForUpdate()->first();
            abort_if($record === null, 404);
            if ((int) $record->lunas === 1 || $this->hasActiveMidtransTransaction($type, $payment)) {
                throw ValidationException::withMessages([
                    'nominal' => 'Nominal tidak dapat diubah jika pembayaran sudah lunas atau transaksi Midtrans masih berjalan/berhasil.',
                ]);
            }
            $connection->table($table)->where('id', $payment)->update([
                'nominal' => $validated['nominal'],
                'petugas' => (string) data_get($request->user(), 'legacy_id', ''),
            ]);
            $activityLogger->record($request->user(), 'Keuangan', 'Mengubah nominal '.$type, [
                'sebelum' => $record->nominal,
                'sesudah' => $validated['nominal'],
            ], $payment);
        });

        return back()->with('status', 'Nominal pembayaran berhasil diperbarui.');
    }

    private function eligibleStudentsQuery(): Builder
    {
        return DB::connection('simak_main')->table('s1m4k_mastermhs as m')
            ->whereIn('m.id_status_mahasiswa', self::ELIGIBLE_STUDENT_STATUSES)
            ->whereNotNull('m.nim')->where('m.nim', '<>', '');
    }

    private function categoryQuery(string $program): Builder
    {
        $query = $this->eligibleStudentsQuery()
            ->whereIn('m.id_status_mahasiswa', self::UKT_CATEGORY_STUDENT_STATUSES)
            ->leftJoin('s1m4k_masterprodi as p', 'm.id_prodi', '=', 'p.id_prodi')
            ->select([
                'm.id_mahasiswa', 'm.nim', 'm.nama_mahasiswa', 'm.id_prodi',
                DB::raw("CASE m.id_status_mahasiswa
                    WHEN 'A' THEN 'Aktif'
                    WHEN 'C' THEN 'Cuti'
                    WHEN 'N' THEN 'Non Aktif'
                    WHEN 'M' THEN 'MBKM'
                END as nama_status_mahasiswa"),
                'm.kategori_ukt', 'm.nominal_ukt',
                'p.nama_program_studi', 'p.nama_jenjang_pendidikan',
            ]);
        if ($program === 'none') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('m.id_prodi')->orWhere('m.id_prodi', '');
            });
        } elseif ($program !== '') {
            $query->where('m.id_prodi', $program);
        }

        return $query;
    }

    private function hasActiveMidtransTransaction(string $type, string $paymentId): bool
    {
        if (! Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
            return false;
        }

        return DB::connection('simak_main')->table('simak_payment_transactions')
            ->where('gateway', 'midtrans')
            ->where('payment_type', $type)
            ->where('payment_id', $paymentId)
            ->whereIn('transaction_status', ['pending', 'capture', 'settlement'])
            ->exists();
    }

    private function isManualPayment(object $payment): bool
    {
        return (int) $payment->lunas === 1
            && in_array(strtoupper((string) $payment->nama_bank), ['BEASISWA', 'PEMDA', 'MANUAL'], true);
    }

    /**
     * @param  array<int, string>  $paymentIds
     * @return Collection<string, object>
     */
    private function latestMidtransTransactions(string $type, array $paymentIds): Collection
    {
        if ($paymentIds === [] || ! Schema::connection('simak_main')->hasTable('simak_payment_transactions')) {
            return collect();
        }

        return DB::connection('simak_main')->table('simak_payment_transactions')
            ->where('gateway', 'midtrans')
            ->where('payment_type', $type)
            ->whereIn('payment_id', $paymentIds)
            ->orderByDesc('created_at')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('payment_id')
            ->keyBy('payment_id');
    }

    private function spreadsheetDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $textColumns = [];
            foreach ($headers as $column => $header) {
                $columnName = Coordinate::stringFromColumnIndex($column + 1);
                $sheet->setCellValue($columnName.'1', $header);
                if (in_array(mb_strtolower(trim((string) $header)), ['id order midtrans'], true)) {
                    $textColumns[] = $column;
                }
            }
            foreach ($rows as $rowIndex => $row) {
                foreach ($row as $column => $value) {
                    $cell = Coordinate::stringFromColumnIndex($column + 1).($rowIndex + 2);
                    if (in_array($column, $textColumns, true) && $value !== null) {
                        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('@');
                    } else {
                        $sheet->setCellValue($cell, $value);
                    }
                }
            }
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function authorizeFinance(Request $request): void
    {
        abort_unless(in_array($request->user()->role, self::ROLES, true), 403);
    }

    private function authorizeFinanceWrite(Request $request): void
    {
        abort_unless($request->user()?->role === 'keuangan', 403);
    }

    private function authorizeIpiUpload(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'keuangan'], true), 403);
    }

    private function authorizeFinanceDashboard(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['keuangan', 'pimuniv'], true), 403);
    }
}
