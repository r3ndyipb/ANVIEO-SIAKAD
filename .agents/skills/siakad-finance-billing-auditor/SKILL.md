---
name: siakad-finance-billing-auditor
description: >-
  Manage student tuition billing, payment gateway integrations (Midtrans Snap & Core API), and financial clearance gatekeeping for SIAKAD Kampus. Handles mass billing generation for UKT (Tiers 1-8), IPI, SPI, and admission fees, validates Midtrans webhook signatures (SHA-512), executes idempotent state transitions (PENDING to SETTLEMENT), automatically toggles academic clearance (is_financial_cleared = true) to unlock KRS enrollment, manages installment plans and scholarship waivers, and audits financial reconciliation ledgers. Use this skill whenever configuring tuition tiers, debugging payment callbacks, handling transaction reconciliation, or managing student billing exceptions.
---

# SIAKAD Academic Billing & Midtrans Gateway Specialist Skill

Panduan keahlian penagihan akademik dan integrasi gerbang pembayaran bagi **Academic Billing & Midtrans Gateway Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar teknis pembuatan tagihan massal UKT/IPI per kluster/tier sosio-ekonomi, integrasi API pembayaran **Midtrans Snap & Core API** (Virtual Account Bank, QRIS, Gerai Ritel), verifikasi keamanan *webhook callback* berbasis tanda tangan digital SHA-512, penanganan transaksi idempoten (*idempotency*), pembukaan blokir otomatis (*real-time academic clearance*) untuk registrasi KRS mahasiswa, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Financial Domain Specialist & Academic Billing Lead**
- **Domain Fokus**: Manajemen tarif UKT/IPI, penerbitan tagihan massal per semester, integrasi gerbang pembayaran Midtrans Snap/Core API, verifikasi keamanan webhook SHA-512, rekonsiliasi kas bank harian, dan gerbang kelayakan finansial (*financial clearance gate*).

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-finance-billing-auditor` | `siakad-security-compliance-auditor` | Verifikasi tanda tangan digital SHA-512 callback webhook, pencegahan race condition via `lockForUpdate()`, dan audit jejak transaksi anti-manipulasi. |
| **Sebagai Reviewer Wajib** | `siakad-thesis-graduation-manager` | `siakad-finance-billing-auditor` | Verifikasi status bebas tunggakan biaya kuliah (saldo Rp 0,-) sebelum mahasiswa disahkan pada sidang yudisium. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap fitur penagihan, konfigurasi jalur pembayaran, atau penanganan webhook didekomposisi menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan integrasi tagihan / konfigurasi tier UKT / webhook handler / rekonsiliasi kas]

## Assigned Implementer
`siakad-finance-billing-auditor`

## Mandatory Reviewer
`siakad-security-compliance-auditor`

## Context & Files
- Scope: Billing Engine, Midtrans API Integration, Payment Webhook Callback
- File terkait: `app/Services/Finance/*`, `app/Http/Controllers/Api/MidtransWebhookController.php`, `database/migrations/*`

## Scrum Steps
1. Definisikan struktur tagihan dan generate order_id unik.
2. Integrasikan payload Midtrans Snap Token dan parameter VA bank.
3. Terapkan validasi signature hash SHA-512 dan locking transaksi atomik.
4. Update flag `is_financial_cleared = true` secara instan pasca settlement.

## Acceptance Criteria & DoD
- [ ] Signature SHA-512 tervalidasi sebelum payload diproses.
- [ ] Transaksi beroperasi idempoten (kebal terhadap pengiriman webhook berulang).
- [ ] Status lunas membuka akses KRS War seketika (< 500ms).
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan nominal tarif dan parameter gateway.
- **In Progress**: Billing Specialist mengimplementasikan skema tagihan, controller callback, dan tes integrasi.
- **Under Review**: Security Auditor memeriksa keamanan hashing, race conditions, dan integritas ledger.
- **Done**: Disetujui reviewer dan diverifikasi pada sandbox gateway.

---

## 3. Penyelarasan Keuangan dengan Siklus Hidup Akademik 6-Fase

Billing Specialist mengontrol gerbang kelayakan finansial di setiap tahapan kalender akademik:

| Fase Akademik | Fokus Penagihan & Keuangan | Dampak Akademik |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Penetapan UKT (Kelompok 1-8) dan tagihan IPI mahasiswa baru, generate Virtual Account massal. | Syarat terbitnya NIM dan akun portal aktif. |
| **Fase 2: Registrasi KRS** | Verifikasi pelunasan UKT semester berjalan, gatekeeper pembukaan akses KRS War. | Mahasiswa belum lunas diblokir dari KRS (`is_financial_cleared = false`). |
| **Fase 3: Masa Perkuliahan** | Penagihan cicilan tahap 2 (skema dispensasi) sebelum ujian tengah semester. | Syarat cetak Kartu Peserta Ujian (KPU). |
| **Fase 4: Evaluasi & Ujian** | Audit pelunasan biaya praktikum khusus / laboratorium sebelum nilai diumumkan. | Pembukaan tampilan KHS online mahasiswa. |
| **Fase 5: Kelulusan & Wisuda** | Audit saldo akhir bebas tunggakan SPP/UKT, tagihan pendaftaran wisuda & toga. | Syarat mutlak pengesahan berkas Yudisium Dekanat. |
| **Fase 6: Feeder PDDIKTI** | Rekonsiliasi pembiayaan beasiswa (KIP-K) untuk pelaporan data AKM Feeder. | Kesesuaian status pembiayaan di Pangkalan Data Dikti. |

---

## 4. Arsitektur Penagihan & Pintu Gerbang Akademik (Financial Gatekeeping)

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

## 5. Struktur Tagihan Kampus (UKT & IPI/SPI)

SIAKAD membedakan jenis-jenis pos penagihan:
1. **UKT (Uang Kuliah Tunggal)**: Tagihan rutin semesteran berjenjang (Kelompok 1 sampai Kelompok 8) berdasarkan verifikasi kemampuan ekonomi orang tua.
2. **IPI / SPI (Iuran Pengembangan Institusi / Sumbangan Pengembangan Institusi)**: Biaya masuk awal untuk jalur seleksi mandiri, dapat dicicil per semester.
3. **Biaya Konversi & Praktikum Khusus**: Biaya sertifikasi profesi atau praktikum laboratorium khusus bagi program studi vokasi/medis.

---

## 6. Integrasi Midtrans API & Verifikasi Webhook Callback

### 6.1 Verifikasi Tanda Tangan Digital SHA-512
Setiap notifikasi webhook dari Midtrans wajib diverifikasi keasliannya guna mencegah serangan *fake payment injection*:

$$\text{Signature} = \text{hash}(\text{'sha512'}, \text{order\_id} + \text{status\_code} + \text{gross\_amount} + \text{ServerKey})$$

### 6.2 Implementasi Controller Idempoten Laravel

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

## 7. Dispensasi Pembayaran, Cicilan & Beasiswa

SIAKAD menyediakan alur pengecualian resmi untuk menjamin hak akademik mahasiswa:

1. **Pengajuan Cicilan (Installment Scheme)**:
   - Mahasiswa dapat mengajukan skema 50% sebelum KRS dan 50% sebelum UTS.
   - Sistem membuka clearance KRS parsial (`is_financial_cleared = true`) namun menetapkan batas tenggat pembayaran kedua sebelum pencetakan Kartu Ujian (KPU).
2. **Beasiswa (KIP-Kuliah, Yayasan, Pemda)**:
   - Integrasi data penerima beasiswa otomatis mengkredit tagihan UKT menjadi Rp 0,- dengan status `PAID_BY_SPONSOR`.
   - Menghasilkan bukti potong pembayaran dan laporan tagihan kolektif ke lembaga donor.
3. **Dispensasi Khusus Rektor / Dekan**:
   - Surat persetujuan pimpinan diunggah ke sistem oleh staf BAUK, mencatat nomor surat dan membuka clearance sementara secara otomatis dengan batas waktu tertentu.

---

## 8. Rekonsiliasi Keuangan & Audit Ledger

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

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap modul billing dan transaksi keuangan wajib memenuhi kriteria berikut:

- [ ] **Financial Clearance Gate for KRS & Graduation**: Mahasiswa berstatus belum lunas terblokir otomatis dari pendaftaran KRS War dan pengesahan Yudisium.
- [ ] **SHA-512 Cryptographic Signature**: Seluruh webhook eksternal tervalidasi menggunakan SHA-512 sebelum memicu mutasi status database.
- [ ] **Idempotent Webhook Handler**: Transaksi menggunakan `lockForUpdate()` untuk mencegah pencatatan ganda saat menerima retry callback.
- [ ] **Real-time Status Unlocking**: Transisi ke `SETTLEMENT` seketika mengubah `is_financial_cleared = true` pada entitas mahasiswa.
- [ ] **Laporan Rekonsiliasi Kas**: Menyediakan rekapitulasi penerimaan kas semesteran yang dapat diekspor ke Excel/PDF untuk audit keuangan.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-security-compliance-auditor`).
