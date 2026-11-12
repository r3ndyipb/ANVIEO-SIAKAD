---
name: siakad-krs-concurrency-guard
description: >-
  Guard against high-concurrency overbooking, race conditions, and database saturation during peak university KRS War enrollment. Implements Redis-backed rate limiting, virtual waiting rooms, fair queue admission tokens, pessimistic row-level locking (SELECT ... FOR UPDATE) stress tests, k6/Locust load testing scripts, deadlock prevention rules, and catalog cache warming. Use this skill whenever engineering peak enrollment concurrency, configuring Redis throttling, stress-testing registration transactions, or investigating KRS race conditions.
---

# SIAKAD KRS War & Concurrency Performance Guardian Skill

Panduan keahlian rekayasa konkurensi ekstrem dan ketahanan sistem bagi **KRS War & Concurrency Performance Guardian** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur perlindungan sistem saat periode puncak pemilihan rencana studi (*KRS War*), di mana ribuan mahasiswa mengakses dan memperebutkan kuota kelas dalam hitungan detik. Mencakup implementasi ruang tunggu virtual (*virtual waiting room*) berbasis Redis, pembatasan laju (*rate limiting & throttling*), penguncian baris pesimistik (**Pessimistic Row-Locking `SELECT ... FOR UPDATE`**), mitigasi deadlock dengan perolehan kunci berurutan (*ordered locking*), strategi *cache warming*, skrip uji beban (*load testing*) menggunakan **k6**, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **High-Load Concurrency Guardian & KRS War Specialist**
- **Domain Fokus**: Ketahanan lonjakan trafik pendaftaran studi serentak, konfigurasi Redis waiting room, integritas row-lock anti-overbooking kuota kelas, pencegahan deadlock multi-MK, dan uji beban k6/Locust.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-krs-concurrency-guard` | `siakad-dbms-postgresql` | Validasi kepatuhan transaksi atomik, eksekusi `lockForUpdate()`, batas waktu `lock_timeout <= 3s`, dan saturasi connection pool pgBouncer. |
| **Sebagai Reviewer Wajib** | `siakad-dbms-postgresql` | `siakad-krs-concurrency-guard` | Memastikan skema baru atau indeks PostgreSQL tidak memicu *lock contention* berlebih saat diakses bersamaan oleh ribuan proses worker. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap optimasi performa puncak, konfigurasi middleware throttling, atau uji beban konkurensi dipecah menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan rekayasa konkurensi / implementasi waiting room / penanganan race condition]

## Assigned Implementer
`siakad-krs-concurrency-guard`

## Mandatory Reviewer
`siakad-dbms-postgresql`

## Context & Files
- Scope: Redis Cache, Waiting Room Middleware, Transaction Locking Engine, Load Test Script
- File terkait: `app/Http/Middleware/KrsWaitingRoomMiddleware.php`, `app/Services/Krs/*`, `tests/Load/krs_war_test.js`

## Scrum Steps
1. Konfigurasi Redis data structures (sorted sets untuk antrean, strings untuk token).
2. Terapkan ordered class locking (`sort($scheduleIds)`) dan `lockForUpdate()`.
3. Jalankan skrip pemanasan cache (`php artisan siakad:warm-krs-cache`).
4. Eksekusi stres-test k6 (500 virtual users serentak) pada kuota 40 kursi.

## Acceptance Criteria & DoD
- [ ] Overbooking kuota kelas tepat 0 (kelas berkuota 40 terisi tepat 40, sisa 460 tertolak rapi).
- [ ] Tidak terjadi circular deadlock pada pendaftaran multi-mata kuliah.
- [ ] Database connection pool tidak mengalami saturasi (CPU < 75%).
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan target SLA latensi dan kapasitas konkurensi.
- **In Progress**: Concurrency Guard menyusun middleware, antrean Redis, dan skrip uji beban.
- **Under Review**: PostgreSQL DBA memvalidasi durasi lock, ketiadaan table scan, dan stabilitas engine.
- **Done**: Disetujui reviewer setelah lolos uji stres test k6 tanpa kegagalan integritas data.

---

## 3. Penyelarasan Konkurensi dengan Siklus Hidup Akademik 6-Fase

Concurrency Guard mengamankan infrastruktur pada momen-momen puncak kalender akademik:

| Fase Akademik | Titik Kritis Konkurensi | Solusi Ketahanan Sistem |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Lonjakan pengumuman seleksi PMB & pembayaran massal tagihan UKT awal semester. | Caching katalog prodi di Redis, CDN caching untuk halaman statis pengumuman. |
| **Fase 2: Registrasi KRS** | **KRS War**: Ribuan mahasiswa memperebutkan kuota kelas dalam detik ke-0 pembukaan portal. | **Virtual Waiting Room FIFO**, **Pessimistic Row Lock (`FOR UPDATE`)**, **Ordered Locking**. |
| **Fase 3: Masa Perkuliahan** | Pindai presensi QR serentak oleh puluhan ribu mahasiswa pada jam kuliah masuk (08:00 & 13:00). | Throttling endpoint presensi per IP/kelas, verifikasi token TOTP tanpa hit database masif. |
| **Fase 4: Evaluasi & Ujian** | Tenggat waktu input nilai dosen semesteran serentak sebelum cut-off sistem. | Antrean asinkron komputasi agregasi nilai dan ekspor berkas KHS. |
| **Fase 5: Kelulusan & Wisuda** | Trafik registrasi wisuda dan pemindaian QR verifikasi ijazah massal saat seremonial. | Redis cache verification endpoints transkrip resmi ber-QR. |
| **Fase 6: Feeder PDDIKTI** | Pengiriman ratusan ribu baris data transaksi ke server lokal Neo Feeder. | Batching 100 record per request dengan checkpoint recovery di database. |

---

## 4. Anatomi Masalah: The "KRS War" Stampede

Saat portal KRS dibuka serentak (misal pukul 08:00 WIB):
1. **The Overbooking Race Condition**: Kelas berkuota 40 kursi diperebutkan oleh 800 mahasiswa pada detik ke-0. Jika sistem hanya membaca `SELECT count(*) < quota` tanpa row lock, dua atau lebih transaksi dapat menyimpulkan bahwa kuota masih tersedia, menyebabkan kapasitas terisi melonjak menjadi 45 kursi (*negative available quota*).
2. **Database Connection Saturation**: Ratusan koneksi PHP-FPM membanjiri PostgreSQL pool, memicu *CPU spikes 100%* dan kegagalan respons (*504 Gateway Timeout*).
3. **Deadlock Multi-Class Selection**: Mahasiswa A memilih kelas X lalu Y, Mahasiswa B memilih kelas Y lalu X dalam detik yang sama; transaksi saling mengunci dan dibatalkan oleh engine database.

---

## 5. Arsitektur Pertahanan Berlapis (Multi-Tier Defense Architecture)

Untuk menjamin ketersediaan 99.9% tanpa anomali data, SIAKAD menerapkan 4 lapis pertahanan:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. LAPIS JARINGAN / EDGE GATEWAY: Cloudflare / Nginx Throttling            │
│    Membatasi burst IP eksternal maksimal 30 request / detik                 │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ lolos
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 2. LAPIS REDIS VIRTUAL WAITING ROOM (Ruang Tunggu Antrean)                  │
│    Tiket antrean bergiliran (FIFO token); hanya 500 user aktif/menit        │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ giliran tiba (Admission Token)
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 3. LAPIS APLIKASI (Laravel 12 Service Layer): Idempotency & Validation      │
│    Verifikasi prasyarat, IPK ceiling SKS, dan jadwal bentrok di memory/cache │
└──────────────────────────────────────┬──────────────────────────────────────┘
                                       │ payload valid
                                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│ 4. LAPIS DATABASE (PostgreSQL 16): Pessimistic Row Lock & Transaction      │
│    SELECT ... FOR UPDATE dengan Lock Timeout ketat (3 detik)                │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 6. Implementasi Redis Virtual Waiting Room

Ruang tunggu virtual mencegah penumpukan koneksi database dengan menahan mahasiswa dalam antrean teratur:

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class KrsWaitingRoomMiddleware
{
    protected int $maxActiveUsers = 500; // Maksimum pengguna yang diizinkan aktif bersamaan
    protected int $sessionTtl = 300;     // 5 menit per sesi KRS

    public function handle(Request $request, Closure $next): Response
    {
        $studentId = $request->user()->id;
        $activeKey = "krs:active:{$studentId}";
        $queueKey = "krs:waiting_queue";

        // 1. Jika mahasiswa sudah memiliki tiket aktif yang valid, izinkan masuk
        if (Redis::exists($activeKey)) {
            Redis::expire($activeKey, $this->sessionTtl);
            return $next($request);
        }

        // 2. Hitung jumlah user aktif saat ini
        $currentActiveCount = count(Redis::keys("krs:active:*"));

        if ($currentActiveCount < $this->maxActiveUsers) {
            // Kuota aktif tersedia: Daftarkan mahasiswa ke slot aktif
            Redis::setex($activeKey, $this->sessionTtl, 'granted');
            return $next($request);
        }

        // 3. Kuota aktif penuh: Masukkan ke antrean berbobot FIFO
        Redis::zadd($queueKey, now()->timestamp, $studentId);
        $rank = Redis::zrank($queueKey, $studentId) + 1;
        $estimatedWaitSeconds = ceil($rank / 5) * 10;

        return response()->view('krs.waiting_room', [
            'position' => $rank,
            'estimated_seconds' => $estimatedWaitSeconds,
        ])->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS);
    }
}
```

---

## 7. Eksekusi Transaksi Atomik PostgreSQL & Mitigasi Deadlock

Pendaftaran KRS dieksekusi dengan protokol perolehan kunci terurut (*ordered locking*) untuk mengeliminasi potensi deadlock:

```php
namespace App\Services\Krs;

use Illuminate\Support\Facades\DB;
use App\Exceptions\KrsQuotaExceededException;
use App\Exceptions\ScheduleConflictException;

class KrsEnrollmentEngine
{
    /**
     * Mendaftarkan multi mata kuliah sekaligus secara atomik
     */
    public function enrollClasses(int $studentId, int $academicPeriodId, array $classScheduleIds): void
    {
        // 1. WAJIB: Urutkan ID jadwal secara ascending untuk mencegah circular deadlock
        sort($classScheduleIds);

        DB::transaction(function () use ($studentId, $academicPeriodId, $classScheduleIds) {
            // Set batas waktu lock maksimal 3 detik agar koneksi tidak menggantung
            DB::statement("SET LOCAL lock_timeout = '3s';");

            // 2. Kunci seluruh baris jadwal kelas yang dipilih secara berurutan
            $schedules = DB::table('class_schedules')
                ->whereIn('id', $classScheduleIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // 3. Validasi kuota dan bentrok waktu
            foreach ($schedules as $schedule) {
                if ($schedule->enrolled_count >= $schedule->quota) {
                    throw new KrsQuotaExceededException("Kuota kelas {$schedule->name} sudah penuh!");
                }
            }

            // 4. Masukkan ke tabel krs_details dan perbarui counter secara atomik
            foreach ($schedules as $schedule) {
                DB::table('krs_details')->insert([
                    'student_id' => $studentId,
                    'academic_period_id' => $academicPeriodId,
                    'class_schedule_id' => $schedule->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('class_schedules')
                    ->where('id', $schedule->id)
                    ->increment('enrolled_count');
            }
        });
    }
}
```

---

## 8. Strategi Pemanasan Cache (Cache Warming)

Sebelum gerbang KRS dibuka resmi, jalankan skrip pemanasan cache agar database tidak menerima *cold read hits*:

```bash
# Perintah pemanasan cache master jadwal & kurikulum
php artisan siakad:warm-krs-cache --period=20261
```
- Menempatkan seluruh jadwal kelas, ruangan, dosen pengampu, dan prasyarat mata kuliah ke dalam memori RAM Redis.
- Menghasilkan respon pembacaan katalog kelas dalam waktu $< 5\text{ms}$.

---

## 9. Skrip Uji Beban Concurrency Menggunakan k6

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 100 },  // Ramp-up 100 user
    { duration: '1m', target: 500 },   // Spike 500 concurrent user saat war
    { duration: '30s', target: 0 },    // Ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<300'], // 95% request harus selesai di bawah 300ms
    http_req_failed: ['rate<0.01'],   // Error server harus di bawah 1%
  },
};

export default function () {
  const token = 'TEST_BEARER_TOKEN_STUDENT';
  const params = {
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
  };

  const payload = JSON.stringify({
    academic_period_id: 20261,
    class_schedule_ids: [101, 104, 112, 115],
  });

  const res = http.post('http://localhost:8000/api/v1/krs/enroll', payload, params);

  check(res, {
    'status is 200 or 429': (r) => r.status === 200 || r.status === 429,
    'no 500 server error': (r) => r.status !== 500,
  });

  sleep(1);
}
```

---

## 10. Definition of Done (DoD) & Acceptance Criteria

Setiap optimasi atau modul pendaftaran konkurensi tinggi wajib memenuhi kriteria berikut:

- [ ] **Pessimistic Row-Locking on Class Capacity**: Seluruh reservasi kuota kelas wajib dieksekusi dengan `SELECT ... FOR UPDATE` dan `lock_timeout <= 3s`.
- [ ] **Zero Overbooking**: Teruji secara empiris via k6 pada kelas berkuota 40 kursi dengan 500 pendaftar serentak menghasilkan tepat 40 baris sukses dan 460 penolakan tanpa kuota minus.
- [ ] **Ordered Locking**: Seluruh perolehan lock jadwal kelas terurut secara *ascending* untuk mencegah deadlock.
- [ ] **Virtual Waiting Room**: Middleware antrean Redis membatasi akses aktif maksimal 500 user/menit dan mengalirkan antrean secara adil (FIFO).
- [ ] **Cache Warming**: Katalog jadwal kuliah telah terisi di Redis dengan waktu respon baca $< 10\text{ms}$.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-dbms-postgresql`).
