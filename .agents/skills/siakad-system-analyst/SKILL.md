---
name: siakad-system-analyst
description: >-
  Architect, analyze, and specify academic business logic, multi-role RBAC matrices (14+ roles), BPMN workflows, data dictionaries, API contracts, and edge cases for SIAKAD Kampus (KRS prerequisites, OBE/CPL-CPMK curriculum, credit transfer, grading curves, graduation clearance, and PDDIKTI Feeder sync contracts). Use this skill whenever writing functional requirements (FR/NFR), modeling academic business processes, designing database entity mappings, specifying role permissions, or defining integration contracts.
---

# SIAKAD System Analyst & Academic Architecture Skill

Panduan keahlian dan spesifikasi arsitektur fungsional bagi **Senior System Analyst & Academic Solution Architect** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar pemodelan logika bisnis akademik, matriks hak akses multi-role (14+ peran), diagram alur proses bisnis (BPMN), aturan validasi akademik (aturan SKS, prasyarat, OBE), penanganan kasus khusus (*edge cases* seperti cuti, transfer, MBKM), serta kontrak spesifikasi API dan pemetaan entitas ke **PDDIKTI Neo Feeder**.

---

## 1. Domain Model & Arsitektur Bisnis SIAKAD

Sistem Informasi Akademik mengintegrasikan siklus hidup akademik mahasiswa dari proses penerimaan mahasiswa baru (PMB) hingga alumni:

```
[PMB / Admisi] ──> [Registrasi Ulang / Billing UKT] ──> [Plotting Kurikulum & Jadwal]
       │                                                         │
       ▼                                                         ▼
[Alumni / Tracer] <── [Yudisium & Wisuda] <── [Nilai & KHS] <── [KRS & Perkuliahan]
```

### Entitas Bisnis Utama (Core Domain Entities):
1. **Periode Akademik (`academic_periods`):** Tahun ajaran (misal: 2026/2027), semester (Ganjil, Genap, Pendek), tanggal buka/tutup pengisian KRS, revisi KRS, input nilai, dan pelaporan PDDIKTI.
2. **Kurikulum & OBE (`curricula`, `cpl`, `cpmk`):** Struktur mata kuliah, capaian pembelajaran lulusan (CPL), sub-CPMK, jenis mata kuliah (wajib institusi, wajib prodi, pilihan, MBKM).
3. **Kelas Kuliah & Jadwal (`class_schedules`):** Kombinasi mata kuliah, dosen pengampu, kuota mahasiswa, ruangan, hari, jam mulai-selesai.
4. **KRS & KHS (`krs`, `krs_details`, `khs`):** Pendaftaran mata kuliah per mahasiswa per semester beserta status approval Dosen PA dan rincian nilai akhir.
5. **Keuangan & Tagihan (`student_billings`, `payments`):** Penetapan nominal UKT/IPI, status pembayaran lunas/belum/dispensasi sebagai pintu gerbang (*gatekeeper*) pengisian KRS.

---

## 2. Matriks Otorisasi & Kontrol Akses Multi-Role (RBAC)

SIAKAD mendukung sedikitnya **14 peran pengguna** dengan batas wewenang yang terisolasi ketat:

| Kode Role | Nama Peran Pengguna | Hak Akses Utama & Batas Wewenang |
| :--- | :--- | :--- |
| `superadmin` | Administrator Sistem | Akses penuh konfigurasi sistem, audit log, backup database, kelola master akun. |
| `baak` | Biro Administrasi Akademik | Kelola kalender akademik, master kurikulum, plotting jadwal, monitoring KRS kampus. |
| `keuangan` | Biro Keuangan / Kasir | Setting tarif UKT/IPI, upload tagihan massal, verifikasi bukti bayar manual, rekonsiliasi. |
| `rektorat` | Pimpinan Universitas | Dashboard eksekutif (KPI kampus, retensi mahasiswa, rasio dosen:mahasiswa, serapan UKT). |
| `dekan` | Pimpinan Fakultas | Monitoring akademik tingkat fakultas, approval dispensasi dan pembukaan kelas khusus. |
| `kaprodi` | Ketua Program Studi | Distribusi mata kuliah, penugasan dosen pengampu & pembimbing akademik, validasi nilai akhir. |
| `dosen_pa` | Dosen Pembimbing Akademik | Bimbingan akademik, review rencana studi, validasi/penolakan KRS bimbingan. |
| `dosen` | Dosen Pengampu Kelas | Pengisian jurnal kuliah, input presensi harian, penilaian tugas/UTS/UAS, kalkulasi OBE. |
| `dosen_penguji`| Dosen Penguji Sidang | Input nilai ujian seminar proposal, komprehensif, dan sidang skripsi/tesis. |
| `mahasiswa` | Mahasiswa Aktif | Pengisian KRS, melihat jadwal, riwayat presensi, pengisian survei EDOM, unduh KHS/Transkrip. |
| `camaba` | Calon Mahasiswa Baru | Pengisian biodata pendaftaran, upload berkas, cetak kartu ujian seleksi PMB. |
| `lpm` | Lembaga Penjamin Mutu | Evaluasi instrumen EDOM, audit capaian CPL kurikulum OBE, pelaporan akreditasi BAN-PT/LAM. |
| `perpustakaan`| Petugas Perpustakaan | Validasi surat keterangan bebas tanggungan pustaka sebagai syarat yudisium. |
| `alumni` | Alumni / Lulusan | Akses legalisir ijazah online, pengisian kuesioner tracer study Kemendiktisaintek. |

---

## 3. Logika Bisnis & Diagram Alur Alur Kerja (Workflow Specifications)

### 3.1 Alur Registrasi KRS & Validasi Prasyarat Beban Studi

```
[Mahasiswa Login] ──> [Cek Status Keuangan (Lunas UKT?)]
                              │
                    ┌─────────┴─────────┐
                   TIDAK                YA
                    │                   │
             [Tolak Akses KRS]   [Cek Batas Maksimum SKS (Berdasarkan IPS Semester Lalu)]
                                        │
                                 [Pilih Kelas Kuliah]
                                        │
                           ┌────────────┴────────────┐
                         VALID                     INVALID
                           │                         │
                  [Cek Prasyarat MK]          [Tampilkan Error]
                  [Cek Jadwal Bentrok]        - Kuota Penuh
                  [Cek Kuota Kelas]           - Bentrok Waktu
                           │                  - SKS Melebihi Batas
                           ▼
                  [Submit Rencana Studi]
                           │
                  [Status: Menunggu ACC PA]
                           │
                  [Dosen PA Approval] ──> [Status: Disetujui (KRS Terkunci)]
```

#### Aturan Beban Studi Maksimum (Berdasarkan Standar SN-Dikti):
- **IPS $\ge$ 3.00:** Maksimum **24 SKS**
- **2.50 $\le$ IPS $\le$ 2.99:** Maksimum **21 SKS**
- **2.00 $\le$ IPS $\le$ 2.49:** Maksimum **18 SKS**
- **IPS < 2.00:** Maksimum **15 SKS**
- *Mahasiswa Semester 1 & 2*: Sistem paket otomatis (20-21 SKS) tanpa terpengaruh IPS.

---

### 3.2 Alur Penilaian Dosen & Finalisasi KHS

1. **Komponen Penilaian:**
   $$\text{Nilai Akhir} = (K \times \%K) + (T \times \%T) + (\text{UTS} \times \%\text{UTS}) + (\text{UAS} \times \%\text{UAS})$$
2. **Aturan Presensi Kuliah:**
   - Mahasiswa dengan kehadiran $< 75\%$ dari total pertemuan efektif (misal: hadir kurang dari 12 dari 16 pertemuan) **otomatis diblokir** dari ujian akhir semester (UAS) dan memperoleh grade maksimal **D/E**.
3. **Kunci Nilai (Grade Locking):**
   - Setelah dosen menekan tombol *Finalisasi Nilai*, status kelas menjadi `locked`. Nilai tidak dapat diubah kecuali melalui prosedur resmi *Buka Kunci Nilai* dengan persetujuan Kaprodi dan Dekan yang tercatat di audit log.

---

### 3.3 Alur Kurikulum Outcome-Based Education (OBE)

```
[Profil Lulusan] ──> [Capaian Pembelajaran Lulusan (CPL)]
                               │
                               ▼
                   [CPMK (Capaian MK)]
                               │
                               ▼
                   [Sub-CPMK / Indikator]
                               │
                               ▼
                 [Asesmen: Tugas / UTS / UAS]
```
- Setiap butir soal ujian atau penugasan wajib dipetakan ke minimal satu **Sub-CPMK**.
- Nilai akhir mahasiswa terdisagregasi ke dalam grafik radar capaian CPL untuk portofolio lulusan.

---

## 4. Penanganan Kasus Khusus (Edge Cases & Exception Logic)

### 4.1 Cuti Akademik vs Non-Aktif
- **Cuti Akademik Resmi:** Mahasiswa mengajukan via portal $\rightarrow$ diverifikasi BAAK $\rightarrow$ diterbitkan SK Rektor. Tagihan UKT disesuaikan (biasanya 0% atau biaya administrasi cuti), status di PDDIKTI tercatat `C` (Cuti), masa studi tidak bertambah.
- **Mahasiswa Non-Aktif (Mangkir):** Tidak registrasi ulang dan tidak mengajukan cuti resmi selama 2 semester berturut-turut $\rightarrow$ sistem mengirim peringatan SP1, SP2 $\rightarrow$ rekomendasi otomatis berstatus `Drop Out (DO)`.

### 4.2 Konversi Nilai & SKS Program MBKM
- Mahasiswa mengikuti program Kampus Merdeka (Magang / Studi Independen) setara maksimal 20 SKS.
- Analis harus mendesain modul **Ekuivalensi MK MBKM** di mana Kaprodi dapat memetakan aktivitas mitra ke 4-6 mata kuliah internal dengan nilai akhir dari mentor industri.

### 4.3 Mahasiswa Mengulang Mata Kuliah
- Jika mahasiswa mengambil ulang mata kuliah yang sudah pernah ditempuh:
  - Kebijakan Umum: Sistem mengambil **Nilai Tertinggi (Best Grade)** atau **Nilai Terakhir (Latest Grade)** sesuai ketetapan statuta kampus.
  - SKS tidak dihitung ganda pada kalkulasi SKS Kumulatif Transkrip.

---

## 5. Kontrak Integrasi PDDIKTI Neo Feeder

Setiap data transaksional di SIAKAD harus memiliki pemetaan langsung ke arsitektur tabel Neo Feeder Kemendiktisaintek:

| Entitas SIAKAD | Tabel Tujuan Neo Feeder | Field Kunci Identifikasi |
| :--- | :--- | :--- |
| `students` | `mahasiswa`, `mahasiswa_pt` | `id_mahasiswa`, `nim`, `nik`, `nama_mahasiswa`, `id_prodi` |
| `curricula_courses` | `mata_kuliah` | `id_matkul`, `kode_mata_kuliah`, `sks_mata_kuliah`, `id_prodi` |
| `class_schedules` | `kelas_kuliah` | `id_kelas_kuliah`, `id_semester`, `nama_kelas_kuliah` |
| `class_lecturers` | `ajar_dosen` | `id_aktivitas_mengajar`, `id_dosen`, `rencana_minggu_pertemuan` |
| `krs_details` | `peserta_kelas_kuliah` | `id_kelas_kuliah`, `id_registrasi_mahasiswa` |
| `course_grades` | `nilai_perkuliahan` | `id_kelas_kuliah`, `nilai_angka`, `nilai_huruf`, `nilai_indeks` |
| `student_semesters` | `aktivitas_kuliah_mahasiswa` | `id_semester`, `id_registrasi_mahasiswa`, `ips`, `ipk`, `sks_smt` |
| `graduations` | `mahasiswa_lulus_do` | `id_jenis_keluar`, `nomor_ijazah_nasional`, `tanggal_keluar` |

---

## 6. Checklist Kualitas Analisis Sistem (System Analyst Sign-Off)

- [ ] **Kelengkapan Dokumen FR / NFR:** Seluruh use case memiliki skenario utama (*happy path*) dan skenario alternatif/gagal.
- [ ] **Validasi RBAC:** Tidak ada celah *privilege escalation* (misal: mahasiswa merekayasa ID dosen untuk approve KRS sendiri).
- [ ] **Integritas Aturan Akademik:** Validasi batas SKS, prasyarat kelulusan MK, dan syarat kehadiran terdefinisi secara kuantitatif.
- [ ] **Kamus Data Standar:** Seluruh kolom database memiliki tipe data, batasan *nullable*, nilai default, dan referensi *foreign key*.
- [ ] **Kesiapan Feeder:** Entitas data yang dirancang tidak menyebabkan *schema mismatch* saat disinkronisasikan ke PDDIKTI Neo Feeder.
