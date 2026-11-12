---
name: siakad-fullstack-laravel
description: >-
  Architect and develop robust, secure, and maintainable fullstack applications for SIAKAD Kampus using Laravel 12/13, PHP 8.3+, Blade, Tailwind CSS, Eloquent/Query Builder, Service Layer pattern, Job Queues, Cache strategies, and PDDIKTI Feeder API integration. Use this skill whenever implementing controllers, business service layers, form requests, Eloquent relationships, PDF/Excel generation, API endpoints, payment webhooks, or background queue jobs in Laravel.
---

# SIAKAD Fullstack PHP Laravel Specialist Skill

Panduan keahlian dan standar rekayasa perangkat lunak bagi **Principal Fullstack Engineer & Senior Laravel Architect** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)** berbasis **PHP 8.3+, Laravel 12/13, Blade, Tailwind CSS v4, dan PostgreSQL**.

Keahlian ini menetapkan standar arsitektur *Service-Action-Controller Pattern*, controller ramping (*thin controllers*), efisiensi kueri bebas *N+1 Query Problem* (`preventLazyLoading`), pemrosesan asinkron (*Redis Queue Workers*) untuk ekspor transkrip PDF/Excel dan sinkronisasi Feeder, penanganan transaksi kritis (KRS `FOR UPDATE` & Pembayaran Midtrans), keamanan data akademik anti-tampering, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Core Backend Engineer & Principal Laravel Architect**
- **Domain Fokus**: Implementasi Service Layer, Form Request, controller ramping, pemodelan relasi Eloquent teroptimasi, antrean Redis asinkron, dan integrasi backend API.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-fullstack-laravel` | `siakad-security-compliance-auditor` | Deteksi N+1 query (`with()`/`loadMissing()`), sanitasi input FormRequest, otorisasi policy/RBAC anti-IDOR, strict typing PHP 8.3, dan coverage unit/feature test. |
| **Sebagai Reviewer Wajib** | `siakad-lms-virtual-classroom` | `siakad-fullstack-laravel` | Validasi endpoint LMS, logika enkripsi TOTP presensi QR 30s, transaksi passback nilai ke gradebook, dan beban kueri kuis. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap fitur baru, perbaikan bug, atau refactoring backend wajib didekomposisi menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Controller dipertahankan tetap tipis (*thin controller*); logika bisnis ditempatkan pada Service/Action.
3. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan implementasi Service / Controller / Action / Queue Job]

## Assigned Implementer
`siakad-fullstack-laravel`

## Mandatory Reviewer
`siakad-security-compliance-auditor`

## Context & Files
- Scope: Backend Service, Form Request, Controller, Queue Job
- File terkait: `app/Services/*`, `app/Http/Controllers/*`, `app/Http/Requests/*`, `tests/Feature/*`

## Scrum Steps
1. Definisikan FormRequest dengan aturan validasi ketat dan pesan informatif.
2. Buat atau perbarui Service/Action class yang membungkus transaksi bisnis (`DB::transaction`).
3. Pastikan seluruh relasi Eloquent dieager-load menggunakan `with()` atau `loadMissing()`.
4. Tulis pengujian otomatis (Feature Test) dan jalankan verifikasi LSP 0 error.

## Acceptance Criteria & DoD
- [ ] Controller hanya bertugas menangani request validation dan dispatch response.
- [ ] Seluruh kueri Eloquent bebas N+1 (lulus pada `preventLazyLoading`).
- [ ] Transaksi kritis (KRS, billing) dibungkus `DB::transaction()`.
- [ ] Pengujian Feature Test lulus 100%.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan brief Markdown lengkap dan acceptance criteria.
- **In Progress**: Laravel Engineer menulis kode di branch/worktree terisolasi, memastikan tes lokal lulus.
- **Under Review**: Security Auditor memverifikasi N+1, otorisasi RBAC, sanitasi input, dan kelulusan test.
- **Done**: Disetujui reviewer dan dimerge oleh user pada kartu pekerja.

---

## 3. Penyelarasan Backend dengan Siklus Hidup Akademik 6-Fase

Laravel Engineer mengimplementasikan logika layanan di setiap fase operasional kampus:

| Fase Akademik | Fokus Layanan Backend Laravel | Service & Action Classes Kunci |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Inisiasi billing massal, webhook handler Midtrans dengan validasi SHA-512, toggle `is_financial_cleared`. | `StudentBillingService`, `MidtransWebhookAction`. |
| **Fase 2: Registrasi KRS** | Transaksi atomik `lockForUpdate()`, collision detection jadwal, validasi prasyarat MK & batas SKS. | `KrsEnrollmentService`, `AssertPrerequisitesAction`. |
| **Fase 3: Masa Perkuliahan** | Manajemen 16 pertemuan, kalkulasi persentase absensi, verifikasi TOTP QR code presensi. | `AttendanceService`, `LmsMeetingService`. |
| **Fase 4: Evaluasi & Ujian** | Agregasi bobot nilai OBE, konversi nilai angka ke huruf/mutu, penguncian status gradebook `LOCKED`. | `GradeCalculationService`, `LockGradebookAction`. |
| **Fase 5: Kelulusan & Wisuda** | Audit kelayakan 144 SKS, validasi berita acara ujian sidang, generate transkrip resmi bertanda tangan QR. | `GraduationClearanceService`, `TranscriptExportAction`. |
| **Fase 6: Feeder PDDIKTI** | Job queue asinkron Redis untuk pengiriman batch data (AKM, Nilai, KRS) dengan pemulihan checkpoint. | `SyncFeederBatchJob`, `PddiktiClientService`. |

---

## 4. Arsitektur Pola Desain (Service-Action-Controller Pattern)

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

## 5. Implementasi Service Inti SIAKAD

### 5.1 KrsEnrollmentService: Validasi Prasyarat & Collision Detection

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
     * Mendaftarkan kelas kuliah ke dalam KRS mahasiswa dengan proteksi transaksi atomik.
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

            // 2. Kunci baris jadwal kelas dengan row-level lock (anti-overbooking)
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

            // 8. Inkrementasi counter pendaftar
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
        // Verifikasi kelulusan mata kuliah prasyarat dengan minimal nilai C
    }
}
```

---

### 5.2 GradeCalculationService: Kalkulasi Bobot OBE & Transkrip

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

## 6. Menghindari N+1 Query & Optimasi Eloquent

Dalam sistem akademik yang menampilkan ribuan mahasiswa dan puluhan mata kuliah, N+1 query dapat melumpuhkan server database.

### 6.1 Strict Eloquent Mode (Wajib di AppServiceProvider)

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

### 6.2 Eager Loading Khusus Relasi Akademik

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

## 7. Antrean Asinkron (Async Queue Workers) & Event Dispatcher

Operasi berat seperti ekspor transkrip PDF massal, rekap ledger keuangan, dan pengiriman batch Neo Feeder **tidak boleh dijalankan secara sinkronus pada siklus HTTP request**:

```php
// Dispatch job ke antrean Redis
dispatch(new ExportStudentTranscriptPdfJob($studentId, $academicPeriodId))
    ->onQueue('documents')
    ->afterCommit();

dispatch(new SyncFeederAkmBatchJob($academicPeriodId, $batchOffset))
    ->onQueue('feeder')
    ->afterCommit();
```

---

## 8. Keamanan Aplikasi & Proteksi Data Akademik

1. **Anti-Tampering Nilai:**
   - Seluruh rute perubahan nilai dilindungi middleware `can:update-grade,grade` dan `VerifyGradeUnclosed` yang memastikan periode input nilai belum ditutup oleh BAAK.
2. **Session Concurrency Protection:**
   - Aktifkan `SESSION_BLOCK=true` di `.env` untuk mencegah race condition pada session pengguna saat membuka banyak tab sekaligus saat KRS.
3. **Penyimpanan Password & Identitas:**
   - Menggunakan hashing `Bcrypt` dengan cost 12.
   - Kolom sensitif seperti NIK, nomor rekening bank, atau nomor telepon mahasiswa dienkripsi menggunakan `casts => ['nik' => 'encrypted']`.

---

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap implementasi kode Laravel wajib memenuhi kriteria berikut sebelum disahkan:

- [ ] **Service-Action Pattern**: Logika bisnis dan transaksi dibungkus rapi di layer Service/Action; Controller tetap tipis.
- [ ] **Zero N+1 Query**: Seluruh pemanggilan relasi Eloquent wajib menyertakan `with()` atau `loadMissing()`, terbukti lolos pada `preventLazyLoading`.
- [ ] **Pessimistic Locking**: Modifikasi kuota kelas KRS dieksekusi dalam transaksi atomik dengan `lockForUpdate()`.
- [ ] **Asynchronous Processing**: Dokumen berat (PDF/Excel) dan integrasi sinkronisasi Feeder dikirim ke Redis Queue.
- [ ] **RBAC & Data Scoping**: Rute dan aksi terproteksi policy check peran dan pembatasan `prodi_id`.
- [ ] **Strict Typing**: Seluruh berkas PHP baru memuat `declare(strict_types=1);` dengan tipe parameter dan return eksplisit.
- [ ] **LSP & Testing**: Diagnostik LSP menghasilkan 0 error dan Feature Test mencakup skenario happy path serta failure edge cases.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-security-compliance-auditor`).
