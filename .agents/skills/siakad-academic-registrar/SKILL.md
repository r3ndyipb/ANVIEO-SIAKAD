---
name: siakad-academic-registrar
description: >-
  Manage registrar operations, master timetable collision resolution, official student lifecycle mutations, and official transcript compilation for SIAKAD Kampus. Identifies and eliminates scheduling collisions across rooms, lecturers, and student cohorts, executes student mutations (academic leave/cuti, re-activation, study program transfers, drop-out/DO processing), monitors academic standing (IPS/IPK warning thresholds), and compiles tamper-evident official transcripts and KHS documents with embedded SHA-256 digital verification QR codes. Use this skill whenever configuring master schedules, resolving timetable collisions, processing student status mutations, or generating official transcripts.
---

# SIAKAD BAAK & Student Lifecycle Registrar Specialist Skill

Panduan keahlian administrasi akademik dan pengelolaan siklus mahasiswa bagi **BAAK & Student Lifecycle Registrar Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar operasional Biro Administrasi Akademik dan Kemahasiswaan (BAAK) dalam penyusunan jadwal kuliah bebas bentrok (*collision-free master timetable*), alokasi kapasitas ruang kuliah, pemrosesan mutasi status akademik mahasiswa (Cuti Akademik, Re-aktivasi, Mutasi/Pindah Prodi, Drop-Out / DO, Pengunduran Diri), pemantauan evaluasi masa studi (*academic standing & retention warning*), serta kompilasi transkrip akademik dan Kartu Hasil Studi (KHS) bertanda tangan digital dengan kode verifikasi QR.

---

## 1. Algoritma Penjadwalan Bebas Bentrok (Timetable Collision Resolution)

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

## 2. Mesin Mutasi Status Akademik Mahasiswa (Student Lifecycle Mutations)

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

## 3. Sistem Peringatan Dini Akademik (Early Warning System)

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

## 4. Kompilasi Transkrip Resmi & KHS dengan Verifikasi Kriptografis QR

Untuk mencegah manipulasi transkrip atau pemalsuan nilai cetak:

1. **Format Transkrip Resmi**:
   - Menampilkan seluruh mata kuliah lulus, bobot SKS, nilai huruf, indeks mutu ($K \times M$), IPK kumulatif, dan predikat kelulusan (*Cum Laude, Sangat Memuaskan, Memuaskan*).
   - Mata kuliah perbaikan hanya menampilkan nilai tertinggi yang diakui.
2. **Kriptografi & Kode Verifikasi QR**:
   - Setiap lembar transkrip resmi dan KHS disematkan tanda tangan digital berbasis hash:
     $$\text{Hash Dokumen} = \text{hash}(\text{'sha256'}, \text{nim} + \text{ipk} + \text{total\_sks} + \text{tanggal\_cetak} + \text{AppSecret})$$
   - Kode QR mengarahkan verifikator publik (perusahaan/kampus lain) ke URL validasi resmi: `https://siakad.kampus.ac.id/verify-document/{hash}` untuk memvalidasi keaslian dokumen secara instan tanpa login.

---

## 5. Checklist Verifikasi Operasional BAAK

- [ ] Master jadwal kuliah semester baru telah bebas bentrok dosen, ruangan, dan kelas cohort 100%.
- [ ] Batas maksimal kapasitas ruangan telah disinkronkan dengan kuota penerimaan kelas.
- [ ] Seluruh mahasiswa cuti dan mutasi telah memiliki nomor SK Dekan yang terarsip di sistem.
- [ ] Evaluasi mahasiswa pra-DO dan peringatan IPS rendah terdistribusi ke seluruh DPA terkait.
- [ ] Pencetakan transkrip akademik resmi memuat kode verifikasi QR yang valid dan dapat dipindai.
