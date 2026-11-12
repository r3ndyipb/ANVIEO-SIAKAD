<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $conn = DB::connection('simak_main');
        $now = now();

        // =========================================================================
        // 1. Master Kurikulum (s1m4k_kurikulum)
        // Menjangkau 8 Fakultas & 3 Jenjang (S1 Bachelor, S2 Master, S3 Doctorate)
        // =========================================================================
        $curriculums = [
            // FBE (Faculty of Business and Economics)
            [
                'id_kurikulum' => 'KUR-BE-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Manajemen Bisnis',
                'id_prodi' => 'PRODI-BE-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],
            [
                'id_kurikulum' => 'KUR-BE-S2-2026',
                'nama_kurikulum' => 'Kurikulum Magister 2026 S2 Magister Manajemen',
                'id_prodi' => 'PRODI-BE-S2',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '36',
                'jumlah_sks_wajib' => '30',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '30',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],
            [
                'id_kurikulum' => 'KUR-BE-S3-2026',
                'nama_kurikulum' => 'Kurikulum Doktoral 2026 S3 Ilmu Ekonomi',
                'id_prodi' => 'PRODI-BE-S3',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '42',
                'jumlah_sks_wajib' => '36',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '36',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],

            // FET (Faculty of Engineering and Technology)
            [
                'id_kurikulum' => 'KUR-ET-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Teknik Informatika',
                'id_prodi' => 'PRODI-ET-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],
            [
                'id_kurikulum' => 'KUR-ET-SI-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Sistem Informasi',
                'id_prodi' => 'PRODI-ET-SI',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '122',
                'jumlah_sks_pilihan' => '22',
                'jumlah_sks_mata_kuliah_wajib' => '122',
                'jumlah_sks_mata_kuliah_pilihan' => '22',
            ],
            [
                'id_kurikulum' => 'KUR-ET-S2-2026',
                'nama_kurikulum' => 'Kurikulum Magister 2026 S2 Teknik Informatika',
                'id_prodi' => 'PRODI-ET-S2',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '36',
                'jumlah_sks_wajib' => '30',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '30',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],
            [
                'id_kurikulum' => 'KUR-ET-S3-2026',
                'nama_kurikulum' => 'Kurikulum Doktoral 2026 S3 Ilmu Komputer',
                'id_prodi' => 'PRODI-ET-S3',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '42',
                'jumlah_sks_wajib' => '36',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '36',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],

            // FSH (Faculty of Social Sciences and Humanities)
            [
                'id_kurikulum' => 'KUR-SH-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Ilmu Komunikasi',
                'id_prodi' => 'PRODI-SH-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],
            [
                'id_kurikulum' => 'KUR-SH-S2-2026',
                'nama_kurikulum' => 'Kurikulum Magister 2026 S2 Ilmu Sosial',
                'id_prodi' => 'PRODI-SH-S2',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '36',
                'jumlah_sks_wajib' => '30',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '30',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],

            // FLW (Faculty of Law)
            [
                'id_kurikulum' => 'KUR-LW-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Ilmu Hukum',
                'id_prodi' => 'PRODI-LW-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],
            [
                'id_kurikulum' => 'KUR-LW-S2-2026',
                'nama_kurikulum' => 'Kurikulum Magister 2026 S2 Magister Hukum',
                'id_prodi' => 'PRODI-LW-S2',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '36',
                'jumlah_sks_wajib' => '30',
                'jumlah_sks_pilihan' => '6',
                'jumlah_sks_mata_kuliah_wajib' => '30',
                'jumlah_sks_mata_kuliah_pilihan' => '6',
            ],

            // FHM (Faculty of Humaniora)
            [
                'id_kurikulum' => 'KUR-HM-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Sastra & Humaniora',
                'id_prodi' => 'PRODI-HM-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],

            // FHS (Faculty of Health Sciences)
            [
                'id_kurikulum' => 'KUR-HS-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Kesehatan Masyarakat',
                'id_prodi' => 'PRODI-HS-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],

            // FIS (Faculty of Islamic Studies)
            [
                'id_kurikulum' => 'KUR-IS-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Hukum Ekonomi Syariah',
                'id_prodi' => 'PRODI-IS-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],

            // FPS (Faculty of Politics and Social Sciences)
            [
                'id_kurikulum' => 'KUR-PS-S1-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Hubungan Internasional',
                'id_prodi' => 'PRODI-PS-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],

            // Backward compatibility aliases
            [
                'id_kurikulum' => 'KUR-TI-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Teknik Informatika',
                'id_prodi' => 'PRODI-ET-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '120',
                'jumlah_sks_pilihan' => '24',
                'jumlah_sks_mata_kuliah_wajib' => '120',
                'jumlah_sks_mata_kuliah_pilihan' => '24',
            ],
            [
                'id_kurikulum' => 'KUR-SI-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Sistem Informasi',
                'id_prodi' => 'PRODI-ET-SI',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '122',
                'jumlah_sks_pilihan' => '22',
                'jumlah_sks_mata_kuliah_wajib' => '122',
                'jumlah_sks_mata_kuliah_pilihan' => '22',
            ],
            [
                'id_kurikulum' => 'KUR-MN-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Manajemen',
                'id_prodi' => 'PRODI-BE-S1',
                'id_semester' => '20261',
                'semester_mulai_berlaku' => '2026/2027 Ganjil',
                'jumlah_sks_lulus' => '144',
                'jumlah_sks_wajib' => '118',
                'jumlah_sks_pilihan' => '26',
                'jumlah_sks_mata_kuliah_wajib' => '118',
                'jumlah_sks_mata_kuliah_pilihan' => '26',
            ],
        ];

        foreach ($curriculums as $kur) {
            $conn->table('s1m4k_kurikulum')->updateOrInsert(
                ['id_kurikulum' => $kur['id_kurikulum']],
                $kur
            );
        }

        // =========================================================================
        // 2. Master Mata Kuliah (s1m4k_mastermk)
        // =========================================================================
        $courses = [
            // --- FET (Faculty of Engineering and Technology) ---
            [
                'id_matkul' => 'MK-ET-101',
                'kode_mata_kuliah' => 'ET101',
                'nama_mata_kuliah' => 'Artificial Intelligence & Deep Learning',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-ET-102',
                'kode_mata_kuliah' => 'ET102',
                'nama_mata_kuliah' => 'Modern Software Architecture & Cloud Systems',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF101',
                'kode_mata_kuliah' => 'TIF101',
                'nama_mata_kuliah' => 'Algoritma dan Pemrograman Dasar',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF102',
                'kode_mata_kuliah' => 'TIF102',
                'nama_mata_kuliah' => 'Matematika Diskrit',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF201',
                'kode_mata_kuliah' => 'TIF201',
                'nama_mata_kuliah' => 'Struktur Data dan Algoritma Lanjut',
                'semester' => '2',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF301',
                'kode_mata_kuliah' => 'TIF301',
                'nama_mata_kuliah' => 'Sistem Basis Data & SQL',
                'semester' => '3',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF302',
                'kode_mata_kuliah' => 'TIF302',
                'nama_mata_kuliah' => 'Sistem Operasi Komputer',
                'semester' => '3',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF401',
                'kode_mata_kuliah' => 'TIF401',
                'nama_mata_kuliah' => 'Rekayasa Perangkat Lunak',
                'semester' => '4',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF402',
                'kode_mata_kuliah' => 'TIF402',
                'nama_mata_kuliah' => 'Jaringan Komputer dan Komunikasi Data',
                'semester' => '4',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF501',
                'kode_mata_kuliah' => 'TIF501',
                'nama_mata_kuliah' => 'Keamanan Siber dan Kriptografi',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF502',
                'kode_mata_kuliah' => 'TIF502',
                'nama_mata_kuliah' => 'Pembelajaran Mesin dan Kecerdasan Buatan',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF701',
                'kode_mata_kuliah' => 'TIF701',
                'nama_mata_kuliah' => 'Metodologi Penelitian Komputasi',
                'semester' => '7',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '2',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF702',
                'kode_mata_kuliah' => 'TIF702',
                'nama_mata_kuliah' => 'Kerja Praktik / Magang Industri',
                'semester' => '7',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '2',
                'sks_tatap_muka' => '0',
                'sks_praktek' => '2',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF801',
                'kode_mata_kuliah' => 'TIF801',
                'nama_mata_kuliah' => 'Skripsi dan Tugas Akhir Komputasi',
                'semester' => '8',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '6',
                'sks_tatap_muka' => '0',
                'sks_praktek' => '6',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // FET Pascasarjana (S2 & S3)
            [
                'id_matkul' => 'MK-ET-201',
                'kode_mata_kuliah' => 'ET801',
                'nama_mata_kuliah' => 'Autonomous Systems & Advanced Neural Computing',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-ET-202',
                'kode_mata_kuliah' => 'ET802',
                'nama_mata_kuliah' => 'Advanced Cloud Architectures & Distributed Systems',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-ET-301',
                'kode_mata_kuliah' => 'ET901',
                'nama_mata_kuliah' => 'Advanced Research Methodology in Computing',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-ET-S3',
                'id_fakultas' => 'FK-FET',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FBE (Faculty of Business and Economics) ---
            [
                'id_matkul' => 'MK-BE-101',
                'kode_mata_kuliah' => 'BE101',
                'nama_mata_kuliah' => 'Strategic Management & Global Business',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-BE-102',
                'kode_mata_kuliah' => 'BE102',
                'nama_mata_kuliah' => 'Managerial Economics & Market Analysis',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-BE-201',
                'kode_mata_kuliah' => 'BE801',
                'nama_mata_kuliah' => 'Executive Leadership & Corporate Transformation',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FSH (Faculty of Social Sciences and Humanities) ---
            [
                'id_matkul' => 'MK-SH-101',
                'kode_mata_kuliah' => 'SH101',
                'nama_mata_kuliah' => 'Global Digital Communication & Social Media',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-SH-S1',
                'id_fakultas' => 'FK-FSH',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FLW (Faculty of Law) ---
            [
                'id_matkul' => 'MK-LW-101',
                'kode_mata_kuliah' => 'LW101',
                'nama_mata_kuliah' => 'International Regulatory Standards & Trade Law',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FHM (Faculty of Humaniora) ---
            [
                'id_matkul' => 'MK-HM-101',
                'kode_mata_kuliah' => 'HM101',
                'nama_mata_kuliah' => 'World Literature & Cross-Cultural Perspectives',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-HM-S1',
                'id_fakultas' => 'FK-FHM',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FHS (Faculty of Health Sciences) ---
            [
                'id_matkul' => 'MK-HS-101',
                'kode_mata_kuliah' => 'HS101',
                'nama_mata_kuliah' => 'Public Wellness & Healthcare Systems Management',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-HS-S1',
                'id_fakultas' => 'FK-FHS',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FIS (Faculty of Islamic Studies) ---
            [
                'id_matkul' => 'MK-IS-101',
                'kode_mata_kuliah' => 'IS101',
                'nama_mata_kuliah' => 'Islamic Jurisprudence & Contemporary Civilizations',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // --- FPS (Faculty of Politics and Social Sciences) ---
            [
                'id_matkul' => 'MK-PS-101',
                'kode_mata_kuliah' => 'PS101',
                'nama_mata_kuliah' => 'International Relations & Global Governance',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-PS-S1',
                'id_fakultas' => 'FK-FPS',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
        ];

        foreach ($courses as $mk) {
            $conn->table('s1m4k_mastermk')->updateOrInsert(
                ['id_matkul' => $mk['id_matkul']],
                array_merge($mk, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_jenis_mata_kuliah' => 'A',
                    'id_kelompok_mata_kuliah' => 'A',
                    'metode_kuliah' => 'Blended Learning',
                    'ada_sap' => 'Y',
                    'ada_silabus' => 'Y',
                    'ada_bahan_ajar' => 'Y',
                    'ada_acara_praktek' => 'Y',
                    'ada_diktat' => 'Y',
                    'tanggal_mulai_efektif' => '2024-01-01',
                    'tanggal_selesai_efektif' => '2030-12-31',
                    'flag_feeder' => 0,
                ])
            );
        }

        // =========================================================================
        // 3. Master Jadwal Kuliah (s1m4k_jadwal)
        // =========================================================================
        $schedules = [
            // Semester 20261 (Ganjil Aktif)
            [
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF501',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-ET-01',
                'id_ruang' => 'LAB-KOMP-1',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Senin',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 3,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-502A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF502',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-004',
                'id_ruang' => 'RUANG-102',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Selasa',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 3,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-101A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF101',
                'nama_kelas' => '1A',
                'dosen1' => 'DOSEN-ET-01',
                'id_ruang' => 'LAB-KOMP-1',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Rabu',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-102A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF102',
                'nama_kelas' => '1A',
                'dosen1' => 'DOSEN-004',
                'id_ruang' => 'RUANG-102',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Kamis',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-701A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF701',
                'nama_kelas' => '7A',
                'dosen1' => 'DOSEN-ET-02',
                'id_ruang' => 'RUANG-SEM',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Kamis',
                'jammulai' => '13:00:00',
                'jamselesai' => '14:40:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-801A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF801',
                'nama_kelas' => '8A',
                'dosen1' => 'DOSEN-ET-01',
                'id_ruang' => 'RUANG-SEM',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Senin',
                'jammulai' => '09:00:00',
                'jamselesai' => '12:00:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-BE-101A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-BE-101',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-BE-01',
                'id_ruang' => 'RUANG-101',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'hari' => 'Selasa',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-LW-101A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-LW-101',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-LW-01',
                'id_ruang' => 'LAB-HUKUM',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'hari' => 'Rabu',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-IS-101A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-IS-101',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-IS-01',
                'id_ruang' => 'RUANG-107',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'hari' => 'Kamis',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-ET-201A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-ET-201',
                'nama_kelas' => '1A-S2',
                'dosen1' => 'DOSEN-ET-02',
                'id_ruang' => 'RUANG-102',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Jumat',
                'jammulai' => '13:30:00',
                'jamselesai' => '16:00:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-BE-201A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-BE-201',
                'nama_kelas' => '3A-S2',
                'dosen1' => 'DOSEN-BE-02',
                'id_ruang' => 'RUANG-101',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'hari' => 'Sabtu',
                'jammulai' => '09:00:00',
                'jamselesai' => '12:00:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],

            // Semester 20252 (Genap Lampau - Lengkap dengan Nilai)
            [
                'id_jadwal' => 'JADWAL-TI-401A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF401',
                'nama_kelas' => '4A',
                'dosen1' => 'DOSEN-ET-01',
                'id_ruang' => 'RUANG-102',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Senin',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'Y',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-402A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF402',
                'nama_kelas' => '4A',
                'dosen1' => 'DOSEN-005',
                'id_ruang' => 'LAB-KOMP-2',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Selasa',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'Y',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-801A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF801',
                'nama_kelas' => '8A',
                'dosen1' => 'DOSEN-ET-02',
                'id_ruang' => 'RUANG-SEM',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'hari' => 'Kamis',
                'jammulai' => '09:00:00',
                'jamselesai' => '12:00:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'Y',
            ],
        ];

        foreach ($schedules as $s) {
            $conn->table('s1m4k_jadwal')->updateOrInsert(
                ['id_jadwal' => $s['id_jadwal']],
                array_merge($s, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'kampus_merdeka' => '0',
                ])
            );
        }

        // =========================================================================
        // 4. KRS & KHS Nilai Mahasiswa (s1m4k_krs)
        // =========================================================================
        $krsRecords = [
            // --- Mahasiswa MHS-001 (Ahmad Fauzi, Angkatan 20261, Smt 1 Baru) ---
            [
                'id_krs' => 'KRS-20261-001-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-001',
                'id_jadwal' => 'JADWAL-TI-101A',
                'id_matkul' => 'MK-TIF101',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 85.00,
                'nilai_quiz' => 88.00,
                'nilai_tugas' => 90.00,
                'nilai_uts' => 86.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-001-02',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-001',
                'id_jadwal' => 'JADWAL-TI-102A',
                'id_matkul' => 'MK-TIF102',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 88.00,
                'nilai_quiz' => 85.00,
                'nilai_tugas' => 87.00,
                'nilai_uts' => 85.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-003 (Kevin Pratama, Angkatan 20241, Smt 5 Aktif) ---
            [
                'id_krs' => 'KRS-20261-003-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_matkul' => 'MK-TIF501',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 90.00,
                'nilai_quiz' => 85.00,
                'nilai_tugas' => 92.00,
                'nilai_uts' => 88.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-003-02',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-502A',
                'id_matkul' => 'MK-TIF502',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 88.00,
                'nilai_quiz' => 84.00,
                'nilai_tugas' => 89.00,
                'nilai_uts' => 86.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-004 (Putri Ayu, Angkatan 20241, Smt 5 Aktif) ---
            [
                'id_krs' => 'KRS-20261-004-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_matkul' => 'MK-TIF501',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 92.00,
                'nilai_quiz' => 90.00,
                'nilai_tugas' => 95.00,
                'nilai_uts' => 91.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-007 (Farhan Alamsyah, Angkatan 20241, Smt 5 Hukum) ---
            [
                'id_krs' => 'KRS-20261-007-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-007',
                'id_jadwal' => 'JADWAL-LW-101A',
                'id_matkul' => 'MK-LW-101',
                'id_fakultas' => 'FK-FLW',
                'id_prodi' => 'PRODI-LW-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 85.00,
                'nilai_quiz' => 88.00,
                'nilai_tugas' => 87.00,
                'nilai_uts' => 85.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-008 (Nadya Zahrani, Angkatan 20241, Smt 5 Manajemen) ---
            [
                'id_krs' => 'KRS-20261-008-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-008',
                'id_jadwal' => 'JADWAL-BE-101A',
                'id_matkul' => 'MK-BE-101',
                'id_fakultas' => 'FK-FBE',
                'id_prodi' => 'PRODI-BE-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 90.00,
                'nilai_quiz' => 88.00,
                'nilai_tugas' => 92.00,
                'nilai_uts' => 89.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-005 (Rizky Ramadhan, Angkatan 20221, Smt 7 Bimbingan Skripsi) ---
            [
                'id_krs' => 'KRS-20261-005-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-005',
                'id_jadwal' => 'JADWAL-TI-801A',
                'id_matkul' => 'MK-TIF801',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 88.00,
                'nilai_quiz' => 0.00,
                'nilai_tugas' => 89.00,
                'nilai_uts' => 88.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-S2-001 (Jessica Williams, Angkatan 20261, S2 FET) ---
            [
                'id_krs' => 'KRS-20261-S2-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-S2-001',
                'id_jadwal' => 'JADWAL-ET-201A',
                'id_matkul' => 'MK-ET-201',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S2',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 92.00,
                'nilai_quiz' => 90.00,
                'nilai_tugas' => 94.00,
                'nilai_uts' => 92.00,
                'nilai_uas' => 0.00,
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-006 (Dewi Kartika, Lulus - Riwayat Nilai Semester Genap 20252) ---
            [
                'id_krs' => 'KRS-20252-006-01',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-006',
                'id_jadwal' => 'JADWAL-TI-801A-252',
                'id_matkul' => 'MK-TIF801',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 95.00,
                'nilai_proyek' => 96.00,
                'nilai_quiz' => 92.00,
                'nilai_tugas' => 95.00,
                'nilai_uts' => 94.00,
                'nilai_uas' => 96.00,
                'nilai_angka' => 95.20,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],

            // Riwayat Kevin Pratama Semester 20252 (Genap)
            [
                'id_krs' => 'KRS-20252-003-01',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-401A-252',
                'id_matkul' => 'MK-TIF401',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 88.00,
                'nilai_proyek' => 90.00,
                'nilai_quiz' => 85.00,
                'nilai_tugas' => 88.00,
                'nilai_uts' => 87.00,
                'nilai_uas' => 89.00,
                'nilai_angka' => 88.20,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],
            [
                'id_krs' => 'KRS-20252-003-02',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-402A-252',
                'id_matkul' => 'MK-TIF402',
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 85.00,
                'nilai_proyek' => 86.00,
                'nilai_quiz' => 82.00,
                'nilai_tugas' => 86.00,
                'nilai_uts' => 84.00,
                'nilai_uas' => 88.00,
                'nilai_angka' => 85.80,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],
        ];

        foreach ($krsRecords as $krs) {
            $conn->table('s1m4k_krs')->updateOrInsert(
                ['id_krs' => $krs['id_krs']],
                array_merge($krs, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'krs_reguler' => '1',
                    'tgl_update' => $now,
                    'user' => 'SYSTEM_SEED',
                    'flag_feeder' => 0,
                    'krs_mbkm' => 'N',
                    'isi_edom' => 'Y',
                ])
            );
        }

        // =========================================================================
        // 5. Pengesahan KRS (s1m4k_pengesahankrs)
        // =========================================================================
        $approvals = [
            [
                'id_pengesahan' => 'PENG-20261-001',
                'id_mahasiswa' => 'MHS-001',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-15 10:00:00',
                'yang_mengesahkan' => 'Dr. Budi Santoso, M.Kom.',
                'tgl_pengesahan_kpro' => '2026-08-16 11:30:00',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'PENG-20261-003',
                'id_mahasiswa' => 'MHS-003',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-10 09:15:00',
                'yang_mengesahkan' => 'Dian Kusuma, M.Cs.',
                'tgl_pengesahan_kpro' => '2026-08-11 14:00:00',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'PENG-20261-004',
                'id_mahasiswa' => 'MHS-004',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-10 09:30:00',
                'yang_mengesahkan' => 'Dian Kusuma, M.Cs.',
                'tgl_pengesahan_kpro' => '2026-08-11 14:00:00',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'PENG-20261-005',
                'id_mahasiswa' => 'MHS-005',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-12 11:00:00',
                'yang_mengesahkan' => 'Dr. Budi Santoso, M.Kom.',
                'tgl_pengesahan_kpro' => '2026-08-13 15:00:00',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
        ];

        foreach ($approvals as $appr) {
            $conn->table('s1m4k_pengesahankrs')->updateOrInsert(
                ['id_pengesahan' => $appr['id_pengesahan']],
                $appr
            );
        }

        // =========================================================================
        // 6. Aktivitas Kuliah Mahasiswa (s1m4k_akm)
        // =========================================================================
        $akmRecords = [
            // Dewi Kartika (Lulus S1 - Cum Laude)
            [
                'id_ipmhs' => 'AKM-20252-006',
                'id_prodi' => 'PRODI-ET-S1',
                'id_mahasiswa' => 'MHS-006',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 4.00,
                'ipk' => 3.94,
                'status_mahasiswa' => 'L',
                'sks_semester' => 6,
                'sks_lulus' => 144,
                'sks_program' => 144,
                'maks_sks_program' => 144,
                'no_ijazah' => 'AUI-2025-S1-00192',
                'tgl_keluar' => '2025-08-20',
                'ket' => 'Lulus dengan predikat Cum Laude',
                'no_sk' => 'SK-REK/2025/YUD-088',
                'tgl_sk' => '2025-08-18',
            ],
            // Dr. Raymond Holt (Lulus S3 Doktor - Summa Cum Laude)
            [
                'id_ipmhs' => 'AKM-20252-S3-001',
                'id_prodi' => 'PRODI-ET-S3',
                'id_mahasiswa' => 'MHS-S3-001',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 4.00,
                'ipk' => 4.00,
                'status_mahasiswa' => 'L',
                'sks_semester' => 12,
                'sks_lulus' => 42,
                'sks_program' => 42,
                'maks_sks_program' => 42,
                'no_ijazah' => 'AUI-2025-S3-00015',
                'tgl_keluar' => '2025-08-20',
                'ket' => 'Lulus Program Doktor dengan predikat Summa Cum Laude',
                'no_sk' => 'SK-REK/2025/YUD-089',
                'tgl_sk' => '2025-08-18',
            ],
            // Rizky Ramadhan (Aktif Semester 7 - Bimbingan Skripsi)
            [
                'id_ipmhs' => 'AKM-20252-005',
                'id_prodi' => 'PRODI-ET-S1',
                'id_mahasiswa' => 'MHS-005',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 3.75,
                'ipk' => 3.68,
                'status_mahasiswa' => 'A',
                'sks_semester' => 20,
                'sks_lulus' => 138,
                'sks_program' => 144,
                'maks_sks_program' => 144,
                'no_ijazah' => null,
                'tgl_keluar' => null,
                'ket' => 'Aktif Penyusunan Skripsi',
                'no_sk' => null,
                'tgl_sk' => null,
            ],
            // Kevin Pratama (Aktif Semester 5)
            [
                'id_ipmhs' => 'AKM-20252-003',
                'id_prodi' => 'PRODI-ET-S1',
                'id_mahasiswa' => 'MHS-003',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 3.85,
                'ipk' => 3.82,
                'status_mahasiswa' => 'A',
                'sks_semester' => 22,
                'sks_lulus' => 92,
                'sks_program' => 144,
                'maks_sks_program' => 144,
                'no_ijazah' => null,
                'tgl_keluar' => null,
                'ket' => 'Aktif',
                'no_sk' => null,
                'tgl_sk' => null,
            ],
            // Putri Ayu Lestari (Aktif Semester 5)
            [
                'id_ipmhs' => 'AKM-20252-004',
                'id_prodi' => 'PRODI-ET-S1',
                'id_mahasiswa' => 'MHS-004',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 3.90,
                'ipk' => 3.88,
                'status_mahasiswa' => 'A',
                'sks_semester' => 22,
                'sks_lulus' => 92,
                'sks_program' => 144,
                'maks_sks_program' => 144,
                'no_ijazah' => null,
                'tgl_keluar' => null,
                'ket' => 'Aktif',
                'no_sk' => null,
                'tgl_sk' => null,
            ],
            // Marcus Chen (Aktif Semester 3 - Tesis Pascasarjana)
            [
                'id_ipmhs' => 'AKM-20252-S2-002',
                'id_prodi' => 'PRODI-BE-S2',
                'id_mahasiswa' => 'MHS-S2-002',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ips' => 3.95,
                'ipk' => 3.90,
                'status_mahasiswa' => 'A',
                'sks_semester' => 12,
                'sks_lulus' => 30,
                'sks_program' => 36,
                'maks_sks_program' => 36,
                'no_ijazah' => null,
                'tgl_keluar' => null,
                'ket' => 'Aktif Penyusunan Tesis Magister',
                'no_sk' => null,
                'tgl_sk' => null,
            ],
        ];

        foreach ($akmRecords as $akm) {
            $conn->table('s1m4k_akm')->updateOrInsert(
                ['id_ipmhs' => $akm['id_ipmhs']],
                array_merge($akm, [
                    'update_akm' => $now,
                    'simpan' => 'Y',
                    'flag_feeder' => 0,
                ])
            );
        }

        // =========================================================================
        // 7. Billing & Pembayaran UKT (s1m4k_billing, s1m4k_pembayaranukt)
        // =========================================================================
        $billings = [
            ['id_billing' => 'BILL-20261-001', 'id_mahasiswa' => 'MHS-001', 'trx_id' => 'TRX-AUI-20261-001', 'billing' => 'INV/2026/08/001', 'nominal' => 5000000, 'expire' => '2026-09-30 23:59:59', 'tahun' => '20261'],
            ['id_billing' => 'BILL-20261-002', 'id_mahasiswa' => 'MHS-002', 'trx_id' => 'TRX-AUI-20261-002', 'billing' => 'INV/2026/08/002', 'nominal' => 5000000, 'expire' => '2026-09-30 23:59:59', 'tahun' => '20261'],
            ['id_billing' => 'BILL-20261-003', 'id_mahasiswa' => 'MHS-003', 'trx_id' => 'TRX-AUI-20261-003', 'billing' => 'INV/2026/08/003', 'nominal' => 6000000, 'expire' => '2026-09-30 23:59:59', 'tahun' => '20261'],
            ['id_billing' => 'BILL-20261-004', 'id_mahasiswa' => 'MHS-004', 'trx_id' => 'TRX-AUI-20261-004', 'billing' => 'INV/2026/08/004', 'nominal' => 6000000, 'expire' => '2026-09-30 23:59:59', 'tahun' => '20261'],
            ['id_billing' => 'BILL-20261-S2-1', 'id_mahasiswa' => 'MHS-S2-001', 'trx_id' => 'TRX-AUI-20261-S2-001', 'billing' => 'INV/2026/08/S2-001', 'nominal' => 9500000, 'expire' => '2026-09-30 23:59:59', 'tahun' => '20261'],
        ];

        foreach ($billings as $b) {
            $conn->table('s1m4k_billing')->updateOrInsert(
                ['id_billing' => $b['id_billing']],
                $b
            );
        }

        // Pembayaran UKT:
        // MHS-001: Settlement (Lunas)
        // MHS-002: Pending (Simulasi financial gate KRS)
        // MHS-003: Settlement (Lunas)
        // MHS-004: Settlement (Lunas)
        // MHS-S2-001: Settlement (Lunas)
        $payments = [
            [
                'id_pembayaran' => 'PAY-20261-001',
                'id_mahasiswa' => 'MHS-001',
                'id_billing' => 'BILL-20261-001',
                'tahun' => '20261',
                'nominal' => 5000000,
                'metode_pembayaran' => 'Bank Mandiri VA',
                'nomor_transaksi' => 'MND-VA-88992026001001',
                'status_pembayaran' => 'settlement',
                'tanggal_pembayaran' => '2026-08-02 14:22:10',
            ],
            [
                'id_pembayaran' => 'PAY-20261-002',
                'id_mahasiswa' => 'MHS-002',
                'id_billing' => 'BILL-20261-002',
                'tahun' => '20261',
                'nominal' => 5000000,
                'metode_pembayaran' => 'BCA Virtual Account',
                'nomor_transaksi' => 'BCA-VA-11222026001002',
                'status_pembayaran' => 'pending',
                'tanggal_pembayaran' => null,
            ],
            [
                'id_pembayaran' => 'PAY-20261-003',
                'id_mahasiswa' => 'MHS-003',
                'id_billing' => 'BILL-20261-003',
                'tahun' => '20261',
                'nominal' => 6000000,
                'metode_pembayaran' => 'BRI Virtual Account',
                'nomor_transaksi' => 'BRI-VA-33442024001003',
                'status_pembayaran' => 'settlement',
                'tanggal_pembayaran' => '2026-08-01 10:15:30',
            ],
            [
                'id_pembayaran' => 'PAY-20261-004',
                'id_mahasiswa' => 'MHS-004',
                'id_billing' => 'BILL-20261-004',
                'tahun' => '20261',
                'nominal' => 6000000,
                'metode_pembayaran' => 'BSI Virtual Account',
                'nomor_transaksi' => 'BSI-VA-55662024001004',
                'status_pembayaran' => 'settlement',
                'tanggal_pembayaran' => '2026-08-01 11:45:00',
            ],
            [
                'id_pembayaran' => 'PAY-20261-S2-1',
                'id_mahasiswa' => 'MHS-S2-001',
                'id_billing' => 'BILL-20261-S2-1',
                'tahun' => '20261',
                'nominal' => 9500000,
                'metode_pembayaran' => 'BNI Virtual Account',
                'nomor_transaksi' => 'BNI-VA-77882026002001',
                'status_pembayaran' => 'settlement',
                'tanggal_pembayaran' => '2026-08-03 09:20:00',
            ],
        ];

        foreach ($payments as $p) {
            $conn->table('s1m4k_pembayaranukt')->updateOrInsert(
                ['id_pembayaran' => $p['id_pembayaran']],
                $p
            );
        }

        // =========================================================================
        // 8. Penerimaan Mahasiswa Baru / PMB (s1m4k_camaba)
        // =========================================================================
        $camabaList = [
            [
                'id' => 'CAMABA-2026-001',
                'no_peserta' => 'PMB-2026-001',
                'nim' => '2026001003',
                'nama_mahasiswa' => 'Muhammad Ilham Pratama',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '2008-03-15',
                'id_agama' => '1',
                'id_negara' => 'ID',
                'nik' => '3171011503080001',
                'nisn' => '0081234561',
                'telepon' => '021-778899',
                'handphone' => '081299887701',
                'email' => 'ilham.pratama@gmail.com',
                'id_jenis_daftar' => '1',
                'id_jalur_daftar' => '1',
                'id_periode_masuk' => '20261',
                'penerima_kps' => '0',
                'tanggal_daftar' => '2026-06-01',
                'biaya_masuk_kuliah' => 7500000,
                'status' => 1,
                'id_fakultas' => 'FK-FET',
                'id_prodi' => 'PRODI-ET-S1',
            ],
            [
                'id' => 'CAMABA-2026-002',
                'no_peserta' => 'PMB-2026-002',
                'nim' => '2026001004',
                'nama_mahasiswa' => 'Anisa Rahmawati',
                'jenis_kelamin' => 'P',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2008-07-20',
                'id_agama' => '1',
                'id_negara' => 'ID',
                'nik' => '3273012007080002',
                'nisn' => '0081234562',
                'telepon' => '022-665544',
                'handphone' => '081299887702',
                'email' => 'anisa.rahma@gmail.com',
                'id_jenis_daftar' => '1',
                'id_jalur_daftar' => '2',
                'id_periode_masuk' => '20261',
                'penerima_kps' => '0',
                'tanggal_daftar' => '2026-06-05',
                'biaya_masuk_kuliah' => 7500000,
                'status' => 1,
                'id_fakultas' => 'FK-FBE',
                'id_prodi' => 'PRODI-BE-S1',
            ],
            [
                'id' => 'CAMABA-2026-003',
                'no_peserta' => 'PMB-2026-003',
                'nim' => '2026001005',
                'nama_mahasiswa' => 'Bagas Prasetyo Nugroho',
                'jenis_kelamin' => 'L',
                'tempat_lahir' => 'Surabaya',
                'tanggal_lahir' => '2008-01-10',
                'id_agama' => '1',
                'id_negara' => 'ID',
                'nik' => '3578011001080003',
                'nisn' => '0081234563',
                'telepon' => '031-443322',
                'handphone' => '081299887703',
                'email' => 'bagas.nugroho@gmail.com',
                'id_jenis_daftar' => '1',
                'id_jalur_daftar' => '3',
                'id_periode_masuk' => '20261',
                'penerima_kps' => '0',
                'tanggal_daftar' => '2026-06-10',
                'biaya_masuk_kuliah' => 10000000,
                'status' => 1,
                'id_fakultas' => 'FK-FLW',
                'id_prodi' => 'PRODI-LW-S1',
            ],
        ];

        foreach ($camabaList as $cam) {
            $conn->table('s1m4k_camaba')->updateOrInsert(
                ['id' => $cam['id']],
                array_merge($cam, [
                    'id_perguruan_tinggi' => 'PT-001',
                ])
            );
        }

        // =========================================================================
        // 9. E-Skripsi / Tugas Akhir S1 (eskripsi_*)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('eskripsi_judul')) {
            $conn->table('eskripsi_judul')->updateOrInsert(
                ['id' => 'JUDUL-001'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                    'id_mahasiswa' => 'MHS-005',
                    'judul' => 'Rancang Bangun Sistem Informasi Akademik Terdistribusi Menggunakan PostgreSQL Concurrency Locking',
                    'deskripsi' => 'Penelitian ini mengembangkan arsitektur row-level locking pada transaksi KRS untuk mencegah overbooking kapasitas kelas.',
                    'status' => 1,
                    'komentar' => 'Judul dan metodologi disetujui oleh Kaprodi.',
                    'tanggal_usul' => '2026-02-10',
                    'tanggal_verifikasi' => '2026-02-15',
                ]
            );
            $conn->table('eskripsi_judul')->updateOrInsert(
                ['id' => 'JUDUL-002'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                    'id_mahasiswa' => 'MHS-006',
                    'judul' => 'Implementasi Convolutional Neural Network untuk Klasifikasi Citra Medis Realtime',
                    'deskripsi' => 'Pengembangan model vision deep learning berbobot ringan untuk deteksi cepat pada perangkat mobile edge.',
                    'status' => 1,
                    'komentar' => 'Judul disetujui dan dilanjutkan ke seminar komprehensif.',
                    'tanggal_usul' => '2025-08-15',
                    'tanggal_verifikasi' => '2025-08-20',
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('eskripsi_skripsi')) {
            $conn->table('eskripsi_skripsi')->updateOrInsert(
                ['id' => 'SKRIPSI-001'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                    'id_mahasiswa' => 'MHS-005',
                    'id_proposal' => 'JUDUL-001',
                    'judul' => 'Rancang Bangun Sistem Informasi Akademik Terdistribusi Menggunakan PostgreSQL Concurrency Locking',
                    'pembimbing1' => 'DOSEN-ET-01',
                    'pembimbing2' => 'DOSEN-ET-02',
                    'penguji1' => 'DOSEN-004',
                    'penguji2' => 'DOSEN-005',
                    'penguji3' => null,
                    'status' => 2, // Sedang Bimbingan Aktif
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $conn->table('eskripsi_skripsi')->updateOrInsert(
                ['id' => 'SKRIPSI-002'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                    'id_mahasiswa' => 'MHS-006',
                    'id_proposal' => 'JUDUL-002',
                    'judul' => 'Implementasi Convolutional Neural Network untuk Klasifikasi Citra Medis Realtime',
                    'pembimbing1' => 'DOSEN-ET-02',
                    'pembimbing2' => 'DOSEN-004',
                    'penguji1' => 'DOSEN-ET-01',
                    'penguji2' => 'DOSEN-BE-01',
                    'penguji3' => null,
                    'status' => 5, // Lulus Sidang
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('eskripsi_bimbingan')) {
            $bimbinganLogs = [
                ['id_bimbingan' => 'BIMB-001', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-ET-01', 'pengirim' => 'mahasiswa', 'pesan' => 'Selamat pagi Pak Budi, saya melampirkan draf Bab 1 & 2 untuk direview.', 'waktu_kirim' => '2026-03-01 09:00:00'],
                ['id_bimbingan' => 'BIMB-002', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-ET-01', 'pengirim' => 'dosen', 'pesan' => 'Draf sudah dibaca. Perbaiki rumusan masalah dan tambahkan kajian pustaka PostgreSQL row locking.', 'waktu_kirim' => '2026-03-05 14:30:00'],
                ['id_bimbingan' => 'BIMB-003', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-ET-01', 'pengirim' => 'mahasiswa', 'pesan' => 'Revisi rumusan masalah sudah diperbaiki dan ditambahkan benchmark transaksi k6.', 'waktu_kirim' => '2026-03-20 10:15:00'],
                ['id_bimbingan' => 'BIMB-004', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-ET-01', 'pengirim' => 'dosen', 'pesan' => 'Bagus, lanjutkan ke Bab 4 implementasi dan evaluasi.', 'waktu_kirim' => '2026-03-25 11:00:00'],
            ];

            foreach ($bimbinganLogs as $bimb) {
                $conn->table('eskripsi_bimbingan')->updateOrInsert(
                    ['id_bimbingan' => $bimb['id_bimbingan']],
                    $bimb
                );
            }
        }

        if (Schema::connection('simak_main')->hasTable('eskripsi_ujian')) {
            $conn->table('eskripsi_ujian')->updateOrInsert(
                ['id_ujian' => 'UJIAN-002'],
                [
                    'id_skripsi' => 'SKRIPSI-002',
                    'jenis_ujian' => 'Sidang',
                    'waktu_ujian' => '2025-07-15 09:00:00',
                    'ruang_ujian' => 'RUANG-SEM',
                    'nilaipembimbing1' => 95,
                    'nilaipembimbing2' => 94,
                    'nilaipenguji1' => 96,
                    'nilaipenguji2' => 95,
                    'nilaipenguji3' => null,
                    'catatanpembimbing1' => 'Penelitian sangat aplikatif dan memenuhi standar jurnal internasional.',
                    'catatanpembimbing2' => 'Hasil eksperimen konsisten.',
                    'catatanpenguji1' => 'Kemampuan argumentasi sangat baik.',
                    'catatanpenguji2' => 'Penyusunan naskah rapi.',
                    'catatanpenguji3' => null,
                    'accpembimbing1' => 1,
                    'accpembimbing2' => 1,
                    'accpenguji1' => 1,
                    'accpenguji2' => 1,
                    'accpenguji3' => 1,
                    'update_at' => $now,
                    'create_at' => $now,
                ]
            );
        }

        // =========================================================================
        // 9B. E-Tesis / Disertasi Pascasarjana S2 & S3 (etesis_*)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('etesis_judul')) {
            $conn->table('etesis_judul')->updateOrInsert(
                ['id' => 'TESIS-JUDUL-001'],
                [
                    'id_fakultas' => 'FK-FBE',
                    'id_prodi' => 'PRODI-BE-S2',
                    'id_mahasiswa' => 'MHS-S2-002',
                    'judul' => 'Strategic Agility & Transformational Leadership in Navigating Global Supply Chain Disruption',
                    'deskripsi' => 'Analisis empiris dynamic capabilities dan kelincahan strategis pada korporasi multinasional Asia Tenggara.',
                    'status' => 1,
                    'komentar' => 'Proposal tesis Magister disetujui untuk riset lapangan.',
                    'tanggal_usul' => '2026-01-15',
                    'tanggal_verifikasi' => '2026-01-20',
                ]
            );
            $conn->table('etesis_judul')->updateOrInsert(
                ['id' => 'DISSERT-JUDUL-001'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S3',
                    'id_mahasiswa' => 'MHS-S3-001',
                    'judul' => 'Novel Architectures in Quantum-Resistant Cryptography and Distributed Consensus Networks',
                    'deskripsi' => 'Pengembangan protokol konsensus tahan-kuantum berbasis lattice cryptography untuk keamanan infrastruktur kritis.',
                    'status' => 1,
                    'komentar' => 'Disertasi Doktoral disetujui untuk ujian terbuka.',
                    'tanggal_usul' => '2024-03-10',
                    'tanggal_verifikasi' => '2024-03-25',
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('etesis_skripsi')) {
            $conn->table('etesis_skripsi')->updateOrInsert(
                ['id' => 'TESIS-001'],
                [
                    'id_fakultas' => 'FK-FBE',
                    'id_prodi' => 'PRODI-BE-S2',
                    'id_mahasiswa' => 'MHS-S2-002',
                    'id_proposal' => 'TESIS-JUDUL-001',
                    'judul' => 'Strategic Agility & Transformational Leadership in Navigating Global Supply Chain Disruption',
                    'pembimbing1' => 'DOSEN-BE-02',
                    'pembimbing2' => 'DOSEN-BE-01',
                    'penguji1' => null,
                    'penguji2' => null,
                    'penguji3' => null,
                    'status' => 2, // Bimbingan Tesis Aktif
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $conn->table('etesis_skripsi')->updateOrInsert(
                ['id' => 'DISSERT-001'],
                [
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S3',
                    'id_mahasiswa' => 'MHS-S3-001',
                    'id_proposal' => 'DISSERT-JUDUL-001',
                    'judul' => 'Novel Architectures in Quantum-Resistant Cryptography and Distributed Consensus Networks',
                    'pembimbing1' => 'DOSEN-ET-02',
                    'pembimbing2' => 'DOSEN-ET-01',
                    'penguji1' => 'DOSEN-004',
                    'penguji2' => null,
                    'penguji3' => null,
                    'status' => 5, // Lulus Promosi Doktor
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('etesis_bimbingan')) {
            $conn->table('etesis_bimbingan')->updateOrInsert(
                ['id_bimbingan' => 'TBIMB-001'],
                [
                    'id_skripsi' => 'TESIS-001',
                    'id_dosen_tujuan' => 'DOSEN-BE-02',
                    'pengirim' => 'mahasiswa',
                    'pesan' => 'Draf Bab 1 & 2 Tesis Magister telah kami perbarui dengan model structural equation modeling (SEM).',
                    'waktu_kirim' => '2026-03-10 14:00:00',
                ]
            );
            $conn->table('etesis_bimbingan')->updateOrInsert(
                ['id_bimbingan' => 'TBIMB-002'],
                [
                    'id_skripsi' => 'TESIS-001',
                    'id_dosen_tujuan' => 'DOSEN-BE-02',
                    'pengirim' => 'dosen',
                    'pesan' => 'Metodologi sudah tepat. Lanjutkan pengumpulan data kuesioner pada perusahaan manufaktur.',
                    'waktu_kirim' => '2026-03-15 10:30:00',
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('etesis_ujian')) {
            $conn->table('etesis_ujian')->updateOrInsert(
                ['id_ujian' => 'DISSERT-EXAM-001'],
                [
                    'id_skripsi' => 'DISSERT-001',
                    'jenis_ujian' => 'Promosi Terbuka',
                    'waktu_ujian' => '2025-07-28 09:30:00',
                    'ruang_ujian' => 'RUANG-SEM',
                    'nilaipembimbing1' => 98,
                    'nilaipembimbing2' => 97,
                    'nilaipenguji1' => 99,
                    'nilaipenguji2' => 98,
                    'nilaipenguji3' => null,
                    'catatanpembimbing1' => 'Disertasi luar biasa, menghasilkan novelty teoritis dan kontribusi nyata pada keamanan siber.',
                    'catatanpembimbing2' => 'Publikasi internasional terindeks Q1 tercapai.',
                    'catatanpenguji1' => 'Pertahanan tesis doktoral sangat meyakinkan.',
                    'catatanpenguji2' => 'Layak dianugerahi predikat Summa Cum Laude.',
                    'catatanpenguji3' => null,
                    'accpembimbing1' => 1,
                    'accpembimbing2' => 1,
                    'accpenguji1' => 1,
                    'accpenguji2' => 1,
                    'accpenguji3' => 1,
                    'update_at' => $now,
                    'create_at' => $now,
                ]
            );
        }

        // =========================================================================
        // 10. Wisuda & Yudisium (s1m4k_wisuda)
        // =========================================================================
        $conn->table('s1m4k_wisuda')->updateOrInsert(
            ['id_peserta' => 'WISUDA-20252-001'],
            [
                'id_periode' => '20252',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'id_mahasiswa' => 'MHS-006',
                'bebas_spp' => 'Y',
                'bebas_perpus' => 'Y',
                'judul_skripsi' => 'Implementasi Convolutional Neural Network untuk Klasifikasi Citra Medis Realtime',
                'status' => 1,
                'tgl_daftar' => '2025-08-05',
                'petugas' => 'PANITIA_WISUDA',
                'lolos' => 'Y',
                'komentar' => 'Berkas yudisium lengkap dan memenuhi syarat kelulusan dengan predikat Cum Laude (IPK 3.94).',
                'pernyataan' => 1,
            ]
        );
        $conn->table('s1m4k_wisuda')->updateOrInsert(
            ['id_peserta' => 'WISUDA-20252-S3-001'],
            [
                'id_periode' => '20252',
                'id_prodi' => 'PRODI-ET-S3',
                'id_fakultas' => 'FK-FET',
                'id_mahasiswa' => 'MHS-S3-001',
                'bebas_spp' => 'Y',
                'bebas_perpus' => 'Y',
                'judul_skripsi' => 'Novel Architectures in Quantum-Resistant Cryptography and Distributed Consensus Networks',
                'status' => 1,
                'tgl_daftar' => '2025-08-08',
                'petugas' => 'PANITIA_WISUDA',
                'lolos' => 'Y',
                'komentar' => 'Memenuhi syarat kelulusan Program Doktoral dengan predikat Summa Cum Laude (IPK 4.00).',
                'pernyataan' => 1,
            ]
        );

        // =========================================================================
        // 11. OBE Kurikulum (ekur_kurikulum, ekur_profil_lulusan, ekur_cpl)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('ekur_kurikulum')) {
            $conn->table('ekur_kurikulum')->updateOrInsert(
                ['id_kur' => 1],
                [
                    'nama_kur' => 'Kurikulum OBE Teknik Informatika 2026',
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                    'tahun' => 2026,
                    'visi_keilmuan' => 'Menjadi pusat keunggulan riset dan pendidikan kecerdasan buatan dan rekayasa perangkat lunak internasional.',
                    'misi_prodi' => 'Menyelenggarakan pendidikan tinggi bertaraf global berbasis luaran capaian pembelajaran (OBE).',
                    'status_kur' => 'Aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if (Schema::connection('simak_main')->hasTable('ekur_profil_lulusan')) {
                $profiles = [
                    ['id_pl' => 'PL-01', 'id_kur' => 1, 'kode_pl' => 'PL01', 'nama_profil' => 'Software Engineer & System Architect', 'deskripsi' => 'Mampu merancang dan membangun sistem perangkat lunak skala enterprise terdistribusi.'],
                    ['id_pl' => 'PL-02', 'id_kur' => 1, 'kode_pl' => 'PL02', 'nama_profil' => 'Artificial Intelligence Specialist', 'deskripsi' => 'Mampu memformulasikan dan menerapkan algoritma deep learning dan computer vision.'],
                    ['id_pl' => 'PL-03', 'id_kur' => 1, 'kode_pl' => 'PL03', 'nama_profil' => 'Cyber Security Analyst', 'deskripsi' => 'Mampu mengamankan infrastruktur komputasi jaringan dan mendesain cryptographic protection.'],
                ];

                foreach ($profiles as $pl) {
                    $conn->table('ekur_profil_lulusan')->updateOrInsert(
                        ['id_pl' => $pl['id_pl']],
                        array_merge($pl, ['created_at' => $now, 'updated_at' => $now])
                    );
                }
            }

            if (Schema::connection('simak_main')->hasTable('ekur_cpl')) {
                $cpls = [
                    ['id_cpl' => 'CPL-01', 'id_kur' => 1, 'kode_cpl' => 'CPL01', 'kategori' => 'Sikap', 'deskripsi' => 'Menunjukkan integritas akademik, etika profesionalisme, dan tanggung jawab sosial.'],
                    ['id_cpl' => 'CPL-02', 'id_kur' => 1, 'kode_cpl' => 'CPL02', 'kategori' => 'Pengetahuan', 'deskripsi' => 'Menguasai konsep teoritis ilmu komputer, matematika diskrit, dan algoritma lanjutan.'],
                    ['id_cpl' => 'CPL-03', 'id_kur' => 1, 'kode_cpl' => 'CPL03', 'kategori' => 'Keterampilan Khusus', 'deskripsi' => 'Mampu mengimplementasikan arsitektur database modern, concurrency control, dan cloud systems.'],
                ];

                foreach ($cpls as $cpl) {
                    $conn->table('ekur_cpl')->updateOrInsert(
                        ['id_cpl' => $cpl['id_cpl']],
                        array_merge($cpl, ['created_at' => $now, 'updated_at' => $now])
                    );
                }
            }
        }

        // =========================================================================
        // 12. LMS Virtual Classroom (lms_pertemuan, lms_tugas)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('lms_pertemuan')) {
            $pertemuanData = [
                ['id_pertemuan' => 'PER-001', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 1, 'judul' => 'Pengantar Keamanan Siber & Lanskap Ancaman Modern', 'deskripsi' => 'Prinsip CIA triad, malware analysis, dan perimeter security.'],
                ['id_pertemuan' => 'PER-002', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 2, 'judul' => 'Kriptografi Kunci Simetris & Standar AES', 'deskripsi' => 'Substitusi-permutasi jaringan, mode operasi CBC/GCM, dan mitigasi timing attack.'],
                ['id_pertemuan' => 'PER-003', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 3, 'judul' => 'Kriptografi Asimetris & Tanda Tangan Digital', 'deskripsi' => 'Algoritma RSA, kurva eliptik (ECC), dan sertifikat X.509 PKI.'],
                ['id_pertemuan' => 'PER-004', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 4, 'judul' => 'Keamanan Jaringan & Protokol TLS 1.3', 'deskripsi' => 'Handshake protocol, cipher suites, dan perfect forward secrecy.'],
                ['id_pertemuan' => 'PER-005', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 5, 'judul' => 'Autentikasi Modern & Zero Trust Architecture', 'deskripsi' => 'OAuth 2.0, OpenID Connect, multi-factor authentication, dan prinsip Zero Trust.'],
                ['id_pertemuan' => 'PER-006', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 6, 'judul' => 'Keamanan Aplikasi Web & OWASP Top 10', 'deskripsi' => 'Mitigasi SQL Injection, XSS, CSRF, dan validasi input berlapis.'],
                ['id_pertemuan' => 'PER-007', 'id_jadwal' => 'JADWAL-TI-501A', 'pertemuan_ke' => 7, 'judul' => 'Ujian Tengah Semester (Review & Studi Kasus)', 'deskripsi' => 'Evaluasi kompetensi praktikum dan studi kasus insiden breach.'],
            ];

            foreach ($pertemuanData as $p) {
                $conn->table('lms_pertemuan')->updateOrInsert(
                    ['id_pertemuan' => $p['id_pertemuan']],
                    array_merge($p, [
                        'tanggal_pertemuan' => $now->toDateString(),
                        'waktu_mulai' => '08:00:00',
                        'waktu_selesai' => '10:30:00',
                        'status' => 'selesai',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        if (Schema::connection('simak_main')->hasTable('lms_tugas')) {
            $conn->table('lms_tugas')->updateOrInsert(
                ['id_tugas' => 'TUGAS-TI-501-01'],
                [
                    'id_jadwal' => 'JADWAL-TI-501A',
                    'judul' => 'Tugas 01: Analisis Kerentanan & Implementasi Enkripsi AES-256-GCM',
                    'deskripsi' => 'Mahasiswa diminta membuat program enkripsi dekripsi payload JSON menggunakan AES-256-GCM dengan authentication tag.',
                    'batas_waktu' => $now->addDays(7),
                    'bobot_nilai' => 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
