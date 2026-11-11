<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
                'nama_perguruan_tinggi' => 'Universitas SIAKAD',
                'email' => 'info@siakad.ac.id',
                'website' => 'https://siakad.ac.id',
            ]
        );

        // 2. Master Fakultas
        $connection->table('s1m4k_masterfakultas')->updateOrInsert(
            ['id_fakultas' => 'FK-01'],
            [
                'id_perguruan_tinggi' => 'PT-001',
                'kode_fakultas' => 'FT',
                'nama_fakultas' => 'Fakultas Sains dan Teknologi',
                'status' => 'Aktif',
            ]
        );

        // 3. Master Program Studi
        $connection->table('s1m4k_masterprodi')->updateOrInsert(
            ['id_prodi' => 'PRODI-TI'],
            [
                'id_perguruan_tinggi' => 'PT-001',
                'id_fakultas' => 'FK-01',
                'kode_program_studi' => '55201',
                'nama_program_studi' => 'Teknik Informatika',
                'nama_jenjang_pendidikan' => 'S1',
                'status' => 'A',
                'k1' => 0, 'k2' => 0, 'k3' => 0, 'k4' => 0,
                'k5' => 0, 'k6' => 0, 'k7' => 0, 'k8' => 0,
            ]
        );

        // 4. Master Kalender Akademik Aktif
        $connection->table('s1m4k_kalender')->updateOrInsert(
            ['id_kalender' => '20261'],
            [
                'id_perguruan_tinggi' => 'PT-001',
                'id_fakultas' => 'FK-01',
                'id_prodi' => 'PRODI-TI',
                'tahun' => '20261',
                'namatahun' => '2026/2027 Ganjil',
                'periode' => 'Ganjil',
                'status' => 'Y',
                'status_sp' => 'N',
                'tgl_mulai_krs' => '2026-08-01',
                'tgl_akhir_krs' => '2026-09-30',
                'tgl_mulai_isinilai' => '2026-11-01',
                'tgl_akhir_isinilai' => '2027-01-31',
            ]
        );

        // 5. Akun Pengelola & Staf (s1m4k_admin)
        $adminUsers = [
            [
                'username' => 'admin',
                'nama_lengkap' => 'Administrator Utama',
                'level' => 'admin',
                'id_prodi' => null,
                'id_fakultas' => null,
            ],
            [
                'username' => 'kaprodi',
                'nama_lengkap' => 'Kaprodi Teknik Informatika',
                'level' => 'userprodi',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
            ],
            [
                'username' => 'tu_prodi',
                'nama_lengkap' => 'Tata Usaha Teknik Informatika',
                'level' => 'usertu',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
            ],
            [
                'username' => 'keuangan',
                'nama_lengkap' => 'Staf Keuangan & UKT',
                'level' => 'keuangan',
                'id_prodi' => null,
                'id_fakultas' => null,
            ],
            [
                'username' => 'pimpinan',
                'nama_lengkap' => 'Rektor Universitas SIAKAD',
                'level' => 'pimpinan',
                'id_prodi' => null,
                'id_fakultas' => null,
            ],
            [
                'username' => 'dekan',
                'nama_lengkap' => 'Dekan Fakultas Sains dan Teknologi',
                'level' => 'pimpinan_fakultas',
                'id_prodi' => null,
                'id_fakultas' => 'FK-01',
            ],
            [
                'username' => 'wisuda',
                'nama_lengkap' => 'Pengelola Kelulusan & Wisuda',
                'level' => 'wisuda',
                'id_prodi' => null,
                'id_fakultas' => null,
            ],
            [
                'username' => 'validator',
                'nama_lengkap' => 'Tim Validasi Akademik',
                'level' => 'validasi',
                'id_prodi' => null,
                'id_fakultas' => null,
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
                    'blokir' => 'N',
                    'updated_at' => $now,
                ]
            );
        }

        // 6. Akun Dosen (s1m4k_masterdosen)
        $connection->table('s1m4k_masterdosen')->updateOrInsert(
            ['id_dosen' => 'DOSEN-001'],
            [
                'id_perguruan_tinggi' => 'PT-001',
                'id_fakultas' => 'FK-01',
                'id_prodi' => 'PRODI-TI',
                'nidn' => '0011223344',
                'nama_dosen' => 'Dr. Budi Santoso, M.Kom.',
                'email' => 'budi.santoso@siakad.ac.id',
                'password' => $defaultPassword,
                'password_baru' => null,
                'jenis_kelamin' => 'L',
                'nama_status_aktif' => 'Aktif',
            ]
        );

        // 7. Akun Mahasiswa (s1m4k_mastermhs)
        $students = [
            [
                'id_mahasiswa' => 'MHS-001',
                'nim' => '2026001001',
                'nama_mahasiswa' => 'Ahmad Fauzi',
                'jenis_kelamin' => 'L',
            ],
            [
                'id_mahasiswa' => 'MHS-002',
                'nim' => '2026001002',
                'nama_mahasiswa' => 'Siti Nurhaliza',
                'jenis_kelamin' => 'P',
            ],
        ];

        foreach ($students as $mhs) {
            $connection->table('s1m4k_mastermhs')->updateOrInsert(
                ['id_mahasiswa' => $mhs['id_mahasiswa']],
                [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'dosen_pa' => 'DOSEN-001',
                    'nim' => $mhs['nim'],
                    'nama_mahasiswa' => $mhs['nama_mahasiswa'],
                    'jenis_kelamin' => $mhs['jenis_kelamin'],
                    'password' => $defaultPassword,
                    'password_baru' => null,
                    'id_status_mahasiswa' => 'A',
                    'nama_status_mahasiswa' => 'Aktif',
                ]
            );
        }

        // 8. Akun Asisten Dosen (s1m4k_masterasdos)
        $connection->table('s1m4k_masterasdos')->updateOrInsert(
            ['id_asdos' => 'ASDOS-001'],
            [
                'id_perguruan_tinggi' => 'PT-001',
                'id_fakultas' => 'FK-01',
                'id_prodi' => 'PRODI-TI',
                'kdasisten' => 'ASDOS01',
                'nama' => 'Rian Pratama, S.Kom.',
                'jkel' => 'L',
                'password' => $defaultPassword,
                'status' => 'Aktif',
            ]
        );

        // 9. Reset Sequence PostgreSQL
        if ($connection->getDriverName() === 'pgsql') {
            app(\App\Console\Commands\ResetPostgresSequences::class);
            $this->command?->call('siakad:reset-sequences', ['--connection' => 'simak_main']);
        }
    }
}
