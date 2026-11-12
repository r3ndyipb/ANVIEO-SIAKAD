---
name: siakad-system-analyst
description: >-
  Architect, analyze, and specify academic business logic, multi-role RBAC matrices (14+ roles), BPMN workflows, data dictionaries, API contracts, and edge cases for SIAKAD Kampus (KRS prerequisites, OBE/CPL-CPMK curriculum, MBKM 20 SKS conversions, E-Skripsi defense rubrics, Midtrans webhook idempotency, grading curves, graduation clearance, and PDDIKTI Feeder sync contracts). Use this skill whenever writing functional requirements (FR/NFR), modeling academic business processes, designing database entity mappings, specifying role permissions, or defining integration contracts.
---

# SIAKAD System Analyst & Academic Architecture Skill

Panduan keahlian dan spesifikasi arsitektur fungsional bagi **Senior System Analyst & Academic Solution Architect** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar pemodelan logika bisnis akademik, matriks hak akses multi-role (14+ peran), diagram alur proses bisnis (BPMN), aturan validasi akademik (aturan SKS, prasyarat, OBE), penanganan kasus khusus (*edge cases* seperti cuti, transfer, MBKM 20 SKS), mesin ujian skripsi/tesis, rekonsiliasi pembayaran Midtrans, pemantauan status retensi AKM, kontrak spesifikasi API, serta pemetaan entitas ke **PDDIKTI Neo Feeder** dalam model kolaborasi **Sokudo Scrum Swarm**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Product Owner / Solution Architect**
- **Domain Fokus**: Spesifikasi persyaratan fungsional/non-fungsional (FR/NFR), diagram BPMN, pemetaan hak akses 14+ peran (RBAC), data contracts, dan spesifikasi API OpenAPI 3.1.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-system-analyst` | `siakad-c2-orchestrator`<br>`siakad-security-compliance-auditor` | Keselarasan arsitektur modul, isolasi data prodi (`prodi_id`), penutupan celah eskalasi privilese/IDOR, dan kelayakan kontrak API. |
| **Sebagai Reviewer Wajib (1)** | `siakad-uiux-designer` | `siakad-system-analyst` | Keselarasan tata letak UI dengan logika bisnis, WCAG 2.1 AA (kontras $\ge 4.5:1$), pesan error form, dan konsistensi status akademik. |
| **Sebagai Reviewer Wajib (2)** | `siakad-obe-curriculum-specialist` | `siakad-system-analyst` | Integritas matematis bobot asesmen tepat 100.00%, struktur CPL-CPMK-SubCPMK, dan kelengkapan rubrik RPS. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap analisis kebutuhan atau perubahan proses bisnis wajib dipecah menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Tiket analisis berfokus pada kejelasan spesifikasi sebelum diimplementasikan oleh agen backend/frontend/database.
3. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan spesifikasi proses bisnis / pemetaan entitas / aturan validasi]

## Assigned Implementer
`siakad-system-analyst`

## Mandatory Reviewer
`siakad-c2-orchestrator` / `siakad-security-compliance-auditor`

## Context & Files
- Scope dokumen: FR/NFR, BPMN, Kamus Data, API Contract
- File terkait: `app/Http/Requests/*`, `routes/web.php`, `database/migrations/*`

## Scrum Steps
1. Identifikasi use case dan pemangku kepentingan (role).
2. Definisikan happy path dan seluruh alur alternatif/eksepsi.
3. Susun data dictionary dan spesifikasi API OpenAPI 3.1.
4. Verifikasi isolasi data scoping dan aturan validasi.

## Acceptance Criteria & DoD
- [ ] Dokumen FR/NFR mendefinisikan skenario sukses dan gagal secara kuantitatif.
- [ ] Matriks RBAC membatasi akses peran secara programatis tanpa celah IDOR.
- [ ] Pemetaan entitas selaras dengan arsitektur tabel PDDIKTI Neo Feeder.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket analisis dibuat oleh Orchestrator dengan brief Markdown lengkap.
- **In Progress**: System Analyst menyusun spesifikasi fungsional, diagram alur, dan kontrak data.
- **Under Review**: Reviewer memeriksa kelayakan logika bisnis, isolasi keamanan, dan keselarasan arsitektur.
- **Done**: Disetujui reviewer dan diverifikasi selesai.

---

## 3. Penyelarasan Siklus Hidup Akademik 6-Fase (6-Phase Lifecycle Alignment)

System Analyst mengawal aturan bisnis di seluruh fase siklus operasional perguruan tinggi:

| Fase Akademik | Fokus Analisis Sistem | Entitas & Kontrak Data Kunci |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Penentuan tarif UKT/IPI per kluster, alur pembayaran Midtrans, dan financial clearance gate. | `student_billings`, `payment_transactions`, flag `is_financial_cleared`. |
| **Fase 2: Registrasi KRS** | Aturan batas SKS berdasarkan IPS lalu, validasi prasyarat MK, collision detection waktu/ruang/dosen. | `krs`, `krs_details`, `class_schedules`, exception `ClassCapacityExceededException`. |
| **Fase 3: Masa Perkuliahan** | Struktur 16 pertemuan, validasi radius GPS dan rotasi TOTP 30s presensi, batas minimal 75% kehadiran. | `course_meetings`, `attendances`, threshold hadir UAS $\ge 75\%$. |
| **Fase 4: Evaluasi & Ujian** | Pemetaan komponen nilai ke CPMK, normalisasi bobot 100%, kurva nilai (A-E), status `LOCKED`. | `course_grades`, `obe_cpmk`, trigger `simak_audit_log`. |
| **Fase 5: Kelulusan & Wisuda** | Gerbang yudisium (144 SKS, Turnitin < 20%, bebas perpus/keuangan), rubrik sidang, penomoran PIN/SKPI. | `theses`, `graduation_clearances`, SK Yudisium. |
| **Fase 6: Feeder PDDIKTI** | Pemetaan skema lokal ke tabel Neo Feeder WS, filter pra-validasi (NIK, SKS, IPK), checkpoint sync. | `feeder_sync_checkpoints`, `mahasiswa_pt`, `nilai_perkuliahan`, `akm`. |

---

## 4. Domain Model & Arsitektur Bisnis SIAKAD

Sistem Informasi Akademik mengintegrasikan siklus hidup akademik mahasiswa dari proses penerimaan mahasiswa baru (PMB) hingga alumni:

```
[PMB / Admisi] ──> [Registrasi Ulang / Billing UKT] ──> [Plotting Kurikulum & Jadwal]
       │                                                         │
       ▼                                                         ▼
[Alumni / Tracer] <── [Yudisium & Wisuda] <── [Nilai & KHS] <── [KRS & Perkuliahan]
```

### Entitas Bisnis Utama (Core Domain Entities):
1. **Periode Akademik (`academic_periods`):** Tahun ajaran (misal: 2026/2027), semester (Ganjil, Genap, Pendek), tanggal buka/tutup pengisian KRS, revisi KRS, input nilai, dan pelaporan PDDIKTI.
2. **Kurikulum & OBE (`curricula`, `obe_cpl`, `obe_cpmk`):** Struktur mata kuliah, capaian pembelajaran lulusan (CPL), sub-CPMK, jenis mata kuliah (wajib institusi, wajib prodi, pilihan, MBKM).
3. **Kelas Kuliah & Jadwal (`class_schedules`):** Kombinasi mata kuliah, dosen pengampu, kuota mahasiswa, ruangan, hari, jam mulai-selesai.
4. **KRS & KHS (`krs`, `krs_details`, `khs`):** Pendaftaran mata kuliah per mahasiswa per semester beserta status approval Dosen PA dan rincian nilai akhir.
5. **Keuangan & Tagihan (`student_billings`, `payment_transactions`):** Penetapan nominal UKT/IPI, status pembayaran lunas/belum/dispensasi sebagai pintu gerbang (*gatekeeper*) pengisian KRS.

---

## 5. Matriks Otorisasi & Kontrol Akses Multi-Role (RBAC)

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

### Aturan Isolasi Data Departemen (Data Scoping Rule):
Akun dengan peran `kaprodi` atau staf tata usaha prodi (`usertu`) **terisolasi ketat secara programatis pada `prodi_id` masing-masing**. Mereka diblokir secara total dari kemampuan melihat, mengubah, atau menyetujui jadwal, kelas kuliah, dan nilai mahasiswa di luar departemen mereka.

---

## 6. Logika Bisnis & Diagram Alur Kerja (Workflow Specifications)

### 6.1 Alur Registrasi KRS & Validasi Prasyarat Beban Studi

```
[Mahasiswa Login] ──> [Cek Status Keuangan (is_financial_cleared == true)]
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

### 6.2 Alur Konversi Nilai & Ekuivalensi 20 SKS MBKM (Kampus Merdeka)

Program Merdeka Belajar Kampus Merdeka (Magang Bersertifikat, Studi Independen, Kampus Mengajar, PMM, dll.):

```
[Mahasiswa Diterima MBKM Mitra] ──> [Pengajuan Program MBKM di Portal SIAKAD]
                                                │
                                                ▼
                                    [Review & ACC Kaprodi]
                                                │
                                                ▼
                            [Matriks Pemetaan 20 SKS Mata Kuliah Konversi]
                            - MK Konversi 1 (4 SKS)
                            - MK Konversi 2 (4 SKS)
                            - MK Konversi 3 (4 SKS)
                            - MK Konversi 4 (4 SKS)
                            - MK Konversi 5 (4 SKS)
                                                │
                                                ▼
[Pelaksanaan & Logbook Mingguan] ──> [Laporan Akhir & Penilaian Mentor Mitra]
                                                │
                                                ▼
                                    [Input Nilai Mutu oleh Kaprodi]
                                                │
                                                ▼
                            [Nilai Masuk Transkrip dengan Label MBKM]
```

---

### 6.3 Alur Penilaian Sidang Tugas Akhir (E-Skripsi & Tesis)

1. **Komposisi Dewan Penguji:** 1 Ketua Penguji, 1 Sekretaris Penguji, dan 1-2 Anggota Penguji (Pembimbing).
2. **Pengisian Rubrik Sidang:** Tiap penguji mengisi lembar rubrik digital independen di portal dosen.
3. **Agregasi Nilai:** Sistem menghitung rata-rata skor penguji secara tertimbang:
   $$\text{Skor Akhir} = \frac{\sum \text{Skor Penguji}}{N}$$
4. **Penandatanganan Berita Acara Ujian (BAU):** Dosen menandatangani dengan PIN/tanda tangan digital, mengunci nilai sidang secara permanen.

---

### 6.4 Alur Rekonsiliasi Webhook Pembayaran Midtrans (State Machine)

```
[Invoice Created] ──> [Status: PENDING] (Generate Snap Token / VA / QRIS)
                              │
                    ┌─────────┴─────────────────────┐
             Webhook POST                      Webhook POST
          (settlement / capture)            (expire / cancel / deny)
                    │                               │
                    ▼                               ▼
          [Status: SETTLEMENT]              [Status: EXPIRED / FAILED]
                    │                               │
                    ▼                               ▼
          - Log transaction ID              - Batalkan invoice
          - Set is_financial_cleared=true   - Mahasiswa harus re-generate
          - Unblock KRS portal
```

---

## 7. Penanganan Kasus Khusus (Edge Cases & Exception Logic)

### 7.1 Cuti Akademik vs Non-Aktif
- **Cuti Akademik Resmi:** Mahasiswa mengajukan via portal $\rightarrow$ diverifikasi BAAK $\rightarrow$ diterbitkan SK Rektor. Tagihan UKT disesuaikan (biasanya 0% atau biaya administrasi cuti), status di PDDIKTI tercatat `C` (Cuti), masa studi tidak bertambah.
- **Mahasiswa Non-Aktif (Mangkir):** Tidak registrasi ulang dan tidak mengajukan cuti resmi selama 2 semester berturut-turut $\rightarrow$ sistem mengirim peringatan SP1, SP2 $\rightarrow$ rekomendasi otomatis berstatus `Drop Out (DO)`.

### 7.2 Mahasiswa Mengulang Mata Kuliah
- Jika mahasiswa mengambil ulang mata kuliah yang sudah pernah ditempuh:
  - Kebijakan Umum: Sistem mengambil **Nilai Tertinggi (Best Grade)** atau **Nilai Terakhir (Latest Grade)** sesuai ketetapan statuta kampus.
  - SKS tidak dihitung ganda pada kalkulasi SKS Kumulatif Transkrip.

### 7.3 Pemantauan Retensi & Early Warning System (AKM)
- Sistem menghitung IPS, IPK, dan akumulasi SKS per semester (Aktivitas Kuliah Mahasiswa).
- Mahasiswa dengan IPK $< 2.00$ selama dua semester berturut-turut otomatis diberi penanda *Academic Warning* dan DPA menerima notifikasi peringatan bimbingan wajib.

---

## 8. Kontrak Integrasi PDDIKTI Neo Feeder

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

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap hasil kerja analisis sistem wajib memenuhi kriteria berikut sebelum disahkan:

- [ ] **Kelengkapan Dokumen FR / NFR**: Seluruh use case memiliki skenario utama (*happy path*) dan skenario alternatif/gagal yang terukur secara kuantitatif.
- [ ] **Validasi Matriks RBAC**: Memastikan isolasi data multi-role 14+ peran tertutup rapat tanpa celah IDOR atau eskalasi privilese.
- [ ] **Data Scoping Prodi**: Aturan isolasi data operator (`kaprodi`, `usertu`) terkunci eksklusif pada `prodi_id` bersangkutan.
- [ ] **Konsistensi Formula Akademik**: Perhitungan IPS/IPK, aturan prasyarat MK, batas SKS, dan normalisasi bobot OBE 100% tervalidasi secara matematis.
- [ ] **Kamus Data & API Contract**: Setiap endpoint dan tabel terpetakan lengkap dengan tipe data, nullability, constraint, dan skema OpenAPI 3.1.
- [ ] **Kesesuaian Feeder**: Entitas data dirancang kompatibel 100% dengan skema kamus data Kemendiktisaintek Neo Feeder WS.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-c2-orchestrator` / `siakad-security-compliance-auditor`).
