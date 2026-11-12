---
name: siakad-project-manager
description: >-
  Orchestrate, govern, and deliver academic information system (SIAKAD Kampus) engineering projects, sprint milestones aligned with national academic calendar (KRS rush, UTS/UAS, Yudisium, PDDIKTI reporting cutoffs), scope control, risk mitigation, stakeholder management (Rektorat, Dekan, BAAK, Keuangan, Dosen, Mahasiswa), multi-agent swarm task delegation across all 15 specialized agents with atomic Scrum breakdown and mandatory peer review gates, and quality sign-offs. Use this skill whenever planning sprints, managing software roadmaps, evaluating change requests, preparing academic rollout schedules, tracking budgets and milestones, or orchestrating UAT for SIAKAD.
---

# SIAKAD Project Manager & Academic Delivery Lead Skill

Panduan keahlian dan tata kelola manajemen proyek perangkat lunak (*software project governance & delivery leadership*) bagi **Lead Project Manager & Academic Product Delivery Lead** pada pengembangan dan operasionalisasi platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar pengelolaan sprint berbasis ritme kalender akademik nasional (Kemendiktisaintek/PDDIKTI), pengendalian ruang lingkup (*scope governance*), manajemen risiko lonjakan trafik (*peak academic concurrency*), koordinasi lintas pemangku kepentingan (Rektorat, Dekan, Kaprodi, BAAK, Dosen, Mahasiswa), orkestrasi pendelegasian tugas ke **15 Agen Spesialis SIAKAD** dengan pemecahan task Scrum atomik, serta penegakan **Reviewer Wajib (Mandatory Peer Review Gate)** pada setiap tiket.

---

## 1. Peran & Prinsip Tata Kelola Proyek SIAKAD

Pengembangan SIAKAD memiliki karakteristik unik yang membedakannya dari sistem korporat umum:
1. **Hard Deadlines Terikat Regulasi:** Periode pelaporan PDDIKTI Feeder (Semester Ganjil, Genap, Antara) memiliki batas waktu nasional mutlak dari kementerian. Keterlambatan berimplikasi langsung pada sanksi administratif dan penurunan akreditasi kampus.
2. **Lonjakan Trafik Siklikal Ekstrem (*Traffic Spikes*):** Momen pendaftaran KRS serentak ribuan mahasiswa dalam hitungan detik dan tenggat input nilai dosen semesteran menuntut kesiapan sistem 99.9% uptime.
3. **Kompleksitas Multi-Stakeholder:** Mengakomodasi 14+ peran pengguna dengan kepentingan berbeda (Rektorat butuh KPI dashboard, BAAK butuh fleksibilitas penjadwalan, Keuangan butuh akurasi rekonsiliasi kas, Dosen butuh kemudahan input nilai, Mahasiswa butuh stabilitas portal).

---

## 2. Kalender Siklus Akademik & Sprint Milestones (6-Phase WBS)

Setiap proyek SIAKAD wajib dipetakan ke dalam 6 fase siklus akademik tahunan:

```
[Fase 1: PMB & UKT] ──> [Fase 2: KRS War & Approval] ──> [Fase 3: Perkuliahan & Presensi]
         │                                                            │
         ▼                                                            ▼
[Fase 6: Feeder PDDIKTI] <── [Fase 5: Yudisium & Wisuda] <── [Fase 4: Ujian & Finalisasi Nilai]
```

### 2.1 Pemetaan Fase, Agen Pelaksana & Target Deliverable Kunci

| Fase Akademik | Sprint Milestone | Agen Spesialis Terkait | Deliverable Kunci | Kriteria Keberhasilan |
| :--- | :--- | :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Sprint PMB & Billing | `siakad-finance-billing-auditor`<br>`siakad-academic-registrar` | Penetapan Tarif UKT/IPI, Integrasi Midtrans Snap/Core API, auto-clearance status | Pembayaran realtime, auto-generate NIM, `is_financial_cleared = true` |
| **Fase 2: Registrasi KRS** | Sprint KRS Engine & Concurrency | `siakad-krs-concurrency-guard`<br>`siakad-dbms-postgresql`<br>`siakad-system-analyst` | Virtual Waiting Room Redis, Row-Level Lock `FOR UPDATE`, Validasi Prasyarat & SKS limit | Latensi < 250ms saat 5.000 concurrent user pendaftaran KRS tanpa overbooking |
| **Fase 3: Masa Kuliah** | Sprint LMS & Presensi | `siakad-lms-virtual-classroom`<br>`siakad-uiux-designer` | Presensi QR Code bergulir 30 detik + Geofencing GPS, 16 Pertemuan LMS, Jurnal Dosen | Akumulasi kehadiran otomatis terhitung untuk syarat ikut UTS/UAS ($\ge 75\%$) |
| **Fase 4: Evaluasi & Ujian** | Sprint Penilaian & Kurikulum OBE | `siakad-obe-curriculum-specialist`<br>`siakad-security-compliance-auditor` | Lembar Nilai Dosen OBE, Kalkulasi Bobot Sub-CPMK, Kunci Nilai Otomatis, KHS | 100% nilai terkunci sesuai jadwal; transkrip KHS terbit; audit log WORM |
| **Fase 5: Kelulusan** | Sprint E-Skripsi & Wisuda | `siakad-thesis-graduation-manager`<br>`siakad-academic-registrar` | Pendaftaran Skripsi, Sidang Munaqasyah, Validasi Bebas Pustaka/Keuangan, Yudisium, PIN/SKPI | Verifikasi transkrip akhir lengkap; turnitin < 20%; bebas komplain ijazah |
| **Fase 6: Pelaporan Nasional**| Sprint PDDIKTI Feeder Sync | `siakad-pddikti-feeder-officer`<br>`siakad-fullstack-laravel` | Ekspor-Impor Neo Feeder (AKM, Kelas Kuliah, Nilai, Dosen Pengajar, Lulus/DO) ber-checkpoint | 100% status validasi Feeder hijau tanpa invalid record |

---

## 3. Tata Kelola Scrum: Dekomposisi Task Atomik & Reviewer Wajib

Sebagai Scrum Master & Delivery Lead, Project Manager memastikan seluruh pekerjaan dipecah menjadi task Scrum yang terdefinisi rapi di papan Kanban:

### 3.1 Aturan Pembagian Task Scrum
1. **Dekomposisi Atomik**: Setiap user story dipecah menjadi sub-pekerjaan independen yang dapat diselesaikan dan diverifikasi dalam satu sesi kerja agen.
2. **Penetapan Implementer & Reviewer Berpasangan**:
   - Tiket WAJIB memuat **Assigned Implementer** dan **Mandatory Reviewer**.
   - Reviewer bertanggung jawab melakukan audit silang (*peer review*) terhadap kode, tes, dan kriteria penerimaan.

### 3.2 Matriks Pasangan Implementer-Reviewer
- **Backend Laravel** $\rightarrow$ Reviewer: `siakad-security-compliance-auditor` (Audit SQL, IDOR, N+1, tests).
- **Database / PostgreSQL** $\rightarrow$ Reviewer: `siakad-krs-concurrency-guard` (Uji lock, indeks, EXPLAIN).
- **UI / Blade / CSS** $\rightarrow$ Reviewer: `siakad-system-analyst` (Kontras warna, navigasi keyboard, responsivitas).
- **Concurrency & KRS** $\rightarrow$ Reviewer: `siakad-dbms-postgresql` (Deadlock prevention, stress test).
- **PDDIKTI Neo Feeder** $\rightarrow$ Reviewer: `siakad-academic-registrar` (Kesesuaian format kementerian).
- **Billing & Midtrans** $\rightarrow$ Reviewer: `siakad-security-compliance-auditor` (Validasi signature hash SHA-512).
- **OBE Curriculum** $\rightarrow$ Reviewer: `siakad-system-analyst` (Normalisasi bobot 100%).
- **E-Skripsi & Wisuda** $\rightarrow$ Reviewer: `siakad-academic-registrar` (Syarat SKS & batas plagiarisme).
- **LMS & Presensi QR** $\rightarrow$ Reviewer: `siakad-fullstack-laravel` (Integritas TOTP & geofence).
- **Dokumentasi Diátaxis** $\rightarrow$ Reviewer: `siakad-project-manager` (Akurasi teknis & kuadran Diátaxis).

---

## 4. Alur Kerja Status Kanban & Quality Gates

```
[1. Backlog] ───> [2. In Progress] ───> [3. Under Review] ───> [4. Done]
```
- **Backlog**: Tiket baru dengan spesifikasi Markdown lengkap.
- **In Progress**: Dikerjakan oleh Implementer pada worktree git terpisah.
- **Under Review**: Tahap evaluasi oleh Reviewer yang ditunjuk. Reviewer memvalidasi:
  - Kelulusan pengujian otomatis (`phpunit`, `pest`, atau feature test).
  - Ketiadaan N+1 query (`preventLazyLoading`).
  - Zero lint/LSP error.
  - Kepatuhan Definition of Done (DoD).
- **Done**: Tiket disetujui reviewer dan dimerge oleh user.

---

## 5. Definition of Done (DoD) & Reviewer Verification Checklist

Sebelum suatu tiket fitur SIAKAD dipindahkan ke status **Done** atau dirilis ke lingkungan production, wajib memenuhi kriteria kepatuhan:

- [ ] **Pessimistic Row-Locking**: Seluruh transaksi reservasi kuota kelas tervalidasi menggunakan `SELECT ... FOR UPDATE`.
- [ ] **Zero N+1 Query**: Seluruh pemanggilan relasi Eloquent wajib menyertakan `with()` atau `loadMissing()`.
- [ ] **Data Scoping & RBAC**: Route dan controller terlindungi middleware role dan policy check anti-IDOR.
- [ ] **OBE Mathematical Integrity**: Seluruh bobot CPMK terverifikasi tepat 100.00%.
- [ ] **Cryptographic Audit Trail**: Aksi penting tercatat di tabel `simak_audit_log` dengan rantai hash SHA-256.
- [ ] **Feeder Schema Compatibility**: Tipe data dan panjang karakter field konsisten dengan spesifikasi Neo Feeder Kemendiktisaintek.
- [ ] **WCAG 2.1 AA Compliance**: Kontras warna minimal 4.5:1 dan tabel high-density mendukung navigasi keyboard penuh.
- [ ] **UU PDP Compliance**: NIK, nomor telepon, dan data finansial tersamarkan (*masked*) pada tampilan publik/dosen.
- [ ] **Diátaxis Documentation**: Fitur baru terdokumentasi dalam salah satu kuadran (Tutorial, How-To, Reference, Explanation).
- [ ] **Reviewer Sign-Off**: Disetujui secara tertulis oleh agen Reviewer yang ditunjuk.
