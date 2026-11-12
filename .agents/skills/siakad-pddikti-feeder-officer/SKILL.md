---
name: siakad-pddikti-feeder-officer
description: >-
  Manage and automate bidirectional synchronization between SIAKAD Kampus and Kemendiktisaintek's PDDIKTI Neo Feeder Web Service. Handles API authentication (GetToken), Redis token caching, master data synchronization (Prodi, Kurikulum, Mata Kuliah, Dosen), transactional reporting (Kelas Kuliah, Dosen Pengajar, KRS Mahasiswa, Nilai Perkuliahan, AKM), checkpointed batch recovery, and pre-flight data validation to eliminate rejected records. Use this skill whenever building Feeder pipelines, troubleshooting sync errors, mapping data schemas, or auditing national academic compliance.
---

# SIAKAD PDDIKTI Neo Feeder Integration Specialist Skill

Panduan keahlian dan integrasi web service bagi **PDDIKTI Neo Feeder Integration Specialist & Academic Compliance Officer** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar teknis komunikasi dua arah dengan **PDDIKTI Neo Feeder Web Service (Kemendiktisaintek)** menggunakan arsitektur REST/JSON-RPC, manajemen siklus hidup token autentikasi di Redis, pipeline sinkronisasi ber-checkpoint (*checkpointed synchronization*), validasi lokal pra-kirim (*pre-flight validation*), resolusi kode kegagalan data nasional, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **National Sync Specialist & Feeder Integration Officer**
- **Domain Fokus**: Pipeline sinkronisasi dua arah Neo Feeder WS, filter pra-validasi integritas data nasional (NIK, SKS, IPK, BKD), arsitektur pemulihan batch ber-checkpoint, dan kepatuhan tenggat waktu pelaporan kementerian.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-pddikti-feeder-officer` | `siakad-academic-registrar` | Validasi kepatuhan format data Kemendiktisaintek, kelengkapan riwayat pendidikan mahasiswa, dan integritas pemulihan batch ber-checkpoint. |
| **Sebagai Reviewer Wajib** | `siakad-academic-registrar` | `siakad-pddikti-feeder-officer` | Memastikan mutasi status mahasiswa (cuti, transfer, DO) dan penomoran ijazah nasional (PIN) valid sebelum disinkronkan ke Feeder. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap modul sinkronisasi entitas (misal: Kurikulum, Kelas, Nilai, AKM) didekomposisi menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Proses sinkronisasi masif wajib didelegasikan ke *Queue Worker* asinkron dan tidak boleh dieksekusi secara sinkronus di HTTP controller.
3. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan sinkronisasi entitas / pembuatan validator pra-kirim / penanganan error Feeder]

## Assigned Implementer
`siakad-pddikti-feeder-officer`

## Mandatory Reviewer
`siakad-academic-registrar`

## Context & Files
- Scope: Feeder Web Service Client, Job Queue, Checkpoint Tracking
- File terkait: `app/Services/Feeder/*`, `app/Jobs/Feeder/*`, `database/migrations/*`

## Scrum Steps
1. Tulis atau perbarui metode pemanggilan RPC Feeder pada FeederClient.
2. Pasang filter validasi pra-kirim lokal untuk mencegah rejected records.
3. Simpan state checkpoint batch pada tabel `feeder_sync_checkpoints`.
4. Uji simulasi kegagalan koneksi dan verifikasi pemulihan batch dari checkpoint terakhir.

## Acceptance Criteria & DoD
- [ ] Validasi lokal NIK 16 digit, batas SKS, dan IPK aktif sebelum kirim.
- [ ] Token autentikasi di-cache di Redis dengan renewal otomatis saat expired.
- [ ] Job sinkronisasi beroperasi asinkron di Redis queue `feeder`.
- [ ] Nol (0%) rejected records pada batch pengujian.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan cakupan entitas dan semester pelaporan.
- **In Progress**: Feeder Officer mengimplementasikan endpoint, validasi, dan batch processing.
- **Under Review**: Academic Registrar memverifikasi kesesuaian data akademik dan kepatuhan regulasi.
- **Done**: Disetujui reviewer dan diverifikasi lulus uji sinkronisasi.

---

## 3. Penyelarasan Sinkronisasi dengan Siklus Hidup Akademik 6-Fase

Feeder Officer bertindak sebagai penjamin kepatuhan data nasional pada seluruh fase operasional perguruan tinggi:

| Fase Akademik | Entitas Pelaporan PDDIKTI | Periode Pelaporan Kemendikbud |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Master Mahasiswa Baru (`InsertBiodataMahasiswa`, `InsertRiwayatPendidikanMahasiswa`). | Awal Semester Ganjil/Genap |
| **Fase 2: Registrasi KRS** | Kelas Kuliah & Dosen Pengajar (`InsertKelasKuliah`, `InsertDosenPengajarKelasKuliah`), KRS Peserta Kelas. | Batas Awal Semester (Bulan ke-2) |
| **Fase 3: Masa Perkuliahan** | Pembaruan status mahasiswa aktif/non-aktif, verifikasi penugasan dosen (BKD). | Tengah Semester |
| **Fase 4: Evaluasi & Ujian** | Nilai Perkuliahan Kelas (`InsertNilaiPerkuliahanKelas`), Aktivitas Kuliah Mahasiswa (`InsertPerkuliahanMahasiswa`). | Akhir Semester (Cut-Off Nilai) |
| **Fase 5: Kelulusan & Wisuda** | Mahasiswa Lulus & Drop-Out (`InsertMahasiswaLulusDO`), Penomoran Ijazah Nasional (PIN). | Pasca Yudisium / Wisuda |
| **Fase 6: Feeder PDDIKTI** | Rekonsiliasi AKM Semesteran menyeluruh, pelaporan penutupan periode semester lampau. | Batas Akhir Pelaporan Nasional |

---

## 4. Arsitektur Web Service PDDIKTI Neo Feeder

Komunikasi sistem SIAKAD dengan aplikasi lokal Neo Feeder (berjalan di lingkungan kampus via Docker/Virtual Machine pada port 8082 atau 8100) menggunakan protokol Web Service:

```
┌─────────────────┐       JSON-RPC / REST WS       ┌─────────────────────┐       Encrypted SSL       ┌───────────────────┐
│  SIAKAD Kampus  │ ─────────────────────────────> │  Neo Feeder Engine  │ ────────────────────────> │ Pangkalan Data    │
│  (Laravel 12)   │ <───────────────────────────── │  (Local Server VM)  │ <──────────────────────── │ Dikti (Nasional)  │
└─────────────────┘        Port 8082 / WSClient    └─────────────────────┘                           └───────────────────┘
```

### 4.1 Siklus Autentikasi & Caching Token (`GetToken`)
Token autentikasi Neo Feeder memiliki masa berlaku terbatas (biasanya 2-4 jam). SIAKAD mengelola token secara proaktif menggunakan Redis:

```php
namespace App\Services\Feeder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class FeederClient
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl = config('services.feeder.url');
        $this->username = config('services.feeder.username');
        $this->password = config('services.feeder.password');
    }

    /**
     * Dapatkan token aktif dari Redis atau lakukan request baru
     */
    public function getToken(): string
    {
        return Cache::remember('pddikti_feeder_token', 7000, function () {
            $response = Http::timeout(15)->post("{$this->baseUrl}/ws/live2.php", [
                'act' => 'GetToken',
                'username' => $this->username,
                'password' => $this->password,
            ]);

            $json = $response->json();

            if (($json['error_code'] ?? -1) !== 0 || empty($json['data']['token'])) {
                throw new RuntimeException("Gagal autentikasi Feeder: " . ($json['error_desc'] ?? 'Unknown Error'));
            }

            return $json['data']['token'];
        });
    }

    /**
     * Eksekusi perintah Feeder generik
     */
    public function request(string $action, array $record = [], ?string $filter = null, int $limit = 100, int $offset = 0): array
    {
        $payload = [
            'act' => $action,
            'token' => $this->getToken(),
        ];

        if (!empty($record)) $payload['record'] = $record;
        if (!empty($filter)) $payload['filter'] = $filter;
        if ($limit > 0) $payload['limit'] = $limit;
        if ($offset > 0) $payload['offset'] = $offset;

        $response = Http::timeout(60)->post("{$this->baseUrl}/ws/live2.php", $payload);
        $result = $response->json();

        // Tangani kedaluwarsa token secara otomatis
        if (($result['error_code'] ?? 0) === 100) {
            Cache::forget('pddikti_feeder_token');
            $payload['token'] = $this->getToken();
            $response = Http::timeout(60)->post("{$this->baseUrl}/ws/live2.php", $payload);
            $result = $response->json();
        }

        return $result;
    }
}
```

---

## 5. Hirarki Entitas & Urutan Sinkronisasi (Dependency Order)

Sinkronisasi entitas ke Neo Feeder wajib mengikuti urutan relasional yang ketat untuk mencegah penolakan berbasis foreign key (`ID Referensi Tidak Ditemukan`):

```
1. Master Program Studi & Fakultas (GetProdi)
       │
       ▼
2. Kurikulum Program Studi (InsertKurikulum)
       │
       ▼
3. Mata Kuliah & Pemetaan Kurikulum (InsertMataKuliah -> InsertMatkulKurikulum)
       │
       ▼
4. Penugasan Dosen (InsertDosenPengajarKelasKuliah)
       │
       ▼
5. Biodata Mahasiswa & Riwayat Pendidikan (InsertBiodataMahasiswa -> InsertRiwayatPendidikanMahasiswa)
       │
       ▼
6. Kelas Kuliah (InsertKelasKuliah)
       │
       ▼
7. Rencana Studi / KRS Mahasiswa (InsertKRSMahasiswa)
       │
       ▼
8. Nilai Perkuliahan Kelas (InsertNilaiPerkuliahanKelas)
       │
       ▼
9. Aktivitas Kuliah Mahasiswa / AKM (InsertPerkuliahanMahasiswa)
       │
       ▼
10. Kelulusan & Mahasiswa Drop-Out (InsertMahasiswaLulusDO)
```

---

## 6. Pipeline Sinkronisasi Ber-Checkpoint (Resilient Checkpointing)

Untuk menangani dataset ratusan ribu baris tanpa risiko duplikasi atau kegagalan total saat terjadi *network timeout*, gunakan tabel pelacak checkpoint:

```sql
CREATE TABLE feeder_sync_checkpoints (
    id BIGSERIAL PRIMARY KEY,
    sync_session_id UUID NOT NULL,
    entity_type VARCHAR(64) NOT NULL, -- 'krs', 'nilai', 'akm'
    academic_period_id BIGINT NOT NULL,
    last_processed_id BIGINT NOT NULL DEFAULT 0,
    batch_size INT NOT NULL DEFAULT 100,
    total_processed INT NOT NULL DEFAULT 0,
    total_failed INT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'IN_PROGRESS', -- 'IN_PROGRESS', 'COMPLETED', 'FAILED'
    error_summary JSONB NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_feeder_checkpoint ON feeder_sync_checkpoints (entity_type, academic_period_id, status);
```

### Mekanisme Resume Batch:
1. Query record dari SIAKAD: `SELECT * FROM t_krs WHERE id > :last_processed_id ORDER BY id ASC LIMIT 100`.
2. Format ke struktur JSON Feeder (`InsertKRSMahasiswa`).
3. Kirim ke Feeder WS. Jika berhasil, catat `last_processed_id = MAX(id)` dan `UPDATE feeder_sync_checkpoints`.
4. Jika server putus di tengah jalan, cron worker berikutnya cukup membaca `last_processed_id` terakhir dan melanjutkan tanpa mengulang dari awal.

---

## 7. Validasi Pra-Kirim Lokal (Pre-Flight Data Cleansing)

Sebelum data dikirim ke Feeder WS, jalankan filter validasi lokal guna menjamin **0% rejected batch**:

| Parameter Data | Aturan Validasi Lokal SIAKAD | Dampak jika Tidak Valid |
| :--- | :--- | :--- |
| **NIK Mahasiswa** | String angka tepat 16 digit, bukan angka fiktif (`123456...` / `000000...`). | Feeder menolak dengan error code `101: NIK Tidak Valid`. |
| **Batas SKS Semester** | Mahasiswa sarjana maksimal 24 SKS per semester (sesuai aturan IPS). | Feeder memblokir registrasi KRS semester tersebut. |
| **Bobot Nilai Relatif** | Nilai mutu wajib terpetakan: A (4.0), B (3.0), C (2.0), D (1.0), E (0.0). | Nilai tidak dapat dihitung dalam IPK nasional. |
| **ID Registrasi Mahasiswa** | UUID Mahasiswa harus sudah terdaftar aktif di semester bersangkutan. | Penolakan KRS dan AKM akibat data belum berstatus aktif. |
| **Rasio Dosen:Mahasiswa** | Kelas kuliah harus memiliki minimal 1 Dosen Pengajar dengan SKS evaluasi. | Peringatan kepatuhan beban dosen (BKD/Serdos). |

---

## 8. Resolusi Kode Kesalahan Feeder yang Sering Terjadi

| Kode Error Feeder | Deskripsi Kesalahan | Tindakan Remediasi Otomatis oleh Sistem |
| :--- | :--- | :--- |
| `100` | *Token Expired* | Flush cache Redis `pddikti_feeder_token`, request `GetToken` baru dan ulangi payload. |
| `200` | *Data Sudah Ada / Duplicate Key* | Ubah aksi dari `Insert...` menjadi `Update...` menggunakan ID Feeder yang tersimpan. |
| `300` | *Field Wajib Kosong* | Cek audit log lokal, lengkapi data mahasiswa (misal: Ibu Kandung, Tanggal Lahir). |
| `400` | *Data Referensi Tidak Ditemukan* | Eksekusi sinkronisasi entitas induk terlebih dahulu (misal: Kurikulum sebelum Mata Kuliah). |
| `500` | *Feeder Internal DB Connection Error* | Backoff eksponensial (tunggu 30 detik), kirim alert ke sysadmin Feeder kampus. |

---

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap pekerjaan sinkronisasi PDDIKTI Feeder wajib memenuhi kriteria berikut:

- [ ] **Pre-Flight Validation**: 100% data lolos uji lokal sebelum dikirim ke endpoint Feeder (bebas NIK fiktif, validitas rentang SKS/IPK).
- [ ] **Checkpointed Resilience**: Batch sinkronisasi mencatat checkpoint di `feeder_sync_checkpoints` dan mampu melanjutkan otomatis pasca kegagalan jaringan.
- [ ] **Urutan Dependensi 10 Tingkat**: Entitas induk terverifikasi tersinkronkan sebelum entitas transaksional anak dikirim.
- [ ] **Asynchronous Processing**: Operasi ekspor data dieksekusi di background worker antrean Redis, bebas blocking HTTP request.
- [ ] **Zero Rejected Batches**: Laporan ringkasan pengiriman menunjukkan status sukses penuh tanpa anomali record tertolak.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-academic-registrar`).
