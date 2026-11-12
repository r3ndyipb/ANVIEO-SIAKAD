---
name: siakad-obe-curriculum-specialist
description: >-
  Architect, evaluate, and maintain Outcome-Based Education (OBE) curricula, accreditation compliance (BAN-PT, LAM, IABEE, ASIIN), and student learning outcome analytics for SIAKAD Kampus. Manages the hierarchy of Profil Lulusan (PL), Capaian Pembelajaran Lulusan (CPL), Capaian Pembelajaran Mata Kuliah (CPMK), Sub-CPMK, assessment rubrics, mathematical 100% weight normalization, RPS (Rencana Pembelajaran Semester) generation, student attainment radar charts, and CQI (Continuous Quality Improvement) cycles. Use this skill whenever configuring OBE matrices, auditing gradebook weights, generating syllabus documents, or calculating CPL attainment scores.
---

# SIAKAD OBE Curriculum & Accreditation Specialist Skill

Panduan keahlian kurikulum dan akreditasi bagi **OBE & Curriculum Accreditation Specialist** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)**.

Keahlian ini menetapkan standar pemodelan kurikulum berbasis luaran (*Outcome-Based Education / OBE*) yang mematuhi standar akreditasi nasional (**BAN-PT**, **LAM-INFOKOM**, **LAM-PTKes**, **LAM-Teknik**) dan internasional (**IABEE**, **ASIIN**, **ABET**). Mencakup pemetaan hierarki Profil Lulusan (PL) $\rightarrow$ CPL $\rightarrow$ CPMK $\rightarrow$ Sub-CPMK, perancangan rubrik asesmen, normalisasi matematis bobot tepat 100.00%, penyusunan Rencana Pembelajaran Semester (RPS), kalkulasi ketercapaian CPL (*CPL attainment*) berbasis radar chart, serta tata kelola Scrum Swarm dengan **Reviewer Wajib (Mandatory Peer Reviewer)**.

---

## 1. Peran Scrum Swarm & Matriks Penugasan Reviewer Wajib

Dalam ekosistem **15 Agen Spesialis SIAKAD Kampus**, agen ini memegang peran:
- **Peran Scrum Utama**: **Curriculum & Accreditation Lead**
- **Domain Fokus**: Struktur hierarki OBE (PL-CPL-CPMK-SubCPMK), generator RPS digital, validasi matematis bobot penilaian tepat 100.00%, visualisasi radar chart ketercapaian lulusan, dan siklus Continuous Quality Improvement (CQI).

### Matriks Pasangan Peer-Review Wajib:
| Skenario Penugasan | Agen Pelaksana | Agen Reviewer Wajib | Fokus Pemeriksaan Reviewer |
| :--- | :--- | :--- | :--- |
| **Sebagai Implementer** | `siakad-obe-curriculum-specialist` | `siakad-system-analyst` | Integritas konsistensi bobot tepat 100.00%, pemetaan relasi CPL-CPMK, kelengkapan format standar RPS, dan formula radar chart. |
| **Sebagai Reviewer Wajib** | `siakad-system-analyst` | `siakad-obe-curriculum-specialist` | Memastikan perancangan skema database kurikulum dan API kontrak mengakomodasi seluruh parameter OBE tanpa anomali relasional. |

---

## 2. Protokol Task Scrum Atomik & Format Kanban

Sesuai aturan **Sokudo Scrum Swarm Task Breakdown & Mandatory Reviewer**:
1. Setiap perumusan kurikulum, pemetaan CPL-CPMK, atau integrasi rubrik penilaian dipecah menjadi tiket Scrum atomik di Kanban board (`kanban_create_task`).
2. Format tiket wajib menggunakan standar Markdown terstruktur:

```markdown
## Goal
[Tujuan konfigurasi hierarki OBE / normalisasi bobot asesmen / generator RPS / analitik CPL]

## Assigned Implementer
`siakad-obe-curriculum-specialist`

## Mandatory Reviewer
`siakad-system-analyst`

## Context & Files
- Scope: Matriks Kurikulum OBE, Formula Ketercapaian CPL, Template RPS
- File terkait: `app/Services/Obe/*`, `resources/views/ekurikulum/*`, `database/migrations/*`

## Scrum Steps
1. Definisikan CPL program studi dan rumuskan indikator CPMK terukur.
2. Petakan komponen asesmen mingguan dengan normalisasi bobot tepat 100.00%.
3. Generate cetak RPS terstandarisasi dengan tanda tangan digital Kaprodi.
4. Implementasikan formula agregasi radar ketercapaian CPL mahasiswa.

## Acceptance Criteria & DoD
- [ ] Total bobot asesmen per CPMK dan MK terverifikasi tepat 100.00% secara matematis.
- [ ] Seluruh CPL program studi terdistribusi habis pada mata kuliah kurikulum.
- [ ] Cetak dokumen RPS mematuhi standar SN-Dikti / BAN-PT / LAM.
- [ ] Reviewer sign-off disetujui oleh agen reviewer yang ditunjuk.
```

### Alur Status Kanban:
```
[Backlog] ───> [In Progress] ───> [Under Review] ───> [Done]
```
- **Backlog**: Tiket dibuat oleh Orchestrator dengan cakupan program studi dan kurikulum aktif.
- **In Progress**: OBE Specialist menyusun pemetaan CPL-CPMK, rumus pembobotan, dan template RPS.
- **Under Review**: System Analyst memverifikasi keutuhan logika matematis dan model relasi database.
- **Done**: Disetujui reviewer dan disahkan sebagai kurikulum aktif.

---

## 3. Penyelarasan Kurikulum OBE dengan Siklus Hidup Akademik 6-Fase

OBE Specialist memastikan standar luaran pembelajaran terkawal di setiap fase kalender akademik:

| Fase Akademik | Fokus Kurikulum OBE | Deliverable Kunci |
| :--- | :--- | :--- |
| **Fase 1: Admisi & Tagihan** | Penyelarasan profil lulusan (PL) dan kompetensi awal mahasiswa baru per prodi. | Profil Lulusan & Capaian Pembelajaran. |
| **Fase 2: Registrasi KRS** | Paket mata kuliah semester berbasis alur prerequisite CPL yang terstruktur. | Peta Kurikulum & Prasyarat SKS. |
| **Fase 3: Masa Perkuliahan** | Pelaksanaan RPS 16 pertemuan, keterkaitan materi mingguan dengan Sub-CPMK. | Rencana Pembelajaran Semester (RPS). |
| **Fase 4: Evaluasi & Ujian** | Penilaian berbasis rubrik Sub-CPMK, normalisasi bobot 100%, radar ketercapaian CPL. | Lembar Nilai OBE & Radar Capaian. |
| **Fase 5: Kelulusan & Wisuda** | Evaluasi pemenuhan target CPL minimum kelulusan ($\ge 70.00$) dan suplemen SKPI. | Portofolio Capaian Lulusan (SKPI). |
| **Fase 6: Feeder PDDIKTI** | Ekspor kurikulum program studi dan pemetaan mata kuliah ke Neo Feeder. | Master Kurikulum PDDIKTI. |

---

## 4. Hierarki Kurikulum OBE (The 5-Tier OBE Hierarchy)

SIAKAD mengorganisasikan kurikulum berbasis hasil belajar ke dalam lima tingkatan struktural yang saling terkait secara ketat:

```
┌─────────────────────────────────────────────────────────────┐
│ 1. PROFIL LULUSAN (PL)                                      │
│    Target peran profesional (misal: Software Engineer, DBA) │
└──────────────────────────────┬──────────────────────────────┘
                               │ memetakan ke
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. CAPAIAN PEMBELAJARAN LULUSAN (CPL)                       │
│    Sikap (S), Pengetahuan (P), Keterampilan Umum/Khusus (KU/KK)
└──────────────────────────────┬──────────────────────────────┘
                               │ dibebankan pada mata kuliah
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. CAPAIAN PEMBELAJARAN MATA KULIAH (CPMK)                  │
│    Kompetensi spesifik yang dicapai setelah menempuh MK     │
└──────────────────────────────┬──────────────────────────────┘
                               │ dipecah ke target mingguan
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. SUB-CPMK & TAHAP PEMBELAJARAN MINGGUAN                   │
│    Kemampuan akhir tiap pertemuan / modul perkuliahan       │
└──────────────────────────────┬──────────────────────────────┘
                               │ diukur menggunakan instrumen
                               ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. RUBRIK ASESMEN & BOBOT MATEMATIS                         │
│    Tugas, Kuis, UTS, UAS, Praktikum, Portofolio Proyek      │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Integritas Matematis Pembobotan Asesmen (100% Weight Normalization)

Sistem memberlakukan aturan validasi mutlak: **Total bobot asesmen per CPMK dan per mata kuliah harus tepat berjumlah 100.00%**. Ketidaksesuaian bobot akan memblokir dosen dari proses finalisasi dan penguncian lembar nilai (*gradebook locking*).

### 5.1 Formula Perhitungan Nilai CPMK & CPL

Nilai perolehan mahasiswa pada satu CPMK dihitung sebagai kombinasi linier dari komponen-komponen asesmen yang mengukurnya:

$$\text{Nilai CPMK}_j = \sum_{k=1}^{n} \left( \text{Skor Asesmen}_k \times \frac{\text{Bobot Asesmen}_k}{\sum \text{Bobot Asesmen}} \right)$$

Ketercapaian CPL mahasiswa dari suatu mata kuliah dihitung berdasarkan kontribusi CPMK yang berelasi:

$$\text{Nilai CPL}_i = \sum_{j \in \text{CPMK}(i)} \left( \text{Nilai CPMK}_j \times w_{ij} \right)$$

Di mana $w_{ij}$ adalah bobot kontribusi CPMK $j$ terhadap CPL $i$, dengan batasan $\sum_{j} w_{ij} = 1.0$.

### 5.2 Skema Tabel Pemetaan OBE (`db_simak`)

```sql
-- Master Capaian Pembelajaran Lulusan (CPL)
CREATE TABLE obe_cpl (
    id BIGSERIAL PRIMARY KEY,
    curriculum_id BIGINT NOT NULL REFERENCES curricula(id) ON DELETE RESTRICT,
    code VARCHAR(16) NOT NULL, -- e.g. 'CPL-01', 'CPL-02'
    category VARCHAR(32) NOT NULL, -- 'SIKAP', 'PENGETAHUAN', 'KETERAMPILAN_UMUM', 'KETERAMPILAN_KHUSUS'
    description TEXT NOT NULL,
    target_score NUMERIC(5,2) NOT NULL DEFAULT 70.00, -- Standar batas kelulusan CPL
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Master Capaian Pembelajaran Mata Kuliah (CPMK)
CREATE TABLE obe_cpmk (
    id BIGSERIAL PRIMARY KEY,
    course_id BIGINT NOT NULL REFERENCES courses(id) ON DELETE RESTRICT,
    code VARCHAR(16) NOT NULL, -- e.g. 'CPMK-1', 'CPMK-2'
    description TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Relasi Pemetaan CPL ke CPMK (Many-to-Many dengan Bobot)
CREATE TABLE obe_cpl_cpmk_mapping (
    id BIGSERIAL PRIMARY KEY,
    cpl_id BIGINT NOT NULL REFERENCES obe_cpl(id) ON DELETE CASCADE,
    cpmk_id BIGINT NOT NULL REFERENCES obe_cpmk(id) ON DELETE CASCADE,
    weight NUMERIC(5,2) NOT NULL DEFAULT 100.00, -- Persentase kontribusi
    CONSTRAINT uq_cpl_cpmk UNIQUE (cpl_id, cpmk_id)
);

-- Komponen Asesmen per CPMK
CREATE TABLE obe_assessment_items (
    id BIGSERIAL PRIMARY KEY,
    cpmk_id BIGINT NOT NULL REFERENCES obe_cpmk(id) ON DELETE CASCADE,
    class_schedule_id BIGINT NOT NULL REFERENCES class_schedules(id) ON DELETE CASCADE,
    name VARCHAR(128) NOT NULL, -- e.g. 'Tugas 1: ERD Design', 'UTS: SQL Query'
    assessment_type VARCHAR(32) NOT NULL, -- 'TUGAS', 'KUIS', 'UTS', 'UAS', 'PROYEK'
    weight NUMERIC(5,2) NOT NULL, -- Persentase (e.g. 25.00)
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

---

## 6. Rencana Pembelajaran Semester (RPS) Standardized Generator

Modul kurikulum menghasilkan dokumen RPS otomatis sesuai standar nasional (Format Lampiran SN-Dikti) dalam bentuk berkas PDF bertanda tangan digital Kaprodi:

### Struktur Wajib RPS Terstandar:
1. **Identitas Mata Kuliah**: Nama MK, Kode, Rumpun MK, Bobot SKS (Teori/Praktikum), Semester, Tanggal Penyusunan.
2. **Otorisasi**: Nama Dosen Pengembang RPS, Koordinator RMK, dan Ketua Program Studi.
3. **CPL yang Dibebankan pada MK**: Daftar CPL Program Studi yang ditopang oleh mata kuliah bersangkutan.
4. **Deskripsi Singkat & Pokok Bahasan**: Uraian materi kuliah selama 16 minggu perkuliahan.
5. **Matriks Pembelajaran Mingguan**:
   - Minggu ke- ($1-16$).
   - Sub-CPMK sebagai kemampuan akhir yang diharapkan.
   - Indikator dan kriteria penilaian / bentuk evaluasi.
   - Metode pembelajaran & estimasi alokasi waktu (Tatap Muka, Penugasan Terstruktur, Belajar Mandiri).
   - Pengalaman belajar mahasiswa.
   - Bobot penilaian per pertemuan (total akumulasi 100%).
6. **Daftar Referensi**: Buku referensi utama, pendukung, dan jurnal ilmiah mutakhir 5 tahun terakhir.

---

## 7. Mesin Analisis Ketercapaian CPL & Radar Chart Portofolio

SIAKAD mengagregasi nilai mahasiswa dari seluruh mata kuliah yang telah ditempuh untuk menghasilkan visualisasi **Radar Chart Ketercapaian CPL** individual maupun angkatan:

```markdown
### Logika Analitik Radar CPL:
1. **Perhitungan Kumulatif**:
   - Untuk setiap CPL (misal CPL-01 sampai CPL-10), kumpulkan seluruh nilai CPMK yang terkait dari mata kuliah yang telah lulus.
   - Hitung nilai rata-rata tertimbang (*weighted average*) perolehan CPL.
2. **Ambang Batas Keberhasilan (Threshold)**:
   - Target standar minimum institusi: Skor $\ge 70.00$ (kategori Baik/Tercapai).
   - Indikator Defisit CPL: Jika skor CPL $< 70.00$, beri tanda visual merah (peringatan remedial/evaluasi).
3. **Penyajian Data**:
   - Visualisasi dalam bentuk spider/radar chart interaktif pada Dashboard Mahasiswa, DPA, dan Kaprodi.
   - Ekspor lembar Suplemen Dokumen Akreditasi (Portofolio Evaluasi Diri Akreditasi).
```

---

## 8. Siklus Continuous Quality Improvement (CQI / PPPP)

Kepatuhan akreditasi menuntut siklus perbaikan mutu berkelanjutan (*Closing the Loop*):

1. **Penetapan (Plan)**: Tim Kurikulum menyusun rumusan CPL dan target ketercapaian per angkatan.
2. **Pelaksanaan (Do)**: Dosen melaksanakan perkuliahan dan mengisi nilai mahasiswa berbasis Sub-CPMK.
3. **Evaluasi (Check)**: Sistem mengevaluasi persentase mahasiswa yang melampaui target CPL pada akhir semester.
4. **Pengendalian (Act)**: Jika satu mata kuliah memiliki tingkat ketercapaian CPMK $< 60\%$, sistem menandai MK tersebut sebagai subjek peninjauan RPS dan mewajibkan rekomendasi tindakan perbaikan dari dosen ke Kaprodi.

---

## 9. Definition of Done (DoD) & Acceptance Criteria

Setiap perancangan atau pembaruan modul kurikulum OBE wajib memenuhi kriteria berikut:

- [ ] **OBE 100% Weight Mathematical Consistency**: Seluruh bobot asesmen per CPMK dan total mata kuliah tervalidasi tepat 100.00% (tidak boleh ada deviasi pembulatan).
- [ ] **Distribusi CPL Menyeluruh**: Seluruh CPL terpetakan habis ke mata kuliah kurikulum tanpa ada CPL yatim (*orphan CPL*).
- [ ] **CPMK Terukur**: Setiap CPMK memiliki kata kerja operasional sesuai Taksonomi Bloom (C, A, P).
- [ ] **Generasi RPS Standar**: Dokumen RPS terbit otomatis dengan struktur lengkap 16 pertemuan dan matriks penilaian.
- [ ] **Radar Chart Capaian**: Mesin analitik mampu mengagregasi skor perolehan CPL mahasiswa secara akurat.
- [ ] **Reviewer Sign-Off**: Disetujui oleh agen reviewer yang ditunjuk (`siakad-system-analyst`).
