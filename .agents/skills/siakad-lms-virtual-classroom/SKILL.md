---
name: siakad-lms-virtual-classroom
description: >-
  Architect and operate the Learning Management System (LMS) and Virtual Classroom module within SIAKAD Kampus. Structures 16-meeting semester plans (14 interactive lectures + UTS + UAS), distributes digital course materials and video lectures, powers automated timed quizzes and question banks with randomized items, implements rolling dynamic QR code attendance (TOTP 30-second rotation with GPS radius bounds), and passes student assignment and exam scores directly into the OBE CPMK gradebook. Use this skill whenever building LMS features, configuring online quiz engines, developing dynamic QR attendance, or synchronizing e-learning grades.
---

# SIAKAD LMS & Interactive Virtual Classroom Specialist Skill

Panduan keahlian sistem pembelajaran daring dan ruang kelas interaktif bagi **LMS & Interactive Classroom Architect** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur modul pembelajaran digital terintegrasi (*Integrated Learning Management System*), mencakup struktur perkuliahan 16 pertemuan terstandar (14 kuliah materi + UTS + UAS), distribusi bahan ajar digital, mesin kuis dan bank soal daring dengan pengacakan otomatis (*randomized question bank*), presensi dinamis berbasis kode QR bergulir (*rolling dynamic QR code*) dengan batasan radius GPS kampus, serta sinkronisasi perolehan nilai tugas langsung ke buku nilai kurikulum OBE (*OBE CPMK gradebook passback*).

---

## 1. Arsitektur Semester 16 Pertemuan Terstruktur

Modul LMS menstrukturkan ruang kelas daring per mata kuliah mengikuti standar kurikulum perguruan tinggi:

```
Pertemuan 01 - 07: Modul Perkuliahan Tatap Muka / Asinkronus (Materi, Video, Forum Diskusi)
        │
        ▼
Pertemuan 08     : Ujian Tengah Semester (UTS) — Ujian Daring Terjadwal / Pengumpulan Berkas
        │
        ▼
Pertemuan 09 - 15: Lanjutan Perkuliahan Lanjutan, Praktikum, & Tugas Proyek Kolaboratif
        │
        ▼
Pertemuan 16     : Ujian Akhir Semester (UAS) — Evaluasi Komprehensif CPMK
```

### Komponen Tiap Pertemuan Perkuliahan:
1. **Rencana Pembelajaran Mingguan (Sub-CPMK)**: Penjelasan kompetensi yang harus dikuasai mahasiswa pada minggu bersangkutan.
2. **Bahan Ajar & Media**: Dokumen PDF (slide presentasi), video rekaman kuliah, pranala jurnal eksternal, atau paket modul SCORM 1.2/2004.
3. **Aktivitas Interaktif**: Forum diskusi terpandu dengan penilaian keaktifan (*discussion rubric*).
4. **Kotak Pengumpulan Tugas (Assignment Dropbox)**: Pengunggahan berkas tugas dengan batas waktu ketat (*hard deadline*), pencatatan waktu keterlambatan, dan integrasi pemeriksaan plagiasi.

---

## 2. Presensi Dinamis Kode QR Bergulir (Rolling Dynamic QR Attendance)

Untuk mencegah kecurangan absensi (*titip absen* atau tangkapan layar kode QR yang dibagikan ke mahasiswa di luar kelas), SIAKAD menggunakan teknologi **Dynamic TOTP QR Code**:

```
┌─────────────────────────┐       Refresh Setiap 30 Detik       ┌─────────────────────────┐
│  Layar Proyektor Dosen  │ ──────────────────────────────────> │  Aplikasi Mobile Siswa  │
│  Tampilkan QR Dinamis   │                                     │  Pindai Kamera & GPS    │
└────────────┬────────────┘                                     └────────────┬────────────┘
             │                                                               │
             │ Payload: AES-256(JadwalID + Microtime + Nonce)                │ POST Validasi
             ▼                                                               ▼
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                    API VALIDASI PRESENSI PRESISI (SERVER-SIDE)                          │
├─────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. Verifikasi Nonce & Time-Window: Token QR masih berlaku (< 30 detik).                 │
│ 2. Validasi Geofencing GPS: Koordinat lintang/bujur mahasiswa berada dalam radius       │
│    maksimal 50 meter dari titik koordinat gedung/ruang kelas.                          │
│ 3. Validasi Device Fingerprint: 1 perangkat ponsel fisik hanya boleh mencatat presensi  │
│    untuk 1 akun mahasiswa per sesi (mencegah multi-login dalam 1 HP).                  │
│ 4. Insert ke t_presensi_perkuliahan dengan status 'HADIR'.                              │
└─────────────────────────────────────────────────────────────────────────────────────────┘
```

### Implementasi Verifikasi Presensi QR:
```php
namespace App\Services\Lms;

use Illuminate\Support\Facades\Crypt;
use App\Exceptions\AttendanceFraudException;
use App\Models\ClassAttendance;

class QrAttendanceValidator
{
    /**
     * Memvalidasi pemindaian QR absensi mahasiswa
     */
    public function validateScan(int $studentId, int $scheduleId, string $qrToken, float $lat, float $lng, string $deviceId): ClassAttendance
    {
        // 1. Dekripsi token QR
        $decrypted = json_decode(Crypt::decryptString($qrToken), true);

        if (!$decrypted || ($decrypted['schedule_id'] ?? 0) !== $scheduleId) {
            throw new AttendanceFraudException("Token QR tidak valid untuk jadwal kelas ini.");
        }

        // 2. Cek toleransi waktu (maksimal 35 detik sejak dibuat di layar dosen)
        if (now()->timestamp - $decrypted['timestamp'] > 35) {
            throw new AttendanceFraudException("Kode QR telah kedaluwarsa. Silakan pindai ulang dari layar.");
        }

        // 3. Validasi Geofencing Jarak (Haversine Formula)
        $room = $decrypted['room']; // ['lat' => -6.2088, 'lng' => 106.8456]
        $distanceMeters = $this->calculateDistance($lat, $lng, $room['lat'], $room['lng']);

        if ($distanceMeters > 50.0) {
            throw new AttendanceFraudException("Posisi Anda berada di luar radius kelas ({$distanceMeters}m).");
        }

        // 4. Cegah joki absen (1 Device ID hanya boleh absen 1 mahasiswa pada jadwal ini)
        $deviceUsed = ClassAttendance::where('class_schedule_id', $scheduleId)
            ->where('meeting_number', $decrypted['meeting_number'])
            ->where('device_id', $deviceId)
            ->where('student_id', '!=', $studentId)
            ->exists();

        if ($deviceUsed) {
            throw new AttendanceFraudException("Perangkat ini telah digunakan untuk absensi mahasiswa lain!");
        }

        // 5. Catat presensi sukses
        return ClassAttendance::updateOrCreate(
            [
                'student_id' => $studentId,
                'class_schedule_id' => $scheduleId,
                'meeting_number' => $decrypted['meeting_number'],
            ],
            [
                'status' => 'HADIR',
                'device_id' => $deviceId,
                'verified_at' => now(),
            ]
        );
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // Meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
```

---

## 3. Mesin Bank Soal, Kuis Daring & Ujian Terjadwal

Modul ujian daring dilengkapi mekanisme integritas ujian untuk mencegah kecurangan:

1. **Pengacakan Ganda (Double Shuffling)**:
   - Pengacakan urutan nomor soal per mahasiswa.
   - Pengacakan urutan pilihan ganda (A, B, C, D, E) untuk tiap butir soal.
2. **Koleksi Bank Soal Bertingkat**:
   - Soal dikelompokkan berdasarkan CPMK dan tingkat kesulitan (Mudah, Sedang, Sulit).
   - Sistem memilih $x$ soal mudah, $y$ soal sedang, dan $z$ soal sulit secara acak dari wadah pool soal.
3. **Deteksi Perpindahan Tab / Browser Focus**:
   - Jika mahasiswa membuka tab lain atau memperkecil jendela ujian, sistem mencatat log pelanggaran dan mengurangi sisa waktu pengerjaan.
4. **Auto-Submit on Timeout**:
   - Saat batas waktu habis, jawaban yang telah tersimpan di browser otomatis terkirim dan terkunci.

---

## 4. Aliran Balik Nilai ke Buku Nilai Akademik (Gradebook Passback)

Setiap kuis atau penugasan di LMS terhubung langsung ke ID instrumen asesmen OBE (`obe_assessment_items`):

$$\text{Tugas LMS / Ujian} \xrightarrow{\text{Kalkulasi Nilai Otomatis}} \text{Tabel Nilai Komponen} \xrightarrow{\text{Agregasi Bobot}} \text{Buku Nilai Dosen (Gradebook)}$$

- Nilai tugas esai dinilai oleh dosen pengampu/asisten dosen menggunakan rubrik penilaian berskala Likert/Kriteria.
- Nilai pilihan ganda dinilai secara instan dalam milidetik dan langsung muncul pada rekap capaian CPMK mahasiswa.

---

## 5. Checklist Kesiapan Operasional LMS

- [ ] Seluruh mata kuliah aktif telah tergenerasi 16 slot pertemuan perkuliahan.
- [ ] Fitur QR Code presensi bergulir 30 detik dan validasi jarak GPS (< 50 meter) berfungsi presisi di perangkat mobile.
- [ ] Fitur single device per student aktif untuk mencegah penitipan presensi.
- [ ] Pengacakan bank soal kuis berjalan independen untuk tiap mahasiswa.
- [ ] Nilai tugas dan kuis yang telah diverifikasi dosen otomatis terpetakan ke komponen buku nilai OBE.
