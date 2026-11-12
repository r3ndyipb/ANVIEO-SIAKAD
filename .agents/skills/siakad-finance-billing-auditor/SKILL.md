---
name: siakad-finance-billing-auditor
description: >-
  Manage student tuition billing, payment gateway integrations (Midtrans Snap & Core API), and financial clearance gatekeeping for SIAKAD Kampus. Handles mass billing generation for UKT (Tiers 1-8), IPI, SPI, and admission fees, validates Midtrans webhook signatures (SHA-512), executes idempotent state transitions (PENDING to SETTLEMENT), automatically toggles academic clearance (is_financial_cleared = true) to unlock KRS enrollment, manages installment plans and scholarship waivers, and audits financial reconciliation ledgers. Use this skill whenever configuring tuition tiers, debugging payment callbacks, handling transaction reconciliation, or managing student billing exceptions.
---

# SIAKAD Academic Billing & Midtrans Gateway Specialist Skill

Panduan keahlian penagihan akademik dan integrasi gerbang pembayaran bagi **Academic Billing & Midtrans Gateway Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar teknis pembuatan tagihan massal UKT/IPI per kluster/tier sosio-ekonomi, integrasi API pembayaran **Midtrans Snap & Core API** (Virtual Account Bank, QRIS, Gerai Ritel), verifikasi keamanan *webhook callback* berbasis tanda tangan digital SHA-512, penanganan transaksi idempoten (*idempotency*), serta pembukaan blokir otomatis (*real-time academic clearance*) untuk registrasi KRS mahasiswa.

---

## 1. Arsitektur Penagihan & Pintu Gerbang Akademik (Financial Gatekeeping)

Pembayaran uang kuliah tunggal (UKT) berfungsi sebagai *gatekeeper* utama sebelum mahasiswa diizinkan mengakses pemilihan mata kuliah (KRS War):

```
┌────────────────────────┐      Tagihan Massal per Angkatan      ┌────────────────────────┐
│  Biro Keuangan (BAUK)  │ ────────────────────────────────────> │  Tabel Tagihan Siswa   │
│  Penetapan Tarif UKT   │                                       │  (student_billings)    │
└────────────────────────┘                                       └───────────┬────────────┘
                                                                             │
                                                                             ▼
┌────────────────────────┐         Midtrans Snap Token           ┌────────────────────────┐
│   Portal Mahasiswa     │ <──────────────────────────────────── │  Generate VA / QRIS    │
│   (Bayar Kuliah)       │ ────────────────────────────────────> │  (Midtrans Core API)   │
└────────────────────────┘          Mahasiswa Membayar           └───────────┬────────────┘
                                                                             │
                                                                             ▼
┌────────────────────────┐       Webhook POST (SHA-512)          ┌────────────────────────┐
│    SIAKAD Callback     │ <──────────────────────────────────── │    Midtrans Server     │
│       Handler          │                                       │ (Bank / Settlement)    │
└───────────┬────────────┘                                       └────────────────────────┘
            │
            ▼ Transaksi Atomik (Idempotent)
┌────────────────────────────────────────────────────────┐
│ 1. Set Status Billing = 'SETTLEMENT'                  │
│ 2. Catat Log Pembayaran di payment_transactions        │
│ 3. Set t_mahasiswa.is_financial_cleared = true         │
│ 4. Unblock Akses Modul KRS War                         │
└────────────────────────────────────────────────────────┘
```

---

## 2. Struktur Tagihan Kampus (UKT & IPI/SPI)

SIAKAD membedakan jenis-jenis pos penagihan:
1. **UKT (Uang Kuliah Tunggal)**: Tagihan rutin semesteran berjenjang (Kelompok 1 sampai Kelompok 8) berdasarkan verifikasi kemampuan ekonomi orang tua.
2. **IPI / SPI (Iuran Pengembangan Institusi / Sumbangan Pengembangan Institusi)**: Biaya masuk awal untuk jalur seleksi mandiri, dapat dicicil per semester.
3. **Biaya Konversi & Praktikum Khusus**: Biaya sertifikasi profesi atau praktikum laboratorium khusus bagi program studi vokasi/medis.

---

## 3. Integrasi Midtrans API & Verifikasi Webhook Callback

### 3.1 Verifikasi Tanda Tangan Digital SHA-512
Setiap notifikasi webhook dari Midtrans wajib diverifikasi keasliannya guna mencegah serangan *fake payment injection*:

$$\text{Signature} = \text{hash}(\text{'sha512'}, \text{order\_id} + \text{status\_code} + \text{gross\_amount} + \text{ServerKey})$$

### 3.2 Implementasi Controller Idempoten Laravel

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StudentBilling;
use Symfony\Component\HttpFoundation\Response;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->all();
        $serverKey = config('services.midtrans.server_key');

        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signatureReceived = $payload['signature_key'] ?? '';
        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus = $payload['fraud_status'] ?? '';

        // 1. Verifikasi Signature Hash SHA-512
        $expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureReceived !== $expectedSignature) {
            Log::warning("Percobaan Fraud Webhook Midtrans: Order {$orderId}");
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
        }

        // 2. Eksekusi Transaksi Idempoten dengan Row Lock
        DB::transaction(function () use ($orderId, $transactionStatus, $fraudStatus, $payload) {
            $billing = StudentBilling::where('order_id', $orderId)->lockForUpdate()->first();

            if (!$billing) {
                Log::error("Tagihan tidak ditemukan untuk Order: {$orderId}");
                return;
            }

            // Mencegah proses ganda jika status sudah lunas
            if ($billing->status === 'SETTLEMENT') {
                return;
            }

            if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
                if ($fraudStatus === 'accept' || empty($fraudStatus)) {
                    $billing->status = 'SETTLEMENT';
                    $billing->settled_at = now();
                    $billing->raw_response = $payload;
                    $billing->save();

                    // Buka blokir KRS mahasiswa secara instan
                    $billing->student()->update([
                        'is_financial_cleared' => true
                    ]);

                    Log::info("Pembayaran Berhasil & KRS Unlocked: {$billing->student->nim} ({$orderId})");
                }
            } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
                $billing->status = strtoupper($transactionStatus);
                $billing->save();
            }
        });

        return response()->json(['message' => 'Webhook processed successfully'], Response::HTTP_OK);
    }
}
```

---

## 4. Dispensasi Pembayaran, Cicilan & Beasiswa

SIAKAD menyediakan alur pengecualian resmi untuk menjamin hak akademik mahasiswa prasejahtera:

1. **Pengajuan Cicilan (Installment Scheme)**:
   - Mahasiswa dapat mengajukan skema 50% sebelum KRS dan 50% sebelum UTS.
   - Sistem membuka clearance KRS parsial (`is_financial_cleared = true`) namun menetapkan batas tenggat pembayaran kedua sebelum pencetakan Kartu Ujian (KPU).
2. **Beasiswa (KIP-Kuliah, Yayasan, Pemda)**:
   - Integrasi data penerima beasiswa otomatis mengkredit tagihan UKT menjadi Rp 0,- dengan status `PAID_BY_SPONSOR`.
   - Menghasilkan bukti potong pembayaran dan laporan tagihan kolektif ke lembaga donor.
3. **Dispensasi Khusus Rektor / Dekan**:
   - Surat persetujuan pimpinan diunggah ke sistem oleh staf BAUK, mencatat nomor surat dan membuka clearance sementara secara otomatis dengan batas waktu tertentu.

---

## 5. Rekonsiliasi Keuangan & Audit Ledger

Setiap hari pada pukul 23:59 WIB, sistem menjalankan *automated financial reconciliation job*:

```sql
-- Deteksi ketidaksesuaian antara jumlah settlement gateway dan saldo billing lokal
SELECT 
    b.order_id,
    b.student_id,
    b.amount AS tagihan_nominal,
    p.amount_received AS bayar_gateway,
    (b.amount - p.amount_received) AS selisih,
    b.status AS status_billing,
    p.status AS status_payment
FROM student_billings b
JOIN payment_transactions p ON b.order_id = p.order_id
WHERE b.academic_period_id = 20261
  AND (b.status != 'SETTLEMENT' OR b.amount != p.amount_received);
```

---

## 6. Checklist Verifikasi Modul Keuangan

- [ ] Kode Virtual Account (BCA, Mandiri, BNI, BRI, Permata) dan QRIS teruji valid di lingkungan *Midtrans Sandbox & Production*.
- [ ] Handler webhook aman dengan validasi SHA-512 dan menerapkan row-level lock (`lockForUpdate()`) anti *race condition*.
- [ ] Perubahan status `SETTLEMENT` secara instan mengaktifkan flag `is_financial_cleared = true` pada entitas mahasiswa.
- [ ] Tersedia fitur ekspor laporan rekapitulasi penerimaan kas semesteran dalam format Excel/PDF untuk audit internal BPKP/KAP.
- [ ] Notifikasi struk pembayaran digital terkirim otomatis ke email dan WhatsApp mahasiswa terdaftar.
