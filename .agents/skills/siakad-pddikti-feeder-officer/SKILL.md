---
name: siakad-pddikti-feeder-officer
description: >-
  Manage and automate bidirectional synchronization between SIAKAD Kampus and Kemendiktisaintek's PDDIKTI Neo Feeder Web Service. Handles API authentication (GetToken), Redis token caching, master data synchronization (Prodi, Kurikulum, Mata Kuliah, Dosen), transactional reporting (Kelas Kuliah, Dosen Pengajar, KRS Mahasiswa, Nilai Perkuliahan, AKM), checkpointed batch recovery, and pre-flight data validation to eliminate rejected records. Use this skill whenever building Feeder pipelines, troubleshooting sync errors, mapping data schemas, or auditing national academic compliance.
---

# SIAKAD PDDIKTI Neo Feeder Integration Specialist Skill

Panduan keahlian dan integrasi web service bagi **PDDIKTI Neo Feeder Integration Specialist & Academic Compliance Officer** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar teknis komunikasi dua arah dengan **PDDIKTI Neo Feeder Web Service (Kemendiktisaintek)** menggunakan arsitektur REST/JSON-RPC, manajemen siklus hidup token autentikasi di Redis, pipeline sinkronisasi ber-checkpoint (*checkpointed synchronization*), validasi lokal pra-kirim (*pre-flight validation*), serta resolusi kode kegagalan data nasional.

---

## 1. Arsitektur Web Service PDDIKTI Neo Feeder

Komunikasi sistem SIAKAD dengan aplikasi lokal Neo Feeder (berjalan di lingkungan kampus via Docker/Virtual Machine pada port 8082 atau 8100) menggunakan protokol Web Service:

```
┌─────────────────┐       JSON-RPC / REST WS       ┌─────────────────────┐       Encrypted SSL       ┌───────────────────┐
│  SIAKAD Kampus  │ ─────────────────────────────> │  Neo Feeder Engine  │ ────────────────────────> │ Pangkalan Data    │
│  (Laravel 12)   │ <───────────────────────────── │  (Local Server VM)  │ <──────────────────────── │ Dikti (Nasional)  │
└─────────────────┘        Port 8082 / WSClient    └─────────────────────┘                           └───────────────────┘
```

### 1.1 Siklus Autentikasi & Caching Token (`GetToken`)
Token autentikasi Neo Feeder memiliki masa berlaku terbatas (biasanya 2-4 jam). SIAKAD mengelola token secara proaktif:

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

## 2. Hirarki Entitas & Urutan Sinkronisasi (Dependency Order)

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

## 3. Pipeline Sinkronisasi Ber-Checkpoint (Resilient Checkpointing)

Untuk menangani dataset ratusan ribu baris tanpa risiko duplikasi atau kegagalan total saat terjadi *network timeout*, gunakan tabel pelacak checkpoint:

### Skema Tabel Checkpoint (`feeder_sync_checkpoints`):
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

## 4. Validasi Pra-Kirim Lokal (Pre-Flight Data Cleansing)

Sebelum data dikirim ke Feeder WS, jalankan filter validasi lokal guna menjamin **0% rejected batch**:

| Parameter Data | Aturan Validasi Lokal SIAKAD | Dampak jika Tidak Valid |
| :--- | :--- | :--- |
| **NIK Mahasiswa** | String angka tepat 16 digit, bukan angka fiktif (`123456...` / `000000...`). | Feeder menolak dengan error code `101: NIK Tidak Valid`. |
| **Batas SKS Semester** | Mahasiswa sarjana maksimal 24 SKS per semester (sesuai aturan IPS). | Feeder memblokir registrasi KRS semester tersebut. |
| **Bobot Nilai Relatif** | Nilai mutu wajib terpetakan: A (4.0), B (3.0), C (2.0), D (1.0), E (0.0). | Nilai tidak dapat dihitung dalam IPK nasional. |
| **ID Registrasi Mahasiswa** | UUID Mahasiswa harus sudah terdaftar aktif di semester bersangkutan. | Penolakan KRS dan AKM akibat data belum berstatus aktif. |
| **Rasio Dosen:Mahasiswa** | Kelas kuliah harus memiliki minimal 1 Dosen Pengajar dengan SKS evaluasi. | Peringatan kepatuhan beban dosen (BKD/Serdos). |

---

## 5. Resolusi Kode Kesalahan Feeder yang Sering Terjadi

| Kode Error Feeder | Deskripsi Kesalahan | Tindakan Remediasi Otomatis oleh Sistem |
| :--- | :--- | :--- |
| `100` | *Token Expired* | Flush cache Redis `pddikti_feeder_token`, request `GetToken` baru dan ulangi payload. |
| `200` | *Data Sudah Ada / Duplicate Key* | Ubah aksi dari `Insert...` menjadi `Update...` menggunakan ID Feeder yang tersimpan. |
| `300` | *Field Wajib Kosong* | Cek audit log lokal, lengkapi data mahasiswa (misal: Ibu Kandung, Tanggal Lahir). |
| `400` | *Data Referensi Tidak Ditemukan* | Eksekusi sinkronisasi entitas induk terlebih dahulu (misal: Kurikulum sebelum Mata Kuliah). |
| `500` | *Feeder Internal DB Connection Error* | Backoff eksponensial (tunggu 30 detik), kirim alert ke sysadmin Feeder kampus. |

---

## 6. Checklist Kepatuhan Pelaporan Semesteran

- [ ] Seluruh kelas kuliah telah memiliki dosen pengajar dengan bobot SKS valid.
- [ ] Seluruh mahasiswa aktif memiliki rekap KRS dan presensi di atas batas minimum.
- [ ] 100% gradebook dosen telah berstatus `LOCKED` dan dikonversi ke nilai huruf/angka Feeder.
- [ ] Data Aktivitas Kuliah Mahasiswa (AKM) terhitung akurat: SKS Semester, IPS, SKS Total, IPK.
- [ ] Mahasiswa lulusan semester berjalan telah memiliki nomor SK Yudisium dan terdata di menu Lulus/DO.
- [ ] Laporan evaluasi validasi Feeder berstatus hijau tanpa record berstatus anomali.
