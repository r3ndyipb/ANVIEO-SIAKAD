---
name: siakad-krs-concurrency-guard
description: >-
  Guard against high-concurrency overbooking, race conditions, and database saturation during peak university KRS War enrollment. Implements Redis-backed rate limiting, virtual waiting rooms, fair queue admission tokens, pessimistic row-level locking (SELECT ... FOR UPDATE) stress tests, k6/Locust load testing scripts, deadlock prevention rules, and catalog cache warming. Use this skill whenever engineering peak enrollment concurrency, configuring Redis throttling, stress-testing registration transactions, or investigating KRS race conditions.
---

# SIAKAD KRS War & Concurrency Performance Guardian Skill

Panduan keahlian rekayasa konkurensi ekstrem dan ketahanan sistem bagi **KRS War & Concurrency Performance Guardian** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur perlindungan sistem saat periode puncak pemilihan rencana studi (*KRS War*), di mana ribuan mahasiswa mengakses dan memperebutkan kuota kelas dalam hitungan detik. Mencakup implementasi ruang tunggu virtual (*virtual waiting room*) berbasis Redis, pembatasan laju (*rate limiting & throttling*), penguncian baris pesimistik (*pessimistic row-level locking*), strategi *cache warming*, mitigasi deadlock, serta skrip uji beban (*load testing*) menggunakan **k6**.

---

## 1. Anatomi Masalah: The "KRS War" Stampede

Saat portal KRS dibuka serentak (misal pukul 08:00 WIB):
1. **The Overbooking Race Condition**: Kelas berkuota 40 kursi diperebutkan oleh 800 mahasiswa pada detik ke-0. Jika sistem hanya membaca `SELECT count(*) < quota` tanpa row lock, dua atau lebih transaksi dapat menyimpulkan bahwa kuota masih tersedia, menyebabkan kapasitas terisi melonjak menjadi 45 kursi (*negative available quota*).
2. **Database Connection Saturation**: Ratusan koneksi PHP-FPM membanjiri PostgreSQL pool, memicu *CPU spikes 100%* dan kegagalan respons (*504 Gateway Timeout*).
3. **Deadlock Multi-Class Selection**: Mahasiswa A memilih kelas X lalu Y, Mahasiswa B memilih kelas Y lalu X dalam detik yang sama; transaksi saling mengunci dan dibatalkan oleh engine database.

---

## 2. Arsitektur Pertahanan Berlapis (Multi-Tier Defense Architecture)

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

## 3. Implementasi Redis Virtual Waiting Room

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

## 4. Eksekusi Transaksi Atomik PostgreSQL & Mitigasi Deadlock

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

## 5. Strategi Pemanasan Cache (Cache Warming)

Sebelum gerbang KRS dibuka resmi, jalankan skrip pemanasan cache agar database tidak menerima *cold read hits*:

```bash
# Perintah pemanasan cache master jadwal & kurikulum
php artisan siakad:warm-krs-cache --period=20261
```
- Menempatkan seluruh jadwal kelas, ruangan, dosen pengampu, dan prasyarat mata kuliah ke dalam memori RAM Redis.
- Menghasilkan respon pembacaan katalog kelas dalam waktu $< 5\text{ms}$.

---

## 6. Template Skrip Uji Beban k6 (Stress Testing)

Jalankan uji beban menggunakan skrip **k6** untuk memvalidasi ketahanan sebelum hari pelaksanaan KRS War:

```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 500 },  // Ramp-up ke 500 virtual users
    { duration: '1m', target: 2000 },  // Lonjakan ekstrem ke 2.000 user serentak
    { duration: '30s', target: 0 },    // Ramp-down
  ],
  thresholds: {
    http_req_duration: ['p(95)<800'], // 95% request harus selesai di bawah 800ms
    http_req_failed: ['rate<0.01'],    // Kegagalan error < 1%
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

## 7. Checklist Kesiapan KRS War

- [ ] Redis server berjalan dengan alokasi memori minimal 4GB dan kebijakan *allkeys-lru*.
- [ ] Database PostgreSQL memiliki `max_connections >= 300` dengan pgBouncer aktif di mode *transaction pooling*.
- [ ] Seluruh kueri pemesanan kursi menggunakan transaksi `lockForUpdate()` dengan ID terurut.
- [ ] Ruang tunggu virtual (Waiting Room) teruji mengalihkan kelebihan trafik tanpa meruntuhkan web server.
- [ ] Uji coba k6 pada kelas berkuota 40 dengan 500 request serentak menghasilkan tepat 40 baris sukses dan 460 penolakan rapi tanpa *overbooking*.
