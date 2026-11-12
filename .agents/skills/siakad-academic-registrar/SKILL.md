---
name: siakad-academic-registrar
description: >-
  Manage registrar operations, master timetable collision resolution, official student lifecycle mutations, and official transcript compilation for SIAKAD Kampus. Identifies and eliminates scheduling collisions across rooms, lecturers, and student cohorts, executes student mutations (academic leave/cuti, re-activation, study program transfers, drop-out/DO processing), monitors academic standing (IPS/IPK warning thresholds), and compiles tamper-evident official transcripts and KHS documents with embedded SHA-256 digital verification QR codes. Use this skill whenever configuring master schedules, resolving timetable collisions, processing student status mutations, or generating official transcripts.
---

# SIAKAD BAAK & Student Lifecycle Registrar Specialist Skill

Panduan keahlian administrasi akademik dan pengelolaan siklus mahasiswa bagi **BAAK & Student Lifecycle Registrar Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar operasional Biro Administrasi Akademik dan Kemahasiswaan (BAAK) dalam penyusunan jadwal kuliah bebas bentrok (*collision-free master timetable*), alokasi kapasitas ruang kuliah, pemrosesan mutasi status akademik mahasiswa (Cuti Akademik, Re-aktivasi, Mutasi/Pindah Prodi, Drop-Out / DO, Pengunduran Diri), pemantauan evaluasi masa studi (*academic standing & retention warning*), kompilasi transkrip akademik dan Kartu Hasil Studi (KHS) bertanda tangan digital dengan kode verifikasi QR, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Academic Registrar Specialist & BAAK Operations Lead**
- **Domain Fokus**: Master timetable penjadwalan kelas bebas bentrok (dosen, ruangan, angkatan), pemrosesan SK mutasi mahasiswa (cuti/DO/transfer), evaluasi batas studi (Early Warning System), dan penerbitan transkrip resmi ber-QR.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-academic-registrar` | `siakad-pddikti-feeder-officer` | Kepatuhan jadwal dan mutasi mahasiswa terhadap regulasi nasional PDDIKTI, serta integritas data AKM. |
| **Sebagai Reviewer Wajib (1)** | `siakad-pddikti-feeder-officer` | `siakad-academic-registrar` | Validasi kelengkapan data master kurikulum, mahasiswa aktif, dan pelaporan AKM semesteran sebelum submit. |
| **Sebagai Reviewer Wajib (2)** | `siakad-thesis-graduation-manager` | `siakad-academic-registrar` | Audit kelayakan kelulusan: minimal 144 SKS (S1), ketiadaan nilai E, dan keabsahan nomor ijazah nasional (PIN). |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap perancangan jadwal master, pemrosesan mutasi massal, atau fitur evaluasi studi dipecah menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan penjadwalan bebas bentrok / pemrosesan mutasi status / penerbitan transkrip resmi]

## Assigned Implementer
`siakad-academic-registrar`

## Mandatory Reviewer
`siakad-pddikti-feeder-officer`

## Context & Files
- Scope: Master Timetable, Status Mutasi Mahasiswa, Transkrip & KHS Ber-QR
- File terkait: `app/Services/Academic/*`, `resources/views/class-schedules/*`, `resources/views/transcripts/*`

## Scrum Steps
1. Validasi collision detection pada dimensi dosen, ruangan, jam, dan kelas angkatan.
2. Proses status mutasi (cuti/pindah) dengan nomor SK Dekan/Rektor resmi.
3. Generate transkrip resmi dengan signature hash SHA-256 dan kode QR verifikasi publik.
4. Uji kelayakan data terhadap ketentuan pelaporan PDDIKTI.

## Acceptance Criteria & DoD
- [ ] Jadwal master terverifikasi 100% bebas bentrok dosen, ruangan, dan kelas cohort.
- [ ] Mutasi status mahasiswa tervalidasi dengan SK resmi dan tersinkronkan ke entitas AKM.
- [ ] Transkrip resmi memuat kode QR publik yang dapat diverifikasi keasliannya.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan lingkup kalender akademik semester baru.
- **In Progress**: Registrar menyusun jadwal, algoritma deteksi bentrok, dan mutasi mahasiswa.
- **Under Review**: Feeder Officer memvalidasi kesesuaian skema kementerian dan aturan beban studi.
- **Done**: Disetujui reviewer dan disahkan sebagai jadwal aktif kampus.

---

## 3. Penyelarasan BAAK dengan Siklus Hidup Akademik 6-Fase

Registrar BAAK mengawal ketertiban administrasi pada seluruh rangkaian siklus akademik:

| Fase Akademik | Tanggung Jawab Registrar BAAK | Output Dokumen & Status |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Verifikasi berkas fisik calon mahasiswa, penetapan nomor induk mahasiswa (NIM). | Master Mahasiswa Baru Terdaftar. |
| **Fase 2: Registrasi KRS** | **Penyusunan Master Timetable Bebas Bentrok**, pembagian kuota kelas, plotting ruangan & dosen. | Jadwal Kuliah Semester Aktif. |
| **Fase 3: Masa Perkuliahan** | Pemrosesan permohonan Cuti Akademik resmi, monitoring presensi harian dosen dan mahasiswa. | SK Cuti Akademik, Rekap Presensi. |
| **Fase 4: Evaluasi & Ujian** | Distribusi Kartu Peserta Ujian (KPU), pemantauan syarat presensi $\ge 75\%$, penerbitan KHS. | KPU Ujian, KHS ber-QR Code. |
| **Fase 5: Kelulusan & Wisuda** | Audit transkrip kelulusan (144 SKS), verifikasi ijazah nasional PIN, penerbitan transkrip resmi. | Transkrip Kelulusan Sah Ber-QR. |
| **Fase 6: Feeder PDDIKTI** | Rekonsiliasi data Aktivitas Kuliah Mahasiswa (AKM) dan status keluar mahasiswa (Lulus/DO). | Berita Acara Sinkronisasi Feeder. |

---

## 4. Algoritma Penjadwalan Bebas Bentrok (Timetable Collision Resolution)

Penyusunan jadwal kuliah semesteran wajib mematuhi 4 dimensi batasan (*constraints*) untuk mencegah bentrok jadwal:

```
                  ┌─────────────────────────────────────────┐
                  │ 1. BATASAN DOSEN (Lecturer Constraint)  │
                  │    Dosen tidak boleh mengajar di 2      │
                  │    kelas berbeda pada waktu bersamaan   │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────┴────────────────────┐
                  │ 2. BATASAN RUANGAN (Room Constraint)    │
                  │    Ruang kelas hanya untuk 1 sesi kuliah│
                  │    & kapasitas kursi >= kuota peserta   │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────┴────────────────────┐
                  │ 3. BATASAN ANGKATAN / KELAS (Cohort)    │
                  │    Mata kuliah paket semester sama di   │
                  │    prodi yang sama tidak boleh bentrok  │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────┴────────────────────┐
                  │ 4. BATASAN WAKTU & JEDA (Time Slots)    │
                  │    Slot waktu valid (07:00 - 21:00)     │
                  │    Jeda istirahat sholat Dzuhur/Jumat   │
                  └─────────────────────────────────────────┘
```

### Kueri Validasi Bentrok Jadwal PostgreSQL:
```sql
-- Deteksi bentrok jadwal dosen dan ruangan sebelum penyimpanan jadwal baru
SELECT 
    id, 
    course_name, 
    lecturer_id, 
    room_id, 
    day_of_week, 
    start_time, 
    end_time
FROM class_schedules
WHERE academic_period_id = :period_id
  AND day_of_week = :day
  AND (room_id = :room_id OR lecturer_id = :lecturer_id)
  AND (start_time < :new_end_time AND end_time > :new_start_time)
  AND id != :current_schedule_id;
```

---

## 5. Mesin Mutasi Status Akademik Mahasiswa (Student Lifecycle Mutations)

SIAKAD mengatur perubahan status administratif mahasiswa melalui alur dekret resmi:

```
┌─────────────────┐      Pengajuan Online (Disertai Alasan/Dokumen)      ┌──────────────────┐
│ Mahasiswa Cuti  │ ───────────────────────────────────────────────────> │ Validasi DPA &   │
│   / Pindah      │                                                      │  Kaprodi         │
└─────────────────┘                                                      └────────┬─────────┘
                                                                                  │
                                                                                  ▼ Persetujuan
┌─────────────────┐       Perubahan Status di db_simak & Feeder AKM      ┌──────────────────┐
│ Mahasiswa Aktif │ <─────────────────────────────────────────────────── │ Terbit SK Dekan  │
│ Kembali / Pindah│                                                      │ Pengesahan BAAK  │
└─────────────────┘                                                      └──────────────────┘
```

### Rincian Aturan Mutasi Status:
1. **Cuti Akademik (Academic Leave)**:
   - Mahasiswa baru semester 1 dan 2 tidak diizinkan mengambil cuti (kecuali alasan medis darurat).
   - Maksimal cuti akademik: 2 semester berturut-turut atau total 4 semester selama masa studi S1.
   - Semester cuti tidak dihitung dalam batas maksimal 14 semester masa studi SN-Dikti.
2. **Re-aktivasi Mahasiswa**:
   - Pengajuan kembali aktif wajib dilakukan minimal 14 hari sebelum masa pembayaran UKT dan KRS dibuka.
   - Sistem membuka kembali tagihan UKT aktif dan mengembalikan hak registrasi KRS.
3. **Pindah Program Studi (Transfer Internal)**:
   - Melakukan ekuivalensi mata kuliah oleh Kaprodi tujuan.
   - Mata kuliah yang diakui otomatis dipindahkan ke riwayat transkrip baru dengan penanda konversi.
4. **Drop-Out (DO) & Evaluasi Masa Studi**:
   - Evaluasi Tahap I (Akhir Semester 4): Mahasiswa wajib mengumpulkan minimal 40 SKS lulus dengan IPK $\ge 2.00$.
   - Evaluasi Tahap Akhir (Akhir Semester 14): Batas maksimal studi habis; mahasiswa yang belum lulus otomatis diterbitkan surat peringatan DO hingga SK Pemberhentian Studi.

---

## 6. Sistem Peringatan Dini Akademik (Early Warning System)

Sistem secara otomatis mengidentifikasi mahasiswa dengan risiko putus studi (*retention risk*) setiap kali KHS semester diterbitkan:

```markdown
### Kategori Peringatan Akademik (Retention Risk Categories):
1. **Peringatan 1 (Kuning)**:
   - Mahasiswa dengan Indeks Prestasi Semester (IPS) $< 2.00$ pada 1 semester.
   - Tindakan: Peringatan otomatis di portal mahasiswa dan notifikasi email ke Dosen Pembimbing Akademik (DPA).
2. **Peringatan 2 (Oranye)**:
   - Mahasiswa dengan IPS $< 2.00$ selama 2 semester berturut-turut, atau total SKS lulus $< 50\%$ dari standar angkatan.
   - Tindakan: Sistem mewajibkan sesi konseling tatap muka dengan DPA sebelum KRS semester berikutnya dapat disetujui.
3. **Peringatan Kritis (Merah / Pra-DO)**:
   - Mahasiswa berada di semester 12 atau lebih tinggi dan belum mengambil mata kuliah Skripsi/Tugas Akhir.
   - Tindakan: Penerbitan Surat Peringatan Resmi dari Dekanat kepada orang tua/wali mahasiswa.
```

---

## 7. Kompilasi Transkrip Resmi & KHS dengan Verifikasi Kriptografis QR

Untuk mencegah manipulasi transkrip atau pemalsuan nilai cetak:

1. **Format Transkrip Resmi**:
   - Menampilkan seluruh mata kuliah lulus, bobot SKS, nilai huruf, indeks mutu ($K \times M$), IPK kumulatif, dan predikat kelulusan (*Cum Laude, Sangat Memuaskan, Memuaskan*).
   - Mata kuliah perbaikan hanya menampilkan nilai tertinggi yang diakui.
2. **Kriptografi & Kode Verifikasi QR**:
   - Setiap lembar transkrip resmi dan KHS disematkan tanda tangan digital berbasis hash:
     $$\text{Hash Dokumen} = \text{hash}(\text{'sha256'}, \text{nim} + \text{ipk} + \text{total\_sks} + \text{tanggal\_cetak} + \text{AppSecret})$$
   - Kode QR mengarahkan verifikator publik ke URL validasi resmi: `https://siakad.kampus.ac.id/verify-document/{hash}` untuk memvalidasi keaslian dokumen secara instan tanpa login.

---

## 8. Definition of Done (DoD) & Acceptance Criteria

Setiap hasil kerja administrasi akademik dan registrasi wajib memenuhi kriteria berikut:

- [ ] **Bebas Bentrok 100%**: Master jadwal kuliah terbukti bebas dari tumpang tindih waktu dosen, ruangan, dan kelas cohort.
- [ ] **Sinkronisasi Kapasitas**: Daya tampung ruangan mencukupi kuota maksimal kelas tanpa kelebihan kapasitas fisik.
- [ ] **Legalitas Mutasi**: Seluruh mutasi status mahasiswa (cuti/transfer/DO) tercatat dengan nomor SK Dekan/Rektor resmi.
- [ ] **Retention Risk Alerting**: Mahasiswa berisiko DO dan IPS rendah menerima peringatan dini serta diteruskan ke DPA.
- [ ] **Validasi Dokumen QR**: Transkrip resmi dan KHS memuat kode QR publik dengan hash SHA-256 yang valid saat dipindai.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-pddikti-feeder-officer`).
