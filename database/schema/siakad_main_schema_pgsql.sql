-- PostgreSQL schema converted from MySQL baseline

CREATE TABLE IF NOT EXISTS cache (
  key varchar(255) NOT NULL,
  value text NOT NULL,
  expiration integer NOT NULL,
  PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS cache_locks (
  key varchar(255) NOT NULL,
  owner varchar(255) NOT NULL,
  expiration integer NOT NULL,
  PRIMARY KEY (key)
);

CREATE TABLE IF NOT EXISTS ekur_bk (
  id_bk SERIAL,
  id_kur bigint NOT NULL,
  kode_bk varchar(20) NOT NULL,
  nama_bk varchar(150) NOT NULL,
  referensi varchar(150) DEFAULT NULL,
  deskripsi_bk text DEFAULT NULL,
  kompetensi varchar(50) NOT NULL DEFAULT 'Utama',
  PRIMARY KEY (id_bk)
);

CREATE TABLE IF NOT EXISTS ekur_cpl (
  id_cpl SERIAL,
  id_kur bigint NOT NULL,
  kode_cpl varchar(20) NOT NULL,
  aspek varchar(50) NOT NULL,
  deskripsi_cpl text NOT NULL,
  referensi varchar(150) DEFAULT NULL,
  PRIMARY KEY (id_cpl)
);

CREATE TABLE IF NOT EXISTS ekur_cpmk (
  id_cpmk BIGSERIAL,
  id_mk_draft bigint NOT NULL,
  kode_cpmk varchar(15) NOT NULL,
  deskripsi_cpmk text NOT NULL,
  PRIMARY KEY (id_cpmk)
);

CREATE TABLE IF NOT EXISTS ekur_draft_mk (
  id_mk_draft SERIAL,
  id_kur bigint NOT NULL,
  kode_mk varchar(30) NOT NULL,
  kode_mk_live varchar(20) DEFAULT NULL,
  nama_mk varchar(150) NOT NULL,
  sks integer NOT NULL,
  sks_teori integer NOT NULL DEFAULT 0,
  sks_praktek integer NOT NULL DEFAULT 0,
  kategori_mk varchar(100) DEFAULT NULL,
  is_capstone varchar(50) NOT NULL DEFAULT 'Non-Capstone Project',
  jenis_mk varchar(100) DEFAULT NULL,
  semester integer DEFAULT NULL,
  prasyarat_mk varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id_mk_draft)
);

CREATE TABLE IF NOT EXISTS ekur_kurikulum (
  id_kur BIGSERIAL,
  nama_kur varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  tahun smallint NOT NULL,
  visi_keilmuan text DEFAULT NULL,
  misi_prodi text DEFAULT NULL,
  status_kur varchar(20) NOT NULL DEFAULT 'Draft',
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_kur)
);

CREATE TABLE IF NOT EXISTS ekur_map_cpl_bk (
  id_cpl integer NOT NULL,
  id_bk integer NOT NULL,
  PRIMARY KEY (id_cpl, id_bk)
);

CREATE TABLE IF NOT EXISTS ekur_map_cpl_pl (
  id_cpl integer NOT NULL,
  id_pl integer NOT NULL,
  PRIMARY KEY (id_cpl, id_pl)
);

CREATE TABLE IF NOT EXISTS ekur_map_cpmk_cpl (
  id_map SERIAL,
  id_cpmk integer NOT NULL,
  id_cpl integer NOT NULL,
  PRIMARY KEY (id_map)
);

CREATE TABLE IF NOT EXISTS ekur_map_mk_bk (
  id_mk_draft integer NOT NULL,
  id_bk integer NOT NULL,
  PRIMARY KEY (id_mk_draft, id_bk)
);

CREATE TABLE IF NOT EXISTS ekur_map_mk_cpl (
  id_mk_draft integer NOT NULL,
  id_cpl integer NOT NULL,
  PRIMARY KEY (id_mk_draft, id_cpl)
);

CREATE TABLE IF NOT EXISTS ekur_map_subcpmk_cpmk (
  id_map BIGSERIAL,
  id_subcpmk bigint NOT NULL,
  id_cpmk bigint NOT NULL,
  PRIMARY KEY (id_map)
);

CREATE TABLE IF NOT EXISTS ekur_penilaian (
  id_penilaian BIGSERIAL,
  id_subcpmk bigint NOT NULL,
  partisipasi decimal(5,2) DEFAULT 0.00,
  project decimal(5,2) DEFAULT 0.00,
  tugas decimal(5,2) DEFAULT 0.00,
  kuis decimal(5,2) DEFAULT 0.00,
  uts decimal(5,2) DEFAULT 0.00,
  uas decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (id_penilaian)
);

CREATE TABLE IF NOT EXISTS ekur_profesi (
  id_profesi BIGSERIAL,
  id_kur bigint NOT NULL,
  nama_profesi varchar(255) NOT NULL,
  referensi varchar(150) DEFAULT NULL,
  PRIMARY KEY (id_profesi)
);

CREATE TABLE IF NOT EXISTS ekur_profil_lulusan (
  id_pl SERIAL,
  id_kur bigint NOT NULL,
  kode_pl varchar(20) NOT NULL,
  profil_lulusan text NOT NULL,
  aspek varchar(50) DEFAULT NULL,
  deskripsi_pl text NOT NULL,
  referensi varchar(150) DEFAULT NULL,
  PRIMARY KEY (id_pl)
);

CREATE TABLE IF NOT EXISTS ekur_rps_kelengkapan (
  id_kelengkapan BIGSERIAL,
  id_mk_draft bigint NOT NULL,
  rumpun_mk varchar(150) DEFAULT NULL,
  deskripsi_singkat text DEFAULT NULL,
  dosen_pengampu text DEFAULT NULL,
  dsn_pengembang varchar(50) DEFAULT NULL,
  koor_rmk varchar(50) DEFAULT NULL,
  koor_prodi varchar(50) DEFAULT NULL,
  wd1 varchar(50) DEFAULT NULL,
  bahan_kajian text DEFAULT NULL,
  pustaka_utama text DEFAULT NULL,
  pustaka_pendukung text DEFAULT NULL,
  PRIMARY KEY (id_kelengkapan),
  CONSTRAINT id_mk_draft UNIQUE (id_mk_draft)
);

CREATE TABLE IF NOT EXISTS ekur_rps_materi (
  id_materi BIGSERIAL,
  id_mk_draft bigint NOT NULL,
  minggu_ke text DEFAULT NULL,
  kemampuan_akhir text DEFAULT NULL,
  indikator text DEFAULT NULL,
  kriteria_teknik text DEFAULT NULL,
  bentuk_pembelajaran text DEFAULT NULL,
  materi_pembelajaran text DEFAULT NULL,
  pustaka_pilihan varchar(100) DEFAULT NULL,
  bobot_penilaian decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (id_materi)
);

CREATE TABLE IF NOT EXISTS ekur_subcpmk (
  id_subcpmk BIGSERIAL,
  id_mk_draft bigint NOT NULL,
  kode_subcpmk varchar(10) NOT NULL,
  deskripsi_subcpmk text NOT NULL,
  PRIMARY KEY (id_subcpmk)
);

CREATE TABLE IF NOT EXISTS eskripsi_bimbingan (
  id_bimbingan varchar(100) NOT NULL,
  id_skripsi varchar(100) NOT NULL,
  id_dosen_tujuan varchar(100) NOT NULL,
  pengirim varchar(50) NOT NULL,
  pesan text NOT NULL,
  url_berkas text DEFAULT NULL,
  waktu_kirim timestamp NOT NULL,
  PRIMARY KEY (id_bimbingan)
);

CREATE TABLE IF NOT EXISTS eskripsi_judul (
  id varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  judul text NOT NULL,
  deskripsi text NOT NULL,
  status integer NOT NULL DEFAULT 0,
  komentar text DEFAULT NULL,
  tanggal_usul date NOT NULL,
  tanggal_verifikasi date DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS eskripsi_notifikasi (
  id char(36) NOT NULL,
  recipient_role varchar(30) NOT NULL,
  recipient_id varchar(100) NOT NULL,
  category varchar(80) NOT NULL,
  title varchar(255) NOT NULL,
  message text NOT NULL,
  url varchar(500) NOT NULL,
  is_read smallint NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL,
  read_at timestamp DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS eskripsi_skripsi (
  id varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  id_proposal varchar(100) NOT NULL,
  judul text NOT NULL,
  pembimbing1 varchar(100) DEFAULT NULL,
  pembimbing2 varchar(100) DEFAULT NULL,
  penguji1 varchar(100) DEFAULT NULL,
  penguji2 varchar(100) DEFAULT NULL,
  penguji3 varchar(100) DEFAULT NULL,
  status integer NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS eskripsi_ujian (
  id_ujian varchar(100) NOT NULL,
  id_skripsi varchar(100) NOT NULL,
  jenis_ujian varchar(10) DEFAULT NULL,
  waktu_ujian timestamp DEFAULT NULL,
  ruang_ujian varchar(100) DEFAULT NULL,
  nilaipembimbing1 integer DEFAULT NULL,
  nilaipembimbing2 integer DEFAULT NULL,
  nilaipenguji1 integer DEFAULT NULL,
  nilaipenguji2 integer DEFAULT NULL,
  nilaipenguji3 integer DEFAULT NULL,
  catatanpembimbing1 text DEFAULT NULL,
  catatanpembimbing2 text DEFAULT NULL,
  catatanpenguji1 text DEFAULT NULL,
  catatanpenguji2 text DEFAULT NULL,
  catatanpenguji3 text DEFAULT NULL,
  accpembimbing1 integer NOT NULL DEFAULT 0,
  accpembimbing2 integer NOT NULL DEFAULT 0,
  accpenguji1 integer NOT NULL DEFAULT 0,
  accpenguji2 integer NOT NULL DEFAULT 0,
  accpenguji3 integer NOT NULL DEFAULT 0,
  update_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ujian)
);

CREATE TABLE IF NOT EXISTS etesis_bimbingan (
  id_bimbingan varchar(100) NOT NULL,
  id_skripsi varchar(100) NOT NULL,
  id_dosen_tujuan varchar(100) NOT NULL,
  pengirim varchar(30) NOT NULL,
  pesan text NOT NULL,
  url_berkas varchar(500) DEFAULT NULL,
  waktu_kirim timestamp NOT NULL,
  PRIMARY KEY (id_bimbingan)
);

CREATE TABLE IF NOT EXISTS etesis_judul (
  id varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  judul text NOT NULL,
  deskripsi text NOT NULL,
  status integer NOT NULL,
  komentar text DEFAULT NULL,
  tanggal_usul date NOT NULL,
  tanggal_verifikasi date DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS etesis_notifikasi (
  id char(36) NOT NULL,
  recipient_role varchar(30) NOT NULL,
  recipient_id varchar(100) NOT NULL,
  category varchar(80) NOT NULL,
  title varchar(255) NOT NULL,
  message text NOT NULL,
  url varchar(500) NOT NULL,
  is_read smallint NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL,
  read_at timestamp DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS etesis_skripsi (
  id varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  id_proposal varchar(100) NOT NULL,
  judul text NOT NULL,
  pembimbing1 varchar(100) DEFAULT NULL,
  pembimbing2 varchar(100) DEFAULT NULL,
  penguji1 varchar(100) DEFAULT NULL,
  penguji2 varchar(100) DEFAULT NULL,
  penguji3 varchar(100) DEFAULT NULL,
  status integer NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS etesis_ujian (
  id_ujian varchar(100) NOT NULL,
  id_skripsi varchar(100) NOT NULL,
  jenis_ujian varchar(50) DEFAULT NULL,
  waktu_ujian timestamp DEFAULT NULL,
  ruang_ujian varchar(255) DEFAULT NULL,
  nilaipembimbing1 integer DEFAULT NULL,
  nilaipembimbing2 integer DEFAULT NULL,
  nilaipenguji1 integer DEFAULT NULL,
  nilaipenguji2 integer DEFAULT NULL,
  nilaipenguji3 integer DEFAULT NULL,
  catatanpembimbing1 text DEFAULT NULL,
  catatanpembimbing2 text DEFAULT NULL,
  catatanpenguji1 text DEFAULT NULL,
  catatanpenguji2 text DEFAULT NULL,
  catatanpenguji3 text DEFAULT NULL,
  accpembimbing1 integer NOT NULL DEFAULT 0,
  accpembimbing2 integer NOT NULL DEFAULT 0,
  accpenguji1 integer NOT NULL DEFAULT 0,
  accpenguji2 integer NOT NULL DEFAULT 0,
  accpenguji3 integer NOT NULL DEFAULT 0,
  update_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  create_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ujian)
);

CREATE TABLE IF NOT EXISTS failed_jobs (
  id BIGSERIAL,
  uuid varchar(255) NOT NULL,
  connection text NOT NULL,
  queue text NOT NULL,
  payload text NOT NULL,
  exception text NOT NULL,
  failed_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid)
);

CREATE TABLE IF NOT EXISTS job_batches (
  id varchar(255) NOT NULL,
  name varchar(255) NOT NULL,
  total_jobs integer NOT NULL,
  pending_jobs integer NOT NULL,
  failed_jobs integer NOT NULL,
  failed_job_ids text NOT NULL,
  options text DEFAULT NULL,
  cancelled_at integer DEFAULT NULL,
  created_at integer NOT NULL,
  finished_at integer DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS jobs (
  id BIGSERIAL,
  queue varchar(255) NOT NULL,
  payload text NOT NULL,
  attempts smallint NOT NULL,
  reserved_at integer DEFAULT NULL,
  available_at integer NOT NULL,
  created_at integer NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS keu_billing_ecoll (
  id_billing char(36) NOT NULL,
  nama_bank varchar(50) NOT NULL,
  nama_pembayar varchar(255) NOT NULL,
  nominal_tagihan bigint NOT NULL,
  prefix_transaksi char(3) DEFAULT NULL,
  jenis_tagihan char(1) DEFAULT NULL,
  email_pelanggan varchar(255) DEFAULT NULL,
  telepon_pelanggan varchar(30) DEFAULT NULL,
  kode_pembayaran char(3) DEFAULT NULL,
  flag_pembayaran char(1) DEFAULT NULL,
  nomor_giro varchar(255) DEFAULT NULL,
  deskripsi varchar(100) DEFAULT NULL,
  masa_berlaku_hari smallint DEFAULT NULL,
  id_transaksi varchar(100) NOT NULL,
  nomor_va varchar(50) NOT NULL,
  status_respon varchar(50) DEFAULT NULL,
  pesan_respon text DEFAULT NULL,
  data_respon_json text NOT NULL,
  status_pembayaran varchar(50) NOT NULL DEFAULT 'belum_lunas',
  nominal_terbayar bigint DEFAULT NULL,
  tanggal_pembayaran timestamp DEFAULT NULL,
  terakhir_dicek_pada timestamp DEFAULT NULL,
  data_pengecekan_json text DEFAULT NULL,
  dibuat_oleh varchar(100) NOT NULL,
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_billing),
  CONSTRAINT uq_keu_billing_ecoll_id_transaksi UNIQUE (id_transaksi)
);

CREATE TABLE IF NOT EXISTS lms_absensi (
  id_absensi varchar(36) NOT NULL,
  id_pertemuan varchar(36) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  status_kehadiran varchar(50) NOT NULL DEFAULT 'hadir',
  metode_absen varchar(50) NOT NULL,
  waktu_absen timestamp NOT NULL,
  catatan varchar(1000) DEFAULT NULL,
  id_pencatat varchar(100) DEFAULT NULL,
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_absensi),
  CONSTRAINT lms_absensi_pertemuan_mahasiswa_unique UNIQUE (id_pertemuan, id_mahasiswa)
);

CREATE TABLE IF NOT EXISTS lms_bank_soal (
  id_soal varchar(36) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  id_matkul varchar(100) NOT NULL,
  jenis_soal varchar(50) NOT NULL,
  pertanyaan text NOT NULL,
  opsi_a text DEFAULT NULL,
  opsi_b text DEFAULT NULL,
  opsi_c text DEFAULT NULL,
  opsi_d text DEFAULT NULL,
  opsi_e text DEFAULT NULL,
  kunci_jawaban char(1) DEFAULT NULL,
  aktif smallint NOT NULL DEFAULT 1,
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_soal)
);

CREATE TABLE IF NOT EXISTS lms_detail_hasil_ujian (
  id_detail_hasil varchar(36) NOT NULL,
  id_hasil_ujian varchar(36) NOT NULL,
  id_soal varchar(36) DEFAULT NULL,
  urutan_tampil smallint NOT NULL,
  soal_snapshot text NOT NULL,
  jawaban_mahasiswa text DEFAULT NULL,
  jawaban_benar smallint DEFAULT NULL,
  nilai_jawaban decimal(7,2) DEFAULT NULL,
  dijawab_pada timestamp DEFAULT NULL,
  PRIMARY KEY (id_detail_hasil),
  CONSTRAINT lms_detail_hasil_urutan_unique UNIQUE (id_hasil_ujian, urutan_tampil)
);

CREATE TABLE IF NOT EXISTS lms_diskusi (
  id_diskusi varchar(36) NOT NULL,
  id_jadwal varchar(100) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  judul varchar(255) NOT NULL,
  isi_diskusi text NOT NULL,
  status_diskusi varchar(50) NOT NULL DEFAULT 'terbuka',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_diskusi)
);

CREATE TABLE IF NOT EXISTS lms_hasil_ujian (
  id_hasil_ujian varchar(36) NOT NULL,
  id_ujian varchar(36) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  dimulai_pada timestamp DEFAULT NULL,
  dikumpulkan_pada timestamp DEFAULT NULL,
  nilai_akhir decimal(7,2) DEFAULT NULL,
  komentar_dosen text DEFAULT NULL,
  status_hasil varchar(50) NOT NULL DEFAULT 'berlangsung',
  dinilai_oleh varchar(100) DEFAULT NULL,
  dinilai_pada timestamp DEFAULT NULL,
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_hasil_ujian),
  CONSTRAINT lms_hasil_ujian_mahasiswa_unique UNIQUE (id_ujian, id_mahasiswa)
);

CREATE TABLE IF NOT EXISTS lms_label_materi (
  id_label_materi varchar(36) NOT NULL,
  id_materi varchar(36) NOT NULL,
  pertemuan_ke smallint NOT NULL,
  PRIMARY KEY (id_label_materi),
  CONSTRAINT lms_label_materi_unique UNIQUE (id_materi, pertemuan_ke)
);

CREATE TABLE IF NOT EXISTS lms_materi_kuliah (
  id_materi varchar(36) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  id_matkul varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  judul varchar(255) NOT NULL,
  deskripsi text DEFAULT NULL,
  nama_berkas varchar(255) DEFAULT NULL,
  path_berkas varchar(500) DEFAULT NULL,
  tipe_berkas varchar(100) DEFAULT NULL,
  ukuran_berkas bigint DEFAULT NULL,
  status_publikasi varchar(50) NOT NULL DEFAULT 'terbit',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_materi)
);

CREATE TABLE IF NOT EXISTS lms_pengumpulan_tugas (
  id_pengumpulan varchar(36) NOT NULL,
  id_tugas varchar(36) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  jawaban_teks text DEFAULT NULL,
  tautan_berkas varchar(2048) DEFAULT NULL,
  dikumpulkan_pada timestamp NOT NULL,
  nilai decimal(7,2) DEFAULT NULL,
  komentar_dosen text DEFAULT NULL,
  dinilai_oleh varchar(100) DEFAULT NULL,
  dinilai_pada timestamp DEFAULT NULL,
  status_pengumpulan varchar(50) NOT NULL DEFAULT 'terkumpul',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengumpulan),
  CONSTRAINT lms_pengumpulan_tugas_mahasiswa_unique UNIQUE (id_tugas, id_mahasiswa)
);

CREATE TABLE IF NOT EXISTS lms_pertemuan (
  id_pertemuan varchar(36) NOT NULL,
  id_jadwal varchar(100) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  pertemuan_ke smallint NOT NULL,
  id_materi varchar(36) DEFAULT NULL,
  judul varchar(255) NOT NULL,
  tanggal_kuliah date NOT NULL,
  waktu_mulai time NOT NULL,
  waktu_selesai time NOT NULL,
  absen_dibuka smallint NOT NULL DEFAULT 0,
  tautan_video varchar(2048) DEFAULT NULL,
  status_pertemuan varchar(50) NOT NULL DEFAULT 'terbit',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pertemuan),
  CONSTRAINT lms_pertemuan_jadwal_ke_unique UNIQUE (id_jadwal, pertemuan_ke)
);

CREATE TABLE IF NOT EXISTS lms_pertemuan_materi (
  id_pertemuan_materi varchar(36) NOT NULL,
  id_pertemuan varchar(36) NOT NULL,
  id_materi varchar(36) NOT NULL,
  PRIMARY KEY (id_pertemuan_materi),
  CONSTRAINT lms_pertemuan_materi_unique UNIQUE (id_pertemuan, id_materi)
);

CREATE TABLE IF NOT EXISTS lms_pesan_diskusi (
  id_pesan varchar(36) NOT NULL,
  id_diskusi varchar(36) NOT NULL,
  id_pesan_induk varchar(36) DEFAULT NULL,
  jenis_pengguna varchar(50) NOT NULL,
  id_pengguna varchar(100) NOT NULL,
  isi_pesan text NOT NULL,
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pesan)
);

CREATE TABLE IF NOT EXISTS lms_tugas (
  id_tugas varchar(36) NOT NULL,
  id_jadwal varchar(100) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  judul varchar(255) NOT NULL,
  deskripsi text NOT NULL,
  batas_pengumpulan timestamp NOT NULL,
  status_tugas varchar(50) NOT NULL DEFAULT 'terbit',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_tugas)
);

CREATE TABLE IF NOT EXISTS lms_ujian (
  id_ujian varchar(36) NOT NULL,
  id_pertemuan varchar(36) NOT NULL,
  id_dosen varchar(100) NOT NULL,
  id_matkul varchar(100) NOT NULL,
  judul varchar(255) NOT NULL,
  petunjuk text DEFAULT NULL,
  jenis_ujian varchar(50) NOT NULL,
  sumber_soal varchar(50) NOT NULL DEFAULT 'semua',
  jumlah_soal_disajikan smallint NOT NULL,
  urutan_soal varchar(50) NOT NULL DEFAULT 'acak',
  acak_pilihan_jawaban smallint NOT NULL DEFAULT 0,
  durasi_menit smallint NOT NULL,
  batas_mulai timestamp NOT NULL,
  batas_selesai timestamp NOT NULL,
  nilai_per_soal decimal(7,2) DEFAULT NULL,
  status_ujian varchar(50) NOT NULL DEFAULT 'draf',
  dibuat_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diperbarui_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ujian),
  CONSTRAINT lms_ujian_pertemuan_unique UNIQUE (id_pertemuan)
);

CREATE TABLE IF NOT EXISTS lms_ujian_soal (
  id_ujian_soal varchar(36) NOT NULL,
  id_ujian varchar(36) NOT NULL,
  id_soal varchar(36) NOT NULL,
  urutan_tetap smallint DEFAULT NULL,
  ditambahkan_pada timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_ujian_soal),
  CONSTRAINT lms_ujian_soal_unique UNIQUE (id_ujian, id_soal)
);

CREATE TABLE IF NOT EXISTS log_aktivitas (
  id_log varchar(36) NOT NULL,
  id_user varchar(100) DEFAULT NULL,
  level_user varchar(50) DEFAULT NULL,
  nama_user varchar(255) NOT NULL,
  modul varchar(100) NOT NULL,
  aktifitas text NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log)
);

CREATE TABLE IF NOT EXISTS migrations (
  id SERIAL,
  migration varchar(255) NOT NULL,
  batch integer NOT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS s1m4k_admin (
  id_admin BIGSERIAL,
  id_prodi varchar(100) DEFAULT NULL,
  id_fakultas varchar(100) DEFAULT NULL,
  username varchar(100) NOT NULL,
  password varchar(100) NOT NULL,
  passwordb varchar(100) DEFAULT NULL,
  nama_lengkap varchar(100) DEFAULT NULL,
  level varchar(50) DEFAULT NULL,
  no_telp varchar(20) DEFAULT NULL,
  email varchar(60) DEFAULT NULL,
  blokir varchar(50) NOT NULL DEFAULT 'N',
  id_session varchar(100) DEFAULT NULL,
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_admin)
);

CREATE TABLE IF NOT EXISTS s1m4k_akd (
  id_akd varchar(100) NOT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_dosen varchar(100) DEFAULT NULL,
  id_jadwal varchar(100) DEFAULT NULL,
  id_kalender varchar(100) DEFAULT NULL,
  tahun varchar(5) DEFAULT NULL,
  rencana varchar(2) DEFAULT NULL,
  realisasi varchar(2) DEFAULT NULL,
  ket varchar(10) DEFAULT NULL,
  PRIMARY KEY (id_akd)
);

CREATE TABLE IF NOT EXISTS s1m4k_akm (
  id_ipmhs varchar(50) NOT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  id_mahasiswa varchar(50) DEFAULT NULL,
  id_kalender varchar(50) DEFAULT NULL,
  tahun varchar(5) DEFAULT NULL,
  ipk varchar(4) DEFAULT NULL,
  ips varchar(4) DEFAULT NULL,
  status_mahasiswa varchar(50) DEFAULT NULL,
  sks_program varchar(10) DEFAULT NULL,
  sks_semester varchar(10) DEFAULT NULL,
  sks_lulus varchar(10) DEFAULT NULL,
  maks_sks_program varchar(10) DEFAULT NULL,
  no_ijazah varchar(50) DEFAULT NULL,
  tgl_keluar date DEFAULT NULL,
  ket text DEFAULT NULL,
  no_sk varchar(50) DEFAULT NULL,
  tgl_sk date DEFAULT NULL,
  update_akm integer NOT NULL DEFAULT 0,
  id_krs text DEFAULT NULL,
  simpan integer DEFAULT 0,
  flag_feeder integer NOT NULL DEFAULT 0,
  PRIMARY KEY (id_ipmhs)
);

CREATE TABLE IF NOT EXISTS s1m4k_billing (
  id_billing char(50) NOT NULL,
  id_mahasiswa char(50) NOT NULL,
  trx_id char(20) NOT NULL,
  billing char(20) NOT NULL,
  nominal integer NOT NULL,
  expire timestamp NOT NULL,
  tahun integer NOT NULL,
  PRIMARY KEY (id_billing)
);

CREATE TABLE IF NOT EXISTS s1m4k_camaba (
  id varchar(100) NOT NULL,
  id_perguruan_tinggi varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  no_peserta varchar(25) NOT NULL,
  nim varchar(24) NOT NULL,
  nama_mahasiswa varchar(100) NOT NULL,
  jenis_kelamin varchar(1) NOT NULL,
  tempat_lahir varchar(100) DEFAULT NULL,
  tanggal_lahir varchar(50) NOT NULL,
  nama_ibu varchar(100) DEFAULT NULL,
  id_agama varchar(11) DEFAULT NULL,
  id_negara varchar(3) DEFAULT NULL,
  nik varchar(16) DEFAULT NULL,
  nisn varchar(50) DEFAULT NULL,
  telepon varchar(20) DEFAULT NULL,
  handphone varchar(20) DEFAULT NULL,
  kelurahan varchar(100) DEFAULT NULL,
  email varchar(60) NOT NULL,
  penerima_kps varchar(1) NOT NULL,
  id_jenis_daftar varchar(2) DEFAULT NULL,
  id_jalur_daftar varchar(4) DEFAULT NULL,
  id_periode_masuk varchar(5) NOT NULL,
  tanggal_daftar varchar(15) NOT NULL,
  id_pembiayaan varchar(50) DEFAULT NULL,
  biaya_masuk_kuliah integer DEFAULT NULL,
  id_kebutuhan_khusus_mahasiswa varchar(11) DEFAULT NULL,
  status integer DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS s1m4k_edom (
  id_edom varchar(100) NOT NULL,
  id_krs varchar(100) NOT NULL,
  id_jadwal varchar(100) NOT NULL,
  id_kalender varchar(100) NOT NULL,
  id_survey varchar(100) NOT NULL,
  nilai integer NOT NULL,
  PRIMARY KEY (id_edom)
);

CREATE TABLE IF NOT EXISTS s1m4k_export (
  id_export varchar(100) NOT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_kurikulum varchar(100) DEFAULT NULL,
  id_kalender varchar(100) DEFAULT NULL,
  id_semester varchar(100) DEFAULT NULL,
  id_data varchar(100) DEFAULT NULL,
  data varchar(100) DEFAULT NULL,
  status varchar(50) NOT NULL,
  waktu varchar(20) DEFAULT NULL,
  PRIMARY KEY (id_export)
);

CREATE TABLE IF NOT EXISTS s1m4k_export_detail (
  id_export_detail char(36) NOT NULL,
  id_export char(36) NOT NULL,
  source_id varchar(191) NOT NULL,
  status varchar(50) NOT NULL DEFAULT 'N',
  payload_hash char(64) DEFAULT NULL,
  payload_json text DEFAULT NULL,
  error_code varchar(100) DEFAULT NULL,
  error_desc text DEFAULT NULL,
  attempts integer NOT NULL DEFAULT 0,
  last_attempt_at timestamp DEFAULT NULL,
  succeeded_at timestamp DEFAULT NULL,
  created_at timestamp DEFAULT NULL,
  updated_at timestamp DEFAULT NULL,
  PRIMARY KEY (id_export_detail),
  CONSTRAINT s1m4k_export_detail_source_unique UNIQUE (id_export, source_id)
);

CREATE TABLE IF NOT EXISTS s1m4k_import (
  id SERIAL,
  id_prodi varchar(100) DEFAULT NULL,
  jum_dosen integer DEFAULT NULL,
  status_dosen varchar(50) DEFAULT '-',
  jum_mhs integer DEFAULT NULL,
  status_mhs varchar(50) DEFAULT '-',
  jum_mk integer DEFAULT NULL,
  status_mk varchar(50) DEFAULT '-',
  jum_ruang integer DEFAULT NULL,
  status_ruang varchar(50) DEFAULT '-',
  jum_asdos integer DEFAULT NULL,
  status_asdos varchar(50) DEFAULT '-',
  jum_kal integer DEFAULT NULL,
  status_kal varchar(50) DEFAULT '-',
  jum_jadwal integer DEFAULT NULL,
  status_jadwal varchar(50) DEFAULT '-',
  ket_jad text DEFAULT NULL,
  jum_krs integer DEFAULT NULL,
  status_krs varchar(50) DEFAULT '-',
  ket_krs text DEFAULT NULL,
  ket text DEFAULT NULL,
  w_dosen varchar(20) DEFAULT NULL,
  w_mhs varchar(20) DEFAULT NULL,
  w_mk varchar(20) DEFAULT NULL,
  w_ruang varchar(20) DEFAULT NULL,
  w_asdos varchar(20) DEFAULT NULL,
  w_kal varchar(20) DEFAULT NULL,
  w_jad varchar(20) DEFAULT NULL,
  w_krs varchar(20) DEFAULT NULL,
  jum_kur varchar(10) DEFAULT NULL,
  status_kur varchar(20) DEFAULT NULL,
  w_kur varchar(20) DEFAULT NULL,
  jum_reg varchar(20) DEFAULT NULL,
  status_reg varchar(20) DEFAULT NULL,
  ket_reg text DEFAULT NULL,
  w_reg varchar(20) DEFAULT NULL,
  tot_krs varchar(11) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS s1m4k_jadwal (
  id_jadwal varchar(50) NOT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  id_kalender varchar(50) DEFAULT NULL,
  id_matkul varchar(50) DEFAULT NULL,
  dosen1 varchar(50) DEFAULT NULL,
  dosen2 varchar(50) DEFAULT NULL,
  dosen3 varchar(50) DEFAULT NULL,
  dosen4 varchar(50) DEFAULT NULL,
  dosen5 varchar(50) DEFAULT NULL,
  dosen6 varchar(50) DEFAULT NULL,
  dosen7 varchar(50) DEFAULT NULL,
  dosen8 varchar(100) DEFAULT NULL,
  dosen9 varchar(100) DEFAULT NULL,
  dosen10 varchar(100) DEFAULT NULL,
  dosen11 varchar(100) DEFAULT NULL,
  dosen12 varchar(100) DEFAULT NULL,
  dosen13 varchar(100) DEFAULT NULL,
  dosen14 varchar(100) DEFAULT NULL,
  id_ruang varchar(50) DEFAULT NULL,
  nama_kelas varchar(25) DEFAULT NULL,
  hari varchar(10) DEFAULT NULL,
  jammulai time DEFAULT NULL,
  jamselesai time DEFAULT NULL,
  idjadwal varchar(15) DEFAULT NULL,
  id_kelas_kuliah varchar(50) DEFAULT NULL,
  kampus_merdeka char(1) NOT NULL DEFAULT '0',
  lingkup_km varchar(2) DEFAULT NULL,
  mode_km varchar(2) DEFAULT NULL,
  tgl_tutup_daftar_km varchar(15) DEFAULT NULL,
  jml_mahasiswa integer NOT NULL DEFAULT 0,
  input_nilai varchar(50) NOT NULL DEFAULT 'N',
  PRIMARY KEY (id_jadwal)
);

CREATE TABLE IF NOT EXISTS s1m4k_jadwal_pengajar_feeder (
  id_jadwal varchar(50) NOT NULL,
  id_dosen varchar(50) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  error_desc text DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id_jadwal, id_dosen)
);

CREATE TABLE IF NOT EXISTS s1m4k_kalender (
  id_kalender varchar(50) NOT NULL,
  periode varchar(20) DEFAULT NULL,
  tahun varchar(5) DEFAULT NULL,
  namatahun varchar(100) DEFAULT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  tgl_mulai_krs date DEFAULT NULL,
  tgl_akhir_krs date DEFAULT NULL,
  tgl_mulai_isinilai date DEFAULT NULL,
  tgl_akhir_isinilai date DEFAULT NULL,
  status varchar(50) DEFAULT 'N',
  status_sp char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (id_kalender)
);

CREATE TABLE IF NOT EXISTS s1m4k_kegiatanmhs (
  id_kegiatan varchar(100) NOT NULL,
  id_kalender varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_mahasiswa varchar(100) DEFAULT NULL,
  jenis_kegiatan varchar(2) DEFAULT NULL,
  judul text DEFAULT NULL,
  lokasi varchar(100) DEFAULT NULL,
  no_sk varchar(50) DEFAULT NULL,
  tgl_sk date DEFAULT NULL,
  jenis_anggota varchar(1) DEFAULT NULL,
  program_mbkm smallint DEFAULT NULL,
  ket text DEFAULT NULL,
  PRIMARY KEY (id_kegiatan)
);

CREATE TABLE IF NOT EXISTS s1m4k_kodereferensi (
  id_referensi varchar(100) NOT NULL,
  kode_referensi varchar(10) DEFAULT NULL,
  ket_referensi text DEFAULT NULL,
  ket_referensi2 varchar(100) DEFAULT NULL,
  keterangan varchar(50) DEFAULT NULL,
  lat decimal(10,7) DEFAULT NULL,
  lng decimal(10,7) DEFAULT NULL,
  nama_provinsi varchar(200) DEFAULT NULL,
  lat_provinsi decimal(10,7) DEFAULT NULL,
  lng_provinsi decimal(10,7) DEFAULT NULL,
  untuk_kampus_merdeka char(1) DEFAULT NULL,
  PRIMARY KEY (id_referensi)
);

CREATE TABLE IF NOT EXISTS s1m4k_krs (
  id_krs varchar(50) NOT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  id_kalender varchar(50) DEFAULT NULL,
  tahun varchar(5) DEFAULT NULL,
  id_mahasiswa varchar(50) DEFAULT NULL,
  id_jadwal varchar(50) DEFAULT NULL,
  id_matkul varchar(50) DEFAULT NULL,
  status_krs varchar(50) DEFAULT NULL,
  nilai_partisipatif decimal(5,2) DEFAULT 0.00,
  nilai_proyek decimal(5,2) DEFAULT 0.00,
  nilai_quiz decimal(5,2) DEFAULT 0.00,
  nilai_tugas decimal(5,2) DEFAULT 0.00,
  nilai_uts decimal(5,2) DEFAULT 0.00,
  nilai_uas decimal(5,2) DEFAULT 0.00,
  nilai_angka decimal(5,2) DEFAULT 0.00,
  nilai_bobot decimal(4,2) DEFAULT 0.00,
  nilai_huruf varchar(2) DEFAULT 'E',
  input_nilai varchar(50) DEFAULT 'N',
  krs_reguler varchar(50) DEFAULT 'Y',
  id_pt_asal varchar(100) DEFAULT NULL,
  kode_mk_asal varchar(200) DEFAULT NULL,
  nama_mk_asal varchar(200) DEFAULT NULL,
  sks_asal varchar(4) DEFAULT NULL,
  nilai_huruf_asal varchar(4) DEFAULT NULL,
  tgl_update date DEFAULT NULL,
  "user" varchar(50) DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  id_trnlm varchar(20) DEFAULT NULL,
  id_mahasiswa_old varchar(50) DEFAULT NULL,
  id_kegiatan varchar(100) DEFAULT NULL,
  krs_mbkm varchar(50) NOT NULL DEFAULT 'N',
  isi_edom varchar(50) NOT NULL DEFAULT 'N',
  PRIMARY KEY (id_krs)
);

CREATE TABLE IF NOT EXISTS s1m4k_kubermas (
  id_peserta varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  id_lokasi varchar(100) DEFAULT NULL,
  keterangan text DEFAULT NULL,
  tahun integer NOT NULL,
  status integer NOT NULL DEFAULT 0,
  tgl_daftar date NOT NULL,
  petugas varchar(100) DEFAULT NULL,
  petugas_usul varchar(100) DEFAULT NULL,
  PRIMARY KEY (id_peserta)
);

CREATE TABLE IF NOT EXISTS s1m4k_kurikulum (
  id_kurikulum varchar(100) NOT NULL,
  nama_kurikulum varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_semester varchar(5) DEFAULT NULL,
  semester_mulai_berlaku varchar(50) DEFAULT NULL,
  jumlah_sks_lulus varchar(10) DEFAULT NULL,
  jumlah_sks_wajib varchar(10) DEFAULT NULL,
  jumlah_sks_pilihan varchar(10) DEFAULT NULL,
  jumlah_sks_mata_kuliah_wajib varchar(10) DEFAULT NULL,
  jumlah_sks_mata_kuliah_pilihan varchar(10) DEFAULT NULL,
  PRIMARY KEY (id_kurikulum)
);

CREATE TABLE IF NOT EXISTS s1m4k_log_device (
  id_log SERIAL,
  id_mahasiswa varchar(50) NOT NULL,
  nim varchar(30) NOT NULL,
  id_prodi varchar(50) NOT NULL,
  nominal_ukt double precision NOT NULL DEFAULT 0,
  kategori_ukt varchar(10) NOT NULL,
  jenis_perangkat varchar(50) NOT NULL,
  raw_user_agent text NOT NULL,
  waktu_akses timestamp NOT NULL,
  PRIMARY KEY (id_log),
  CONSTRAINT mhs_perangkat_unik UNIQUE (id_mahasiswa, jenis_perangkat)
);

CREATE TABLE IF NOT EXISTS s1m4k_lokasi_kubermas (
  id_lokasi varchar(100) NOT NULL,
  nama_lokasi varchar(200) NOT NULL,
  kuota integer NOT NULL,
  aktif varchar(50) NOT NULL DEFAULT 'Y',
  PRIMARY KEY (id_lokasi)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterallprodi (
  id_perguruan_tinggi varchar(100) NOT NULL,
  kode_perguruan_tinggi varchar(20) DEFAULT NULL,
  id_prodi varchar(100) NOT NULL DEFAULT '',
  kode_program_studi varchar(20) DEFAULT NULL,
  nama_program_studi varchar(100) DEFAULT NULL,
  status varchar(10) DEFAULT NULL,
  id_jenjang_pendidikan varchar(10) DEFAULT NULL,
  PRIMARY KEY (id_perguruan_tinggi, id_prodi)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterallpt (
  id_perguruan_tinggi varchar(100) NOT NULL,
  kode_perguruan_tinggi varchar(20) DEFAULT NULL,
  nama_perguruan_tinggi varchar(200) DEFAULT NULL,
  nama_singkat varchar(50) DEFAULT NULL,
  PRIMARY KEY (id_perguruan_tinggi)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterasdos (
  id_asdos varchar(100) NOT NULL,
  id_perguruan_tinggi varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_fakultas varchar(100) DEFAULT NULL,
  kdasisten varchar(10) DEFAULT NULL,
  nama varchar(100) DEFAULT NULL,
  jkel varchar(1) DEFAULT NULL,
  tplahir varchar(50) DEFAULT NULL,
  tgllahir date DEFAULT NULL,
  password varchar(100) DEFAULT NULL,
  passwordb varchar(100) DEFAULT NULL,
  notlp varchar(15) DEFAULT NULL,
  email varchar(60) DEFAULT NULL,
  gelard varchar(25) DEFAULT NULL,
  gelarb varchar(25) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  PRIMARY KEY (id_asdos)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterbobotnilai (
  id_bobot_nilai varchar(100) NOT NULL,
  id_perguruan_tinggi varchar(100) DEFAULT NULL,
  nilai_huruf varchar(3) DEFAULT NULL,
  nilai_indeks varchar(6) DEFAULT NULL,
  bobot_minimum varchar(6) DEFAULT NULL,
  bobot_maksimum varchar(6) DEFAULT NULL,
  tanggal_mulai_efektif date DEFAULT NULL,
  tanggal_akhir_efektif date DEFAULT NULL,
  status varchar(50) DEFAULT 'Y',
  flag_feeder integer DEFAULT 0,
  PRIMARY KEY (id_bobot_nilai)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterdosen (
  id_dosen varchar(100) NOT NULL,
  id_registrasi_dosen varchar(100) DEFAULT NULL,
  id_perguruan_tinggi varchar(100) DEFAULT NULL,
  id_fakultas varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_tahun_ajaran varchar(10) DEFAULT NULL,
  nama_dosen varchar(100) DEFAULT NULL,
  gelar_depan varchar(20) DEFAULT NULL,
  password varchar(100) DEFAULT NULL,
  password_baru varchar(100) DEFAULT NULL,
  gelar_belakang varchar(50) DEFAULT NULL,
  nidn varchar(50) DEFAULT NULL,
  nuptk varchar(50) DEFAULT NULL,
  nip varchar(50) DEFAULT NULL,
  email varchar(50) DEFAULT NULL,
  jenis_kelamin varchar(1) DEFAULT NULL,
  id_agama varchar(2) DEFAULT NULL,
  tanggal_lahir date DEFAULT NULL,
  id_status_aktif varchar(2) DEFAULT NULL,
  nama_status_aktif varchar(100) DEFAULT NULL,
  nomor_surat_tugas varchar(50) DEFAULT NULL,
  tanggal_surat_tugas date DEFAULT NULL,
  mulai_surat_tugas date DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  PRIMARY KEY (id_dosen)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterfakultas (
  id_fakultas varchar(50) NOT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  kode_fakultas varchar(2) DEFAULT NULL,
  nama_fakultas varchar(50) DEFAULT NULL,
  dekan varchar(50) DEFAULT NULL,
  pd1 varchar(50) DEFAULT NULL,
  pd2 varchar(50) DEFAULT NULL,
  pd3 varchar(50) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  PRIMARY KEY (id_fakultas)
);

CREATE TABLE IF NOT EXISTS s1m4k_mastermhs (
  id_mahasiswa varchar(50) NOT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  dosen_pa varchar(50) DEFAULT NULL,
  id_registrasi_mahasiswa varchar(50) DEFAULT NULL,
  id_status_mahasiswa varchar(1) DEFAULT NULL,
  nama_status_mahasiswa varchar(50) DEFAULT NULL,
  nim varchar(24) DEFAULT NULL,
  nama_mahasiswa varchar(50) DEFAULT NULL,
  password varchar(100) DEFAULT NULL,
  password_baru varchar(100) DEFAULT NULL,
  jenis_kelamin varchar(1) DEFAULT NULL,
  jalan text DEFAULT NULL,
  rt varchar(20) DEFAULT NULL,
  rw varchar(20) DEFAULT NULL,
  dusun varchar(60) DEFAULT NULL,
  kelurahan varchar(60) DEFAULT NULL,
  kode_pos varchar(5) DEFAULT NULL,
  nisn varchar(50) DEFAULT NULL,
  nik varchar(16) DEFAULT NULL,
  tempat_lahir varchar(32) DEFAULT NULL,
  tanggal_lahir char(15) DEFAULT NULL,
  nama_ayah varchar(100) DEFAULT NULL,
  tanggal_lahir_ayah char(15) DEFAULT NULL,
  nik_ayah varchar(50) DEFAULT NULL,
  id_pendidikan_ayah varchar(2) DEFAULT NULL,
  id_pekerjaan_ayah varchar(11) DEFAULT NULL,
  id_penghasilan_ayah varchar(11) DEFAULT NULL,
  id_kebutuhan_khusus_ayah varchar(11) DEFAULT NULL,
  nama_ibu varchar(100) DEFAULT NULL,
  tanggal_lahir_ibu char(15) DEFAULT NULL,
  nik_ibu varchar(50) DEFAULT NULL,
  id_pendidikan_ibu varchar(2) DEFAULT NULL,
  id_pekerjaan_ibu varchar(11) DEFAULT NULL,
  id_penghasilan_ibu varchar(11) DEFAULT NULL,
  id_kebutuhan_khusus_ibu varchar(11) DEFAULT NULL,
  nama_wali varchar(100) DEFAULT NULL,
  tanggal_lahir_wali char(15) DEFAULT NULL,
  id_pendidikan_wali varchar(2) DEFAULT NULL,
  id_pekerjaan_wali varchar(11) DEFAULT NULL,
  id_penghasilan_wali varchar(11) DEFAULT NULL,
  id_kebutuhan_khusus_mahasiswa varchar(11) DEFAULT NULL,
  telepon varchar(20) DEFAULT NULL,
  handphone varchar(20) DEFAULT NULL,
  email varchar(60) DEFAULT NULL,
  penerima_kps varchar(1) DEFAULT NULL,
  nomor_kps varchar(80) DEFAULT NULL,
  npwp varchar(100) DEFAULT NULL,
  id_wilayah varchar(8) DEFAULT NULL,
  id_jenis_tinggal varchar(2) DEFAULT NULL,
  id_agama varchar(11) DEFAULT NULL,
  id_alat_transportasi varchar(2) DEFAULT NULL,
  id_negara varchar(3) DEFAULT NULL,
  id_jenis_daftar varchar(2) DEFAULT NULL,
  id_jalur_daftar varchar(4) DEFAULT NULL,
  id_periode_masuk varchar(5) DEFAULT NULL,
  tanggal_daftar char(15) DEFAULT NULL,
  sks_diakui varchar(10) DEFAULT NULL,
  id_perguruan_tinggi_asal varchar(50) DEFAULT NULL,
  id_prodi_asal varchar(50) DEFAULT NULL,
  id_pembiayaan varchar(50) DEFAULT NULL,
  biaya_masuk_kuliah integer DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  kategori_ukt varchar(2) DEFAULT NULL,
  nominal_ukt integer DEFAULT 0,
  update_ukt varchar(50) NOT NULL DEFAULT 'N',
  PRIMARY KEY (id_mahasiswa)
);

CREATE TABLE IF NOT EXISTS s1m4k_mastermk (
  id_matkul varchar(50) NOT NULL,
  kode_mata_kuliah varchar(20) DEFAULT NULL,
  nama_mata_kuliah varchar(150) DEFAULT NULL,
  semester varchar(2) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  id_jenis_mata_kuliah varchar(1) DEFAULT NULL,
  id_kelompok_mata_kuliah varchar(1) DEFAULT NULL,
  sks_mata_kuliah varchar(5) DEFAULT NULL,
  sks_tatap_muka varchar(5) DEFAULT NULL,
  sks_praktek varchar(5) DEFAULT NULL,
  sks_praktek_lapangan varchar(5) DEFAULT NULL,
  sks_simulasi varchar(5) DEFAULT NULL,
  metode_kuliah varchar(50) DEFAULT NULL,
  ada_sap varchar(1) DEFAULT NULL,
  ada_silabus varchar(1) DEFAULT NULL,
  ada_bahan_ajar varchar(1) DEFAULT NULL,
  ada_acara_praktek varchar(1) DEFAULT NULL,
  ada_diktat varchar(1) DEFAULT NULL,
  tanggal_mulai_efektif date DEFAULT NULL,
  tanggal_selesai_efektif date DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  PRIMARY KEY (id_matkul)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterprodi (
  id_prodi varchar(50) NOT NULL,
  kode_program_studi varchar(10) DEFAULT NULL,
  nama_program_studi varchar(50) DEFAULT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  ketua_prodi varchar(50) DEFAULT NULL,
  status varchar(1) DEFAULT NULL,
  id_jenjang_pendidikan varchar(5) DEFAULT NULL,
  nama_jenjang_pendidikan varchar(20) DEFAULT NULL,
  k1 integer NOT NULL,
  k2 integer NOT NULL,
  k3 integer NOT NULL,
  k4 integer NOT NULL,
  k5 integer NOT NULL,
  k6 integer NOT NULL,
  k7 integer NOT NULL,
  k8 integer NOT NULL,
  izin_pendirian varchar(150) DEFAULT NULL,
  peringkat_akreditasi varchar(50) DEFAULT NULL,
  gelar_lulusan varchar(100) DEFAULT NULL,
  visi_keilmuan text DEFAULT NULL,
  misi_prodi text DEFAULT NULL,
  PRIMARY KEY (id_prodi)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterpt (
  id_perguruan_tinggi varchar(100) NOT NULL,
  kode_perguruan_tinggi varchar(8) DEFAULT NULL,
  nama_perguruan_tinggi varchar(100) DEFAULT NULL,
  telepon varchar(20) DEFAULT NULL,
  faximile varchar(20) DEFAULT NULL,
  email varchar(60) DEFAULT NULL,
  website varchar(250) DEFAULT NULL,
  jalan varchar(80) DEFAULT NULL,
  dusun varchar(60) DEFAULT NULL,
  rt_rw varchar(4) DEFAULT NULL,
  kelurahan varchar(60) DEFAULT NULL,
  kode_pos varchar(5) DEFAULT NULL,
  id_wilayah varchar(8) DEFAULT NULL,
  nama_wilayah varchar(60) DEFAULT NULL,
  lintang_bujur varchar(60) DEFAULT NULL,
  bank varchar(50) DEFAULT NULL,
  unit_cabang varchar(60) DEFAULT NULL,
  nomor_rekening varchar(20) DEFAULT NULL,
  mbs varchar(2) DEFAULT NULL,
  luas_tanah_milik varchar(7) DEFAULT NULL,
  luas_tanah_bukan_milik varchar(7) DEFAULT NULL,
  sk_pendirian varchar(80) DEFAULT NULL,
  tanggal_sk_pendirian varchar(10) DEFAULT NULL,
  id_status_milik varchar(2) DEFAULT NULL,
  nama_status_milik varchar(50) DEFAULT NULL,
  status_perguruan_tinggi varchar(1) DEFAULT NULL,
  sk_izin_operasional varchar(80) DEFAULT NULL,
  tanggal_izin_operasional varchar(10) DEFAULT NULL,
  alamat_feeder varchar(200) DEFAULT NULL,
  alamat_wsdl varchar(100) DEFAULT NULL,
  user_feeder varchar(100) DEFAULT NULL,
  password_feeder varchar(100) DEFAULT NULL,
  PRIMARY KEY (id_perguruan_tinggi)
);

CREATE TABLE IF NOT EXISTS s1m4k_masterruang (
  id_ruang varchar(50) NOT NULL,
  nama_ruang varchar(100) DEFAULT NULL,
  id_prodi varchar(50) DEFAULT NULL,
  id_perguruan_tinggi varchar(50) DEFAULT NULL,
  id_fakultas varchar(50) DEFAULT NULL,
  kapasitas varchar(4) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  flag_feeder integer DEFAULT 0,
  PRIMARY KEY (id_ruang)
);

CREATE TABLE IF NOT EXISTS s1m4k_pembayaranipi (
  id varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  nama_bank varchar(100) DEFAULT NULL,
  trx_id varchar(100) DEFAULT NULL,
  no_va varchar(100) DEFAULT NULL,
  nominal integer NOT NULL,
  tgl_expire varchar(100) DEFAULT NULL,
  lunas integer NOT NULL,
  tanggal_bayar varchar(100) DEFAULT NULL,
  petugas varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS s1m4k_pembayaranukt (
  id varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  tahun varchar(5) NOT NULL,
  nama_bank varchar(100) DEFAULT NULL,
  trx_id varchar(100) DEFAULT NULL,
  no_va varchar(100) DEFAULT NULL,
  nominal integer NOT NULL,
  tgl_expire varchar(100) DEFAULT NULL,
  lunas integer NOT NULL,
  tanggal_bayar varchar(100) DEFAULT NULL,
  petugas varchar(100) DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS s1m4k_pengesahankrs (
  id_pengesahan varchar(100) NOT NULL,
  id_mahasiswa varchar(100) DEFAULT NULL,
  id_kalender varchar(100) DEFAULT NULL,
  tgl_pengesahan date DEFAULT NULL,
  yang_mengesahkan varchar(100) DEFAULT NULL,
  tgl_pengesahan_kpro date DEFAULT NULL,
  nama_kpro varchar(255) DEFAULT NULL,
  PRIMARY KEY (id_pengesahan)
);

CREATE TABLE IF NOT EXISTS s1m4k_peran_kegiatan (
  id_peran varchar(100) NOT NULL,
  id_anggota varchar(100) DEFAULT NULL,
  id_bimbing_mahasiswa varchar(100) DEFAULT NULL,
  id_uji varchar(100) DEFAULT NULL,
  id_aktivitas varchar(100) DEFAULT NULL,
  id_registrasi_mahasiswa varchar(100) DEFAULT NULL,
  id_kategori varchar(10) DEFAULT NULL,
  id_dosen varchar(100) DEFAULT NULL,
  jenis_peran varchar(1) DEFAULT NULL,
  pembimbing_ke varchar(1) DEFAULT NULL,
  penguji_ke varchar(1) DEFAULT NULL,
  status varchar(20) DEFAULT NULL,
  PRIMARY KEY (id_peran)
);

CREATE TABLE IF NOT EXISTS s1m4k_periode (
  id_periode varchar(100) NOT NULL,
  nama_periode varchar(255) NOT NULL,
  tahun varchar(5) DEFAULT NULL,
  tanggal_mulai date NOT NULL,
  tanggal_akhir date NOT NULL,
  jenis varchar(100) NOT NULL,
  kuota_wisuda integer DEFAULT NULL,
  aktif varchar(50) NOT NULL DEFAULT 'N',
  PRIMARY KEY (id_periode)
);

CREATE TABLE IF NOT EXISTS s1m4k_registrasiulang (
  id_registrasi varchar(100) NOT NULL,
  id_perguruan_tinggi varchar(100) DEFAULT NULL,
  id_fakultas varchar(100) DEFAULT NULL,
  id_prodi varchar(100) DEFAULT NULL,
  id_mahasiswa varchar(100) DEFAULT NULL,
  id_kalender varchar(100) DEFAULT NULL,
  tgl_registasi date DEFAULT NULL,
  "user" varchar(100) DEFAULT NULL,
  PRIMARY KEY (id_registrasi)
);

CREATE TABLE IF NOT EXISTS s1m4k_survey_edom (
  id_survey SERIAL,
  pertanyaan text NOT NULL,
  PRIMARY KEY (id_survey)
);

CREATE TABLE IF NOT EXISTS s1m4k_wisuda (
  id_peserta varchar(100) NOT NULL,
  id_periode varchar(100) NOT NULL,
  id_prodi varchar(100) NOT NULL,
  id_fakultas varchar(100) NOT NULL,
  id_mahasiswa varchar(100) NOT NULL,
  foto varchar(255) DEFAULT NULL,
  bebas_spp varchar(255) DEFAULT NULL,
  bebas_perpus varchar(255) DEFAULT NULL,
  judul_skripsi text DEFAULT NULL,
  skripsi varchar(255) DEFAULT NULL,
  status integer NOT NULL DEFAULT 0,
  tgl_daftar date DEFAULT NULL,
  petugas varchar(100) DEFAULT NULL,
  lolos varchar(1) DEFAULT NULL,
  komentar text DEFAULT NULL,
  pernyataan integer DEFAULT 0,
  PRIMARY KEY (id_peserta)
);

CREATE TABLE IF NOT EXISTS simak_activity_log_reads (
  recipient_role VARCHAR(30) NOT NULL,
  recipient_id VARCHAR(100) NOT NULL,
  activity_id CHAR(36) NOT NULL,
  read_at timestamp NOT NULL,
  PRIMARY KEY (recipient_role, recipient_id, activity_id)
);

CREATE TABLE IF NOT EXISTS simak_hero_images (
  slot smallint NOT NULL,
  image_path VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (slot)
);

CREATE TABLE IF NOT EXISTS simak_mail_settings (
  id smallint NOT NULL,
  enabled smallint NOT NULL DEFAULT 0,
  scheme VARCHAR(20) NOT NULL DEFAULT 'smtps',
  host VARCHAR(255) DEFAULT NULL,
  port smallint DEFAULT NULL,
  username VARCHAR(255) DEFAULT NULL,
  password_encrypted TEXT DEFAULT NULL,
  from_address VARCHAR(255) DEFAULT NULL,
  from_name VARCHAR(255) DEFAULT NULL,
  last_tested_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS simak_payment_gateways (
  gateway VARCHAR(40) NOT NULL,
  enabled smallint NOT NULL DEFAULT 0,
  environment VARCHAR(20) NOT NULL DEFAULT 'sandbox',
  client_key VARCHAR(255) DEFAULT NULL,
  service_fee bigint NOT NULL DEFAULT 0,
  credentials_encrypted TEXT DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (gateway)
);

CREATE TABLE IF NOT EXISTS simak_payment_transactions (
  id CHAR(36) NOT NULL,
  gateway VARCHAR(40) NOT NULL,
  payment_type VARCHAR(10) NOT NULL,
  payment_id VARCHAR(100) NOT NULL,
  student_id VARCHAR(100) NOT NULL,
  order_id VARCHAR(100) NOT NULL,
  gross_amount bigint NOT NULL,
  service_fee bigint NOT NULL DEFAULT 0,
  transaction_status VARCHAR(40) NOT NULL DEFAULT 'pending',
  transaction_id VARCHAR(100) DEFAULT NULL,
  snap_token VARCHAR(255) DEFAULT NULL,
  redirect_url VARCHAR(1000) DEFAULT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  notification_received_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  CONSTRAINT simak_payment_transactions_order_id_unique UNIQUE (order_id)
);

CREATE TABLE IF NOT EXISTS simak_manual_bills (
  id CHAR(36) NOT NULL,
  description VARCHAR(255) NOT NULL,
  payer_name VARCHAR(100) NOT NULL,
  payer_email VARCHAR(255) DEFAULT NULL,
  payer_phone VARCHAR(30) DEFAULT NULL,
  amount bigint NOT NULL,
  expires_at TIMESTAMP NULL DEFAULT NULL,
  checkout_status VARCHAR(20) NOT NULL DEFAULT 'creating',
  created_by VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS simak_site_settings (
  id smallint NOT NULL,
  ministry_name VARCHAR(255) NOT NULL DEFAULT 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS DAN TEKNOLOGI',
  motto VARCHAR(255) NOT NULL DEFAULT 'Motto Perguruan Tinggi',
  primary_color CHAR(7) NOT NULL DEFAULT '#2167a5',
  logo_path VARCHAR(500) DEFAULT NULL,
  preloader_logo_path VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS validasi_dok (
  id varchar(100) NOT NULL,
  id_dokumen varchar(100) NOT NULL,
  jenis_dokumen varchar(50) NOT NULL,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- Indexes
CREATE INDEX IF NOT EXISTS idx_ekur_bk_fk_bk_kurikulum ON ekur_bk (id_kur);
CREATE INDEX IF NOT EXISTS idx_ekur_cpl_fk_cpl_kurikulum ON ekur_cpl (id_kur);
CREATE INDEX IF NOT EXISTS idx_ekur_cpmk_id_mk_draft ON ekur_cpmk (id_mk_draft);
CREATE INDEX IF NOT EXISTS idx_ekur_draft_mk_fk_mk_kurikulum ON ekur_draft_mk (id_kur);
CREATE INDEX IF NOT EXISTS idx_ekur_kurikulum_idx_prodi_tahun ON ekur_kurikulum (id_prodi, tahun);
CREATE INDEX IF NOT EXISTS idx_ekur_map_cpl_bk_fk_map_bk ON ekur_map_cpl_bk (id_bk);
CREATE INDEX IF NOT EXISTS idx_ekur_map_cpl_pl_fk_map_pl ON ekur_map_cpl_pl (id_pl);
CREATE INDEX IF NOT EXISTS idx_ekur_map_cpmk_cpl_id_cpmk ON ekur_map_cpmk_cpl (id_cpmk);
CREATE INDEX IF NOT EXISTS idx_ekur_map_cpmk_cpl_id_cpl ON ekur_map_cpmk_cpl (id_cpl);
CREATE INDEX IF NOT EXISTS idx_ekur_map_mk_bk_fk_map_bk2 ON ekur_map_mk_bk (id_bk);
CREATE INDEX IF NOT EXISTS idx_ekur_map_mk_cpl_fk_map_cpl3 ON ekur_map_mk_cpl (id_cpl);
CREATE INDEX IF NOT EXISTS idx_ekur_map_subcpmk_cpmk_id_subcpmk ON ekur_map_subcpmk_cpmk (id_subcpmk);
CREATE INDEX IF NOT EXISTS idx_ekur_map_subcpmk_cpmk_id_cpmk ON ekur_map_subcpmk_cpmk (id_cpmk);
CREATE INDEX IF NOT EXISTS idx_ekur_penilaian_id_subcpmk ON ekur_penilaian (id_subcpmk);
CREATE INDEX IF NOT EXISTS idx_ekur_profesi_fk_profesi_kur ON ekur_profesi (id_kur);
CREATE INDEX IF NOT EXISTS idx_ekur_profil_lulusan_fk_pl_kurikulum ON ekur_profil_lulusan (id_kur);
CREATE INDEX IF NOT EXISTS idx_ekur_rps_materi_id_mk_draft ON ekur_rps_materi (id_mk_draft);
CREATE INDEX IF NOT EXISTS idx_ekur_subcpmk_id_mk_draft ON ekur_subcpmk (id_mk_draft);
CREATE INDEX IF NOT EXISTS idx_eskripsi_judul_id_fakultas ON eskripsi_judul (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_eskripsi_judul_id_prodi ON eskripsi_judul (id_prodi);
CREATE INDEX IF NOT EXISTS idx_eskripsi_judul_id_mahasiswa ON eskripsi_judul (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_eskripsi_notifikasi_eskripsi_notifikasi_recipient_unread ON eskripsi_notifikasi (recipient_role, recipient_id, is_read, created_at);
CREATE INDEX IF NOT EXISTS idx_eskripsi_notifikasi_eskripsi_notifikasi_recipient_created ON eskripsi_notifikasi (recipient_role, recipient_id, created_at);
CREATE INDEX IF NOT EXISTS idx_eskripsi_skripsi_id_fakultas ON eskripsi_skripsi (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_eskripsi_skripsi_id_prodi ON eskripsi_skripsi (id_prodi);
CREATE INDEX IF NOT EXISTS idx_eskripsi_skripsi_id_mahasiswa ON eskripsi_skripsi (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_etesis_bimbingan_etesis_bimbingan_thesis_time ON etesis_bimbingan (id_skripsi, waktu_kirim);
CREATE INDEX IF NOT EXISTS idx_etesis_bimbingan_etesis_bimbingan_lecturer_time ON etesis_bimbingan (id_dosen_tujuan, waktu_kirim);
CREATE INDEX IF NOT EXISTS idx_etesis_judul_etesis_judul_program_date ON etesis_judul (id_prodi, tanggal_usul);
CREATE INDEX IF NOT EXISTS idx_etesis_judul_etesis_judul_student_status ON etesis_judul (id_mahasiswa, status);
CREATE INDEX IF NOT EXISTS idx_etesis_notifikasi_etesis_notifikasi_recipient_unread ON etesis_notifikasi (recipient_role, recipient_id, is_read, created_at);
CREATE INDEX IF NOT EXISTS idx_etesis_notifikasi_etesis_notifikasi_recipient_created ON etesis_notifikasi (recipient_role, recipient_id, created_at);
CREATE INDEX IF NOT EXISTS idx_etesis_skripsi_etesis_skripsi_program_status ON etesis_skripsi (id_prodi, status);
CREATE INDEX IF NOT EXISTS idx_etesis_skripsi_etesis_skripsi_student ON etesis_skripsi (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_etesis_skripsi_etesis_skripsi_proposal ON etesis_skripsi (id_proposal);
CREATE INDEX IF NOT EXISTS idx_etesis_ujian_etesis_ujian_thesis_type ON etesis_ujian (id_skripsi, jenis_ujian);
CREATE INDEX IF NOT EXISTS idx_jobs_jobs_queue_index ON jobs (queue);
CREATE INDEX IF NOT EXISTS idx_keu_billing_ecoll_idx_keu_billing_ecoll_nomor_va ON keu_billing_ecoll (nomor_va);
CREATE INDEX IF NOT EXISTS idx_keu_billing_ecoll_idx_keu_billing_ecoll_bank_dibuat_pada ON keu_billing_ecoll (nama_bank, dibuat_pada);
CREATE INDEX IF NOT EXISTS idx_keu_billing_ecoll_idx_keu_billing_ecoll_status_pembayaran ON keu_billing_ecoll (status_pembayaran);
CREATE INDEX IF NOT EXISTS idx_keu_billing_ecoll_idx_keu_billing_ecoll_dibuat_oleh ON keu_billing_ecoll (dibuat_oleh);
CREATE INDEX IF NOT EXISTS idx_lms_absensi_lms_absensi_mahasiswa_index ON lms_absensi (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_lms_absensi_lms_absensi_status_index ON lms_absensi (status_kehadiran);
CREATE INDEX IF NOT EXISTS idx_lms_bank_soal_lms_bank_soal_dosen_matkul_index ON lms_bank_soal (id_dosen, id_matkul);
CREATE INDEX IF NOT EXISTS idx_lms_bank_soal_lms_bank_soal_jenis_aktif_index ON lms_bank_soal (jenis_soal, aktif);
CREATE INDEX IF NOT EXISTS idx_lms_detail_hasil_ujian_lms_detail_hasil_soal_index ON lms_detail_hasil_ujian (id_soal);
CREATE INDEX IF NOT EXISTS idx_lms_diskusi_lms_diskusi_jadwal_index ON lms_diskusi (id_jadwal);
CREATE INDEX IF NOT EXISTS idx_lms_diskusi_lms_diskusi_dosen_index ON lms_diskusi (id_dosen);
CREATE INDEX IF NOT EXISTS idx_lms_diskusi_lms_diskusi_status_index ON lms_diskusi (status_diskusi);
CREATE INDEX IF NOT EXISTS idx_lms_hasil_ujian_lms_hasil_ujian_mahasiswa_index ON lms_hasil_ujian (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_lms_hasil_ujian_lms_hasil_ujian_status_index ON lms_hasil_ujian (status_hasil);
CREATE INDEX IF NOT EXISTS idx_lms_label_materi_lms_label_pertemuan_index ON lms_label_materi (pertemuan_ke);
CREATE INDEX IF NOT EXISTS idx_lms_materi_kuliah_lms_materi_dosen_matkul_index ON lms_materi_kuliah (id_dosen, id_matkul);
CREATE INDEX IF NOT EXISTS idx_lms_materi_kuliah_lms_materi_prodi_index ON lms_materi_kuliah (id_prodi);
CREATE INDEX IF NOT EXISTS idx_lms_materi_kuliah_lms_materi_publikasi_index ON lms_materi_kuliah (status_publikasi);
CREATE INDEX IF NOT EXISTS idx_lms_pengumpulan_tugas_lms_pengumpulan_tugas_mahasiswa_index ON lms_pengumpulan_tugas (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_lms_pengumpulan_tugas_lms_pengumpulan_tugas_status_index ON lms_pengumpulan_tugas (status_pengumpulan);
CREATE INDEX IF NOT EXISTS idx_lms_pertemuan_lms_pertemuan_dosen_index ON lms_pertemuan (id_dosen);
CREATE INDEX IF NOT EXISTS idx_lms_pertemuan_lms_pertemuan_tanggal_index ON lms_pertemuan (tanggal_kuliah);
CREATE INDEX IF NOT EXISTS idx_lms_pertemuan_lms_pertemuan_materi_fk ON lms_pertemuan (id_materi);
CREATE INDEX IF NOT EXISTS idx_lms_pertemuan_materi_lms_pertemuan_materi_materi_index ON lms_pertemuan_materi (id_materi);
CREATE INDEX IF NOT EXISTS idx_lms_pesan_diskusi_lms_pesan_diskusi_diskusi_index ON lms_pesan_diskusi (id_diskusi, dibuat_pada);
CREATE INDEX IF NOT EXISTS idx_lms_pesan_diskusi_lms_pesan_diskusi_pengguna_index ON lms_pesan_diskusi (jenis_pengguna, id_pengguna);
CREATE INDEX IF NOT EXISTS idx_lms_pesan_diskusi_lms_pesan_diskusi_induk_fk ON lms_pesan_diskusi (id_pesan_induk);
CREATE INDEX IF NOT EXISTS idx_lms_tugas_lms_tugas_jadwal_index ON lms_tugas (id_jadwal);
CREATE INDEX IF NOT EXISTS idx_lms_tugas_lms_tugas_dosen_index ON lms_tugas (id_dosen);
CREATE INDEX IF NOT EXISTS idx_lms_tugas_lms_tugas_deadline_index ON lms_tugas (batas_pengumpulan);
CREATE INDEX IF NOT EXISTS idx_lms_ujian_lms_ujian_dosen_matkul_index ON lms_ujian (id_dosen, id_matkul);
CREATE INDEX IF NOT EXISTS idx_lms_ujian_lms_ujian_waktu_index ON lms_ujian (batas_mulai, batas_selesai);
CREATE INDEX IF NOT EXISTS idx_lms_ujian_soal_lms_ujian_soal_bank_index ON lms_ujian_soal (id_soal);
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_log_aktivitas_id_user_index ON log_aktivitas (id_user);
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_log_aktivitas_modul_index ON log_aktivitas (modul);
CREATE INDEX IF NOT EXISTS idx_log_aktivitas_log_aktivitas_created_at_index ON log_aktivitas (created_at);
CREATE INDEX IF NOT EXISTS idx_s1m4k_admin_username ON s1m4k_admin (username);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akd_id_prodi ON s1m4k_akd (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akd_id_dosen ON s1m4k_akd (id_dosen);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akd_id_jadwal ON s1m4k_akd (id_jadwal);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akd_id_kalender ON s1m4k_akd (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akd_tahun ON s1m4k_akd (tahun);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akm_id_prodi ON s1m4k_akm (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akm_id_mahasiswa ON s1m4k_akm (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_akm_id_kalender ON s1m4k_akm (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_camaba_id_prodi ON s1m4k_camaba (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_camaba_id_fakultas ON s1m4k_camaba (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_camaba_id_perguruan_tinggi ON s1m4k_camaba (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_edom_id_krs ON s1m4k_edom (id_krs);
CREATE INDEX IF NOT EXISTS idx_s1m4k_edom_id_jadwal ON s1m4k_edom (id_jadwal);
CREATE INDEX IF NOT EXISTS idx_s1m4k_export_detail_s1m4k_export_detail_status_index ON s1m4k_export_detail (id_export, status);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_perguruan_tinggi ON s1m4k_jadwal (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_fakultas ON s1m4k_jadwal (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_prodi ON s1m4k_jadwal (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_kalender ON s1m4k_jadwal (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_matkul ON s1m4k_jadwal (id_matkul);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_dosen1 ON s1m4k_jadwal (dosen1);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_dosen2 ON s1m4k_jadwal (dosen2);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_dosen3 ON s1m4k_jadwal (dosen3);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_id_ruang ON s1m4k_jadwal (id_ruang);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_kampus_merdeka ON s1m4k_jadwal (kampus_merdeka);
CREATE INDEX IF NOT EXISTS idx_s1m4k_jadwal_pengajar_feeder_s1m4k_jadwal_pengajar_feeder_status_id_jadwal_index ON s1m4k_jadwal_pengajar_feeder (status, id_jadwal);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kalender_status ON s1m4k_kalender (status);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kalender_tahun ON s1m4k_kalender (tahun);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kalender_id_perguruan_tinggi ON s1m4k_kalender (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kalender_id_fakultas ON s1m4k_kalender (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kalender_id_prodi ON s1m4k_kalender (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kodereferensi_keterangan ON s1m4k_kodereferensi (keterangan);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kodereferensi_kode_referensi ON s1m4k_kodereferensi (kode_referensi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_perguruan_tinggi ON s1m4k_krs (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_fakultas ON s1m4k_krs (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_prodi ON s1m4k_krs (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_kalender ON s1m4k_krs (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_tahun ON s1m4k_krs (tahun);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_mahasiswa ON s1m4k_krs (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_jadwal ON s1m4k_krs (id_jadwal);
CREATE INDEX IF NOT EXISTS idx_s1m4k_krs_id_matkul ON s1m4k_krs (id_matkul);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kubermas_id_peserta ON s1m4k_kubermas (id_peserta);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kurikulum_id_prodi ON s1m4k_kurikulum (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_kurikulum_id_semester ON s1m4k_kurikulum (id_semester);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterallprodi_id_prodi ON s1m4k_masterallprodi (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterasdos_id_prodi ON s1m4k_masterasdos (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterasdos_kdasisten ON s1m4k_masterasdos (kdasisten);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterdosen_id_prodi ON s1m4k_masterdosen (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterdosen_nidn ON s1m4k_masterdosen (nidn);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterfakultas_id_perguruan_tinggi ON s1m4k_masterfakultas (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterfakultas_pd1 ON s1m4k_masterfakultas (pd1);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterfakultas_pd3 ON s1m4k_masterfakultas (pd3);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterfakultas_pd2 ON s1m4k_masterfakultas (pd2);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermhs_id_prodi ON s1m4k_mastermhs (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermhs_dosen_pa ON s1m4k_mastermhs (dosen_pa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermhs_id_status_mahasiswa ON s1m4k_mastermhs (id_status_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermhs_nama_status_mahasiswa ON s1m4k_mastermhs (nama_status_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermhs_nim ON s1m4k_mastermhs (nim);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_kode_mata_kuliah ON s1m4k_mastermk (kode_mata_kuliah);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_id_perguruan_tinggi ON s1m4k_mastermk (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_id_fakultas ON s1m4k_mastermk (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_id_jenis_mata_kuliah ON s1m4k_mastermk (id_jenis_mata_kuliah);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_id_kelompok_mata_kuliah ON s1m4k_mastermk (id_kelompok_mata_kuliah);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_id_prodi ON s1m4k_mastermk (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_mastermk_status ON s1m4k_mastermk (status);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterprodi_kode_program_studi ON s1m4k_masterprodi (kode_program_studi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterprodi_id_perguruan_tinggi ON s1m4k_masterprodi (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterprodi_id_fakultas ON s1m4k_masterprodi (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterprodi_ketua_prodi ON s1m4k_masterprodi (ketua_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_masterruang_id_prodi ON s1m4k_masterruang (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pengesahankrs_id_kalender ON s1m4k_pengesahankrs (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pengesahankrs_id_mahasiswa ON s1m4k_pengesahankrs (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pengesahankrs_yang_mengesahkan ON s1m4k_pengesahankrs (yang_mengesahkan);
CREATE INDEX IF NOT EXISTS idx_s1m4k_registrasiulang_id_perguruan_tinggi ON s1m4k_registrasiulang (id_perguruan_tinggi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_registrasiulang_id_fakultas ON s1m4k_registrasiulang (id_fakultas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_registrasiulang_id_prodi ON s1m4k_registrasiulang (id_prodi);
CREATE INDEX IF NOT EXISTS idx_s1m4k_registrasiulang_id_mahasiswa ON s1m4k_registrasiulang (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_registrasiulang_id_kalender ON s1m4k_registrasiulang (id_kalender);
CREATE INDEX IF NOT EXISTS idx_s1m4k_wisuda_id_peserta ON s1m4k_wisuda (id_peserta);
CREATE INDEX IF NOT EXISTS idx_simak_activity_log_reads_simak_activity_log_reads_activity ON simak_activity_log_reads (activity_id);
CREATE INDEX IF NOT EXISTS idx_simak_payment_transactions_simak_payment_transactions_payment_lookup ON simak_payment_transactions (payment_type, payment_id, created_at);
CREATE INDEX IF NOT EXISTS idx_simak_payment_transactions_simak_payment_transactions_student_lookup ON simak_payment_transactions (student_id, created_at);
CREATE INDEX IF NOT EXISTS idx_simak_manual_bills_simak_manual_bills_created_at_index ON simak_manual_bills (created_at);

-- Foreign Keys
ALTER TABLE ekur_bk ADD CONSTRAINT fk_bk_kurikulum FOREIGN KEY (id_kur) REFERENCES ekur_kurikulum (id_kur) ON DELETE CASCADE;
ALTER TABLE ekur_cpl ADD CONSTRAINT fk_cpl_kurikulum FOREIGN KEY (id_kur) REFERENCES ekur_kurikulum (id_kur) ON DELETE CASCADE;
ALTER TABLE ekur_draft_mk ADD CONSTRAINT fk_mk_kurikulum FOREIGN KEY (id_kur) REFERENCES ekur_kurikulum (id_kur) ON DELETE CASCADE;
ALTER TABLE ekur_map_cpl_bk ADD CONSTRAINT fk_map_cpl2 FOREIGN KEY (id_cpl) REFERENCES ekur_cpl (id_cpl) ON DELETE CASCADE;
ALTER TABLE ekur_map_cpl_pl ADD CONSTRAINT fk_map_cpl FOREIGN KEY (id_cpl) REFERENCES ekur_cpl (id_cpl) ON DELETE CASCADE;
ALTER TABLE ekur_map_cpl_pl ADD CONSTRAINT fk_map_pl FOREIGN KEY (id_pl) REFERENCES ekur_profil_lulusan (id_pl) ON DELETE CASCADE;
ALTER TABLE ekur_map_mk_bk ADD CONSTRAINT fk_map_bk2 FOREIGN KEY (id_bk) REFERENCES ekur_bk (id_bk) ON DELETE CASCADE;
ALTER TABLE ekur_map_mk_bk ADD CONSTRAINT fk_map_mk FOREIGN KEY (id_mk_draft) REFERENCES ekur_draft_mk (id_mk_draft) ON DELETE CASCADE;
ALTER TABLE ekur_map_mk_cpl ADD CONSTRAINT fk_map_cpl3 FOREIGN KEY (id_cpl) REFERENCES ekur_cpl (id_cpl) ON DELETE CASCADE;
ALTER TABLE ekur_map_mk_cpl ADD CONSTRAINT fk_map_mk2 FOREIGN KEY (id_mk_draft) REFERENCES ekur_draft_mk (id_mk_draft) ON DELETE CASCADE;
ALTER TABLE ekur_profesi ADD CONSTRAINT fk_profesi_kurikulum FOREIGN KEY (id_kur) REFERENCES ekur_kurikulum (id_kur) ON DELETE CASCADE;
ALTER TABLE ekur_profil_lulusan ADD CONSTRAINT fk_pl_kurikulum FOREIGN KEY (id_kur) REFERENCES ekur_kurikulum (id_kur) ON DELETE CASCADE;

-- Indeks performa modul keuangan dan pembayaran
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranukt_id_mahasiswa ON s1m4k_pembayaranukt (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranukt_tahun ON s1m4k_pembayaranukt (tahun);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranukt_lunas ON s1m4k_pembayaranukt (lunas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranukt_trx_id ON s1m4k_pembayaranukt (trx_id);

CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranipi_id_mahasiswa ON s1m4k_pembayaranipi (id_mahasiswa);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranipi_lunas ON s1m4k_pembayaranipi (lunas);
CREATE INDEX IF NOT EXISTS idx_s1m4k_pembayaranipi_trx_id ON s1m4k_pembayaranipi (trx_id);

