---
name: siakad-technical-writer
description: >-
  Author, structure, and maintain high-grade technical documentation, user manuals for multi-role academic stakeholders, API references (OpenAPI 3.1), Software Architecture Documents (SAD), and PDDIKTI Feeder sync runbooks for SIAKAD Kampus using the Diátaxis framework across all 15 specialized agents. Use this skill whenever drafting user manuals, writing system administrator SOPs, documenting API endpoints, creating Feeder troubleshooting guides, authoring disaster recovery runbooks, or maintaining release notes.
---

# SIAKAD Technical Writer & Documentation Specialist Skill

Panduan keahlian dan standar baku penyusunan dokumentasi teknis serta operasional bagi **Lead Technical Writer & Academic Documentation Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur dokumentasi modern mengadopsi **Diátaxis Framework**, buku panduan operasional pengguna (*User Manuals*) berbasis persona multi-role (Mahasiswa, Dosen, BAAK, Keuangan, Admin), Dokumen Arsitektur Perangkat Lunak (*Software Architecture Document - SAD*), spesifikasi OpenAPI 3.1, SOP tanggap darurat bencana (*Disaster Recovery Runbook*), serta buku panduan operasional (*Runbook*) sinkronisasi **PDDIKTI Neo Feeder** melingkupi seluruh ekosistem **15 Agen Spesialis SIAKAD**.

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

### Kuadran Penerapan pada SIAKAD Kampus:
1. **Tutorials (Berorientasi Pembelajaran):**
   - Panduan Mahasiswa Baru: 5 Langkah Mengisi KRS dan Memilih Mata Kuliah untuk Pertama Kali.
   - Panduan Dosen Baru: Membuat Rencana Pembelajaran 16 Pertemuan dan Presensi QR di LMS.
2. **How-To Guides (Berorientasi Masalah / Problem-Solving):**
   - Cara Operator BAAK Menyelesaikan Bentrok Ruang Kuliah dan Dosen.
   - Cara Dosen Mengimpor Lembar Nilai dari Template Excel dengan Validasi Sub-CPMK.
   - Cara Operator Feeder Melakukan Pemulihan Sync Ber-Checkpoint Pasca Putus Jaringan.
3. **Reference (Berorientasi Informasi / Spesifikasi Teknis):**
   - Spesifikasi OpenAPI 3.1 untuk REST API `/api/v1/krs` dan `/api/v1/attendance`.
   - Kamus Data & Skema PostgreSQL `db_simak` (Foreign Keys, Constraints, Indexes).
   - Kamus Kode Error Neo Feeder Web Service Kemendiktisaintek.
4. **Explanation (Berorientasi Pemahaman / Arsitektur):**
   - Mengapa SIAKAD Menggunakan *Pessimistic Row-Locking* (`SELECT ... FOR UPDATE`) pada KRS War.
   - Filosofi Kurikulum Berbasis Luaran (OBE) dan Formula Ketercapaian CPL Mahasiswa.
   - Arsitektur Rantai Hash Kriptografis SHA-256 pada Tabel `simak_audit_log`.

---

## 2. Struktur Buku Panduan Pengguna Berbasis Persona (User Manuals)

Setiap buku panduan pengguna disusun menggunakan bahasa Indonesia yang lugas, terstruktur, dilengkapi tangkapan layar (*UI Screenshots*), dan alur navigasi menu yang presisi:

### 2.1 Panduan Mahasiswa (Student User Manual)
- **Modul 1: Akses Portal & Dashboard Akademik:** Login pertama kali, ganti kata sandi, aktivasi autentikasi 2 faktor (2FA).
- **Modul 2: Pembayaran Tagihan Kuliah (UKT/IPI):** Cara melihat rincian tagihan, pembayaran via Virtual Account Bank (BCA, BNI, BRI, Mandiri) dan QRIS Midtrans.
- **Modul 3: Rencana Studi (KRS Online):** Melihat batas beban SKS, memilih paket mata kuliah, cek jadwal kuliah, antrean virtual waiting room, mengajukan persetujuan ke Dosen PA.
- **Modul 4: Evaluasi Dosen (EDOM):** Mengisi kuesioner evaluasi dosen sebelum unduh KHS.
- **Modul 5: Kartu Hasil Studi (KHS) & Transkrip:** Mengunduh KHS per semester ber-QR Code validasi resmi, cetak transkrip kumulatif sementara.
- **Modul 6: Skripsi & Yudisium:** Pengajuan judul tugas akhir, logbook bimbingan, pendaftaran sidang, dan verifikasi berkas bebas pustaka/keuangan.

### 2.2 Panduan Dosen (Lecturer User Manual)
- **Modul 1: Perwalian Akademik (Dosen PA):** Rekap mahasiswa bimbingan, verifikasi/revisi rencana studi KRS mahasiswa, catatan pembimbingan.
- **Modul 2: Presensi Kuliah & Jurnal Perkuliahan:** Generate QR Code presensi bergulir 30 detik di ruang kelas, input izin/sakit manual, rekapitulasi kehadiran (syarat 75% ujian).
- **Modul 3: Input & Finalisasi Nilai:** Pengisian nilai per komponen (Kehadiran, Tugas, UTS, UAS), pembobotan sub-CPMK kurikulum OBE, kunci nilai (*Lock Grades*).
- **Modul 4: Bimbingan & Pengujian Tugas Akhir:** Log bimbingan skripsi, penilaian seminar proposal, dan penilaian sidang komprehensif berbobot rubrik.

### 2.3 Panduan Operator BAAK & Keuangan
- **BAAK:** Setup kalender akademik semester baru, kurikulum & konversi MK, plotting ruangan bebas bentrok dan dosen pengampu, distribusi mahasiswa ke kelas.
- **Keuangan:** Konfigurasi tarif UKT per jalur masuk, generate tagihan massal, rekonsiliasi manual bukti transfer bank, pembukaan dispensasi KRS.

---

## 3. Spesifikasi OpenAPI 3.1 untuk Integrasi Layanan (API Standards)

Dokumentasi REST API wajib mengikuti standar OpenAPI 3.1:

```yaml
openapi: 3.1.0
info:
  title: SIAKAD Kampus REST API
  version: 1.0.0
  description: API Endpoint Resmi Sistem Informasi Akademik Kampus
paths:
  /api/v1/krs/enroll:
    post:
      summary: Mendaftarkan mata kuliah mahasiswa pada KRS War
      security:
        - BearerAuth: []
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - academic_period_id
                - class_schedule_ids
              properties:
                academic_period_id:
                  type: integer
                  example: 20261
                class_schedule_ids:
                  type: array
                  items:
                    type: integer
                  example: [101, 104, 112]
      responses:
        '200':
          description: Pendaftaran KRS Berhasil
        '409':
          description: Kuota Kelas Habis (ClassCapacityExceededException)
        '429':
          description: Terkena Throttling / Waiting Room Active
```

---

## 4. Runbook Operasional: Sinkronisasi PDDIKTI Neo Feeder

Buku panduan operasional wajib bagi tim IT dan Operator PDDIKTI kampus:

```markdown
# SOP Pelaporan Neo Feeder PDDIKTI

## 1. Fase Pra-Sinkronisasi (Data Cleansing & Pre-Flight Audit)
Sebelum menekan tombol ekspor data, lakukan audit integritas di menu:
`SIAKAD > Feeder > Pre-Validation Check`

Pastikan tidak ada data anomali:
- Mahasiswa aktif tanpa NIK (wajib 16 digit angka) atau tanpa Nama Ibu Kandung.
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

## 5. SOP Penanganan Kedaruratan & Pemulihan Bencana (Disaster Recovery Runbook)

Jika terjadi insiden sistem (misal: korupsi data, server database down saat KRS, atau serangan siber):

1. **Failover Database PostgreSQL:**
   - Putuskan koneksi pool aplikasi utama.
   - Promosikan *Standby Replica* menjadi *Primary* menggunakan `pg_ctl promote`.
   - Perbarui variabel `DB_HOST` pada file `.env` Laravel dan jalankan `php artisan config:cache`.
2. **Pemulihan Snapshot Backup:**
   - Seluruh backup harian tersimpan dalam format terenkripsi AES-256 di storage terisolasi.
   - Perintah restorasi: `pg_restore -d db_simak --clean --no-owner /backup/db_simak_latest.dump`.
3. **Verifikasi Hash Integritas Nilai:**
   - Jalankan verifikasi keutuhan rantai hash pada `simak_audit_log` untuk mendeteksi apakah terdapat data nilai yang disisipkan di luar transaksi resmi.

---

## 6. Checklist Verifikasi Standar Dokumentasi

- [ ] Seluruh dokumen panduan mematuhi struktur Diátaxis (Tutorial, How-To, Reference, Explanation).
- [ ] Panduan pengguna multi-role memuat tangkapan layar antarmuka terbaru dan instruksi yang teruji.
- [ ] Endpoint REST API telah terdokumentasi dalam format OpenAPI 3.1 lengkap dengan contoh *request* dan *response*.
- [ ] Runbook pemecahan masalah Feeder memuat seluruh kode error umum beserta solusi langkah demi langkah.
- [ ] Dokumen pemulihan bencana (*Disaster Recovery*) telah diverifikasi bersama Lead DBA dan Lead System Architect.
