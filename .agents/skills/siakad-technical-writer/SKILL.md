---
name: siakad-technical-writer
description: >-
  Author, structure, and maintain high-grade technical documentation, user manuals for multi-role academic stakeholders, API references, Software Architecture Documents (SAD), and PDDIKTI Feeder sync runbooks for SIAKAD Kampus using the Diátaxis framework. Use this skill whenever drafting user manuals, writing system administrator SOPs, documenting API endpoints, creating Feeder troubleshooting guides, or maintaining release notes.
---

# SIAKAD Technical Writer & Documentation Specialist Skill

Panduan keahlian dan standar baku penyusunan dokumentasi teknis serta operasional bagi **Lead Technical Writer & Academic Documentation Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur dokumentasi modern mengadopsi **Diátaxis Framework**, buku panduan operasional pengguna (*User Manuals*) berbasis persona multi-role (Mahasiswa, Dosen, BAAK, Keuangan, Admin), Dokumen Arsitektur Perangkat Lunak (*Software Architecture Document - SAD*), spesifikasi OpenAPI 3.1, serta buku panduan operasional (*Runbook*) sinkronisasi **PDDIKTI Neo Feeder**.

---

## 1. Kerangka Dokumentasi Diátaxis (The Diátaxis Framework)

Seluruh dokumentasi SIAKAD wajib diklasifikasikan ke dalam 4 kuadran Diátaxis untuk mencegah campur aduk antara panduan belajar dan referensi teknis:

```
                      PRAKTIK (Action)
                            ▲
        Tutorials           │         How-To Guides
    (Belajar Terpandu)      │     (Penyelesaian Masalah)
                            │
PEMBELAJARAN ───────────────┼─────────────── INFORMASI
 (Learning)                 │               (Information)
        Explanation         │          Reference
    (Pemahaman Konseptual)  │    (Spesifikasi Teknis / API)
                            ▼
                      TEORI (Knowledge)
```

### Kuadran Penerapan pada SIAKAD:
1. **Tutorials (Berorientasi Pembelajaran):** Panduan Mahasiswa Baru: 5 Langkah Mengisi KRS dan Memilih Mata Kuliah untuk Pertama Kali.
2. **How-To Guides (Berorientasi Tugas/Masalah):** Cara Operator BAAK Menyelesaikan Bentrok Ruang Kuliah; Cara Dosen Mengimpor Nilai dari Template Excel.
3. **Reference (Berorientasi Informasi/Katalog):** Kamus Kode Error Neo Feeder; Spesifikasi Endpoint REST API SIAKAD; Format Kolom Unggah Pembayaran Bank.
4. **Explanation (Berorientasi Pemahaman):** Mengapa Nilai Akhir OBE Dihitung dari CPMK; Mekanisme Penomoran Ijazah Nasional (PIN/SIVIL) Terintegrasi.

---

## 2. Struktur Buku Panduan Pengguna Berbasis Persona (User Manuals)

Setiap buku panduan pengguna disusun menggunakan bahasa Indonesia yang lugas, terstruktur, dilengkapi tangkapan layar (*UI Screenshots*), dan alur navigasi menu yang presisi:

### 2.1 Panduan Mahasiswa (Student User Manual)
- **Modul 1: Akses Portal & Dashboard Akademik:** Login pertama kali, ganti kata sandi, aktivasi autentikasi 2 faktor (2FA).
- **Modul 2: Pembayaran Tagihan Kuliah (UKT/IPI):** Cara melihat rincian tagihan, pembayaran via Virtual Account Bank (BCA, BNI, BRI, Mandiri) dan QRIS.
- **Modul 3: Rencana Studi (KRS Online):** Melihat batas beban SKS, memilih paket mata kuliah, cek jadwal kuliah, mengajukan persetujuan ke Dosen PA.
- **Modul 4: Evaluasi Dosen (EDOM):** Mengisi kuesioner evaluasi dosen sebelum unduh KHS.
- **Modul 5: Kartu Hasil Studi (KHS) & Transkrip:** Mengunduh KHS per semester ber-QR Code validasi resmi, cetak transkrip kumulatif sementara.
- **Modul 6: Skripsi & Yudisium:** Pengajuan judul tugas akhir, unggah bab skripsi, pendaftaran sidang, dan verifikasi berkas bebas pustaka.

### 2.2 Panduan Dosen (Lecturer User Manual)
- **Modul 1: Perwalian Akademik (Dosen PA):** Rekap mahasiswa bimbingan, verifikasi/revisi rencana studi KRS mahasiswa, catatan pembimbingan.
- **Modul 2: Presensi Kuliah & Jurnal Perkuliahan:** Generate QR Code presensi di ruang kelas, input izin/sakit manual, rekapitulasi kehadiran (syarat 75% ujian).
- **Modul 3: Input & Finalisasi Nilai:** Pengisian nilai per komponen (Kehadiran, Tugas, UTS, UAS), pembobotan sub-CPMK kurikulum OBE, kunci nilai (*Lock Grades*).
- **Modul 4: Bimbingan & Pengujian Tugas Akhir:** Log bimbingan skripsi, penilaian seminar proposal, dan penilaian sidang komprehensif.

### 2.3 Panduan Operator BAAK & Keuangan
- **BAAK:** Setup kalender akademik semester baru, kurikulum & konversi MK, plotting ruangan dan dosen pengampu, distribusi mahasiswa ke kelas.
- **Keuangan:** Konfigurasi tarif UKT per jalur masuk, generate tagihan massal, rekonsiliasi manual bukti transfer bank, pembukaan dispensasi KRS.

---

## 3. Runbook Operasional: Sinkronisasi PDDIKTI Neo Feeder

Buku panduan operasional wajib bagi tim IT dan Operator PDDIKTI kampus:

```markdown
# SOP Pelaporan Neo Feeder PDDIKTI

## 1. Fase Pra-Sinkronisasi (Data Cleaning & Audit)
Sebelum menekan tombol ekspor data, lakukan audit integritas di menu:
`SIAKAD > Feeder > Pre-Validation Check`

Pastikan tidak ada data anomali:
- Mahasiswa aktif tanpa NIK / NISN / Nama Ibu Kandung.
- Dosen pengajar tanpa NIDN/NUPN yang terdaftar di PDDIKTI.
- Kelas kuliah tanpa dosen pengajar atau tanpa jadwal hari/jam.
- SKS total mata kuliah pada kurikulum yang tidak sesuai aturan jenjang.

## 2. Urutan Kronologis Sinkronisasi (Dependency Order)
Wajib mengikuti urutan hierarki relasional agar tidak terjadi error "Parent ID Not Found":
1. `Mata Kuliah & Kurikulum`
2. `Dosen & Penugasan Dosen`
3. `Mahasiswa Baru & Registrasi Mahasiswa`
4. `Kelas Kuliah`
5. `Dosen Pengajar Kelas (Ajar Dosen)`
6. `Peserta Kelas Kuliah (KRS)`
7. `Nilai Perkuliahan`
8. `Aktivitas Kuliah Mahasiswa (AKM: IPS, IPK, Status Semester)`
9. `Kelulusan & Mahasiswa Drop Out`

## 3. Kamus Pemecahan Masalah Kode Error Feeder (Troubleshooting Matrix)
- **Error 100: "Token Session Expired"**
  - Solusi: Buka `Pengaturan Feeder`, tekan tombol `Perbarui Token Otomatis` atau restart service Web Service Feeder di server lokal kampus.
- **Error 204: "Peserta kelas kuliah tidak dapat ditambahkan karena kelas tidak ditemukan"**
  - Solusi: Sinkronkan kembali modul `Kelas Kuliah` sebelum mengekspor `Peserta Kelas`.
- **Error 422: "Jumlah SKS semester melebihi batas ketentuan"**
  - Solusi: Cek IPS semester sebelumnya pada data AKM mahasiswa bersangkutan.
```

---

## 4. Dokumen Arsitektur Perangkat Lunak (SAD) & Spesifikasi API

### 4.1 Standar Dokumentasi Endpoint API (OpenAPI 3.1)
Setiap endpoint API SIAKAD wajib mendokumentasikan:
- **HTTP Method & URI:** `POST /api/v1/academic/krs/enroll`
- **Headers:** `Authorization: Bearer <token>`, `Accept: application/json`
- **Request Body (JSON Schema):** Tipe data, validasi, dan contoh payload.
- **Responses:** Kode 200 (OK), 400 (Bad Request / Prerequisite Failed), 409 (Quota Full / Collision), 422 (Validation Error), 500 (Internal Server Error).

```yaml
openapi: 3.1.0
info:
  title: SIAKAD Kampus Academic API
  version: 1.0.0
  description: Dokumentasi resmi REST API untuk integrasi portal akademik dan mobile app.
paths:
  /api/v1/krs/enroll:
    post:
      summary: Pendaftaran mata kuliah ke dalam KRS mahasiswa
      security:
        - BearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [class_schedule_id, academic_period_id]
              properties:
                class_schedule_id:
                  type: integer
                  example: 845
                academic_period_id:
                  type: integer
                  example: 20261
      responses:
        '201':
          description: Mata kuliah berhasil didaftarkan.
        '422':
          description: Kuota kelas penuh atau jadwal bentrok.
```

---

## 5. Release Notes & Log Pembaruan (Changelog SOP)

Setiap rilis versi baru mengikuti standar **Semantic Versioning (SemVer)**: `MAJOR.MINOR.PATCH` (contoh: `v2.4.1`):

```markdown
## [2.4.0] - 2026-11-15
### Ditambahkan (Added)
- Modul Evaluasi Dosen (EDOM) baru dengan 15 instrumen evaluasi standar SPMI.
- Integrasi QRIS dinamis pada modul pembayaran UKT via Midtrans Snap.
- Fitur ekspor transkrip digital ber-QR Code terverifikasi validator resmi kampus.

### Diperbaiki (Fixed)
- Mengatasi masalah race condition saat pemilihan kelas KRS pada jam sibuk.
- Memperbaiki perhitungan otomatis SKS Kumulatif untuk mata kuliah konversi MBKM.

### Keamanan (Security)
- Penambahan verifikasi anti-tampering dan audit log pada perubahan nilai dosen pasca-finalisasi.
```

---

## 6. Checklist Standar Kualitas Dokumentasi (Technical Writer Sign-Off)

- [ ] **Kepatuhan Diátaxis:** Dokumen terdistribusi dengan benar antara Tutorial, How-To, Reference, dan Explanation.
- [ ] **Akurasi Alur Menu:** Urutan klik menu pada panduan pengguna 100% cocok dengan tata letak antarmuka SIAKAD terkini.
- [ ] **Visual Clarity:** Tangkapan layar menggunakan penanda (highlight box merah/panah) pada tombol aksi yang dimaksud.
- [ ] **Keamanan Data Contoh:** Data NIM, NIK, nama mahasiswa, dan nominal rekening pada buku panduan menggunakan data dummy fiktif (bukan data mahasiswa riil).
- [ ] **Pemberian Versi Jelas:** Setiap buku panduan mencantumkan tanggal revisi, nomor versi aplikasi, dan narahubung *IT Helpdesk*.
