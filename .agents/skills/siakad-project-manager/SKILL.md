---
name: siakad-project-manager
description: >-
  Orchestrate, govern, and deliver academic information system (SIAKAD Kampus) engineering projects, sprint milestones aligned with national academic calendar (KRS rush, UTS/UAS, Yudisium, PDDIKTI reporting cutoffs), scope control, risk mitigation, stakeholder management (Rektorat, Dekan, BAAK, Keuangan, Dosen, Mahasiswa), and quality sign-offs. Use this skill whenever planning sprints, managing software roadmaps, evaluating change requests, preparing academic rollout schedules, tracking budgets and milestones, or orchestrating UAT for SIAKAD.
---

# SIAKAD Project Manager & Academic Delivery Lead Skill

Panduan keahlian dan tata kelola manajemen proyek perangkat lunak (*software project governance & delivery leadership*) bagi **Lead Project Manager & Academic Product Delivery Lead** pada pengembangan dan operasionalisasi platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar pengelolaan sprint berbasis ritme kalender akademik nasional (Kemendiktisaintek/PDDIKTI), pengendalian ruang lingkup (*scope governance*), manajemen risiko lonjakan trafik (*peak academic concurrency*), koordinasi lintas pemangku kepentingan (Rektorat, Dekan, Kaprodi, BAAK, Dosen, Mahasiswa), serta jaminan kualitas (*Definition of Done & UAT*).

---

## 1. Peran & Prinsip Tata Kelola Proyek SIAKAD

Pengembangan SIAKAD memiliki karakteristik unik yang membedakannya dari sistem korporat umum:
1. **Hard Deadlines Terikat Regulasi:** Periode pelaporan PDDIKTI Feeder (Semester Ganjil, Genap, Antara) memiliki batas waktu nasional mutlak dari kementerian. Keterlambatan berimplikasi langsung pada sanksi administratif dan akreditasi kampus.
2. **Lonjakan Trafik Siklikal Ekstrem (*Traffic Spikes*):** Momen pendaftaran KRS serentak ribuan mahasiswa dalam hitungan detik dan tenggat input nilai dosen semesteran menuntut kesiapan sistem 99.9% uptime.
3. **Kompleksitas Multi-Stakeholder:** Mengakomodasi 14+ peran pengguna dengan kepentingan berbeda (Rektorat butuh KPI dashboard, BAAK butuh fleksibilitas penjadwalan, Keuangan butuh akurasi rekonsiliasi kas, Dosen butuh kemudahan input nilai, Mahasiswa butuh stabilitas portal).

---

## 2. Kalender Siklus Akademik & Sprint Milestones

Setiap proyek SIAKAD wajib dipetakan ke dalam 6 fase siklus akademik tahunan:

```
[Fase 1: PMB & UKT] ──> [Fase 2: KRS War & Approval] ──> [Fase 3: Perkuliahan & Presensi]
         │                                                            │
         ▼                                                            ▼
[Fase 6: Feeder PDDIKTI] <── [Fase 5: Yudisium & Wisuda] <── [Fase 4: Ujian & Finalisasi Nilai]
```

### 2.1 Pemetaan Fase & Target Rilis Fitur

| Fase Akademik | Milestone Sprint | Deliverable Kunci | Kriteria Keberhasilan |
| :--- | :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Sprint PMB & Billing | Portal Camaba, Setting Tarif UKT/IPI, Integrasi Payment Gateway / H2H Bank | Pembayaran realtime, auto-generate NIM & akun SIAKAD |
| **Fase 2: Registrasi KRS** | Sprint KRS Engine | Plotting Kurikulum/Jadwal, Validasi Prasyarat SKS, Fitur Kuota Kelas, Approval PA | Latensi < 250ms saat 5.000 concurrent user pendaftaran KRS |
| **Fase 3: Masa Kuliah** | Sprint Perkuliahan | Presensi QR Code/Mobile, E-Learning/LMS, Monitoring Jurnal Dosen, Survei EDOM | Akumulasi kehadiran otomatis terhitung untuk syarat ikut UTS/UAS |
| **Fase 4: Evaluasi & Ujian** | Sprint Grade Engine | Kartu Ujian (KPU), Lembar Nilai Dosen, Kalkulasi Bobot OBE, Kunci Nilai Otomatis | 100% nilai terkunci sesuai jadwal; transkrip semester (KHS) terbit |
| **Fase 5: Kelulusan** | Sprint Wisuda | Pendaftaran Skripsi/Tesis, Ujian Sidang, Validasi Bebas Pustaka/Keuangan, Yudisium, Penomoran PIN/SIVIL | Verifikasi transkrip akhir lengkap; bebas komplain data ijazah |
| **Fase 6: Pelaporan Nasional**| Sprint PDDIKTI Sync | Ekspor-Impor Neo Feeder (AKM, Kelas Kuliah, Nilai, Dosen Pengajar, Mahasiswa Lulus/DO) | 100% status validasi Feeder hijau tanpa invalid record |

---

## 3. Manajemen Ruang Lingkup & Penanganan Change Request (CR)

Untuk mencegah pembengkakan ruang lingkup (*scope creep*) yang membahayakan tenggat akademik, terapkan matriks klasifikasi Change Request:

### 3.1 Matriks Evaluasi Change Request (CR)

```markdown
### Prosedur Evaluasi Perubahan (CR Assessment)
1. **Tingkat 1: Regulasi Mandatori Nasional (Wajib & Prioritas Tertinggi)**
   - Perubahan format Feeder PDDIKTI terbaru, skema penomoran ijazah nasional (PIN/SIVIL), atau format pelaporan Kemendikbud.
   - Tindakan: *Fast-track sprint*, sesuaikan alokasi developer segera.
2. **Tingkat 2: Kebijakan Khusus Senat / Pimpinan Kampus (Medium)**
   - Perubahan aturan dispensasi pembayaran UKT, kebijakan semester antara, atau konversi SKS kurikulum OBE.
   - Tindakan: Buat Impact Analysis (Dampak ke modul KRS & Transkrip), ajukan ke sprint review berikutnya.
3. **Tingkat 3: Preferensi Kosmetik / Alur Prodi Non-Standar (Rendah / Backlog)**
   - Permintaan penambahan field kustom prodi tertentu yang tidak ada di standar PDDIKTI.
   - Tindakan: Masukkan ke *Post-Rollout Enhancement Backlog*.
```

### 3.2 Form Standar Dampak Perubahan (Impact Assessment Sheet)

Saat pemangku kepentingan meminta fitur baru di tengah periode berjalan, PM wajib merumuskan:
- **Nama Permintaan & Pengusul:** (Contoh: BAAK - Dispensasi KRS Manual lewat Kuota)
- **Modul Terdampak:** `KrsController`, Database Table `krs_details`, `ClassScheduleController`.
- **Dampak Kinerja & Risiko:** Potensi ketidaksesuaian rasio dosen:mahasiswa di PDDIKTI Feeder.
- **Estimasi Man-Days:** 3 Hari Kerja.
- **Keputusan:** Ditolak/Diterima dengan persetujuan Wakil Rektor I (Bidang Akademik).

---

## 4. Manajemen Risiko Lonjakan Trafik & High-Concurrency

| Skenario Risiko | Dampak | Level | Rencana Mitigasi Proaktif & Kontinjensi |
| :--- | :--- | :--- | :--- |
| **KRS Rush Hour (Server Down)** | Ribuan mahasiswa gagal ambil mata kuliah, protes massal | **KRITIS** | - Jadwal pembukaan KRS bertahap per angkatan/fakultas.<br>- Optimasi database query (hindari lock tabel penuh, gunakan row-level locking `FOR UPDATE SKIP LOCKED`).<br>- Redis caching untuk jadwal mata kuliah statis. |
| **Dosen Terlambat Input Nilai** | KHS tidak terbit, pelaporan Feeder tertunda | **TINGGI** | - Notifikasi WhatsApp/Email berkala H-7, H-3, H-1 tenggat.<br>- Auto-lock system dengan eskalasi laporan rekap ke Dekan & Kaprodi.<br>- Fitur perpanjangan masa nilai berbasis izin tertulis Dekan. |
| **Ketidaksesuaian Data Feeder PDDIKTI** | Rekap AKM bermasalah, sanksi Pangkalan Data Dikti | **TINGGI** | - Fitur Pre-validation Check di SIAKAD sebelum ekspor ke Feeder.<br>- Sinkronisasi berkala per minggu, bukan di akhir batas penutupan.<br>- Log audit error sync dengan rincian NIM dan field bermasalah. |
| **Data Pembayaran UKT Gagal Sinkron** | Mahasiswa terblokir KRS padahal sudah membayar | **SEDANG** | - Webhook retry mechanism dengan idempoten key pada payment gateway.<br>- Fitur override manual dari kasir keuangan berizin khusus dengan audit log. |

---

## 5. Matriks Komunikasi & Rapat Kerja Tim

1. **Daily Standup (15 Menit):** Fokus pada blocker hari ini, status tiket di Kanban, kesiapan rilis modul.
2. **Sprint Planning (Awal Sprint):** Breakdown backlog fitur akademik ke dalam tiket dengan estimasi *story points*.
3. **Stakeholder Alignment Meeting (Mingguan):** Update kemajuan ke BAAK, Keuangan, dan Tim IT Internal Kampus.
4. **Post-Mortem & Retrospective (Pasca KRS War / Pasca Rilis):** Analisis performa server, bottleneck query, dan feedback komplain tiket helpdesk mahasiswa.

---

## 6. Definition of Done (DoD) & Checklist Kualitas Rilis

Sebelum suatu tiket fitur SIAKAD dipindahkan ke status **Done** atau dirilis ke lingkungan production:

- [ ] **Functional Integrity:** Alur bisnis utama lolos uji skenario positif dan negatif (misal: mahasiswa dengan IPK 2.5 tidak bisa mengambil lebih dari 20 SKS).
- [ ] **Multi-Role Permission Check:** Hanya role yang berhak yang bisa mengakses endpoint (misal: Dosen tidak bisa mengedit nilai mata kuliah dosen lain).
- [ ] **Database Integrity:** Foreign key, transactional boundary (`DB::transaction()`), dan rollback aman saat terjadi kegagalan sistem.
- [ ] **Audit Trail:** Aksi penting (ubah nilai, approve KRS, ubah status bayar) tercatat di tabel `activity_logs`.
- [ ] **UI Responsiveness:** Tampilan dashboard dan tabel berjalan optimal di desktop dan mobile.
- [ ] **Feeder Compatibility:** Skema data mahasiswa/kelas/nilai konsisten dengan spesifikasi field PDDIKTI.
- [ ] **UAT Sign-Off:** Ditandatangani / disetujui oleh penanggung jawab modul (BAAK / Keuangan / Dekanat).
