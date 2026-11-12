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
                'nama_perguruan_tinggi' => 'Universitas SIAKAD (AUI)',
                'telepon' => '021-5551234',
                'faximile' => '021-5551235',
                'email' => 'info@siakad.ac.id',
                'website' => 'https://siakad.ac.id',
                'jalan' => 'Jl. Jenderal Sudirman No. 45',
                'kelurahan' => 'Karet Semanggi',
                'nama_wilayah' => 'Jakarta Selatan',
                'kode_pos' => '12930',
            ]
        );

        // 2. Master Fakultas
        $faculties = [
            [
                'id_fakultas' => 'FK-01',
                'kode_fakultas' => 'FT',
                'nama_fakultas' => 'Fakultas Sains dan Teknologi',
                'dekan' => 'Dr. Ir. Hendra Wijaya, M.Sc.',
                'pd1' => 'Dr. Budi Santoso, M.Kom.',
                'status' => 'Aktif',
            ],
            [
                'id_fakultas' => 'FK-02',
                'kode_fakultas' => 'EB',
                'nama_fakultas' => 'Fakultas Ekonomi dan Bisnis',
                'dekan' => 'Dr. Maya Indah, S.E., M.M.',
                'pd1' => 'Drs. Suryono, M.B.A.',
                'status' => 'Aktif',
            ],
        ];

        foreach ($faculties as $f) {
            $connection->table('s1m4k_masterfakultas')->updateOrInsert(
                ['id_fakultas' => $f['id_fakultas']],
                array_merge($f, ['id_perguruan_tinggi' => 'PT-001'])
            );
        }

        // 3. Master Program Studi
        $programs = [
            [
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Budi Santoso, M.Kom.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0,
                'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-01',
                'kode_program_studi' => '57201',
                'nama_program_studi' => 'Sistem Informasi',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0,
                'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ],
            [
                'id_prodi' => 'PRODI-MN',
                'id_fakultas' => 'FK-02',
                'kode_program_studi' => '61201',
                'nama_program_studi' => 'Manajemen',
                'nama_jenjang_pendidikan' => 'S1',
                'id_jenjang_pendidikan' => 'S1',
                'ketua_prodi' => 'Dr. Maya Indah, S.E., M.M.',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0,
                'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
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

        // 6. Master Ruang Perkuliahan
        $rooms = [
            ['id_ruang' => 'RUANG-101', 'nama_ruang' => 'Ruang Teori 101 (FST)', 'kapasitas' => '40', 'status' => 'Aktif'],
            ['id_ruang' => 'RUANG-102', 'nama_ruang' => 'Ruang Teori 102 (FST)', 'kapasitas' => '40', 'status' => 'Aktif'],
            ['id_ruang' => 'RUANG-103', 'nama_ruang' => 'Ruang Teori 103 (FEB)', 'kapasitas' => '40', 'status' => 'Aktif'],
            ['id_ruang' => 'LAB-KOMP-1', 'nama_ruang' => 'Laboratorium Komputer & AI', 'kapasitas' => '35', 'status' => 'Aktif'],
            ['id_ruang' => 'LAB-KOMP-2', 'nama_ruang' => 'Laboratorium Jaringan & Security', 'kapasitas' => '35', 'status' => 'Aktif'],
            ['id_ruang' => 'RUANG-SEM', 'nama_ruang' => 'Ruang Seminar & Sidang Tugas Akhir', 'kapasitas' => '50', 'status' => 'Aktif'],
        ];

        foreach ($rooms as $r) {
            $connection->table('s1m4k_masterruang')->updateOrInsert(
                ['id_ruang' => $r['id_ruang']],
                array_merge($r, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
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
            [
                'username' => 'dekan',
                'nama_lengkap' => 'Dr. Ir. Hendra Wijaya, M.Sc.',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-01',
                'email' => 'dekan.fst@siakad.ac.id',
                'no_telp' => '081234567893',
            ],
            [
                'username' => 'pimfak',
                'nama_lengkap' => 'Dekanat Sains dan Teknologi',
                'level' => 'pimfak',
                'id_prodi' => null,
                'id_fakultas' => 'FK-01',
                'email' => 'dekanat.fst@siakad.ac.id',
                'no_telp' => '081234567894',
            ],
            [
                'username' => 'kaprodi',
                'nama_lengkap' => 'Dr. Budi Santoso, M.Kom.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'email' => 'kaprodi.ti@siakad.ac.id',
                'no_telp' => '081234567895',
            ],
            [
                'username' => 'kaprodi_si',
                'nama_lengkap' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-01',
                'email' => 'kaprodi.si@siakad.ac.id',
                'no_telp' => '081234567896',
            ],
            [
                'username' => 'kaprodi_mn',
                'nama_lengkap' => 'Dr. Maya Indah, S.E., M.M.',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-MN',
                'id_fakultas' => 'FK-02',
                'email' => 'kaprodi.mn@siakad.ac.id',
                'no_telp' => '081234567897',
            ],
            [
                'username' => 'tu_prodi',
                'nama_lengkap' => 'Tata Usaha Teknik Informatika',
                'level' => 'usertu',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'email' => 'tu.ti@siakad.ac.id',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
        $lecturers = [
            [
                'id_dosen' => 'DOSEN-001',
                'nidn' => '0011223344',
                'nip' => '198001012005011001',
                'nama_dosen' => 'Budi Santoso',
                'gelar_depan' => 'Dr.',
                'gelar_belakang' => 'M.Kom.',
                'email' => 'budi.santoso@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-01',
            ],
            [
                'id_dosen' => 'DOSEN-003',
                'nidn' => '0033445566',
                'nip' => '197503031998031003',
                'nama_dosen' => 'Hendra Wijaya',
                'gelar_depan' => 'Ir.',
                'gelar_belakang' => 'Ph.D.',
                'email' => 'hendra.wijaya@siakad.ac.id',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-MN',
                'id_fakultas' => 'FK-02',
            ],
            [
                'id_dosen' => 'DOSEN-007',
                'nidn' => '0077889900',
                'nip' => '199007072015022007',
                'nama_dosen' => 'Fitri Handayani',
                'gelar_depan' => '',
                'gelar_belakang' => 'S.Kom., M.Kom.',
                'email' => 'fitri.handayani@siakad.ac.id',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
        $students = [
            [
                'id_mahasiswa' => 'MHS-001',
                'nim' => '2026001001',
                'nama_mahasiswa' => 'Ahmad Fauzi',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'dosen_pa' => 'DOSEN-001',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'dosen_pa' => 'DOSEN-001',
                'email' => 'siti.nurhaliza@student.siakad.ac.id',
                'handphone' => '082100010002',
                'id_periode_masuk' => '20261',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-003',
                'nim' => '2024001003',
                'nama_mahasiswa' => 'Kevin Pratama',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_mahasiswa' => 'MHS-005',
                'nim' => '2022001005',
                'nama_mahasiswa' => 'Rizky Ramadhan',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'dosen_pa' => 'DOSEN-002',
                'email' => 'rizky.ramadhan@student.siakad.ac.id',
                'handphone' => '082100010005',
                'id_periode_masuk' => '20221',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
            ],
            [
                'id_mahasiswa' => 'MHS-006',
                'nim' => '2021001006',
                'nama_mahasiswa' => 'Dewi Kartika',
                'jenis_kelamin' => 'P',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'dosen_pa' => 'DOSEN-003',
                'email' => 'dewi.kartika@student.siakad.ac.id',
                'handphone' => '082100010006',
                'id_periode_masuk' => '20211',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'L',
                'nama_status_mahasiswa' => 'Lulus',
            ],
            [
                'id_mahasiswa' => 'MHS-007',
                'nim' => '2024002001',
                'nama_mahasiswa' => 'Farhan Alamsyah',
                'jenis_kelamin' => 'L',
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-01',
                'dosen_pa' => 'DOSEN-002',
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
                'id_prodi' => 'PRODI-MN',
                'id_fakultas' => 'FK-02',
                'dosen_pa' => 'DOSEN-006',
                'email' => 'nadya.zahrani@student.siakad.ac.id',
                'handphone' => '082100010008',
                'id_periode_masuk' => '20241',
                'kategori_ukt' => '3',
                'nominal_ukt' => 5000000,
                'id_status_mahasiswa' => 'A',
                'nama_status_mahasiswa' => 'Aktif',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
            ],
            [
                'id_asdos' => 'ASDOS-002',
                'kdasisten' => 'ASDOS02',
                'nama' => 'Nabila Putri, S.Kom.',
                'jkel' => 'P',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
            $connection->table('simak_site_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'ministry_name' => 'Kementerian Pendidikan Tinggi, Sains, dan Teknologi',
                    'motto' => 'Excellence in Academic Innovation & Integrity',
                    'primary_color' => '#1e3a8a',
                    'logo_path' => '/assets/images/logo-siakad.png',
                    'preloader_logo_path' => '/assets/images/logo-siakad.png',
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
