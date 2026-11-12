<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection('simak_main');
        $defaultPassword = Hash::make('password123');
        $now = now();

        // 1. Master Perguruan Tinggi
        $connection->table('s1m4k_masterpt')->updateOrInsert(
            ['id_perguruan_tinggi' => 'PT-001'],
            [
                'kode_perguruan_tinggi' => '001001',
                'nama_perguruan_tinggi' => 'ASEAN University International',
                'telepon' => '021-5551234',
                'faximile' => '021-5551235',
                'email' => 'info@asean.university',
                'website' => 'https://asean.university',
                'jalan' => 'Jl. Jenderal Sudirman No. 45',
                'kelurahan' => 'Karet Semanggi',
                'nama_wilayah' => 'Jakarta Selatan',
                'kode_pos' => '12930',
            ]
        );

        // 2. Master 8 Academic Faculties (s1m4k_masterfakultas)
        // kode_fakultas is VARCHAR(2), nama_fakultas VARCHAR(50)
        $faculties = [
            [
                'id_fakultas' => 'FK-FBE',
                'kode_fakultas' => 'BE',
                'nama_fakultas' => 'Faculty of Business and Economics',
                'dekan' => 'Prof. Dr. Robert Tan, Ph.D.',
                'pd1' => 'Dr. Maya Indah, S.E., M.M.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FET',
                'kode_fakultas' => 'ET',
                'nama_fakultas' => 'Faculty of Engineering and Technology',
                'dekan' => 'Prof. Ir. Hendra Wijaya, M.Sc., Ph.D.',
                'pd1' => 'Dr. Budi Santoso, M.Kom.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FSH',
                'kode_fakultas' => 'SH',
                'nama_fakultas' => 'Faculty of Social Sciences and Humanities',
                'dekan' => 'Prof. Dr. Sarah Jenkins, M.A.',
                'pd1' => 'Dr. Anita Rahmawati, M.Si.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FLW',
                'kode_fakultas' => 'LW',
                'nama_fakultas' => 'Faculty of Law',
                'dekan' => 'Prof. Dr. Marcus Vance, LL.M.',
                'pd1' => 'Dr. Denny Pratama, S.H., M.H.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FHM',
                'kode_fakultas' => 'HM',
                'nama_fakultas' => 'Faculty of Humaniora',
                'dekan' => 'Prof. Dr. Clara Higgins, M.Hum.',
                'pd1' => 'Dr. Ahmad Syafii, M.A.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FHS',
                'kode_fakultas' => 'HS',
                'nama_fakultas' => 'Faculty of Health Sciences',
                'dekan' => 'Prof. Dr. dr. Jonathan Lee, M.P.H.',
                'pd1' => 'Dr. Siti Sarah, S.KM., M.Kes.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FIS',
                'kode_fakultas' => 'IS',
                'nama_fakultas' => 'Faculty of Islamic Studies',
                'dekan' => 'Prof. Dr. Tariq Al-Mansoor, Lc., M.A.',
                'pd1' => 'Dr. Muhammad Firdaus, M.Ag.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-FPS',
                'kode_fakultas' => 'PS',
                'nama_fakultas' => 'Faculty of Politics and Social Sciences',
                'dekan' => 'Prof. Dr. Eleanor Sterling, Ph.D.',
                'pd1' => 'Dr. Faisal Anwar, M.Si.',
                'status' => 'Aktif',
            ],
            // Legacy / backward-compatible aliases
            [
                'id_fakultas' => 'FK-01',
                'kode_fakultas' => 'ET',
                'nama_fakultas' => 'Faculty of Engineering and Technology',
                'dekan' => 'Prof. Ir. Hendra Wijaya, M.Sc., Ph.D.',
                'pd1' => 'Dr. Budi Santoso, M.Kom.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-02',
                'kode_fakultas' => 'BE',
                'nama_fakultas' => 'Faculty of Business and Economics',
                'dekan' => 'Prof. Dr. Robert Tan, Ph.D.',
                'pd1' => 'Dr. Maya Indah, S.E., M.M.',
                'status' => 'Aktif',
            ],
        ];

        foreach ($faculties as $f) {
            $connection->table('s1m4k_masterfakultas')->updateOrInsert(
                ['id_fakultas' => $f['id_fakultas']],
                array_merge($f, ['id_perguruan_tinggi' => 'PT-001'])
            );
        }

        // 3. Master Program Studi (s1m4k_masterprodi)
        // Levels:
        // - Undergraduate (S1) Bachelor Degree · 3.5–4 Years
        // - Graduate (S2) Master Degree · 1.5–2 Years
        // - Doctoral (S3) Ph.D / Doctorate · 3–5 Years
        $programs = [
            // --- Faculty of Business and Economics (FBE) ---
            [
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'kode_program_studi' => '61201',
                'nama_program_studi' => 'S1 Manajemen Bisnis',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Maya Indah, S.E., M.M.',
                'gelar_lulusan' => 'S.M. / B.B.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'kode_program_studi' => '61101',
                'nama_program_studi' => 'S2 Magister Manajemen',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Robert Tan, Ph.D.',
                'gelar_lulusan' => 'M.M. / M.B.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-BE-S3',
                'id_fakultas' => 'FK-FBE',
                'kode_program_studi' => '61001',
                'nama_program_studi' => 'S3 Doktor Ilmu Ekonomi',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Robert Tan, Ph.D.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Engineering and Technology (FET) ---
            [
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'S1 Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Budi Santoso, M.Kom.',
                'gelar_lulusan' => 'S.Kom. / B.Comp.Sc.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-ET-SI',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '57201',
                'nama_program_studi' => 'S1 Sistem Informasi',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'gelar_lulusan' => 'S.Kom. / B.I.S.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '55101',
                'nama_program_studi' => 'S2 Magister Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Ir. Hendra Wijaya, Ph.D.',
                'gelar_lulusan' => 'M.Kom. / M.C.S.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-ET-S3',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '55001',
                'nama_program_studi' => 'S3 Doktor Ilmu Komputer',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Ir. Hendra Wijaya, Ph.D.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Social Sciences and Humanities (FSH) ---
            [
                'id_prodi' => 'PRODI-SH-S1',
                'id_fakultas' => 'FK-FSH',
                'kode_program_studi' => '70201',
                'nama_program_studi' => 'S1 Ilmu Komunikasi',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Anita Rahmawati, M.Si.',
                'gelar_lulusan' => 'S.I.Kom. / B.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-SH-S2',
                'id_fakultas' => 'FK-FSH',
                'kode_program_studi' => '70101',
                'nama_program_studi' => 'S2 Magister Ilmu Sosial',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Sarah Jenkins, M.A.',
                'gelar_lulusan' => 'M.Sos. / M.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-SH-S3',
                'id_fakultas' => 'FK-FSH',
                'kode_program_studi' => '70001',
                'nama_program_studi' => 'S3 Doktor Ilmu Sosial',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Sarah Jenkins, M.A.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Law (FLW) ---
            [
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'kode_program_studi' => '74201',
                'nama_program_studi' => 'S1 Ilmu Hukum',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Denny Pratama, S.H., M.H.',
                'gelar_lulusan' => 'S.H. / LL.B.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-LW-S2',
                'id_fakultas' => 'FK-FLW',
                'kode_program_studi' => '74101',
                'nama_program_studi' => 'S2 Magister Hukum',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Marcus Vance, LL.M.',
                'gelar_lulusan' => 'M.H. / LL.M.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-LW-S3',
                'id_fakultas' => 'FK-FLW',
                'kode_program_studi' => '74001',
                'nama_program_studi' => 'S3 Doktor Ilmu Hukum',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Marcus Vance, LL.M.',
                'gelar_lulusan' => 'Dr. / S.J.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Humaniora (FHM) ---
            [
                'id_prodi' => 'PRODI-HM-S1',
                'id_fakultas' => 'FK-FHM',
                'kode_program_studi' => '79201',
                'nama_program_studi' => 'S1 Sastra & Humaniora',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Ahmad Syafii, M.A.',
                'gelar_lulusan' => 'S.Hum. / B.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-HM-S2',
                'id_fakultas' => 'FK-FHM',
                'kode_program_studi' => '79101',
                'nama_program_studi' => 'S2 Magister Humaniora',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Clara Higgins, M.Hum.',
                'gelar_lulusan' => 'M.Hum. / M.A.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-HM-S3',
                'id_fakultas' => 'FK-FHM',
                'kode_program_studi' => '79001',
                'nama_program_studi' => 'S3 Doktor Humaniora',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Clara Higgins, M.Hum.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Health Sciences (FHS) ---
            [
                'id_prodi' => 'PRODI-HS-S1',
                'id_fakultas' => 'FK-FHS',
                'kode_program_studi' => '13201',
                'nama_program_studi' => 'S1 Kesehatan Masyarakat',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Siti Sarah, S.KM., M.Kes.',
                'gelar_lulusan' => 'S.K.M. / B.P.H.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-HS-S2',
                'id_fakultas' => 'FK-FHS',
                'kode_program_studi' => '13101',
                'nama_program_studi' => 'S2 Magister Kesehatan Masyarakat',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. dr. Jonathan Lee, M.P.H.',
                'gelar_lulusan' => 'M.K.M. / M.P.H.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-HS-S3',
                'id_fakultas' => 'FK-FHS',
                'kode_program_studi' => '13001',
                'nama_program_studi' => 'S3 Doktor Ilmu Kesehatan',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. dr. Jonathan Lee, M.P.H.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Islamic Studies (FIS) ---
            [
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'kode_program_studi' => '74202',
                'nama_program_studi' => 'S1 Hukum Ekonomi Syariah',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Muhammad Firdaus, M.Ag.',
                'gelar_lulusan' => 'S.H. / B.I.S.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-IS-S2',
                'id_fakultas' => 'FK-FIS',
                'kode_program_studi' => '74102',
                'nama_program_studi' => 'S2 Magister Studi Islam',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Tariq Al-Mansoor, Lc., M.A.',
                'gelar_lulusan' => 'M.Ag. / M.I.S.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-IS-S3',
                'id_fakultas' => 'FK-FIS',
                'kode_program_studi' => '74002',
                'nama_program_studi' => 'S3 Doktor Studi Islam',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Tariq Al-Mansoor, Lc., M.A.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Faculty of Politics and Social Sciences (FPS) ---
            [
                'id_prodi' => 'PRODI-PS-S1',
                'id_fakultas' => 'FK-FPS',
                'kode_program_studi' => '71201',
                'nama_program_studi' => 'S1 Hubungan Internasional',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Faisal Anwar, M.Si.',
                'gelar_lulusan' => 'S.Hub.Int. / B.I.R.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-PS-S2',
                'id_fakultas' => 'FK-FPS',
                'kode_program_studi' => '71101',
                'nama_program_studi' => 'S2 Magister Kebijakan Publik',
                'nama_jenjang_pendidikan' => 'S2',
                'id_jenjang_pendidikan' => 'S2',
                'ketua_prodi' => 'Prof. Dr. Eleanor Sterling, Ph.D.',
                'gelar_lulusan' => 'M.A.P. / M.P.P.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-PS-S3',
                'id_fakultas' => 'FK-FPS',
                'kode_program_studi' => '71001',
                'nama_program_studi' => 'S3 Doktor Ilmu Politik',
                'nama_jenjang_pendidikan' => 'S3',
                'id_jenjang_pendidikan' => 'S3',
                'ketua_prodi' => 'Prof. Dr. Eleanor Sterling, Ph.D.',
                'gelar_lulusan' => 'Dr. / Ph.D.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],

            // --- Backward compatibility aliases ---
            [
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Budi Santoso, M.Kom.',
                'gelar_lulusan' => 'S.Kom.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-FET',
                'kode_program_studi' => '57201',
                'nama_program_studi' => 'Sistem Informasi',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'gelar_lulusan' => 'S.Kom.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-MN',
                'id_fakultas' => 'FK-FBE',
                'kode_program_studi' => '61201',
                'nama_program_studi' => 'Manajemen',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Maya Indah, S.E., M.M.',
                'gelar_lulusan' => 'S.M.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0, 'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
        ];

        foreach ($programs as $prog) {
            $connection->table('s1m4k_masterprodi')->updateOrInsert(
                ['id_prodi' => $prog['id_prodi']],
                array_merge($prog, ['id_perguruan_tinggi' => 'PT-001'])
            );
        }

        // 4. Master Kalender Akademik Aktif & Riwayat
        $calendars = [
            [
                'id_kalender' => '20261',
                'tahun' => '20261',
                'namatahun' => '2026/2027 Ganjil',
                'periode' => 'Ganjil',
                'status' => 'Y',
                'status_sp' => 'N',
                'tgl_mulai_krs' => '2026-08-01',
                'tgl_akhir_krs' => '2026-09-30',
                'tgl_mulai_isinilai' => '2026-11-01',
                'tgl_akhir_isinilai' => '2027-01-31',
            ],
            [
                'id_kalender' => '20252',
                'tahun' => '20252',
                'namatahun' => '2025/2026 Genap',
                'periode' => 'Genap',
                'status' => 'N',
                'status_sp' => 'N',
                'tgl_mulai_krs' => '2026-01-05',
                'tgl_akhir_krs' => '2026-02-15',
                'tgl_mulai_isinilai' => '2026-06-01',
                'tgl_akhir_isinilai' => '2026-07-20',
            ],
        ];

        foreach ($calendars as $cal) {
            $connection->table('s1m4k_kalender')->updateOrInsert(
                ['id_kalender' => $cal['id_kalender']],
                array_merge($cal, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_fakultas' => 'FK-FET',
                    'id_prodi' => 'PRODI-ET-S1',
                ])
            );
        }

        // 5. Master Periode Masuk / Wisuda
        if (Schema::connection('simak_main')->hasTable('s1m4k_periode')) {
            $connection->table('s1m4k_periode')->updateOrInsert(
                ['id_periode' => '20261'],
                [
                    'tahun' => '20261',
                    'nama_periode' => 'Tahun Akademik 2026/2027 Ganjil',
                    'tanggal_mulai' => '2026-08-01',
                    'tanggal_akhir' => '2027-01-31',
                    'jenis' => 'Reguler',
                    'kuota_wisuda' => 500,
                    'aktif' => 'Y',
                ]
            );
            $connection->table('s1m4k_periode')->updateOrInsert(
                ['id_periode' => '20252'],
                [
                    'tahun' => '20252',
                    'nama_periode' => 'Tahun Akademik 2025/2026 Genap',
                    'tanggal_mulai' => '2026-02-01',
                    'tanggal_akhir' => '2026-07-31',
                    'jenis' => 'Reguler',
                    'kuota_wisuda' => 450,
                    'aktif' => 'N',
                ]
            );
        }

        // 6. Master Ruang Perkuliahan & Laboratorium (s1m4k_masterruang)
        $rooms = [
            ['id_ruang' => 'RUANG-101', 'nama_ruang' => 'Ruang Teori 101 (FBE)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FBE', 'id_prodi' => 'PRODI-BE-S1'],
            ['id_ruang' => 'RUANG-102', 'nama_ruang' => 'Ruang Teori 102 (FET)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FET', 'id_prodi' => 'PRODI-ET-S1'],
            ['id_ruang' => 'RUANG-103', 'nama_ruang' => 'Ruang Teori 103 (FSH)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FSH', 'id_prodi' => 'PRODI-SH-S1'],
            ['id_ruang' => 'RUANG-104', 'nama_ruang' => 'Ruang Teori 104 (FLW)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FLW', 'id_prodi' => 'PRODI-LW-S1'],
            ['id_ruang' => 'RUANG-105', 'nama_ruang' => 'Ruang Teori 105 (FHM)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FHM', 'id_prodi' => 'PRODI-HM-S1'],
            ['id_ruang' => 'RUANG-106', 'nama_ruang' => 'Ruang Teori 106 (FHS)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FHS', 'id_prodi' => 'PRODI-HS-S1'],
            ['id_ruang' => 'RUANG-107', 'nama_ruang' => 'Ruang Teori 107 (FIS)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FIS', 'id_prodi' => 'PRODI-IS-S1'],
            ['id_ruang' => 'RUANG-108', 'nama_ruang' => 'Ruang Teori 108 (FPS)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FPS', 'id_prodi' => 'PRODI-PS-S1'],
            ['id_ruang' => 'LAB-KOMP-1', 'nama_ruang' => 'Laboratorium Komputer & AI', 'kapasitas' => '35', 'status' => 'Aktif', 'id_fakultas' => 'FK-FET', 'id_prodi' => 'PRODI-ET-S1'],
            ['id_ruang' => 'LAB-KOMP-2', 'nama_ruang' => 'Laboratorium Jaringan & Security', 'kapasitas' => '35', 'status' => 'Aktif', 'id_fakultas' => 'FK-FET', 'id_prodi' => 'PRODI-ET-S1'],
            ['id_ruang' => 'LAB-HUKUM', 'nama_ruang' => 'Laboratorium Peradilan Semu (Moot Court)', 'kapasitas' => '40', 'status' => 'Aktif', 'id_fakultas' => 'FK-FLW', 'id_prodi' => 'PRODI-LW-S1'],
            ['id_ruang' => 'RUANG-SEM', 'nama_ruang' => 'Ruang Seminar & Sidang Tugas Akhir / Tesis', 'kapasitas' => '60', 'status' => 'Aktif', 'id_fakultas' => 'FK-FET', 'id_prodi' => 'PRODI-ET-S1'],
        ];

        foreach ($rooms as $r) {
            $connection->table('s1m4k_masterruang')->updateOrInsert(
                ['id_ruang' => $r['id_ruang']],
                array_merge($r, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'flag_feeder' => 0,
                ])
            );
        }

        // 7. Master Bobot Nilai (Standard Standar Nasional Pendidikan Tinggi)
        $grades = [
            ['id_bobot_nilai' => 'GRADE-A', 'nilai_huruf' => 'A', 'nilai_indeks' => '4.00', 'bobot_minimum' => '85.00', 'bobot_maksimum' => '100.00'],
            ['id_bobot_nilai' => 'GRADE-BPLUS', 'nilai_huruf' => 'B+', 'nilai_indeks' => '3.50', 'bobot_minimum' => '75.00', 'bobot_maksimum' => '84.99'],
            ['id_bobot_nilai' => 'GRADE-B', 'nilai_huruf' => 'B', 'nilai_indeks' => '3.00', 'bobot_minimum' => '70.00', 'bobot_maksimum' => '74.99'],
            ['id_bobot_nilai' => 'GRADE-CPLUS', 'nilai_huruf' => 'C+', 'nilai_indeks' => '2.50', 'bobot_minimum' => '65.00', 'bobot_maksimum' => '69.99'],
            ['id_bobot_nilai' => 'GRADE-C', 'nilai_huruf' => 'C', 'nilai_indeks' => '2.00', 'bobot_minimum' => '55.00', 'bobot_maksimum' => '64.99'],
            ['id_bobot_nilai' => 'GRADE-D', 'nilai_huruf' => 'D', 'nilai_indeks' => '1.00', 'bobot_minimum' => '45.00', 'bobot_maksimum' => '54.99'],
            ['id_bobot_nilai' => 'GRADE-E', 'nilai_huruf' => 'E', 'nilai_indeks' => '0.00', 'bobot_minimum' => '0.00', 'bobot_maksimum' => '44.99'],
        ];

        foreach ($grades as $g) {
            $connection->table('s1m4k_masterbobotnilai')->updateOrInsert(
                ['id_bobot_nilai' => $g['id_bobot_nilai']],
                array_merge($g, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'tanggal_mulai_efektif' => '2024-01-01',
                    'tanggal_akhir_efektif' => '2030-12-31',
                    'status' => 'Y',
                    'flag_feeder' => 0,
                ])
            );
        }

        // 8. Akun Pengelola & Staf (s1m4k_admin - 14+ RBAC Roles)
        $adminUsers = [
            [
                'username' => 'admin',
                'nama_lengkap' => 'Administrator Utama SIAKAD',
                'level' => 'admin',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'admin@siakad.ac.id',
                'no_telp' => '081234567890',
            ],
            [
                'username' => 'pimpinan',
                'nama_lengkap' => 'Prof. Dr. Ir. H. Bambang Soeprapto, M.Eng.',
                'level' => 'pimpinan',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'rektor@siakad.ac.id',
                'no_telp' => '081234567891',
            ],
            [
                'username' => 'pimuniv',
                'nama_lengkap' => 'Wakil Rektor I Bidang Akademik',
                'level' => 'pimuniv',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'warek1@siakad.ac.id',
                'no_telp' => '081234567892',
            ],

            // Dekan 8 Fakultas
            [
                'username' => 'dekan_fet',
                'nama_lengkap' => 'Prof. Ir. Hendra Wijaya, M.Sc., Ph.D.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FET',
                'email' => 'dekan.fet@siakad.ac.id',
                'no_telp' => '081234567893',
            ],
            [
                'username' => 'dekan',
                'nama_lengkap' => 'Prof. Ir. Hendra Wijaya, M.Sc., Ph.D.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FET',
                'email' => 'dekan@siakad.ac.id',
                'no_telp' => '081234567893',
            ],
            [
                'username' => 'pimfak',
                'nama_lengkap' => 'Dekanat Sains & Teknologi (FET)',
                'level' => 'pimfak',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FET',
                'email' => 'dekanat.fet@siakad.ac.id',
                'no_telp' => '081234567894',
            ],
            [
                'username' => 'dekan_fbe',
                'nama_lengkap' => 'Prof. Dr. Robert Tan, Ph.D.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FBE',
                'email' => 'dekan.fbe@siakad.ac.id',
                'no_telp' => '081234567810',
            ],
            [
                'username' => 'dekan_fsh',
                'nama_lengkap' => 'Prof. Dr. Sarah Jenkins, M.A.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FSH',
                'email' => 'dekan.fsh@siakad.ac.id',
                'no_telp' => '081234567811',
            ],
            [
                'username' => 'dekan_flw',
                'nama_lengkap' => 'Prof. Dr. Marcus Vance, LL.M.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FLW',
                'email' => 'dekan.flw@siakad.ac.id',
                'no_telp' => '081234567812',
            ],
            [
                'username' => 'dekan_fhm',
                'nama_lengkap' => 'Prof. Dr. Clara Higgins, M.Hum.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FHM',
                'email' => 'dekan.fhm@siakad.ac.id',
                'no_telp' => '081234567813',
            ],
            [
                'username' => 'dekan_fhs',
                'nama_lengkap' => 'Prof. Dr. dr. Jonathan Lee, M.P.H.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FHS',
                'email' => 'dekan.fhs@siakad.ac.id',
                'no_telp' => '081234567814',
            ],
            [
                'username' => 'dekan_fis',
                'nama_lengkap' => 'Prof. Dr. Tariq Al-Mansoor, Lc., M.A.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FIS',
                'email' => 'dekan.fis@siakad.ac.id',
                'no_telp' => '081234567815',
            ],
            [
                'username' => 'dekan_fps',
                'nama_lengkap' => 'Prof. Dr. Eleanor Sterling, Ph.D.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-FPS',
                'email' => 'dekan.fps@siakad.ac.id',
                'no_telp' => '081234567816',
            ],

            // Kaprodi
            [
                'username' => 'kaprodi',
                'nama_lengkap' => 'Dr. Budi Santoso, M.Kom.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'email' => 'kaprodi.ti@siakad.ac.id',
                'no_telp' => '081234567895',
            ],
            [
                'username' => 'kaprodi_be',
                'nama_lengkap' => 'Dr. Maya Indah, S.E., M.M.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'email' => 'kaprodi.be@siakad.ac.id',
                'no_telp' => '081234567897',
            ],
            [
                'username' => 'kaprodi_mn',
                'nama_lengkap' => 'Dr. Maya Indah, S.E., M.M.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'email' => 'kaprodi.mn@siakad.ac.id',
                'no_telp' => '081234567897',
            ],
            [
                'username' => 'kaprodi_si',
                'nama_lengkap' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-ET-SI',
                'id_fakultas' => 'FK-FET',
                'email' => 'kaprodi.si@siakad.ac.id',
                'no_telp' => '081234567896',
            ],
            [
                'username' => 'kaprodi_sh',
                'nama_lengkap' => 'Dr. Anita Rahmawati, M.Si.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-SH-S1',
                'id_fakultas' => 'FK-FSH',
                'email' => 'kaprodi.sh@siakad.ac.id',
                'no_telp' => '081234567820',
            ],
            [
                'username' => 'kaprodi_lw',
                'nama_lengkap' => 'Dr. Denny Pratama, S.H., M.H.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'email' => 'kaprodi.lw@siakad.ac.id',
                'no_telp' => '081234567821',
            ],
            [
                'username' => 'kaprodi_hm',
                'nama_lengkap' => 'Dr. Ahmad Syafii, M.A.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-HM-S1',
                'id_fakultas' => 'FK-FHM',
                'email' => 'kaprodi.hm@siakad.ac.id',
                'no_telp' => '081234567822',
            ],
            [
                'username' => 'kaprodi_hs',
                'nama_lengkap' => 'Dr. Siti Sarah, S.KM., M.Kes.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-HS-S1',
                'id_fakultas' => 'FK-FHS',
                'email' => 'kaprodi.hs@siakad.ac.id',
                'no_telp' => '081234567823',
            ],
            [
                'username' => 'kaprodi_is',
                'nama_lengkap' => 'Dr. Muhammad Firdaus, M.Ag.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'email' => 'kaprodi.is@siakad.ac.id',
                'no_telp' => '081234567824',
            ],
            [
                'username' => 'kaprodi_ps',
                'nama_lengkap' => 'Dr. Faisal Anwar, M.Si.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-PS-S1',
                'id_fakultas' => 'FK-FPS',
                'email' => 'kaprodi.ps@siakad.ac.id',
                'no_telp' => '081234567825',
            ],
            [
                'username' => 'kaprodi_s2_et',
                'nama_lengkap' => 'Prof. Ir. Hendra Wijaya, Ph.D.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'email' => 'kaprodi.s2et@siakad.ac.id',
                'no_telp' => '081234567826',
            ],
            [
                'username' => 'kaprodi_s2_be',
                'nama_lengkap' => 'Prof. Dr. Robert Tan, Ph.D.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'email' => 'kaprodi.s2be@siakad.ac.id',
                'no_telp' => '081234567827',
            ],

            // Staf Pendukung & Fungsional
            [
                'username' => 'tu_prodi',
                'nama_lengkap' => 'Tata Usaha Akademik',
                'level' => 'usertu',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'email' => 'tu.akademik@siakad.ac.id',
                'no_telp' => '081234567898',
            ],
            [
                'username' => 'keuangan',
                'nama_lengkap' => 'Staf Keuangan, Billing & UKT',
                'level' => 'keuangan',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'keuangan@siakad.ac.id',
                'no_telp' => '081234567899',
            ],
            [
                'username' => 'wisuda',
                'nama_lengkap' => 'Panitia Yudisium & Wisuda',
                'level' => 'wisuda',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'wisuda@siakad.ac.id',
                'no_telp' => '081234567800',
            ],
            [
                'username' => 'validator',
                'nama_lengkap' => 'Tim Validasi & Kearsipan Akademik',
                'level' => 'validasi',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'validasi@siakad.ac.id',
                'no_telp' => '081234567801',
            ],
            [
                'username' => 'regulang',
                'nama_lengkap' => 'Petugas Registrasi Ulang & PMB',
                'level' => 'regulang',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'regulang@siakad.ac.id',
                'no_telp' => '081234567802',
            ],
            [
                'username' => 'lppm',
                'nama_lengkap' => 'Operator LPPM & Pengabdian Masyarakat',
                'level' => 'lppm',
                'id_prodi' => null,
                'id_fakultas' => null,
                'email' => 'lppm@siakad.ac.id',
                'no_telp' => '081234567803',
            ],
            [
                'username' => 'timkur',
                'nama_lengkap' => 'Koordinator Tim Kurikulum OBE',
                'level' => 'timkur',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'email' => 'kurikulum@siakad.ac.id',
                'no_telp' => '081234567804',
            ],
        ];

        foreach ($adminUsers as $user) {
            $connection->table('s1m4k_admin')->updateOrInsert(
                ['username' => $user['username']],
                [
                    'password' => $defaultPassword,
                    'nama_lengkap' => $user['nama_lengkap'],
                    'level' => $user['level'],
                    'id_prodi' => $user['id_prodi'],
                    'id_fakultas' => $user['id_fakultas'],
                    'email' => $user['email'],
                    'no_telp' => $user['no_telp'],
                    'blokir' => 'N',
                    'updated_at' => $now,
                ]
            );
        }

        // 9. Akun Dosen Pengampu & Pembimbing Akademik (s1m4k_masterdosen)
        // Default password: password123, login via NIDN
        $lecturers = [
            // FET (Engineering & Technology)
            [
                'id_dosen' => 'DOSEN-ET-01',
                'nidn' => '0011223344',
                'nip' => '198001012005011001',
                'nama_dosen' => 'Budi Santoso',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Kom.',
                'email' => 'budi.santoso@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-001',
                'nidn' => '0011223344',
                'nip' => '198001012005011001',
                'nama_dosen' => 'Budi Santoso',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Kom.',
                'email' => 'budi.santoso@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-ET-02',
                'nidn' => '0033445566',
                'nip' => '197503031998031003',
                'nama_dosen' => 'Hendra Wijaya',
                'gelar_depan' => 'Prof. Ir.',
                'gelar_belakang' => 'M.Sc., Ph.D.',
                'email' => 'hendra.wijaya@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-003',
                'nidn' => '0033445566',
                'nip' => '197503031998031003',
                'nama_dosen' => 'Hendra Wijaya',
                'gelar_depan' => 'Prof. Ir.',
                'gelar_belakang' => 'M.Sc., Ph.D.',
                'email' => 'hendra.wijaya@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-002',
                'nidn' => '0022334455',
                'nip' => '198202022006022002',
                'nama_dosen' => 'Siti Aminah',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'S.Kom., M.T.',
                'email' => 'siti.aminah@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-ET-SI',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-004',
                'nidn' => '0044556677',
                'nip' => '198604042010012004',
                'nama_dosen' => 'Dian Kusuma',
                'gelar_depan' => '',
                'gelar_belakang' => 'M.Cs.',
                'email' => 'dian.kusuma@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_dosen' => 'DOSEN-005',
                'nidn' => '0055667788',
                'nip' => '198805052012011005',
                'nama_dosen' => 'Rahmat Hidayat',
                'gelar_depan' => '',
                'gelar_belakang' => 'M.Kom.',
                'email' => 'rahmat.hidayat@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],

            // FBE (Business & Economics)
            [
                'id_dosen' => 'DOSEN-BE-01',
                'nidn' => '0066778899',
                'nip' => '198106062005022006',
                'nama_dosen' => 'Maya Indah',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'S.E., M.M.',
                'email' => 'maya.indah@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
            ],
            [
                'id_dosen' => 'DOSEN-006',
                'nidn' => '0066778899',
                'nip' => '198106062005022006',
                'nama_dosen' => 'Maya Indah',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'S.E., M.M.',
                'email' => 'maya.indah@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
            ],
            [
                'id_dosen' => 'DOSEN-BE-02',
                'nidn' => '0022334466',
                'nip' => '197008151995031002',
                'nama_dosen' => 'Robert Tan',
                'gelar_depan' => 'Prof. Dr.',
                'gelar_belakang' => 'Ph.D.',
                'email' => 'robert.tan@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
            ],

            // FSH (Social Sciences & Humanities)
            [
                'id_dosen' => 'DOSEN-SH-01',
                'nidn' => '0055443322',
                'nip' => '198309122008012003',
                'nama_dosen' => 'Anita Rahmawati',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Si.',
                'email' => 'anita.rahmawati@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-SH-S1',
                'id_fakultas' => 'FK-FSH',
            ],

            // FLW (Law)
            [
                'id_dosen' => 'DOSEN-LW-01',
                'nidn' => '0077665544',
                'nip' => '198511202010011005',
                'nama_dosen' => 'Denny Pratama',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'S.H., M.H.',
                'email' => 'denny.pratama@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
            ],

            // FHM (Humaniora)
            [
                'id_dosen' => 'DOSEN-HM-01',
                'nidn' => '0088776655',
                'nip' => '198403152009021004',
                'nama_dosen' => 'Ahmad Syafii',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.A.',
                'email' => 'ahmad.syafii@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-HM-S1',
                'id_fakultas' => 'FK-FHM',
            ],

            // FHS (Health Sciences)
            [
                'id_dosen' => 'DOSEN-HS-01',
                'nidn' => '0099887766',
                'nip' => '198712102012022006',
                'nama_dosen' => 'Siti Sarah',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'S.KM., M.Kes.',
                'email' => 'siti.sarah@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-HS-S1',
                'id_fakultas' => 'FK-FHS',
            ],

            // FIS (Islamic Studies)
            [
                'id_dosen' => 'DOSEN-IS-01',
                'nidn' => '0011335577',
                'nip' => '198205182007011003',
                'nama_dosen' => 'Muhammad Firdaus',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Ag.',
                'email' => 'muhammad.firdaus@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
            ],

            // FPS (Politics & Social Sciences)
            [
                'id_dosen' => 'DOSEN-PS-01',
                'nidn' => '0022446688',
                'nip' => '198607222011011004',
                'nama_dosen' => 'Faisal Anwar',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Si.',
                'email' => 'faisal.anwar@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-PS-S1',
                'id_fakultas' => 'FK-FPS',
            ],
        ];

        foreach ($lecturers as $d) {
            $connection->table('s1m4k_masterdosen')->updateOrInsert(
                ['id_dosen' => $d['id_dosen']],
                array_merge($d, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_tahun_ajaran' => '2026',
                    'password' => $defaultPassword,
                    'password_baru' => null,
                    'nama_status_aktif' => 'Aktif',
                    'flag_feeder' => 0,
                ])
            );
        }

        // 10. Akun Mahasiswa Lintas Angkatan & Persona (s1m4k_mastermhs)
        // Cohorts: 2026 (Baru S1/S2), 2024/2025 (Aktif), 2022/2023 (Skripsi/Tesis), 2020/2021 (Lulus/Wisuda)
        // Login via NIM, password: password123
        $students = [
            // Angkatan 2026 - Baru (Semester 1)
            [
                'id_mahasiswa' => 'MHS-001',
                'nim' => '2026001001',
                'nama_mahasiswa' => 'Ahmad Fauzi',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-ET-01',
                'email' => 'ahmad.fauzi@student.siakad.ac.id',
                'handphone' => '082100010001',
                'id_periode_masuk' => '20261',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-002',
                'nim' => '2026001002',
                'nama_mahasiswa' => 'Siti Nurhaliza',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'dosen_pa' => 'DOSEN-BE-01',
                'email' => 'siti.nurhaliza@student.siakad.ac.id',
                'handphone' => '082100010002',
                'id_periode_masuk' => '20261',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-S2-001',
                'nim' => '2026002001',
                'nama_mahasiswa' => 'Jessica Williams',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-ET-S2',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-ET-02',
                'email' => 'jessica.williams@student.siakad.ac.id',
                'handphone' => '082100020001',
                'id_periode_masuk' => '20261',
                'kategori_ukt' => '5',
                'nominal_ukt' => 9500000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],

            // Angkatan 2024 - Aktif (Semester 5)
            [
                'id_mahasiswa' => 'MHS-003',
                'nim' => '2024001003',
                'nama_mahasiswa' => 'Kevin Pratama',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-004',
                'email' => 'kevin.pratama@student.siakad.ac.id',
                'handphone' => '082100010003',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '4',
                'nominal_ukt' => 6000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-004',
                'nim' => '2024001004',
                'nama_mahasiswa' => 'Putri Ayu Lestari',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-004',
                'email' => 'putri.ayu@student.siakad.ac.id',
                'handphone' => '082100010004',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '4',
                'nominal_ukt' => 6000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-007',
                'nim' => '2024002001',
                'nama_mahasiswa' => 'Farhan Alamsyah',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-LW-S1',
                'id_fakultas' => 'FK-FLW',
                'dosen_pa' => 'DOSEN-LW-01',
                'email' => 'farhan.alamsyah@student.siakad.ac.id',
                'handphone' => '082100010007',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-008',
                'nim' => '2024003001',
                'nama_mahasiswa' => 'Nadya Zahrani',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-BE-S1',
                'id_fakultas' => 'FK-FBE',
                'dosen_pa' => 'DOSEN-BE-01',
                'email' => 'nadya.zahrani@student.siakad.ac.id',
                'handphone' => '082100010008',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-009',
                'nim' => '2024004001',
                'nama_mahasiswa' => 'Aisyah Putri',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-IS-S1',
                'id_fakultas' => 'FK-FIS',
                'dosen_pa' => 'DOSEN-IS-01',
                'email' => 'aisyah.putri@student.siakad.ac.id',
                'handphone' => '082100010009',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '3',
                'nominal_ukt' => 4500000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],

            // Angkatan 2022/2023 - Tahap Akhir / E-Skripsi & E-Tesis Bimbingan Aktif
            [
                'id_mahasiswa' => 'MHS-005',
                'nim' => '2022001005',
                'nama_mahasiswa' => 'Rizky Ramadhan',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-ET-01',
                'email' => 'rizky.ramadhan@student.siakad.ac.id',
                'handphone' => '082100010005',
                'id_periode_masuk' => '20221',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-S2-002',
                'nim' => '2023002001',
                'nama_mahasiswa' => 'Marcus Chen',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-BE-S2',
                'id_fakultas' => 'FK-FBE',
                'dosen_pa' => 'DOSEN-BE-02',
                'email' => 'marcus.chen@student.siakad.ac.id',
                'handphone' => '082100020002',
                'id_periode_masuk' => '20231',
                'kategori_ukt' => '5',
                'nominal_ukt' => 9500000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],

            // Angkatan 2021 & 2020 - Lulus / Wisudawan (Cum Laude S1 & Doktor S3)
            [
                'id_mahasiswa' => 'MHS-006',
                'nim' => '2021001006',
                'nama_mahasiswa' => 'Dewi Kartika',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-ET-02',
                'email' => 'dewi.kartika@student.siakad.ac.id',
                'handphone' => '082100010006',
                'id_periode_masuk' => '20211',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'L',
                'nama_status_mahasiswa' => 'Lulus',
            ],
            [
                'id_mahasiswa' => 'MHS-S3-001',
                'nim' => '2020003001',
                'nama_mahasiswa' => 'Dr. Raymond Holt',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-ET-S3',
                'id_fakultas' => 'FK-FET',
                'dosen_pa' => 'DOSEN-ET-02',
                'email' => 'raymond.holt@student.siakad.ac.id',
                'handphone' => '082100030001',
                'id_periode_masuk' => '20201',
                'kategori_ukt' => '6',
                'nominal_ukt' => 12500000,
                'id_status_mahasiswa' => 'L',
                'nama_status_mahasiswa' => 'Lulus',
            ],
        ];

        foreach ($students as $mhs) {
            $connection->table('s1m4k_mastermhs')->updateOrInsert(
                ['id_mahasiswa' => $mhs['id_mahasiswa']],
                array_merge($mhs, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'password' => $defaultPassword,
                    'password_baru' => null,
                    'tanggal_daftar' => '2026-07-15',
                    'id_jenis_daftar' => '1',
                    'id_jalur_daftar' => '1',
                    'flag_feeder' => 0,
                ])
            );
        }

        // 11. Akun Asisten Dosen (s1m4k_masterasdos)
        $assistants = [
            [
                'id_asdos' => 'ASDOS-001',
                'kdasisten' => 'ASDOS01',
                'nama' => 'Rian Pratama, S.Kom.',
                'jkel' => 'L',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
            [
                'id_asdos' => 'ASDOS-002',
                'kdasisten' => 'ASDOS02',
                'nama' => 'Nabila Putri, S.Kom.',
                'jkel' => 'P',
                'id_prodi' => 'PRODI-ET-S1',
                'id_fakultas' => 'FK-FET',
            ],
        ];

        foreach ($assistants as $asdos) {
            $connection->table('s1m4k_masterasdos')->updateOrInsert(
                ['id_asdos' => $asdos['id_asdos']],
                array_merge($asdos, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'password' => $defaultPassword,
                    'status' => 'Aktif',
                ])
            );
        }

        // 12. Site Settings & Payment Gateways
        if (Schema::connection('simak_main')->hasTable('simak_site_settings')) {
            $existingSettings = $connection->table('simak_site_settings')->where('id', 1)->first();
            $defaultLogoPath = 'branding/aui-logo.png';
            $defaultPreloaderPath = 'branding/aui-logo.png';
            
            $logoPath = filled($existingSettings?->logo_path) && ! str_contains((string) $existingSettings->logo_path, 'logo-siakad')
                ? $existingSettings->logo_path
                : $defaultLogoPath;
            $preloaderLogoPath = filled($existingSettings?->preloader_logo_path) && ! str_contains((string) $existingSettings->preloader_logo_path, 'logo-siakad')
                ? $existingSettings->preloader_logo_path
                : $defaultPreloaderPath;

            $connection->table('simak_site_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'ministry_name' => filled($existingSettings?->ministry_name) ? $existingSettings->ministry_name : 'Kementerian Pendidikan Tinggi, Sains, dan Teknologi',
                    'motto' => filled($existingSettings?->motto) ? $existingSettings->motto : 'Excellence in Academic Innovation & Integrity',
                    'primary_color' => filled($existingSettings?->primary_color) ? $existingSettings->primary_color : '#1e3a8a',
                    'logo_path' => $logoPath,
                    'preloader_logo_path' => $preloaderLogoPath,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('simak_payment_gateways')) {
            $connection->table('simak_payment_gateways')->updateOrInsert(
                ['gateway' => 'midtrans'],
                [
                    'enabled' => 1,
                    'environment' => 'sandbox',
                    'client_key' => 'SB-Mid-client-demoKey123',
                    'service_fee' => 4500,
                    'credentials_encrypted' => null,
                    'updated_at' => $now,
                ]
            );
        }

        // 13. Reset Sequence PostgreSQL secara spesifik untuk s1m4k_admin
        if ($connection->getDriverName() === 'pgsql') {
            app(\App\Console\Commands\ResetPostgresSequences::class);
            $this->command?->call('siakad:reset-sequences', [
                '--connection' => 'simak_main',
                '--table' => 's1m4k_admin',
            ]);
        }
    }
}
