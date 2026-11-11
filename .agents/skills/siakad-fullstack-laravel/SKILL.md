---
name: siakad-fullstack-laravel
description: >-
  Architect and develop robust, secure, and maintainable fullstack applications for SIAKAD Kampus using Laravel 12/13, PHP 8.3+, Blade, Tailwind CSS, Eloquent/Query Builder, Service Layer pattern, Job Queues, Cache strategies, and PDDIKTI Feeder API integration. Use this skill whenever implementing controllers, business service layers, form requests, Eloquent relationships, PDF/Excel generation, API endpoints, payment webhooks, or background queue jobs in Laravel.
---

# SIAKAD Fullstack PHP Laravel Specialist Skill

Panduan keahlian dan standar rekayasa perangkat lunak bagi **Principal Fullstack Engineer & Senior Laravel Architect** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)** berbasis **PHP 8.3+, Laravel 12/13, Blade, Tailwind CSS v4, dan PostgreSQL**.

Keahlian ini menetapkan standar arsitektur *Service Layer Pattern*, efisiensi kueri bebas *N+1 Problem*, pemrosesan asinkron (*Queue Workers*) untuk ekspor transkrip PDF/Excel dan sinkronisasi Feeder, penanganan transaksi kritis (KRS & Pembayaran), serta keamanan data akademik anti-tampering.

---

## 1. Arsitektur Pola Desain (Service-Action-Controller Pattern)

Hindari penulisan logika bisnis di dalam Controller (*Fat Controller Antipattern*). Terapkan pemisahan tanggung jawab yang bersih:

```
[HTTP Request] ──> [FormRequest (Validasi)] ──> [Controller (Orkestrator HTTP)]
                                                       │
                                                       ▼
                                            [Service Layer (Logika Bisnis)]
                                                       │
                                    ┌──────────────────┴──────────────────┐
                                    ▼                                     ▼
                        [Eloquent Model / Query]              [Queue Job / Event Dispatch]
```

### Aturan Baku Struktur Kode:
1. **Controllers (`app/Http/Controllers`):** Hanya bertugas menerima request, memanggil service method yang relevan, dan mengembalikan HTTP response (Blade view, JSON, atau redirect dengan flash message).
2. **Form Requests (`app/Http/Requests`):** Menangani seluruh aturan validasi otorisasi dan format input (tipe data, rentang nilai, regex NIM).
3. **Services (`app/Services/Academic/*`):** Menampung seluruh aturan bisnis akademik (kalkulasi IPK, validasi prasyarat KRS, collision detection jadwal kuliah, integrasi Feeder).
4. **Data Transfer Objects (DTO) & Strict Typing:** Selalu aktifkan `declare(strict_types=1);` di setiap berkas PHP dan gunakan PHP 8.3 *Typed Properties* dan *Enums*.

---

## 2. Implementasi Service Inti SIAKAD

### 2.1 KrsEnrollmentService: Validasi Prasyarat & Collision Detection

Layanan untuk memvalidasi dan mendaftarkan kelas kuliah mahasiswa dengan pencegahan bentrok jadwal dan overbooking:

```php
<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\ClassSchedule;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class KrsEnrollmentService
{
    /**
     * Mendaftarkan kelas kuliah ke dalam KRS mahasiswa dengan proteksi transaksi.
     */
    public function enrollClass(Student $student, int $classScheduleId, int $academicPeriodId): KrsDetail
    {
        return DB::transaction(function () use ($student, $classScheduleId, $academicPeriodId): KrsDetail {
            // 1. Dapatkan atau inisialisasi header KRS aktif
            $krs = Krs::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_period_id' => $academicPeriodId,
                ],
                [
                    'status' => 'draft',
                    'max_credits' => $this->calculateMaxCredits($student, $academicPeriodId),
                ]
            );

            // 2. Kunci baris jadwal kelas dengan row-level lock
            /** @var ClassSchedule $class */
            $class = ClassSchedule::query()
                ->with(['course', 'timeSlot'])
                ->lockForUpdate()
                ->findOrFail($classScheduleId);

            // 3. Validasi kuota kelas
            if ($class->enrolled_count >= $class->quota) {
                throw ValidationException::withMessages([
                    'class_schedule_id' => "Kuota untuk kelas {$class->name} telah penuh ({$class->quota} kursi).",
                ]);
            }

            // 4. Validasi batas maksimum SKS
            $currentTotalCredits = $krs->details()->with('classSchedule.course')->get()
                ->sum(fn ($detail) => $detail->classSchedule->course->credits);

            if (($currentTotalCredits + $class->course->credits) > $krs->max_credits) {
                throw ValidationException::withMessages([
                    'credits' => "Total SKS melebihi batas beban studi maksimum ({$krs->max_credits} SKS).",
                ]);
            }

            // 5. Validasi bentrok jadwal (Schedule Collision Detection)
            $this->assertNoScheduleClash($krs, $class);

            // 6. Validasi prasyarat mata kuliah (Prerequisites)
            $this->assertPrerequisitesSatisfied($student, $class->course_id);

            // 7. Simpan detail KRS
            $detail = $krs->details()->create([
                'class_schedule_id' => $class->id,
                'status' => 'pending',
            ]);

            // 8. Inkrementasi counter kelas
            $class->increment('enrolled_count');

            return $detail;
        }, attempts: 3);
    }

    private function assertNoScheduleClash(Krs $krs, ClassSchedule $newClass): void
    {
        $existingClasses = $krs->details()
            ->with('classSchedule')
            ->get()
            ->pluck('classSchedule');

        foreach ($existingClasses as $existing) {
            if ($existing->day_of_week === $newClass->day_of_week) {
                // Cek tumpang tindih rentang waktu
                $hasOverlap = ($newClass->start_time < $existing->end_time) 
                           && ($newClass->end_time > $existing->start_time);

                if ($hasOverlap) {
                    throw ValidationException::withMessages([
                        'schedule_clash' => "Jadwal bentrok dengan mata kuliah {$existing->course->name} pada hari {$existing->day_of_week} ({$existing->start_time} - {$existing->end_time}).",
                    ]);
                }
            }
        }
    }

    private function calculateMaxCredits(Student $student, int $periodId): int
    {
        // Standar SN-Dikti: Evaluasi IPS semester lalu
        $previousIps = $student->getPreviousSemesterIps($periodId);

        return match (true) {
            $previousIps >= 3.00 => 24,
            $previousIps >= 2.50 => 21,
            $previousIps >= 2.00 => 18,
            default => 15,
        };
    }

    private function assertPrerequisitesSatisfied(Student $student, int $courseId): void
    {
        // Logika verifikasi kelulusan mata kuliah prasyarat dengan minimal nilai C
    }
}
```

---

### 2.2 GradeCalculationService: Kalkulasi Bobot OBE & Transkrip

Layanan kalkulasi nilai akhir angka, konversi nilai huruf, dan indeks prestasi semester (IPS):

```php
<?php

declare(strict_types=1);

namespace App\Services\Academic;

use App\Models\CourseGrade;

final class GradeCalculationService
{
    /**
     * Hitung nilai akhir berdasarkan bobot komponen mata kuliah.
     */
    public function computeFinalScore(
        float $attendanceScore,
        float $assignmentScore,
        float $midExamScore,
        float $finalExamScore,
        array $weights = ['attendance' => 0.10, 'assignment' => 0.20, 'uts' => 0.30, 'uas' => 0.40]
    ): array {
        $finalScore = ($attendanceScore * $weights['attendance'])
                    + ($assignmentScore * $weights['assignment'])
                    + ($midExamScore * $weights['uts'])
                    + ($finalExamScore * $weights['uas']);

        $finalScore = round($finalScore, 2);

        [$letter, $point] = $this->convertScoreToGradePoint($finalScore);

        return [
            'final_score' => $finalScore,
            'grade_letter' => $letter,
            'grade_point' => $point,
        ];
    }

    public function convertScoreToGradePoint(float $score): array
    {
        return match (true) {
            $score >= 85.00 => ['A', 4.00],
            $score >= 80.00 => ['A-', 3.75],
            $score >= 75.00 => ['B+', 3.50],
            $score >= 70.00 => ['B', 3.00],
            $score >= 65.00 => ['B-', 2.75],
            $score >= 60.00 => ['C+', 2.50],
            $score >= 55.00 => ['C', 2.00],
            $score >= 40.00 => ['D', 1.00],
            default => ['E', 0.00],
        };
    }
}
```

---

## 3. Menghindari N+1 Query & Optimasi Eloquent

Dalam sistem akademik yang menampilkan ribuan mahasiswa dan puluhan mata kuliah, N+1 query dapat melumpuhkan server database.

### 3.1 Strict Eloquent Mode (Wajib di AppServiceProvider)

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Cegah lazy loading secara global pada environment development & staging
    Model::preventLazyLoading(! app()->isProduction());
    Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
}
```

### 3.2 Eager Loading Khusus Relasi Akademik

```php
// BURUK: 1 kueri KRS + 50 kueri detail + 50 kueri kelas + 50 kueri mata kuliah (151 kueri)
$krsDetails = KrsDetail::where('krs_id', $krsId)->get();

// BAIK: Hanya 4 kueri terstruktur dengan Eager Loading
$krsDetails = KrsDetail::query()
    ->where('krs_id', $krsId)
    ->with([
        'classSchedule.course:id,code,name,credits',
        'classSchedule.lecturers:id,name,nidn',
        'classSchedule.room:id,name,building',
    ])
    ->get();
```

---

## 4. Pemrosesan Asinkron (Queues, Background Jobs & Export)

Operasi berat seperti ekspor transkrip PDF 500 mahasiswa atau sinkronisasi 10.000 riwayat nilai ke Neo Feeder **wajib dialihkan ke background queue worker** (Redis / Database Queue):

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AcademicPeriod;
use App\Services\FeederExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncGradesToFeederJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 menit
    public int $tries = 3;

    public function __construct(
        public readonly int $academicPeriodId,
        public readonly int $operatorUserId
    ) {}

    public function handle(FeederExportService $feederService): void
    {
        Log::info("Memulai sinkronisasi nilai ke PDDIKTI untuk periode: {$this->academicPeriodId}");

        $result = $feederService->syncGradesByPeriod($this->academicPeriodId);

        Log::info("Sinkronisasi Feeder selesai. Berhasil: {$result['success']}, Gagal: {$result['failed']}");
    }
}
```

---

## 5. Keamanan Aplikasi & Proteksi Data Akademik

1. **Anti-Tampering Nilai:**
   - Seluruh rute perubahan nilai dilindungi middleware `can:update-grade,grade` dan `VerifyGradeUnclosed` yang memastikan periode input nilai belum ditutup oleh BAAK.
2. **Session Concurrency Protection:**
   - Aktifkan `SESSION_BLOCK=true` di `.env` untuk mencegah race condition pada session pengguna saat membuka banyak tab sekaligus saat KRS.
3. **Penyimpanan Password & Identitas:**
   - Menggunakan hashing `Bcrypt` dengan cost 12.
   - Kolom sensitif seperti NIK, nomor rekening bank, atau nomor telepon mahasiswa dienkripsi menggunakan `casts => ['nik' => 'encrypted']`.

---

## 6. Checklist Standar Kualitas Fullstack Laravel Sebelum Deploy

- [ ] **Zero Unhandled N+1:** Seluruh halaman daftar mahasiswa, jadwal, dan nilai bebas dari query `select *` berulang di loop Blade.
- [ ] **Transaction Wrappers:** Seluruh proses mutasi data (enroll KRS, batalkan KRS, bayar UKT, ubah nilai) dibungkus `DB::transaction()`.
- [ ] **Form Request Validation:** Input disaring ketat melalui `FormRequest` khusus dengan pesan bahasa Indonesia yang informatif.
- [ ] **Async PDF/Excel Generation:** Download file besar (> 50 halaman) menggunakan job antrian atau *streamed response* (`response()->streamDownload()`).
- [ ] **Pint / Code Formatting:** Seluruh berkas PHP diformat rapi dengan `vendor/bin/pint`.
