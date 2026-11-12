---
name: siakad-dbms-postgresql
description: >-
  Architect, optimize, and maintain PostgreSQL 15/16+ database architectures for SIAKAD Kampus (db_simak). Master data partitioning, transactional concurrency locking during KRS rush, B-Tree and GIN indexing, CTEs, window functions, views, trigger-based audit logs, migration baselines, and query performance tuning (EXPLAIN ANALYZE). Use this skill whenever designing database schemas, tuning slow queries, handling race conditions, configuring table partitioning, or securing academic data integrity in PostgreSQL.
---

# SIAKAD PostgreSQL Database Architect & Performance Tuning Skill

Panduan keahlian dan standar baku pengelolaan basis data bagi **Lead Database Architect & Senior PostgreSQL DBA** pada platform **Sistem Informasi Akademik Kampus (SIAKAD/SIMAK)** berbasis **PostgreSQL 15/16+ (`db_simak`)**.

Keahlian ini menetapkan standar pemodelan data relasional ternormalisasi (3NF), teknik penanganan konkurensi ekstrem saat *KRS War*, strategi pengindeksan presisi (*B-Tree, Partial, GIN*), partisi tabel (*table partitioning*) untuk data presensi dan nilai massal, integritas audit trigger anti-kecurangan nilai, serta optimasi kueri analitik transkrip menggunakan *Window Functions* dan *Common Table Expressions (CTE)*.

---

## 1. Konvensi Skema & Naming Convention PostgreSQL (`db_simak`)

Konsistensi skema adalah fondasi performa dan kemudahan pemeliharaan:

1. **Naming Standard:**
   - Nama tabel: Jamak, huruf kecil, *snake_case* (contoh: `students`, `class_schedules`, `course_grades`, `krs_details`).
   - Primary Key: `id BIGSERIAL` atau `id UUID DEFAULT gen_random_uuid()`.
   - Foreign Key: Format tunggal `[entity_name]_id` (contoh: `student_id`, `academic_period_id`, `class_schedule_id`).
   - Tabel Pivot / Many-to-Many: Alfabetis atau hierarkis (contoh: `class_schedule_lecturer`, `curriculum_course`).
   - Kolom Audit Wajib: `created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP`, `deleted_at TIMESTAMPTZ NULL` (jika soft delete).
2. **Integritas Relasional:**
   - Selalu tetapkan batasan `ON DELETE RESTRICT` pada entitas master akademik kritis (seperti `students`, `courses`, `academic_periods`) guna mencegah penghapusan data bersejarah secara tidak sengaja.
   - Gunakan `ON DELETE CASCADE` hanya pada data turunan murni (seperti `krs_details` terhadap `krs` draft).

---

## 2. Penanganan Konkurensi Tinggi & Race Condition saat "War KRS"

Momen pemilihan kartu rencana studi (KRS) oleh ribuan mahasiswa serentak sering memicu *race condition* (kapasitas kelas 40 kursi diperebutkan oleh 500 mahasiswa dalam detik yang sama).

### 2.1 Pola Atomic Seat Reservation (Pencegahan Overbooking)

Hindari pendekatan naif `SELECT count(*) ...` kemudian `INSERT`, karena berpotensi meloloskan mahasiswa melebihi kuota akibat *read-committed race condition*.

Gunakan transaksi atomik dengan **Row-Level Locking (`FOR UPDATE`)** atau *atomic conditional update*:

```sql
-- Skenario: Mahasiswa mengambil kelas kuliah ID = 845
BEGIN;

-- 1. Kunci baris jadwal kelas secara eksklusif untuk mengecek sisa kuota
SELECT id, quota, enrolled_count 
FROM class_schedules 
WHERE id = 845 
FOR UPDATE;

-- 2. Validasi kuota di sisi aplikasi (atau via constraint trigger)
-- Jika enrolled_count >= quota, segera ROLLBACK;

-- 3. Tambahkan rincian KRS mahasiswa dengan penanganan unique constraint
INSERT INTO krs_details (
    krs_id, 
    class_schedule_id, 
    status, 
    created_at, 
    updated_at
) VALUES (
    12044, 
    845, 
    'pending', 
    CURRENT_TIMESTAMP, 
    CURRENT_TIMESTAMP
);

-- 4. Inkrementasi jumlah pendaftar secara atomik
UPDATE class_schedules 
SET enrolled_count = enrolled_count + 1,
    updated_at = CURRENT_TIMESTAMP
WHERE id = 845;

COMMIT;
```

### 2.2 Pencegahan Deadlock saat Pengambilan Multi-Mata Kuliah
Jika satu transaksi melakukan *lock* pada beberapa kelas sekaligus (misal: pendaftaran 8 mata kuliah dalam satu klik tombol submit):
- **Wajib Urutkan ID:** Selalu urutkan `class_schedule_id` secara *ascending* sebelum dieksekusi dalam loop transaksi guna menjamin urutan perolehan lock yang seragam di seluruh koneksi.
- **Konfigurasi Timeout:**
  ```sql
  SET LOCAL lock_timeout = '3s';
  SET LOCAL statement_timeout = '5s';
  ```

---

## 3. Strategi Pengindeksan & Optimasi Kueri (Indexing Masterclass)

### 3.1 Composite Index untuk Pencarian Akademik Tercepat
Kueri SIAKAD hampir selalu difilter berdasarkan `academic_period_id` digabungkan dengan `student_id` atau `class_schedule_id`:

```sql
-- Kueri melihat riwayat KRS mahasiswa per semester
CREATE INDEX idx_krs_period_student 
ON krs (academic_period_id, student_id);

-- Kueri absensi per pertemuan kelas
CREATE INDEX idx_attendance_schedule_meeting 
ON course_attendances (class_schedule_id, meeting_number);

-- Kueri nilai mahasiswa per kelas
CREATE INDEX idx_grades_schedule_student 
ON course_grades (class_schedule_id, student_id);
```

### 3.2 Partial Index (Hemat Ruang & Super Cepat)
Indeks parsial hanya mengindeks subset baris yang sering menjadi target filter:

```sql
-- Hanya indeks KRS yang berstatus 'pending' (menunggu validasi Dosen PA)
CREATE INDEX idx_krs_details_pending_approval 
ON krs_details (class_schedule_id) 
WHERE status = 'pending';

-- Hanya indeks tagihan UKT yang belum lunas (outstanding)
CREATE INDEX idx_unpaid_billings 
ON student_billings (student_id, academic_period_id) 
WHERE payment_status IN ('unpaid', 'partially_paid');
```

### 3.3 GIN Index untuk Pencarian Mahasiswa & Katalog
Untuk pencarian instan nama mahasiswa, NIM, judul skripsi, atau nama mata kuliah:

```sql
-- Aktifkan ekstensi pg_trgm untuk pencarian fuzzy / autocomplete
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Indeks GIN pada nama dan NIM mahasiswa
CREATE INDEX idx_students_search_trgm 
ON students USING gin ((nim || ' ' || full_name) gin_trgm_ops);

-- Indeks GIN pada judul skripsi
CREATE INDEX idx_theses_title_trgm 
ON theses USING gin (title gin_trgm_ops);
```

---

## 4. Partisi Tabel Deklaratif (Table Partitioning by Academic Period)

Tabel presensi (`course_attendances`) dan log aktivitas (`activity_logs`) dapat menampung puluhan juta baris dalam beberapa tahun ajaran. Gunakan **Declarative Range/List Partitioning**:

```sql
-- 1. Buat Tabel Master Partisi
CREATE TABLE course_attendances (
    id BIGSERIAL,
    academic_period_id INT NOT NULL,
    class_schedule_id BIGINT NOT NULL,
    student_id BIGINT NOT NULL,
    meeting_number SMALLINT NOT NULL,
    status VARCHAR(20) NOT NULL, -- hadir, izin, sakit, alpa
    check_in_time TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, academic_period_id)
) PARTITION BY LIST (academic_period_id);

-- 2. Buat Partisi per Tahun Ajaran / Semester
CREATE TABLE course_attendances_20251 
PARTITION OF course_attendances 
FOR VALUES IN (20251); -- Semester Ganjil 2025/2026

CREATE TABLE course_attendances_20252 
PARTITION OF course_attendances 
FOR VALUES IN (20252); -- Semester Genap 2025/2026

CREATE TABLE course_attendances_20261 
PARTITION OF course_attendances 
FOR VALUES IN (20261); -- Semester Ganjil 2026/2027
```

---

## 5. Kueri Lanjut: Kalkulasi IPK, IPS & Transkrip Akademik (CTE & Window Functions)

Kalkulasi IPK mahasiswa dengan aturan *mengambil nilai terbaik (highest grade)* untuk mata kuliah yang diulang:

```sql
WITH ranked_grades AS (
    SELECT 
        cg.student_id,
        c.id AS course_id,
        c.code AS course_code,
        c.name AS course_name,
        c.credits,
        cg.grade_point,
        cg.grade_letter,
        -- Urutkan nilai tertinggi mahasiswa untuk mata kuliah yang sama
        ROW_NUMBER() OVER (
            PARTITION BY cg.student_id, c.id 
            ORDER BY cg.grade_point DESC, cg.created_at DESC
        ) AS rank_order
    FROM course_grades cg
    JOIN class_schedules cs ON cs.id = cg.class_schedule_id
    JOIN courses c ON c.id = cs.course_id
    WHERE cg.student_id = :target_student_id
      AND cg.is_published = true
),
best_grades AS (
    -- Ambil hanya nilai terbaik (rank_order = 1)
    SELECT * FROM ranked_grades WHERE rank_order = 1
)
SELECT 
    student_id,
    COUNT(course_id) AS total_courses_passed,
    SUM(credits) AS total_sks_completed,
    ROUND(SUM(grade_point * credits) / NULLIF(SUM(credits), 0), 2) AS calculated_gpa
FROM best_grades
GROUP BY student_id;
```

---

## 6. Audit Trail & Trigger Keamanan Anti-Kecurangan Nilai (Cryptographic Hash Chains)

Setiap modifikasi nilai akhir mata kuliah atau KRS wajib tercatat permanen di tabel audit WORM (*Write Once, Read Many*) yang dilindungi rantai hash kriptografis SHA-256 (`simak_audit_log`):

```sql
-- 1. Tabel Audit Nilai Kriptografis Berantai
CREATE TABLE simak_audit_log (
    id BIGSERIAL PRIMARY KEY,
    table_name VARCHAR(64) NOT NULL,
    record_id BIGINT NOT NULL,
    action VARCHAR(16) NOT NULL, -- 'INSERT', 'UPDATE', 'DELETE'
    old_values JSONB NULL,
    new_values JSONB NULL,
    modified_by BIGINT NULL,
    ip_address INET NULL,
    previous_hash VARCHAR(64) NOT NULL,
    record_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_simak_audit_table_record ON simak_audit_log (table_name, record_id);

-- 2. Trigger Function dengan Proteksi Rantai Hash SHA-256
CREATE OR REPLACE FUNCTION audit_grade_mutation()
RETURNS TRIGGER AS $$
DECLARE
    last_hash VARCHAR(64);
    new_hash VARCHAR(64);
BEGIN
    -- Ambil hash terakhir untuk membentuk rantai hash anti-tamper
    SELECT record_hash INTO last_hash 
    FROM simak_audit_log 
    ORDER BY id DESC LIMIT 1;
    
    IF last_hash IS NULL THEN
        last_hash := 'GENESIS_HASH_SIAKAD_2026';
    END IF;

    -- Hitung hash baru secara deterministik
    new_hash := encode(digest(
        COALESCE(OLD.id::text, '') || 
        COALESCE(OLD.grade_point::text, '') || 
        COALESCE(NEW.grade_point::text, '') || 
        current_setting('app.current_user_id', true) || 
        clock_timestamp()::text || 
        last_hash, 
        'sha256'
    ), 'hex');

    -- Simpan entri audit log yang tidak dapat diubah
    INSERT INTO simak_audit_log (
        table_name,
        record_id,
        action,
        old_values,
        new_values,
        modified_by,
        ip_address,
        previous_hash,
        record_hash,
        created_at
    ) VALUES (
        TG_TABLE_NAME,
        NEW.id,
        TG_OP,
        row_to_json(OLD)::jsonb,
        row_to_json(NEW)::jsonb,
        NULLIF(current_setting('app.current_user_id', true), '')::bigint,
        inet_client_addr(),
        last_hash,
        new_hash,
        clock_timestamp()
    );

    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- 3. Pasang Trigger pada Tabel Penilaian
CREATE TRIGGER trg_track_grade_changes
AFTER UPDATE OR DELETE ON course_grades
FOR EACH ROW
EXECUTE FUNCTION audit_grade_mutation();
```

---

## 7. Tuning Parameter Server PostgreSQL untuk Beban SIAKAD

Rekomendasi konfigurasi `postgresql.conf` (Server RAM 16 GB, 8 vCPU):

```ini
# Memory Configuration
shared_buffers = 4GB                  # 25% dari total RAM
effective_cache_size = 12GB           # 75% dari total RAM
work_mem = 32MB                       # Memori per-sort/hash operation
maintenance_work_mem = 1GB            # Memori untuk CREATE INDEX dan VACUUM

# Concurrency & Connections
max_connections = 250                 # Gunakan PgBouncer di layer aplikasi!
shared_preload_libraries = 'pg_stat_statements'

# Write-Ahead Log (WAL) & Checkpoints
checkpoint_completion_target = 0.9
wal_buffers = 16MB
min_wal_size = 1GB
max_wal_size = 16GB

# Query Planner Cost Constants (NVMe SSD)
random_page_cost = 1.1
effective_io_concurrency = 200
```

---

## 8. Checklist Kualitas Database PostgreSQL Sebelum Rilis

- [ ] **EXPLAIN ANALYZE Verification:** Seluruh kueri pada endpoint KRS dan Dashboard bebas dari *Sequential Scan* pada tabel berukuran > 10.000 baris.
- [ ] **Foreign Key Indexes:** Seluruh kolom foreign key (`*_id`) memiliki indeks pendukung untuk mempercepat join dan mencegah cascade-locking.
- [ ] **Concurrency Safe:** Transaksi pemilihan kelas KRS menggunakan row-level lock atau constraint isolasi yang teruji beban.
- [ ] **Audit Trigger Aktif:** Modifikasi data krusial (nilai, persetujuan KRS, mutasi keuangan) terpasang trigger log audit.
- [ ] **Routine Maintenance:** Tugas berkala `VACUUM ANALYZE` terkonfigurasi pada jam sepi kampus (02:00 dini hari).
