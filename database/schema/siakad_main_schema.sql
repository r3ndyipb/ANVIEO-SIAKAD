-- Structure-only Siakad baseline for a new, empty simak_main database.
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ekur_bk` (
  `id_bk` int(11) NOT NULL AUTO_INCREMENT,
  `id_kur` bigint(20) unsigned NOT NULL,
  `kode_bk` varchar(20) NOT NULL,
  `nama_bk` varchar(150) NOT NULL,
  `referensi` varchar(150) DEFAULT NULL,
  `deskripsi_bk` text DEFAULT NULL,
  `kompetensi` enum('Utama','Pendukung','Umum') NOT NULL DEFAULT 'Utama',
  PRIMARY KEY (`id_bk`),
  KEY `fk_bk_kurikulum` (`id_kur`),
  CONSTRAINT `fk_bk_kurikulum` FOREIGN KEY (`id_kur`) REFERENCES `ekur_kurikulum` (`id_kur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_cpl` (
  `id_cpl` int(11) NOT NULL AUTO_INCREMENT,
  `id_kur` bigint(20) unsigned NOT NULL,
  `kode_cpl` varchar(20) NOT NULL,
  `aspek` enum('Sikap','Pengetahuan','Keterampilan Umum','Keterampilan Khusus') NOT NULL,
  `deskripsi_cpl` text NOT NULL,
  `referensi` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_cpl`),
  KEY `fk_cpl_kurikulum` (`id_kur`),
  CONSTRAINT `fk_cpl_kurikulum` FOREIGN KEY (`id_kur`) REFERENCES `ekur_kurikulum` (`id_kur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_cpmk` (
  `id_cpmk` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_mk_draft` bigint(20) NOT NULL,
  `kode_cpmk` varchar(15) NOT NULL,
  `deskripsi_cpmk` text NOT NULL,
  PRIMARY KEY (`id_cpmk`),
  KEY `id_mk_draft` (`id_mk_draft`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_draft_mk` (
  `id_mk_draft` int(11) NOT NULL AUTO_INCREMENT,
  `id_kur` bigint(20) unsigned NOT NULL,
  `kode_mk` varchar(30) NOT NULL,
  `kode_mk_live` varchar(20) DEFAULT NULL,
  `nama_mk` varchar(150) NOT NULL,
  `sks` int(2) NOT NULL,
  `sks_teori` int(2) NOT NULL DEFAULT 0,
  `sks_praktek` int(2) NOT NULL DEFAULT 0,
  `kategori_mk` varchar(100) DEFAULT NULL,
  `is_capstone` varchar(50) NOT NULL DEFAULT 'Non-Capstone Project',
  `jenis_mk` varchar(100) DEFAULT NULL,
  `semester` int(2) DEFAULT NULL,
  `prasyarat_mk` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_mk_draft`),
  KEY `fk_mk_kurikulum` (`id_kur`),
  CONSTRAINT `fk_mk_kurikulum` FOREIGN KEY (`id_kur`) REFERENCES `ekur_kurikulum` (`id_kur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_kurikulum` (
  `id_kur` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nama_kur` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `tahun` year(4) NOT NULL,
  `visi_keilmuan` text DEFAULT NULL,
  `misi_prodi` text DEFAULT NULL,
  `status_kur` varchar(20) NOT NULL DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_kur`),
  KEY `idx_prodi_tahun` (`id_prodi`,`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_cpl_bk` (
  `id_cpl` int(11) NOT NULL,
  `id_bk` int(11) NOT NULL,
  PRIMARY KEY (`id_cpl`,`id_bk`),
  KEY `fk_map_bk` (`id_bk`),
  CONSTRAINT `fk_map_cpl2` FOREIGN KEY (`id_cpl`) REFERENCES `ekur_cpl` (`id_cpl`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_cpl_pl` (
  `id_cpl` int(11) NOT NULL,
  `id_pl` int(11) NOT NULL,
  PRIMARY KEY (`id_cpl`,`id_pl`),
  KEY `fk_map_pl` (`id_pl`),
  CONSTRAINT `fk_map_cpl` FOREIGN KEY (`id_cpl`) REFERENCES `ekur_cpl` (`id_cpl`) ON DELETE CASCADE,
  CONSTRAINT `fk_map_pl` FOREIGN KEY (`id_pl`) REFERENCES `ekur_profil_lulusan` (`id_pl`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_cpmk_cpl` (
  `id_map` int(11) NOT NULL AUTO_INCREMENT,
  `id_cpmk` int(11) NOT NULL,
  `id_cpl` int(11) NOT NULL,
  PRIMARY KEY (`id_map`),
  KEY `id_cpmk` (`id_cpmk`),
  KEY `id_cpl` (`id_cpl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_mk_bk` (
  `id_mk_draft` int(11) NOT NULL,
  `id_bk` int(11) NOT NULL,
  PRIMARY KEY (`id_mk_draft`,`id_bk`),
  KEY `fk_map_bk2` (`id_bk`),
  CONSTRAINT `fk_map_bk2` FOREIGN KEY (`id_bk`) REFERENCES `ekur_bk` (`id_bk`) ON DELETE CASCADE,
  CONSTRAINT `fk_map_mk` FOREIGN KEY (`id_mk_draft`) REFERENCES `ekur_draft_mk` (`id_mk_draft`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_mk_cpl` (
  `id_mk_draft` int(11) NOT NULL,
  `id_cpl` int(11) NOT NULL,
  PRIMARY KEY (`id_mk_draft`,`id_cpl`),
  KEY `fk_map_cpl3` (`id_cpl`),
  CONSTRAINT `fk_map_cpl3` FOREIGN KEY (`id_cpl`) REFERENCES `ekur_cpl` (`id_cpl`) ON DELETE CASCADE,
  CONSTRAINT `fk_map_mk2` FOREIGN KEY (`id_mk_draft`) REFERENCES `ekur_draft_mk` (`id_mk_draft`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_map_subcpmk_cpmk` (
  `id_map` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_subcpmk` bigint(20) NOT NULL,
  `id_cpmk` bigint(20) NOT NULL,
  PRIMARY KEY (`id_map`),
  KEY `id_subcpmk` (`id_subcpmk`),
  KEY `id_cpmk` (`id_cpmk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_penilaian` (
  `id_penilaian` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_subcpmk` bigint(20) NOT NULL,
  `partisipasi` decimal(5,2) DEFAULT 0.00,
  `project` decimal(5,2) DEFAULT 0.00,
  `tugas` decimal(5,2) DEFAULT 0.00,
  `kuis` decimal(5,2) DEFAULT 0.00,
  `uts` decimal(5,2) DEFAULT 0.00,
  `uas` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id_penilaian`),
  KEY `id_subcpmk` (`id_subcpmk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_profesi` (
  `id_profesi` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_kur` bigint(20) unsigned NOT NULL,
  `nama_profesi` varchar(255) NOT NULL,
  `referensi` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_profesi`),
  KEY `fk_profesi_kur` (`id_kur`),
  CONSTRAINT `fk_profesi_kurikulum` FOREIGN KEY (`id_kur`) REFERENCES `ekur_kurikulum` (`id_kur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_profil_lulusan` (
  `id_pl` int(11) NOT NULL AUTO_INCREMENT,
  `id_kur` bigint(20) unsigned NOT NULL,
  `kode_pl` varchar(20) NOT NULL,
  `profil_lulusan` text NOT NULL,
  `aspek` varchar(50) DEFAULT NULL,
  `deskripsi_pl` text NOT NULL,
  `referensi` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_pl`),
  KEY `fk_pl_kurikulum` (`id_kur`),
  CONSTRAINT `fk_pl_kurikulum` FOREIGN KEY (`id_kur`) REFERENCES `ekur_kurikulum` (`id_kur`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_rps_kelengkapan` (
  `id_kelengkapan` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_mk_draft` bigint(20) NOT NULL,
  `rumpun_mk` varchar(150) DEFAULT NULL,
  `deskripsi_singkat` text DEFAULT NULL,
  `dosen_pengampu` text DEFAULT NULL,
  `dsn_pengembang` varchar(50) DEFAULT NULL,
  `koor_rmk` varchar(50) DEFAULT NULL,
  `koor_prodi` varchar(50) DEFAULT NULL,
  `wd1` varchar(50) DEFAULT NULL,
  `bahan_kajian` text DEFAULT NULL,
  `pustaka_utama` text DEFAULT NULL,
  `pustaka_pendukung` text DEFAULT NULL,
  PRIMARY KEY (`id_kelengkapan`),
  UNIQUE KEY `id_mk_draft` (`id_mk_draft`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_rps_materi` (
  `id_materi` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_mk_draft` bigint(20) NOT NULL,
  `minggu_ke` text DEFAULT NULL,
  `kemampuan_akhir` text DEFAULT NULL,
  `indikator` text DEFAULT NULL,
  `kriteria_teknik` text DEFAULT NULL,
  `bentuk_pembelajaran` text DEFAULT NULL,
  `materi_pembelajaran` text DEFAULT NULL,
  `pustaka_pilihan` varchar(100) DEFAULT NULL,
  `bobot_penilaian` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id_materi`),
  KEY `id_mk_draft` (`id_mk_draft`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ekur_subcpmk` (
  `id_subcpmk` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_mk_draft` bigint(20) NOT NULL COMMENT 'Relasi ke tabel ekur_draft_mk',
  `kode_subcpmk` varchar(10) NOT NULL COMMENT 'Contoh: L1, L2, dst',
  `deskripsi_subcpmk` text NOT NULL,
  PRIMARY KEY (`id_subcpmk`),
  KEY `id_mk_draft` (`id_mk_draft`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `eskripsi_bimbingan` (
  `id_bimbingan` varchar(100) NOT NULL,
  `id_skripsi` varchar(100) NOT NULL,
  `id_dosen_tujuan` varchar(100) NOT NULL COMMENT 'ID Dosen yang diajak bimbingan',
  `pengirim` enum('mahasiswa','dosen') NOT NULL,
  `pesan` text NOT NULL,
  `url_berkas` text DEFAULT NULL COMMENT 'Link Google Drive',
  `waktu_kirim` datetime NOT NULL,
  PRIMARY KEY (`id_bimbingan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `eskripsi_judul` (
  `id` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `judul` text NOT NULL,
  `deskripsi` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0 = belum verifikasi, 1 = proposal diterima, 2=proposal ditolak',
  `komentar` text DEFAULT NULL,
  `tanggal_usul` date NOT NULL,
  `tanggal_verifikasi` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_mahasiswa` (`id_mahasiswa`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `eskripsi_notifikasi` (
  `id` char(36) NOT NULL,
  `recipient_role` varchar(30) NOT NULL,
  `recipient_id` varchar(100) NOT NULL,
  `category` varchar(80) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `url` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `eskripsi_notifikasi_recipient_unread` (`recipient_role`,`recipient_id`,`is_read`,`created_at`),
  KEY `eskripsi_notifikasi_recipient_created` (`recipient_role`,`recipient_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `eskripsi_skripsi` (
  `id` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `id_proposal` varchar(100) NOT NULL,
  `judul` text NOT NULL,
  `pembimbing1` varchar(100) DEFAULT NULL,
  `pembimbing2` varchar(100) DEFAULT NULL,
  `penguji1` varchar(100) DEFAULT NULL,
  `penguji2` varchar(100) DEFAULT NULL,
  `penguji3` varchar(100) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0 = Menunggu Penugasan (Dosen belum ada)\r\n\r\n1 = Membimbing Proposal\r\n\r\n2 = Ujian Proposal (Ter-trigger saat 2 Pembimbing ACC Ujian Proposal)\r\n\r\n3 = Membimbing Hasil (Ter-trigger saat 5 Dosen ACC Revisi Proposal)\r\n\r\n4 = Ujian Hasil (Ter-trigger saat 2 Pembimbing ACC Ujian Hasil)\r\n\r\n5 = Ujian Tutup / Skripsi (Ter-trigger saat 5 Dosen ACC Revisi Hasil)\r\n\r\n6 = Selesai Bimbingan Skripsi (Ter-trigger saat 5 Dosen ACC Revisi Tutup)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_mahasiswa` (`id_mahasiswa`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `eskripsi_ujian` (
  `id_ujian` varchar(100) NOT NULL,
  `id_skripsi` varchar(100) NOT NULL,
  `jenis_ujian` varchar(10) DEFAULT NULL COMMENT 'jenis ujian : proposal, hasil, skripsi',
  `waktu_ujian` datetime DEFAULT NULL COMMENT 'Tanggal dan Jam Pelaksanaan Ujian',
  `ruang_ujian` varchar(100) DEFAULT NULL COMMENT 'Lokasi Ruang Ujian',
  `nilaipembimbing1` int(11) DEFAULT NULL,
  `nilaipembimbing2` int(11) DEFAULT NULL,
  `nilaipenguji1` int(11) DEFAULT NULL,
  `nilaipenguji2` int(11) DEFAULT NULL,
  `nilaipenguji3` int(11) DEFAULT NULL,
  `catatanpembimbing1` text DEFAULT NULL,
  `catatanpembimbing2` text DEFAULT NULL,
  `catatanpenguji1` text DEFAULT NULL,
  `catatanpenguji2` text DEFAULT NULL,
  `catatanpenguji3` text DEFAULT NULL,
  `accpembimbing1` int(11) NOT NULL DEFAULT 0,
  `accpembimbing2` int(11) NOT NULL DEFAULT 0,
  `accpenguji1` int(11) NOT NULL DEFAULT 0,
  `accpenguji2` int(11) NOT NULL DEFAULT 0,
  `accpenguji3` int(11) NOT NULL DEFAULT 0,
  `update_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ujian`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `etesis_bimbingan` (
  `id_bimbingan` varchar(100) NOT NULL,
  `id_skripsi` varchar(100) NOT NULL,
  `id_dosen_tujuan` varchar(100) NOT NULL,
  `pengirim` varchar(30) NOT NULL,
  `pesan` text NOT NULL,
  `url_berkas` varchar(500) DEFAULT NULL,
  `waktu_kirim` datetime NOT NULL,
  PRIMARY KEY (`id_bimbingan`),
  KEY `etesis_bimbingan_thesis_time` (`id_skripsi`,`waktu_kirim`),
  KEY `etesis_bimbingan_lecturer_time` (`id_dosen_tujuan`,`waktu_kirim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etesis_judul` (
  `id` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `judul` text NOT NULL,
  `deskripsi` text NOT NULL,
  `status` int(11) NOT NULL,
  `komentar` text DEFAULT NULL,
  `tanggal_usul` date NOT NULL,
  `tanggal_verifikasi` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etesis_judul_program_date` (`id_prodi`,`tanggal_usul`),
  KEY `etesis_judul_student_status` (`id_mahasiswa`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etesis_notifikasi` (
  `id` char(36) NOT NULL,
  `recipient_role` varchar(30) NOT NULL,
  `recipient_id` varchar(100) NOT NULL,
  `category` varchar(80) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `url` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etesis_notifikasi_recipient_unread` (`recipient_role`,`recipient_id`,`is_read`,`created_at`),
  KEY `etesis_notifikasi_recipient_created` (`recipient_role`,`recipient_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etesis_skripsi` (
  `id` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `id_proposal` varchar(100) NOT NULL,
  `judul` text NOT NULL,
  `pembimbing1` varchar(100) DEFAULT NULL,
  `pembimbing2` varchar(100) DEFAULT NULL,
  `penguji1` varchar(100) DEFAULT NULL,
  `penguji2` varchar(100) DEFAULT NULL,
  `penguji3` varchar(100) DEFAULT NULL,
  `status` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `etesis_skripsi_program_status` (`id_prodi`,`status`),
  KEY `etesis_skripsi_student` (`id_mahasiswa`),
  KEY `etesis_skripsi_proposal` (`id_proposal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `etesis_ujian` (
  `id_ujian` varchar(100) NOT NULL,
  `id_skripsi` varchar(100) NOT NULL,
  `jenis_ujian` varchar(50) DEFAULT NULL,
  `waktu_ujian` datetime DEFAULT NULL,
  `ruang_ujian` varchar(255) DEFAULT NULL,
  `nilaipembimbing1` int(11) DEFAULT NULL,
  `nilaipembimbing2` int(11) DEFAULT NULL,
  `nilaipenguji1` int(11) DEFAULT NULL,
  `nilaipenguji2` int(11) DEFAULT NULL,
  `nilaipenguji3` int(11) DEFAULT NULL,
  `catatanpembimbing1` text DEFAULT NULL,
  `catatanpembimbing2` text DEFAULT NULL,
  `catatanpenguji1` text DEFAULT NULL,
  `catatanpenguji2` text DEFAULT NULL,
  `catatanpenguji3` text DEFAULT NULL,
  `accpembimbing1` int(11) NOT NULL DEFAULT 0,
  `accpembimbing2` int(11) NOT NULL DEFAULT 0,
  `accpenguji1` int(11) NOT NULL DEFAULT 0,
  `accpenguji2` int(11) NOT NULL DEFAULT 0,
  `accpenguji3` int(11) NOT NULL DEFAULT 0,
  `update_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `create_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id_ujian`),
  KEY `etesis_ujian_thesis_type` (`id_skripsi`,`jenis_ujian`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `keu_billing_ecoll` (
  `id_billing` char(36) NOT NULL,
  `nama_bank` enum('BNI','BTN') NOT NULL,
  `nama_pembayar` varchar(255) NOT NULL,
  `nominal_tagihan` bigint(20) unsigned NOT NULL,
  `prefix_transaksi` char(3) DEFAULT NULL,
  `jenis_tagihan` char(1) DEFAULT NULL,
  `email_pelanggan` varchar(255) DEFAULT NULL,
  `telepon_pelanggan` varchar(30) DEFAULT NULL,
  `kode_pembayaran` char(3) DEFAULT NULL,
  `flag_pembayaran` char(1) DEFAULT NULL,
  `nomor_giro` varchar(255) DEFAULT NULL,
  `deskripsi` varchar(100) DEFAULT NULL,
  `masa_berlaku_hari` smallint(5) unsigned DEFAULT NULL,
  `id_transaksi` varchar(100) NOT NULL,
  `nomor_va` varchar(50) NOT NULL,
  `status_respon` varchar(50) DEFAULT NULL,
  `pesan_respon` text DEFAULT NULL,
  `data_respon_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data_respon_json`)),
  `status_pembayaran` enum('belum_lunas','lunas') NOT NULL DEFAULT 'belum_lunas',
  `nominal_terbayar` bigint(20) unsigned DEFAULT NULL,
  `tanggal_pembayaran` datetime DEFAULT NULL,
  `terakhir_dicek_pada` datetime DEFAULT NULL,
  `data_pengecekan_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_pengecekan_json`)),
  `dibuat_oleh` varchar(100) NOT NULL,
  `dibuat_pada` timestamp NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_billing`),
  UNIQUE KEY `uq_keu_billing_ecoll_id_transaksi` (`id_transaksi`),
  KEY `idx_keu_billing_ecoll_nomor_va` (`nomor_va`),
  KEY `idx_keu_billing_ecoll_bank_dibuat_pada` (`nama_bank`,`dibuat_pada`),
  KEY `idx_keu_billing_ecoll_status_pembayaran` (`status_pembayaran`),
  KEY `idx_keu_billing_ecoll_dibuat_oleh` (`dibuat_oleh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_absensi` (
  `id_absensi` varchar(36) NOT NULL,
  `id_pertemuan` varchar(36) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `status_kehadiran` enum('hadir','izin','sakit','alpa') NOT NULL DEFAULT 'hadir',
  `metode_absen` enum('mandiri','dosen') NOT NULL,
  `waktu_absen` datetime NOT NULL,
  `catatan` varchar(1000) DEFAULT NULL,
  `id_pencatat` varchar(100) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_absensi`),
  UNIQUE KEY `lms_absensi_pertemuan_mahasiswa_unique` (`id_pertemuan`,`id_mahasiswa`),
  KEY `lms_absensi_mahasiswa_index` (`id_mahasiswa`),
  KEY `lms_absensi_status_index` (`status_kehadiran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_bank_soal` (
  `id_soal` varchar(36) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `id_matkul` varchar(100) NOT NULL,
  `jenis_soal` enum('pilihan_ganda','essay') NOT NULL,
  `pertanyaan` longtext NOT NULL,
  `opsi_a` longtext DEFAULT NULL,
  `opsi_b` longtext DEFAULT NULL,
  `opsi_c` longtext DEFAULT NULL,
  `opsi_d` longtext DEFAULT NULL,
  `opsi_e` longtext DEFAULT NULL,
  `kunci_jawaban` char(1) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_soal`),
  KEY `lms_bank_soal_dosen_matkul_index` (`id_dosen`,`id_matkul`),
  KEY `lms_bank_soal_jenis_aktif_index` (`jenis_soal`,`aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lms_detail_hasil_ujian` (
  `id_detail_hasil` varchar(36) NOT NULL,
  `id_hasil_ujian` varchar(36) NOT NULL,
  `id_soal` varchar(36) DEFAULT NULL,
  `urutan_tampil` smallint(5) unsigned NOT NULL,
  `soal_snapshot` longtext NOT NULL,
  `jawaban_mahasiswa` longtext DEFAULT NULL,
  `jawaban_benar` tinyint(1) DEFAULT NULL,
  `nilai_jawaban` decimal(7,2) DEFAULT NULL,
  `dijawab_pada` datetime DEFAULT NULL,
  PRIMARY KEY (`id_detail_hasil`),
  UNIQUE KEY `lms_detail_hasil_urutan_unique` (`id_hasil_ujian`,`urutan_tampil`),
  KEY `lms_detail_hasil_soal_index` (`id_soal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_diskusi` (
  `id_diskusi` varchar(36) NOT NULL,
  `id_jadwal` varchar(100) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `isi_diskusi` longtext NOT NULL,
  `status_diskusi` enum('terbuka','ditutup') NOT NULL DEFAULT 'terbuka',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_diskusi`),
  KEY `lms_diskusi_jadwal_index` (`id_jadwal`),
  KEY `lms_diskusi_dosen_index` (`id_dosen`),
  KEY `lms_diskusi_status_index` (`status_diskusi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_hasil_ujian` (
  `id_hasil_ujian` varchar(36) NOT NULL,
  `id_ujian` varchar(36) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `dimulai_pada` datetime DEFAULT NULL,
  `dikumpulkan_pada` datetime DEFAULT NULL,
  `nilai_akhir` decimal(7,2) DEFAULT NULL,
  `komentar_dosen` longtext DEFAULT NULL,
  `status_hasil` enum('berlangsung','terkumpul','dinilai') NOT NULL DEFAULT 'berlangsung',
  `dinilai_oleh` varchar(100) DEFAULT NULL,
  `dinilai_pada` datetime DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_hasil_ujian`),
  UNIQUE KEY `lms_hasil_ujian_mahasiswa_unique` (`id_ujian`,`id_mahasiswa`),
  KEY `lms_hasil_ujian_mahasiswa_index` (`id_mahasiswa`),
  KEY `lms_hasil_ujian_status_index` (`status_hasil`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_label_materi` (
  `id_label_materi` varchar(36) NOT NULL,
  `id_materi` varchar(36) NOT NULL,
  `pertemuan_ke` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id_label_materi`),
  UNIQUE KEY `lms_label_materi_unique` (`id_materi`,`pertemuan_ke`),
  KEY `lms_label_pertemuan_index` (`pertemuan_ke`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lms_materi_kuliah` (
  `id_materi` varchar(36) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `id_matkul` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` longtext DEFAULT NULL,
  `nama_berkas` varchar(255) DEFAULT NULL,
  `path_berkas` varchar(500) DEFAULT NULL,
  `tipe_berkas` varchar(100) DEFAULT NULL,
  `ukuran_berkas` bigint(20) unsigned DEFAULT NULL,
  `status_publikasi` enum('draf','terbit') NOT NULL DEFAULT 'terbit',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_materi`),
  KEY `lms_materi_dosen_matkul_index` (`id_dosen`,`id_matkul`),
  KEY `lms_materi_prodi_index` (`id_prodi`),
  KEY `lms_materi_publikasi_index` (`status_publikasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_pengumpulan_tugas` (
  `id_pengumpulan` varchar(36) NOT NULL,
  `id_tugas` varchar(36) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `jawaban_teks` longtext DEFAULT NULL,
  `tautan_berkas` varchar(2048) DEFAULT NULL,
  `dikumpulkan_pada` datetime NOT NULL,
  `nilai` decimal(7,2) DEFAULT NULL,
  `komentar_dosen` longtext DEFAULT NULL,
  `dinilai_oleh` varchar(100) DEFAULT NULL,
  `dinilai_pada` datetime DEFAULT NULL,
  `status_pengumpulan` enum('terkumpul','dinilai') NOT NULL DEFAULT 'terkumpul',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pengumpulan`),
  UNIQUE KEY `lms_pengumpulan_tugas_mahasiswa_unique` (`id_tugas`,`id_mahasiswa`),
  KEY `lms_pengumpulan_tugas_mahasiswa_index` (`id_mahasiswa`),
  KEY `lms_pengumpulan_tugas_status_index` (`status_pengumpulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lms_pertemuan` (
  `id_pertemuan` varchar(36) NOT NULL,
  `id_jadwal` varchar(100) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `pertemuan_ke` tinyint(3) unsigned NOT NULL,
  `id_materi` varchar(36) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `tanggal_kuliah` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `waktu_selesai` time NOT NULL,
  `absen_dibuka` tinyint(1) NOT NULL DEFAULT 0,
  `tautan_video` varchar(2048) DEFAULT NULL,
  `status_pertemuan` enum('draf','terbit','selesai') NOT NULL DEFAULT 'terbit',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pertemuan`),
  UNIQUE KEY `lms_pertemuan_jadwal_ke_unique` (`id_jadwal`,`pertemuan_ke`),
  KEY `lms_pertemuan_dosen_index` (`id_dosen`),
  KEY `lms_pertemuan_tanggal_index` (`tanggal_kuliah`),
  KEY `lms_pertemuan_materi_fk` (`id_materi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lms_pertemuan_materi` (
  `id_pertemuan_materi` varchar(36) NOT NULL,
  `id_pertemuan` varchar(36) NOT NULL,
  `id_materi` varchar(36) NOT NULL,
  PRIMARY KEY (`id_pertemuan_materi`),
  UNIQUE KEY `lms_pertemuan_materi_unique` (`id_pertemuan`,`id_materi`),
  KEY `lms_pertemuan_materi_materi_index` (`id_materi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_pesan_diskusi` (
  `id_pesan` varchar(36) NOT NULL,
  `id_diskusi` varchar(36) NOT NULL,
  `id_pesan_induk` varchar(36) DEFAULT NULL,
  `jenis_pengguna` enum('dosen','mahasiswa') NOT NULL,
  `id_pengguna` varchar(100) NOT NULL,
  `isi_pesan` longtext NOT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_pesan`),
  KEY `lms_pesan_diskusi_diskusi_index` (`id_diskusi`,`dibuat_pada`),
  KEY `lms_pesan_diskusi_pengguna_index` (`jenis_pengguna`,`id_pengguna`),
  KEY `lms_pesan_diskusi_induk_fk` (`id_pesan_induk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_tugas` (
  `id_tugas` varchar(36) NOT NULL,
  `id_jadwal` varchar(100) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` longtext NOT NULL,
  `batas_pengumpulan` datetime NOT NULL,
  `status_tugas` enum('draf','terbit','ditutup') NOT NULL DEFAULT 'terbit',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_tugas`),
  KEY `lms_tugas_jadwal_index` (`id_jadwal`),
  KEY `lms_tugas_dosen_index` (`id_dosen`),
  KEY `lms_tugas_deadline_index` (`batas_pengumpulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `lms_ujian` (
  `id_ujian` varchar(36) NOT NULL,
  `id_pertemuan` varchar(36) NOT NULL,
  `id_dosen` varchar(100) NOT NULL,
  `id_matkul` varchar(100) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `petunjuk` longtext DEFAULT NULL,
  `jenis_ujian` enum('pilihan_ganda','essay') NOT NULL,
  `sumber_soal` enum('semua','pilihan') NOT NULL DEFAULT 'semua',
  `jumlah_soal_disajikan` smallint(5) unsigned NOT NULL,
  `urutan_soal` enum('acak','berurutan') NOT NULL DEFAULT 'acak',
  `acak_pilihan_jawaban` tinyint(1) NOT NULL DEFAULT 0,
  `durasi_menit` smallint(5) unsigned NOT NULL,
  `batas_mulai` datetime NOT NULL,
  `batas_selesai` datetime NOT NULL,
  `nilai_per_soal` decimal(7,2) DEFAULT NULL,
  `status_ujian` enum('draf','terbit','ditutup') NOT NULL DEFAULT 'draf',
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  `diperbarui_pada` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ujian`),
  UNIQUE KEY `lms_ujian_pertemuan_unique` (`id_pertemuan`),
  KEY `lms_ujian_dosen_matkul_index` (`id_dosen`,`id_matkul`),
  KEY `lms_ujian_waktu_index` (`batas_mulai`,`batas_selesai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `lms_ujian_soal` (
  `id_ujian_soal` varchar(36) NOT NULL,
  `id_ujian` varchar(36) NOT NULL,
  `id_soal` varchar(36) NOT NULL,
  `urutan_tetap` smallint(5) unsigned DEFAULT NULL,
  `ditambahkan_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ujian_soal`),
  UNIQUE KEY `lms_ujian_soal_unique` (`id_ujian`,`id_soal`),
  KEY `lms_ujian_soal_bank_index` (`id_soal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `log_aktivitas` (
  `id_log` varchar(36) NOT NULL,
  `id_user` varchar(100) DEFAULT NULL,
  `level_user` varchar(50) DEFAULT NULL,
  `nama_user` varchar(255) NOT NULL,
  `modul` varchar(100) NOT NULL,
  `aktifitas` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `log_aktivitas_id_user_index` (`id_user`),
  KEY `log_aktivitas_modul_index` (`modul`),
  KEY `log_aktivitas_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s1m4k_admin` (
  `id_admin` bigint(20) NOT NULL AUTO_INCREMENT,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_fakultas` varchar(100) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `passwordb` varchar(100) DEFAULT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `blokir` enum('Y','N') NOT NULL DEFAULT 'N',
  `id_session` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_admin`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_akd` (
  `id_akd` varchar(100) NOT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_dosen` varchar(100) DEFAULT NULL,
  `id_jadwal` varchar(100) DEFAULT NULL,
  `id_kalender` varchar(100) DEFAULT NULL,
  `tahun` varchar(5) DEFAULT NULL,
  `rencana` varchar(2) DEFAULT NULL,
  `realisasi` varchar(2) DEFAULT NULL,
  `ket` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_akd`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_dosen` (`id_dosen`),
  KEY `id_jadwal` (`id_jadwal`),
  KEY `id_kalender` (`id_kalender`),
  KEY `tahun` (`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_akm` (
  `id_ipmhs` varchar(50) NOT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `id_mahasiswa` varchar(50) DEFAULT NULL,
  `id_kalender` varchar(50) DEFAULT NULL,
  `tahun` varchar(5) DEFAULT NULL,
  `ipk` varchar(4) DEFAULT NULL,
  `ips` varchar(4) DEFAULT NULL,
  `status_mahasiswa` varchar(50) DEFAULT NULL,
  `sks_program` varchar(10) DEFAULT NULL,
  `sks_semester` varchar(10) DEFAULT NULL,
  `sks_lulus` varchar(10) DEFAULT NULL,
  `maks_sks_program` varchar(10) DEFAULT NULL,
  `no_ijazah` varchar(50) DEFAULT NULL,
  `tgl_keluar` date DEFAULT NULL,
  `ket` text DEFAULT NULL,
  `no_sk` varchar(50) DEFAULT NULL,
  `tgl_sk` date DEFAULT NULL,
  `update_akm` int(5) NOT NULL DEFAULT 0,
  `id_krs` text DEFAULT NULL,
  `simpan` int(1) DEFAULT 0,
  `flag_feeder` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_ipmhs`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_mahasiswa` (`id_mahasiswa`),
  KEY `id_kalender` (`id_kalender`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_billing` (
  `id_billing` char(50) NOT NULL,
  `id_mahasiswa` char(50) NOT NULL,
  `trx_id` char(20) NOT NULL,
  `billing` char(20) NOT NULL,
  `nominal` int(7) NOT NULL,
  `expire` datetime NOT NULL,
  `tahun` int(5) NOT NULL,
  PRIMARY KEY (`id_billing`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `s1m4k_camaba` (
  `id` varchar(100) NOT NULL,
  `id_perguruan_tinggi` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `no_peserta` varchar(25) NOT NULL,
  `nim` varchar(24) NOT NULL,
  `nama_mahasiswa` varchar(100) NOT NULL,
  `jenis_kelamin` varchar(1) NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` varchar(50) NOT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `id_agama` varchar(11) DEFAULT NULL,
  `id_negara` varchar(3) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `nisn` varchar(50) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `handphone` varchar(20) DEFAULT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `email` varchar(60) NOT NULL,
  `penerima_kps` varchar(1) NOT NULL,
  `id_jenis_daftar` varchar(2) DEFAULT NULL,
  `id_jalur_daftar` varchar(4) DEFAULT NULL,
  `id_periode_masuk` varchar(5) NOT NULL,
  `tanggal_daftar` varchar(15) NOT NULL,
  `id_pembiayaan` varchar(50) DEFAULT NULL,
  `biaya_masuk_kuliah` int(11) DEFAULT NULL,
  `id_kebutuhan_khusus_mahasiswa` varchar(11) DEFAULT NULL,
  `status` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_edom` (
  `id_edom` varchar(100) NOT NULL,
  `id_krs` varchar(100) NOT NULL,
  `id_jadwal` varchar(100) NOT NULL,
  `id_kalender` varchar(100) NOT NULL,
  `id_survey` varchar(100) NOT NULL,
  `nilai` int(11) NOT NULL,
  PRIMARY KEY (`id_edom`),
  KEY `id_krs` (`id_krs`),
  KEY `id_jadwal` (`id_jadwal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_export` (
  `id_export` varchar(100) NOT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_kurikulum` varchar(100) DEFAULT NULL,
  `id_kalender` varchar(100) DEFAULT NULL,
  `id_semester` varchar(100) DEFAULT NULL,
  `id_data` varchar(100) DEFAULT NULL,
  `data` varchar(100) DEFAULT NULL,
  `status` enum('Y','N','P') NOT NULL,
  `waktu` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_export`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_export_detail` (
  `id_export_detail` char(36) NOT NULL,
  `id_export` char(36) NOT NULL,
  `source_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `status` enum('Y','N') NOT NULL DEFAULT 'N',
  `payload_hash` char(64) DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `error_code` varchar(100) DEFAULT NULL,
  `error_desc` text DEFAULT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `succeeded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_export_detail`),
  UNIQUE KEY `s1m4k_export_detail_source_unique` (`id_export`,`source_id`),
  KEY `s1m4k_export_detail_status_index` (`id_export`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s1m4k_import` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_prodi` varchar(100) DEFAULT NULL,
  `jum_dosen` int(11) DEFAULT NULL,
  `status_dosen` varchar(50) DEFAULT '-',
  `jum_mhs` int(11) DEFAULT NULL,
  `status_mhs` varchar(50) DEFAULT '-',
  `jum_mk` int(11) DEFAULT NULL,
  `status_mk` varchar(50) DEFAULT '-',
  `jum_ruang` int(11) DEFAULT NULL,
  `status_ruang` varchar(50) DEFAULT '-',
  `jum_asdos` int(11) DEFAULT NULL,
  `status_asdos` varchar(50) DEFAULT '-',
  `jum_kal` int(11) DEFAULT NULL,
  `status_kal` varchar(50) DEFAULT '-',
  `jum_jadwal` int(11) DEFAULT NULL,
  `status_jadwal` varchar(50) DEFAULT '-',
  `ket_jad` text DEFAULT NULL,
  `jum_krs` int(11) DEFAULT NULL,
  `status_krs` varchar(50) DEFAULT '-',
  `ket_krs` text DEFAULT NULL,
  `ket` text DEFAULT NULL,
  `w_dosen` varchar(20) DEFAULT NULL,
  `w_mhs` varchar(20) DEFAULT NULL,
  `w_mk` varchar(20) DEFAULT NULL,
  `w_ruang` varchar(20) DEFAULT NULL,
  `w_asdos` varchar(20) DEFAULT NULL,
  `w_kal` varchar(20) DEFAULT NULL,
  `w_jad` varchar(20) DEFAULT NULL,
  `w_krs` varchar(20) DEFAULT NULL,
  `jum_kur` varchar(10) DEFAULT NULL,
  `status_kur` varchar(20) DEFAULT NULL,
  `w_kur` varchar(20) DEFAULT NULL,
  `jum_reg` varchar(20) DEFAULT NULL,
  `status_reg` varchar(20) DEFAULT NULL,
  `ket_reg` text DEFAULT NULL,
  `w_reg` varchar(20) DEFAULT NULL,
  `tot_krs` varchar(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_jadwal` (
  `id_jadwal` varchar(50) NOT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `id_kalender` varchar(50) DEFAULT NULL,
  `id_matkul` varchar(50) DEFAULT NULL,
  `dosen1` varchar(50) DEFAULT NULL,
  `dosen2` varchar(50) DEFAULT NULL,
  `dosen3` varchar(50) DEFAULT NULL,
  `dosen4` varchar(50) DEFAULT NULL,
  `dosen5` varchar(50) DEFAULT NULL,
  `dosen6` varchar(50) DEFAULT NULL,
  `dosen7` varchar(50) DEFAULT NULL,
  `dosen8` varchar(100) DEFAULT NULL,
  `dosen9` varchar(100) DEFAULT NULL,
  `dosen10` varchar(100) DEFAULT NULL,
  `dosen11` varchar(100) DEFAULT NULL,
  `dosen12` varchar(100) DEFAULT NULL,
  `dosen13` varchar(100) DEFAULT NULL,
  `dosen14` varchar(100) DEFAULT NULL,
  `id_ruang` varchar(50) DEFAULT NULL,
  `nama_kelas` varchar(25) DEFAULT NULL,
  `hari` varchar(10) DEFAULT NULL,
  `jammulai` time DEFAULT NULL,
  `jamselesai` time DEFAULT NULL,
  `idjadwal` varchar(15) DEFAULT NULL,
  `id_kelas_kuliah` varchar(50) DEFAULT NULL,
  `kampus_merdeka` char(1) NOT NULL DEFAULT '0',
  `lingkup_km` varchar(2) DEFAULT NULL,
  `mode_km` varchar(2) DEFAULT NULL,
  `tgl_tutup_daftar_km` varchar(15) DEFAULT NULL,
  `jml_mahasiswa` int(5) NOT NULL DEFAULT 0,
  `input_nilai` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id_jadwal`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_kalender` (`id_kalender`),
  KEY `id_matkul` (`id_matkul`),
  KEY `dosen1` (`dosen1`),
  KEY `dosen2` (`dosen2`),
  KEY `dosen3` (`dosen3`),
  KEY `id_ruang` (`id_ruang`),
  KEY `kampus_merdeka` (`kampus_merdeka`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_jadwal_pengajar_feeder` (
  `id_jadwal` varchar(50) NOT NULL,
  `id_dosen` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `error_desc` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_jadwal`,`id_dosen`),
  KEY `s1m4k_jadwal_pengajar_feeder_status_id_jadwal_index` (`status`,`id_jadwal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `s1m4k_kalender` (
  `id_kalender` varchar(50) NOT NULL,
  `periode` varchar(20) DEFAULT NULL,
  `tahun` varchar(5) DEFAULT NULL,
  `namatahun` varchar(100) DEFAULT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `tgl_mulai_krs` date DEFAULT NULL,
  `tgl_akhir_krs` date DEFAULT NULL,
  `tgl_mulai_isinilai` date DEFAULT NULL,
  `tgl_akhir_isinilai` date DEFAULT NULL,
  `status` enum('Y','N') DEFAULT 'N',
  `status_sp` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id_kalender`),
  KEY `status` (`status`),
  KEY `tahun` (`tahun`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_kegiatanmhs` (
  `id_kegiatan` varchar(100) NOT NULL,
  `id_kalender` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_mahasiswa` varchar(100) DEFAULT NULL,
  `jenis_kegiatan` varchar(2) DEFAULT NULL,
  `judul` text DEFAULT NULL,
  `lokasi` varchar(100) DEFAULT NULL,
  `no_sk` varchar(50) DEFAULT NULL,
  `tgl_sk` date DEFAULT NULL,
  `jenis_anggota` varchar(1) DEFAULT NULL,
  `program_mbkm` tinyint(1) DEFAULT NULL,
  `ket` text DEFAULT NULL,
  PRIMARY KEY (`id_kegiatan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_kodereferensi` (
  `id_referensi` varchar(100) NOT NULL,
  `kode_referensi` varchar(10) DEFAULT NULL,
  `ket_referensi` text DEFAULT NULL,
  `ket_referensi2` varchar(100) DEFAULT NULL,
  `keterangan` varchar(50) DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `nama_provinsi` varchar(200) DEFAULT NULL,
  `lat_provinsi` decimal(10,7) DEFAULT NULL,
  `lng_provinsi` decimal(10,7) DEFAULT NULL,
  `untuk_kampus_merdeka` char(1) DEFAULT NULL,
  PRIMARY KEY (`id_referensi`),
  KEY `keterangan` (`keterangan`),
  KEY `kode_referensi` (`kode_referensi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_krs` (
  `id_krs` varchar(50) NOT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `id_kalender` varchar(50) DEFAULT NULL,
  `tahun` varchar(5) DEFAULT NULL,
  `id_mahasiswa` varchar(50) DEFAULT NULL,
  `id_jadwal` varchar(50) DEFAULT NULL,
  `id_matkul` varchar(50) DEFAULT NULL,
  `status_krs` enum('B','U') DEFAULT NULL COMMENT 'B atau U',
  `nilai_partisipatif` decimal(5,2) DEFAULT 0.00,
  `nilai_proyek` decimal(5,2) DEFAULT 0.00,
  `nilai_quiz` decimal(5,2) DEFAULT 0.00,
  `nilai_tugas` decimal(5,2) DEFAULT 0.00,
  `nilai_uts` decimal(5,2) DEFAULT 0.00,
  `nilai_uas` decimal(5,2) DEFAULT 0.00,
  `nilai_angka` decimal(5,2) DEFAULT 0.00,
  `nilai_bobot` decimal(4,2) DEFAULT 0.00,
  `nilai_huruf` varchar(2) DEFAULT 'E',
  `input_nilai` enum('Y','N') DEFAULT 'N',
  `krs_reguler` enum('Y','N') DEFAULT 'Y',
  `id_pt_asal` varchar(100) DEFAULT NULL,
  `kode_mk_asal` varchar(200) DEFAULT NULL,
  `nama_mk_asal` varchar(200) DEFAULT NULL,
  `sks_asal` varchar(4) DEFAULT NULL,
  `nilai_huruf_asal` varchar(4) DEFAULT NULL,
  `tgl_update` date DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `flag_feeder` int(11) DEFAULT 0,
  `id_trnlm` varchar(20) DEFAULT NULL,
  `id_mahasiswa_old` varchar(50) DEFAULT NULL,
  `id_kegiatan` varchar(100) DEFAULT NULL,
  `krs_mbkm` enum('Y','N') NOT NULL DEFAULT 'N',
  `isi_edom` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id_krs`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_kalender` (`id_kalender`),
  KEY `tahun` (`tahun`),
  KEY `id_mahasiswa` (`id_mahasiswa`),
  KEY `id_jadwal` (`id_jadwal`),
  KEY `id_matkul` (`id_matkul`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_kubermas` (
  `id_peserta` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `id_lokasi` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tahun` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `tgl_daftar` date NOT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  `petugas_usul` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_peserta`),
  KEY `id_peserta` (`id_peserta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_kurikulum` (
  `id_kurikulum` varchar(100) NOT NULL,
  `nama_kurikulum` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_semester` varchar(5) DEFAULT NULL,
  `semester_mulai_berlaku` varchar(50) DEFAULT NULL,
  `jumlah_sks_lulus` varchar(10) DEFAULT NULL,
  `jumlah_sks_wajib` varchar(10) DEFAULT NULL,
  `jumlah_sks_pilihan` varchar(10) DEFAULT NULL,
  `jumlah_sks_mata_kuliah_wajib` varchar(10) DEFAULT NULL,
  `jumlah_sks_mata_kuliah_pilihan` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_kurikulum`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_semester` (`id_semester`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_log_device` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` varchar(50) NOT NULL,
  `nim` varchar(30) NOT NULL,
  `id_prodi` varchar(50) NOT NULL,
  `nominal_ukt` double NOT NULL DEFAULT 0,
  `kategori_ukt` varchar(10) NOT NULL,
  `jenis_perangkat` varchar(50) NOT NULL,
  `raw_user_agent` text NOT NULL,
  `waktu_akses` datetime NOT NULL,
  PRIMARY KEY (`id_log`),
  UNIQUE KEY `mhs_perangkat_unik` (`id_mahasiswa`,`jenis_perangkat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `s1m4k_lokasi_kubermas` (
  `id_lokasi` varchar(100) NOT NULL,
  `nama_lokasi` varchar(200) NOT NULL,
  `kuota` int(11) NOT NULL,
  `aktif` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`id_lokasi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterallprodi` (
  `id_perguruan_tinggi` varchar(100) NOT NULL,
  `kode_perguruan_tinggi` varchar(20) DEFAULT NULL,
  `id_prodi` varchar(100) NOT NULL DEFAULT '',
  `kode_program_studi` varchar(20) DEFAULT NULL,
  `nama_program_studi` varchar(100) DEFAULT NULL,
  `status` varchar(10) DEFAULT NULL,
  `id_jenjang_pendidikan` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id_perguruan_tinggi`,`id_prodi`),
  KEY `id_prodi` (`id_prodi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterallpt` (
  `id_perguruan_tinggi` varchar(100) NOT NULL,
  `kode_perguruan_tinggi` varchar(20) DEFAULT NULL,
  `nama_perguruan_tinggi` varchar(200) DEFAULT NULL,
  `nama_singkat` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_perguruan_tinggi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterasdos` (
  `id_asdos` varchar(100) NOT NULL,
  `id_perguruan_tinggi` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_fakultas` varchar(100) DEFAULT NULL,
  `kdasisten` varchar(10) DEFAULT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `jkel` varchar(1) DEFAULT NULL,
  `tplahir` varchar(50) DEFAULT NULL,
  `tgllahir` date DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `passwordb` varchar(100) DEFAULT NULL,
  `notlp` varchar(15) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `gelard` varchar(25) DEFAULT NULL,
  `gelarb` varchar(25) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `flag_feeder` int(1) DEFAULT 0,
  PRIMARY KEY (`id_asdos`),
  KEY `id_prodi` (`id_prodi`),
  KEY `kdasisten` (`kdasisten`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterbobotnilai` (
  `id_bobot_nilai` varchar(100) NOT NULL,
  `id_perguruan_tinggi` varchar(100) DEFAULT NULL,
  `nilai_huruf` varchar(3) DEFAULT NULL,
  `nilai_indeks` varchar(6) DEFAULT NULL,
  `bobot_minimum` varchar(6) DEFAULT NULL,
  `bobot_maksimum` varchar(6) DEFAULT NULL,
  `tanggal_mulai_efektif` date DEFAULT NULL,
  `tanggal_akhir_efektif` date DEFAULT NULL,
  `status` enum('Y','N') DEFAULT 'Y',
  `flag_feeder` int(1) DEFAULT 0,
  PRIMARY KEY (`id_bobot_nilai`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterdosen` (
  `id_dosen` varchar(100) NOT NULL,
  `id_registrasi_dosen` varchar(100) DEFAULT NULL,
  `id_perguruan_tinggi` varchar(100) DEFAULT NULL,
  `id_fakultas` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_tahun_ajaran` varchar(10) DEFAULT NULL,
  `nama_dosen` varchar(100) DEFAULT NULL,
  `gelar_depan` varchar(20) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `password_baru` varchar(100) DEFAULT NULL,
  `gelar_belakang` varchar(50) DEFAULT NULL,
  `nidn` varchar(50) DEFAULT NULL,
  `nuptk` varchar(50) DEFAULT NULL,
  `nip` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `jenis_kelamin` varchar(1) DEFAULT NULL,
  `id_agama` varchar(2) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `id_status_aktif` varchar(2) DEFAULT NULL,
  `nama_status_aktif` varchar(100) DEFAULT NULL,
  `nomor_surat_tugas` varchar(50) DEFAULT NULL,
  `tanggal_surat_tugas` date DEFAULT NULL,
  `mulai_surat_tugas` date DEFAULT NULL,
  `flag_feeder` int(1) DEFAULT 0,
  PRIMARY KEY (`id_dosen`),
  KEY `id_prodi` (`id_prodi`),
  KEY `nidn` (`nidn`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterfakultas` (
  `id_fakultas` varchar(50) NOT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `kode_fakultas` varchar(2) DEFAULT NULL,
  `nama_fakultas` varchar(50) DEFAULT NULL,
  `dekan` varchar(50) DEFAULT NULL,
  `pd1` varchar(50) DEFAULT NULL,
  `pd2` varchar(50) DEFAULT NULL,
  `pd3` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_fakultas`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `pd1` (`pd1`),
  KEY `pd3` (`pd3`),
  KEY `pd2` (`pd2`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_mastermhs` (
  `id_mahasiswa` varchar(50) NOT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `dosen_pa` varchar(50) DEFAULT NULL,
  `id_registrasi_mahasiswa` varchar(50) DEFAULT NULL,
  `id_status_mahasiswa` varchar(1) DEFAULT NULL,
  `nama_status_mahasiswa` varchar(50) DEFAULT NULL,
  `nim` varchar(24) DEFAULT NULL,
  `nama_mahasiswa` varchar(50) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `password_baru` varchar(100) DEFAULT NULL,
  `jenis_kelamin` varchar(1) DEFAULT NULL,
  `jalan` text DEFAULT NULL,
  `rt` varchar(20) DEFAULT NULL,
  `rw` varchar(20) DEFAULT NULL,
  `dusun` varchar(60) DEFAULT NULL,
  `kelurahan` varchar(60) DEFAULT NULL,
  `kode_pos` varchar(5) DEFAULT NULL,
  `nisn` varchar(50) DEFAULT NULL,
  `nik` varchar(16) DEFAULT NULL,
  `tempat_lahir` varchar(32) DEFAULT NULL,
  `tanggal_lahir` char(15) DEFAULT NULL,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `tanggal_lahir_ayah` char(15) DEFAULT NULL,
  `nik_ayah` varchar(50) DEFAULT NULL,
  `id_pendidikan_ayah` varchar(2) DEFAULT NULL,
  `id_pekerjaan_ayah` varchar(11) DEFAULT NULL,
  `id_penghasilan_ayah` varchar(11) DEFAULT NULL,
  `id_kebutuhan_khusus_ayah` varchar(11) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `tanggal_lahir_ibu` char(15) DEFAULT NULL,
  `nik_ibu` varchar(50) DEFAULT NULL,
  `id_pendidikan_ibu` varchar(2) DEFAULT NULL,
  `id_pekerjaan_ibu` varchar(11) DEFAULT NULL,
  `id_penghasilan_ibu` varchar(11) DEFAULT NULL,
  `id_kebutuhan_khusus_ibu` varchar(11) DEFAULT NULL,
  `nama_wali` varchar(100) DEFAULT NULL,
  `tanggal_lahir_wali` char(15) DEFAULT NULL,
  `id_pendidikan_wali` varchar(2) DEFAULT NULL,
  `id_pekerjaan_wali` varchar(11) DEFAULT NULL,
  `id_penghasilan_wali` varchar(11) DEFAULT NULL,
  `id_kebutuhan_khusus_mahasiswa` varchar(11) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `handphone` varchar(20) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `penerima_kps` varchar(1) DEFAULT NULL,
  `nomor_kps` varchar(80) DEFAULT NULL,
  `npwp` varchar(100) DEFAULT NULL,
  `id_wilayah` varchar(8) DEFAULT NULL,
  `id_jenis_tinggal` varchar(2) DEFAULT NULL,
  `id_agama` varchar(11) DEFAULT NULL,
  `id_alat_transportasi` varchar(2) DEFAULT NULL,
  `id_negara` varchar(3) DEFAULT NULL,
  `id_jenis_daftar` varchar(2) DEFAULT NULL,
  `id_jalur_daftar` varchar(4) DEFAULT NULL,
  `id_periode_masuk` varchar(5) DEFAULT NULL,
  `tanggal_daftar` char(15) DEFAULT NULL,
  `sks_diakui` varchar(10) DEFAULT NULL,
  `id_perguruan_tinggi_asal` varchar(50) DEFAULT NULL,
  `id_prodi_asal` varchar(50) DEFAULT NULL,
  `id_pembiayaan` varchar(50) DEFAULT NULL,
  `biaya_masuk_kuliah` int(11) DEFAULT NULL,
  `flag_feeder` int(1) DEFAULT 0,
  `kategori_ukt` varchar(2) DEFAULT NULL,
  `nominal_ukt` int(11) DEFAULT 0,
  `update_ukt` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id_mahasiswa`),
  KEY `id_prodi` (`id_prodi`),
  KEY `dosen_pa` (`dosen_pa`),
  KEY `id_status_mahasiswa` (`id_status_mahasiswa`),
  KEY `nama_status_mahasiswa` (`nama_status_mahasiswa`),
  KEY `nim` (`nim`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_mastermk` (
  `id_matkul` varchar(50) NOT NULL,
  `kode_mata_kuliah` varchar(20) DEFAULT NULL,
  `nama_mata_kuliah` varchar(150) DEFAULT NULL,
  `semester` varchar(2) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `id_jenis_mata_kuliah` varchar(1) DEFAULT NULL,
  `id_kelompok_mata_kuliah` varchar(1) DEFAULT NULL,
  `sks_mata_kuliah` varchar(5) DEFAULT NULL,
  `sks_tatap_muka` varchar(5) DEFAULT NULL,
  `sks_praktek` varchar(5) DEFAULT NULL,
  `sks_praktek_lapangan` varchar(5) DEFAULT NULL,
  `sks_simulasi` varchar(5) DEFAULT NULL,
  `metode_kuliah` varchar(50) DEFAULT NULL,
  `ada_sap` varchar(1) DEFAULT NULL,
  `ada_silabus` varchar(1) DEFAULT NULL,
  `ada_bahan_ajar` varchar(1) DEFAULT NULL,
  `ada_acara_praktek` varchar(1) DEFAULT NULL,
  `ada_diktat` varchar(1) DEFAULT NULL,
  `tanggal_mulai_efektif` date DEFAULT NULL,
  `tanggal_selesai_efektif` date DEFAULT NULL,
  `flag_feeder` int(1) DEFAULT 0,
  PRIMARY KEY (`id_matkul`),
  KEY `kode_mata_kuliah` (`kode_mata_kuliah`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_jenis_mata_kuliah` (`id_jenis_mata_kuliah`),
  KEY `id_kelompok_mata_kuliah` (`id_kelompok_mata_kuliah`),
  KEY `id_prodi` (`id_prodi`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterprodi` (
  `id_prodi` varchar(50) NOT NULL,
  `kode_program_studi` varchar(10) DEFAULT NULL,
  `nama_program_studi` varchar(50) DEFAULT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `ketua_prodi` varchar(50) DEFAULT NULL,
  `status` varchar(1) DEFAULT NULL,
  `id_jenjang_pendidikan` varchar(5) DEFAULT NULL,
  `nama_jenjang_pendidikan` varchar(20) DEFAULT NULL,
  `k1` int(11) NOT NULL,
  `k2` int(11) NOT NULL,
  `k3` int(11) NOT NULL,
  `k4` int(11) NOT NULL,
  `k5` int(11) NOT NULL,
  `k6` int(11) NOT NULL,
  `k7` int(11) NOT NULL,
  `k8` int(11) NOT NULL,
  `izin_pendirian` varchar(150) DEFAULT NULL,
  `peringkat_akreditasi` varchar(50) DEFAULT NULL,
  `gelar_lulusan` varchar(100) DEFAULT NULL,
  `visi_keilmuan` text DEFAULT NULL,
  `misi_prodi` text DEFAULT NULL,
  PRIMARY KEY (`id_prodi`),
  KEY `kode_program_studi` (`kode_program_studi`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `ketua_prodi` (`ketua_prodi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterpt` (
  `id_perguruan_tinggi` varchar(100) NOT NULL,
  `kode_perguruan_tinggi` varchar(8) DEFAULT NULL,
  `nama_perguruan_tinggi` varchar(100) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `faximile` varchar(20) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `website` varchar(250) DEFAULT NULL,
  `jalan` varchar(80) DEFAULT NULL,
  `dusun` varchar(60) DEFAULT NULL,
  `rt_rw` varchar(4) DEFAULT NULL,
  `kelurahan` varchar(60) DEFAULT NULL,
  `kode_pos` varchar(5) DEFAULT NULL,
  `id_wilayah` varchar(8) DEFAULT NULL,
  `nama_wilayah` varchar(60) DEFAULT NULL,
  `lintang_bujur` varchar(60) DEFAULT NULL,
  `bank` varchar(50) DEFAULT NULL,
  `unit_cabang` varchar(60) DEFAULT NULL,
  `nomor_rekening` varchar(20) DEFAULT NULL,
  `mbs` varchar(2) DEFAULT NULL,
  `luas_tanah_milik` varchar(7) DEFAULT NULL,
  `luas_tanah_bukan_milik` varchar(7) DEFAULT NULL,
  `sk_pendirian` varchar(80) DEFAULT NULL,
  `tanggal_sk_pendirian` varchar(10) DEFAULT NULL,
  `id_status_milik` varchar(2) DEFAULT NULL,
  `nama_status_milik` varchar(50) DEFAULT NULL,
  `status_perguruan_tinggi` varchar(1) DEFAULT NULL,
  `sk_izin_operasional` varchar(80) DEFAULT NULL,
  `tanggal_izin_operasional` varchar(10) DEFAULT NULL,
  `alamat_feeder` varchar(200) DEFAULT NULL,
  `alamat_wsdl` varchar(100) DEFAULT NULL,
  `user_feeder` varchar(100) DEFAULT NULL,
  `password_feeder` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_perguruan_tinggi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_masterruang` (
  `id_ruang` varchar(50) NOT NULL,
  `nama_ruang` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(50) DEFAULT NULL,
  `id_perguruan_tinggi` varchar(50) DEFAULT NULL,
  `id_fakultas` varchar(50) DEFAULT NULL,
  `kapasitas` varchar(4) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `flag_feeder` int(1) DEFAULT 0,
  PRIMARY KEY (`id_ruang`),
  KEY `id_prodi` (`id_prodi`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_pembayaranipi` (
  `id` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `nama_bank` varchar(100) DEFAULT NULL,
  `trx_id` varchar(100) DEFAULT NULL,
  `no_va` varchar(100) DEFAULT NULL,
  `nominal` int(11) NOT NULL,
  `tgl_expire` varchar(100) DEFAULT NULL,
  `lunas` int(11) NOT NULL,
  `tanggal_bayar` varchar(100) DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_pembayaranukt` (
  `id` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `tahun` varchar(5) NOT NULL,
  `nama_bank` varchar(100) DEFAULT NULL,
  `trx_id` varchar(100) DEFAULT NULL,
  `no_va` varchar(100) DEFAULT NULL,
  `nominal` int(11) NOT NULL,
  `tgl_expire` varchar(100) DEFAULT NULL,
  `lunas` int(11) NOT NULL,
  `tanggal_bayar` varchar(100) DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_pengesahankrs` (
  `id_pengesahan` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) DEFAULT NULL,
  `id_kalender` varchar(100) DEFAULT NULL,
  `tgl_pengesahan` date DEFAULT NULL,
  `yang_mengesahkan` varchar(100) DEFAULT NULL,
  `tgl_pengesahan_kpro` date DEFAULT NULL,
  `nama_kpro` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_pengesahan`),
  KEY `id_kalender` (`id_kalender`),
  KEY `id_mahasiswa` (`id_mahasiswa`),
  KEY `yang_mengesahkan` (`yang_mengesahkan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_peran_kegiatan` (
  `id_peran` varchar(100) NOT NULL,
  `id_anggota` varchar(100) DEFAULT NULL,
  `id_bimbing_mahasiswa` varchar(100) DEFAULT NULL,
  `id_uji` varchar(100) DEFAULT NULL,
  `id_aktivitas` varchar(100) DEFAULT NULL,
  `id_registrasi_mahasiswa` varchar(100) DEFAULT NULL,
  `id_kategori` varchar(10) DEFAULT NULL,
  `id_dosen` varchar(100) DEFAULT NULL,
  `jenis_peran` varchar(1) DEFAULT NULL,
  `pembimbing_ke` varchar(1) DEFAULT NULL,
  `penguji_ke` varchar(1) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_peran`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_periode` (
  `id_periode` varchar(100) NOT NULL,
  `nama_periode` varchar(255) NOT NULL,
  `tahun` varchar(5) DEFAULT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_akhir` date NOT NULL,
  `jenis` varchar(100) NOT NULL,
  `kuota_wisuda` int(11) DEFAULT NULL,
  `aktif` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id_periode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_registrasiulang` (
  `id_registrasi` varchar(100) NOT NULL,
  `id_perguruan_tinggi` varchar(100) DEFAULT NULL,
  `id_fakultas` varchar(100) DEFAULT NULL,
  `id_prodi` varchar(100) DEFAULT NULL,
  `id_mahasiswa` varchar(100) DEFAULT NULL,
  `id_kalender` varchar(100) DEFAULT NULL,
  `tgl_registasi` date DEFAULT NULL,
  `user` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_registrasi`),
  KEY `id_perguruan_tinggi` (`id_perguruan_tinggi`),
  KEY `id_fakultas` (`id_fakultas`),
  KEY `id_prodi` (`id_prodi`),
  KEY `id_mahasiswa` (`id_mahasiswa`),
  KEY `id_kalender` (`id_kalender`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_survey_edom` (
  `id_survey` int(11) NOT NULL AUTO_INCREMENT,
  `pertanyaan` text NOT NULL,
  PRIMARY KEY (`id_survey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `s1m4k_wisuda` (
  `id_peserta` varchar(100) NOT NULL,
  `id_periode` varchar(100) NOT NULL,
  `id_prodi` varchar(100) NOT NULL,
  `id_fakultas` varchar(100) NOT NULL,
  `id_mahasiswa` varchar(100) NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `bebas_spp` varchar(255) DEFAULT NULL,
  `bebas_perpus` varchar(255) DEFAULT NULL,
  `judul_skripsi` text DEFAULT NULL,
  `skripsi` varchar(255) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT 'null = belum verifikasi, 0 = tidak lolos, 1= lolos',
  `tgl_daftar` date DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  `lolos` varchar(1) DEFAULT NULL,
  `komentar` text DEFAULT NULL,
  `pernyataan` int(11) DEFAULT 0,
  PRIMARY KEY (`id_peserta`),
  KEY `id_peserta` (`id_peserta`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `simak_activity_log_reads` (
  `recipient_role` VARCHAR(30) NOT NULL,
  `recipient_id` VARCHAR(100) NOT NULL,
  `activity_id` CHAR(36) NOT NULL,
  `read_at` DATETIME NOT NULL,
  PRIMARY KEY (`recipient_role`, `recipient_id`, `activity_id`),
  KEY `simak_activity_log_reads_activity` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_hero_images` (
  `slot` TINYINT(3) UNSIGNED NOT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`slot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_mail_settings` (
  `id` TINYINT(3) UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `scheme` VARCHAR(20) NOT NULL DEFAULT 'smtps',
  `host` VARCHAR(255) DEFAULT NULL,
  `port` SMALLINT(5) UNSIGNED DEFAULT NULL,
  `username` VARCHAR(255) DEFAULT NULL,
  `password_encrypted` TEXT DEFAULT NULL,
  `from_address` VARCHAR(255) DEFAULT NULL,
  `from_name` VARCHAR(255) DEFAULT NULL,
  `last_tested_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_payment_gateways` (
  `gateway` VARCHAR(40) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `environment` VARCHAR(20) NOT NULL DEFAULT 'sandbox',
  `client_key` VARCHAR(255) DEFAULT NULL,
  `service_fee` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `credentials_encrypted` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`gateway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_payment_transactions` (
  `id` CHAR(36) NOT NULL,
  `gateway` VARCHAR(40) NOT NULL,
  `payment_type` VARCHAR(10) NOT NULL,
  `payment_id` VARCHAR(100) NOT NULL,
  `student_id` VARCHAR(100) NOT NULL,
  `order_id` VARCHAR(100) NOT NULL,
  `gross_amount` BIGINT(20) UNSIGNED NOT NULL,
  `service_fee` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `snap_token` VARCHAR(255) DEFAULT NULL,
  `redirect_url` VARCHAR(1000) DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `notification_received_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `simak_payment_transactions_order_id_unique` (`order_id`),
  KEY `simak_payment_transactions_payment_lookup` (`payment_type`,`payment_id`,`created_at`),
  KEY `simak_payment_transactions_student_lookup` (`student_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_manual_bills` (
  `id` CHAR(36) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `payer_name` VARCHAR(100) NOT NULL,
  `payer_email` VARCHAR(255) DEFAULT NULL,
  `payer_phone` VARCHAR(30) DEFAULT NULL,
  `amount` BIGINT(20) UNSIGNED NOT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `checkout_status` VARCHAR(20) NOT NULL DEFAULT 'creating',
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `simak_manual_bills_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `simak_site_settings` (
  `id` TINYINT(3) UNSIGNED NOT NULL,
  `ministry_name` VARCHAR(255) NOT NULL DEFAULT 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS DAN TEKNOLOGI',
  `motto` VARCHAR(255) NOT NULL DEFAULT 'Motto Perguruan Tinggi',
  `primary_color` CHAR(7) NOT NULL DEFAULT '#2167a5',
  `logo_path` VARCHAR(500) DEFAULT NULL,
  `preloader_logo_path` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `validasi_dok` (
  `id` varchar(100) NOT NULL,
  `id_dokumen` varchar(100) NOT NULL COMMENT 'Saat ini id_skripsi gunakan UUID, id_dokumen global : saat ini menyimpan, id_skripsi namun kedepan bisa gunakan id dokumen lainnya',
  `jenis_dokumen` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

SET FOREIGN_KEY_CHECKS=1;
