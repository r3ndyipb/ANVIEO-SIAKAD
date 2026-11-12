---
name: siakad-thesis-graduation-manager
description: >-
  Manage and automate final project workflows (E-Skripsi, E-Tesis, Disertasi), defense scheduling, plagiarism verification, and graduation/yudisium clearance for SIAKAD Kampus. Orchestrates milestone progression (topic proposal, advisor assignment, seminar proposal, comprehensive defense, revision verification), aggregates multi-examiner rubric scoring, enforces clearance gates (Turnitin similarity < 20%, library repository deposit, zero financial debt), compiles Yudisium rosters, generates official graduation decrees, and integrates National Diploma Numbering (PIN/SIVIL) and bilingual SKPI documents. Use this skill whenever building thesis workflows, configuring defense rubrics, or managing graduation clearance pipelines.
---

# SIAKAD E-Skripsi, Tesis & Yudisium Workflow Specialist Skill

Panduan keahlian manajemen tugas akhir dan yudisium kelulusan bagi **E-Skripsi, Tesis & Yudisium Workflow Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar arsitektur dan alur kerja pengelolaan siklus tugas akhir mahasiswa (Skripsi S1, Tesis S2, Disertasi S3), penjadwalan seminar proposal dan sidang munaqasyah/komprehensif, rubrik penilaian multi-penguji, verifikasi ambang batas plagiarisme (*Turnitin similarity check < 20%*), validasi bebas tanggungan kampus (*graduation clearance gatekeeping*), penerbitan Surat Keputusan (SK) Yudisium, penomoran ijazah nasional (**PIN/SIVIL**), dokumen **SKPI** bilingual, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Graduation Clearance Specialist & E-Skripsi Lead**
- **Domain Fokus**: Alur siklus tugas akhir mahasiswa, penjadwalan sidang, rubrik multi-penguji, penegakan 3 gerbang bebas tanggungan (Turnitin, Perpus, Keuangan), kompilasi SK Yudisium Dekanat, dan penomoran PIN/SKPI.

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-thesis-graduation-manager` | `siakad-academic-registrar` | Validasi pemenuhan syarat minimal 144 SKS (S1), ketiadaan nilai E, batas masa studi, dan validitas nomor ijazah nasional (PIN). |
| **Sebagai Reviewer Wajib** | `siakad-finance-billing-auditor` | `siakad-thesis-graduation-manager` | Memastikan rekonsiliasi bebas tunggakan finansial mahasiswa berstatus lunas (Rp 0,-) sebelum dinyatakan berhak mengikuti sidang atau yudisium. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap fitur tugas akhir, integrasi rubrik penguji, atau alur clearance yudisium dipecah menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan alur tugas akhir / integrasi rubrik sidang / validasi clearance yudisium / PIN & SKPI]

## Assigned Implementer
`siakad-thesis-graduation-manager`

## Mandatory Reviewer
`siakad-academic-registrar`

## Context & Files
- Scope: E-Skripsi Workflow, Rubrik Sidang, Gerbang Yudisium, Generator PIN/SKPI
- File terkait: `app/Services/Thesis/*`, `resources/views/theses/*`, `database/migrations/*`

## Scrum Steps
1. Definisikan state machine tahapan tugas akhir (Proposal -> Riset -> Sidang -> Revisi).
2. Terapkan perhitungan agregasi skor multi-penguji dan Berita Acara Ujian digital.
3. Kunci tiga gerbang kelayakan: Turnitin < 20%, Bebas Pustaka, Bebas Keuangan.
4. Susun generator roster yudisium dan integrasi penomoran ijazah nasional PIN.

## Acceptance Criteria & DoD
- [ ] Skor kemiripan Turnitin terverifikasi komputasional < 20%.
- [ ] Mahasiswa terbukti telah menuntaskan minimal 144 SKS tanpa nilai E.
- [ ] Status keuangan berstatus lunas penuh (SETTLEMENT) tanpa tunggakan.
- [ ] Berita Acara Ujian ditandatangani digital oleh seluruh dewan penguji.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan lingkup tahapan sidang atau clearance.
- **In Progress**: Specialist mengimplementasikan state machine, rubrik, dan gatekeeper.
- **Under Review**: Academic Registrar memverifikasi kelayakan SKS, batas waktu studi, dan integritas transkrip.
- **Done**: Disetujui reviewer dan diverifikasi pada simulasi peserta yudisium.

---

## 3. Penyelarasan E-Skripsi dengan Siklus Hidup Akademik 6-Fase

Tugas akhir dan kelulusan mengawal transisi fase puncak mahasiswa hingga menjadi alumni:

| Fase Akademik | Titik Integrasi E-Skripsi & Yudisium | Aksi Sistem SIAKAD |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Pembayaran tagihan pendaftaran seminar proposal, sidang komprehensif, dan wisuda. | Gatekeeper keuangan tagihan wisuda. |
| **Fase 2: Registrasi KRS** | Pengambilan mata kuliah Skripsi/Tugas Akhir di KRS (prasyarat minimal 110-120 SKS). | Validasi prasyarat pengambilan MK Skripsi. |
| **Fase 3: Masa Perkuliahan** | Pelaksanaan bimbingan berkala tercatat di logbook online (minimal 8 pertemuan per pembimbing). | Verifikasi kelayakan daftar Sempro/Sidang. |
| **Fase 4: Evaluasi & Ujian** | Sidang seminar proposal dan sidang munaqasyah/komprehensif berbobot rubrik. | Agregasi skor penguji & penandatanganan BAU. |
| **Fase 5: Kelulusan & Wisuda** | **Puncak**: Evaluasi 3 gerbang clearance, SK Yudisium Dekan, reservasi PIN, dan SKPI. | Penerbitan ijazah dan transkrip akhir resmi. |
| **Fase 6: Feeder PDDIKTI** | Pelaporan data kelulusan mahasiswa ke tabel Neo Feeder `mahasiswa_lulus_do`. | Ekspor nomor SK Yudisium dan PIN ke kementerian. |

---

## 4. Siklus Hidup Tugas Akhir & Gerbang Yudisium (Milestone Progression)

Proses penyelesaian studi diatur dalam 6 tahapan sequential dengan *checkpoint* persetujuan ketat:

```
[1. Pengajuan Topik & Calon Pembimbing]
                 │ ACC Kaprodi
                 ▼
[2. Seminar Proposal (Sempro)] ──> Penilaian Kelayakan & Revisi Proposal
                 │ Lulus Sempro
                 ▼
[3. Riset, Logbook & Bimbingan] ──> Minimal 8x Bimbingan per Dosen Pembimbing
                 │ Persetujuan Maju Sidang
                 ▼
[4. Sidang Skripsi / Komprehensif] ──> Dewan Penguji (Ketua, Sekretaris, Anggota)
                 │ Lulus dengan Revisi (Maks 30 Hari)
                 ▼
[5. Tiga Gerbang Bebas Tanggungan (Clearance Gateways)]:
   ├─ Gerbang 1: Uji Plagiarisme (Turnitin < 20%)
   ├─ Gerbang 2: Repositori Pustaka (Upload Mandiri Skripsi Lengkap & Jurnal)
   └─ Gerbang 3: Bebas Keuangan (Saldo Tagihan UKT = Rp 0)
                 │ 100% Cleared
                 ▼
[6. Rapat Yudisium & Penomoran PIN/SKPI] ──> Terbit SK Yudisium, Ijazah, & Transkrip
```

---

## 5. Manajemen Sidang & Rubrik Penilaian Multi-Penguji

Sidang akhir dinilai oleh dewan penguji yang terdiri dari 3 hingga 5 dosen (Pembimbing dan Penguji Ahli). Penilaian menggunakan rubrik terstandar:

### 5.1 Komponen Rubrik Penilaian Sidang
1. **Naskah Tulisan (Bobot 30%)**: Sistematika penulisan, kebaruan literatur, metodologi riset, ketepatan analisis data.
2. **Presentasi & Penguasaan Materi (Bobot 40%)**: Kualitas slide, kejelasan pemaparan, ketepatan menjawab pertanyaan kritis penguji.
3. **Produk / Karya / Publikasi Ilmiah (Bobot 30%)**: Fungsionalitas aplikasi/alat yang dibangun, artikel ilmiah yang disubmit ke jurnal/prosiding terakreditasi.

### 5.2 Agregasi Skor Otomatis & Pembulatan Nilai Mutu

$$\text{Skor Akhir Sidang} = \frac{\sum_{i=1}^{k} \text{Skor Penguji}_i}{k}$$

Sistem secara instan mengonversi skor angka (0-100) ke dalam bobot huruf mutu (A, A-, B+, B, dll.) dan memblokir pengeditan nilai pasca penandatanganan Berita Acara Ujian (BAU) digital.

---

## 6. Tiga Gerbang Bebas Tanggungan (Graduation Clearance Gateways)

Sebelum nama mahasiswa dapat dimasukkan ke dalam draft Surat Keputusan (SK) Yudisium, ketiga gerbang clearance berikut wajib tervalidasi secara komputasional:

| Gerbang Validasi | Persyaratan Mutlak | Bukti Otomatisasi Sistem |
| :--- | :--- | :--- |
| **1. Cek Kemiripan / Plagiasi** | Indeks kemiripan Turnitin $< 20\%$ (tanpa menyertakan daftar pustaka dan kutipan langsung). | Berkas laporan similarity terverifikasi oleh operator perpustakaan/fakultas. |
| **2. Repositori Pustaka** | Unggah softcopy lengkap (Cover s/d Lampiran), artikel publikasi, dan surat persetujuan publikasi. | ID Repositori / Bukti penyerahan karya ilmiah terbit dari sistem e-library. |
| **3. Bebas Finansial** | Tidak memiliki tunggakan UKT semester berjalan, denda perpustakaan, atau biaya wisuda. | Rekonsiliasi otomatis modul `student_billings`: seluruh tagihan berstatus `SETTLEMENT`. |

---

## 7. Penomoran Ijazah Nasional (PIN/SIVIL) & SKPI Generator

SIAKAD terintegrasi dengan standarisasi Direktorat Jenderal Pendidikan Tinggi:

### 7.1 Persyaratan Reservasi PIN (Penomoran Ijazah Nasional):
- Mahasiswa terdata aktif pada Pangkalan Data Pendidikan Tinggi (PDDIKTI) dengan status masa studi valid ($\le 14$ semester untuk S1).
- Total SKS lulus $\ge 144$ SKS (S1) atau $\ge 36$ SKS (S2) tanpa mata kuliah bernilai E.
- Nomor Induk Kependudukan (NIK) tervalidasi di Disdukcapil (16 digit angka).
- Akreditasi program studi dan perguruan tinggi berstatus aktif saat tanggal kelulusan.

### 7.2 Penerbitan SKPI (Surat Keterangan Pendamping Ijazah):
Sistem menyusun dokumen SKPI bilingual (Bahasa Indonesia dan Bahasa Inggris) yang mencakup:
- **Informasi Kualifikasi**: Tingkat Kerangka Kualifikasi Nasional Indonesia (KKNI Level 6 untuk Sarjana).
- **Capaian Pembelajaran Lulusan (CPL)** yang dikuasai lulusan.
- **Aktivitas, Prestasi & Rekognisi**: Keikutsertaan lomba, sertifikasi profesi internasional, organisasi kemahasiswaan, dan MBKM yang telah divalidasi oleh institusi.

---

## 8. Skema Database Modul E-Skripsi & Yudisium (`db_simak`)

```sql
-- Pengajuan dan Bimbingan Tugas Akhir
CREATE TABLE thesis_projects (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE RESTRICT,
    academic_period_id BIGINT NOT NULL REFERENCES academic_periods(id) ON DELETE RESTRICT,
    title TEXT NOT NULL,
    abstract TEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'PROPOSAL', -- 'PROPOSAL', 'RESEARCH', 'DEFENSE', 'REVISION', 'PASSED', 'REJECTED'
    turnitin_similarity_score NUMERIC(5,2) NULL,
    turnitin_receipt_path VARCHAR(255) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Tim Dosen Pembimbing & Penguji
CREATE TABLE thesis_examiners (
    id BIGSERIAL PRIMARY KEY,
    thesis_id BIGINT NOT NULL REFERENCES thesis_projects(id) ON DELETE CASCADE,
    lecturer_id BIGINT NOT NULL REFERENCES lecturers(id) ON DELETE RESTRICT,
    role VARCHAR(32) NOT NULL, -- 'PEMBIMBING_1', 'PEMBIMBING_2', 'KETUA_PENGUJI', 'ANGGOTA_PENGUJI'
    score NUMERIC(5,2) NULL,
    rubric_scores JSONB NULL,
    is_signed BOOLEAN NOT NULL DEFAULT false,
    signed_at TIMESTAMPTZ NULL,
    CONSTRAINT uq_thesis_examiner UNIQUE (thesis_id, lecturer_id, role)
);

-- Roster Yudisium & Penomoran Ijazah
CREATE TABLE graduation_clearances (
    id BIGSERIAL PRIMARY KEY,
    student_id BIGINT NOT NULL REFERENCES students(id) ON DELETE RESTRICT,
    thesis_id BIGINT NOT NULL REFERENCES thesis_projects(id) ON DELETE RESTRICT,
    yudisium_decree_number VARCHAR(128) NULL, -- Nomor SK Yudisium Dekan
    yudisium_date DATE NULL,
    national_diploma_number VARCHAR(64) NULL, -- PIN dari Kemendiktisaintek
    skpi_number VARCHAR(64) NULL,
    is_turnitin_cleared BOOLEAN NOT NULL DEFAULT false,
    is_library_cleared BOOLEAN NOT NULL DEFAULT false,
    is_finance_cleared BOOLEAN NOT NULL DEFAULT false,
    status VARCHAR(32) NOT NULL DEFAULT 'PENDING_CLEARANCE', -- 'CLEARED', 'GRADUATED'
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap modul atau berkas terkait tugas akhir dan yudisium wajib memenuhi kriteria berikut:

- [ ] **Batas Plagiarisme Turnitin**: Skor kemiripan terverifikasi komputasional $< 20\%$ dengan arsip bukti laporan resmi.
- [ ] **Audit Beban SKS Kelulusan**: Mahasiswa memiliki minimal 144 SKS (S1) tanpa nilai E pada seluruh mata kuliah wajib.
- [ ] **Tiga Gerbang Clearance Tervalidasi**: Gerbang Turnitin, Bebas Perpustakaan, dan Bebas Finansial berstatus 100% `CLEARED`.
- [ ] **Tanda Tangan Digital Berita Acara**: Berita Acara Ujian telah ditandatangani digital oleh seluruh dewan penguji dan nilai terkunci permanen.
- [ ] **Kesiapan PIN & SKPI**: Nomor PIN tervalidasi dan dokumen SKPI bilingual siap terbit dengan QR code verifikasi.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-academic-registrar`).
