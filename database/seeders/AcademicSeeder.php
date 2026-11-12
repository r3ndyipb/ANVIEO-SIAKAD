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
        // =========================================================================
        $curriculums = [
            [
                'id_kurikulum' => 'KUR-TI-2026',
                'nama_kurikulum' => 'Kurikulum OBE 2026 S1 Teknik Informatika',
                'id_prodi' => 'PRODI-TI',
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
                'id_prodi' => 'PRODI-SI',
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
                'id_prodi' => 'PRODI-MN',
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
            // Semester 1
            [
                'id_matkul' => 'MK-TIF101',
                'kode_mata_kuliah' => 'TIF101',
                'nama_mata_kuliah' => 'Algoritma dan Pemrograman Dasar',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF103',
                'kode_mata_kuliah' => 'TIF103',
                'nama_mata_kuliah' => 'Pengantar Teknologi Informasi',
                'semester' => '1',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '2',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // Semester 2
            [
                'id_matkul' => 'MK-TIF201',
                'kode_mata_kuliah' => 'TIF201',
                'nama_mata_kuliah' => 'Struktur Data dan Algoritma Lanjut',
                'semester' => '2',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF202',
                'kode_mata_kuliah' => 'TIF202',
                'nama_mata_kuliah' => 'Pemrograman Berorientasi Objek',
                'semester' => '2',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // Semester 3
            [
                'id_matkul' => 'MK-TIF301',
                'kode_mata_kuliah' => 'TIF301',
                'nama_mata_kuliah' => 'Sistem Basis Data & SQL',
                'semester' => '3',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // Semester 4
            [
                'id_matkul' => 'MK-TIF401',
                'kode_mata_kuliah' => 'TIF401',
                'nama_mata_kuliah' => 'Rekayasa Perangkat Lunak',
                'semester' => '4',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
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
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // Semester 5 (Semester Berjalan 20261)
            [
                'id_matkul' => 'MK-TIF501',
                'kode_mata_kuliah' => 'TIF501',
                'nama_mata_kuliah' => 'Pemrograman Web Lanjut & API Service',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF502',
                'kode_mata_kuliah' => 'TIF502',
                'nama_mata_kuliah' => 'Kecerdasan Buatan dan Sistem Pakar',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF503',
                'kode_mata_kuliah' => 'TIF503',
                'nama_mata_kuliah' => 'Data Mining & Analitika Data Besar',
                'semester' => '5',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF504',
                'kode_mata_kuliah' => 'TIF504',
                'nama_mata_kuliah' => 'Interaksi Manusia dan Komputer (UI/UX)',
                'semester' => '5',
                'status' => 'Pilihan',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '2',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF505',
                'kode_mata_kuliah' => 'TIF505',
                'nama_mata_kuliah' => 'Keamanan Sistem Informasi & Cyber Security',
                'semester' => '5',
                'status' => 'Pilihan',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '1',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],

            // Semester 7 & 8
            [
                'id_matkul' => 'MK-TIF701',
                'kode_mata_kuliah' => 'TIF701',
                'nama_mata_kuliah' => 'Magang Industri & MBKM',
                'semester' => '7',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '4',
                'sks_tatap_muka' => '0',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '4',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF702',
                'kode_mata_kuliah' => 'TIF702',
                'nama_mata_kuliah' => 'Metodologi Penelitian & Penulisan Ilmiah',
                'semester' => '7',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '2',
                'sks_tatap_muka' => '2',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
            [
                'id_matkul' => 'MK-TIF801',
                'kode_mata_kuliah' => 'TIF801',
                'nama_mata_kuliah' => 'Skripsi / Tugas Akhir',
                'semester' => '8',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '6',
                'sks_tatap_muka' => '0',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '6',
                'sks_simulasi' => '0',
            ],

            // Sistem Informasi
            [
                'id_matkul' => 'MK-SIF301',
                'kode_mata_kuliah' => 'SIF301',
                'nama_mata_kuliah' => 'Manajemen Proyek Sistem Informasi',
                'semester' => '3',
                'status' => 'Wajib',
                'id_prodi' => 'PRODI-SI',
                'id_fakultas' => 'FK-01',
                'sks_mata_kuliah' => '3',
                'sks_tatap_muka' => '3',
                'sks_praktek' => '0',
                'sks_praktek_lapangan' => '0',
                'sks_simulasi' => '0',
            ],
        ];

        foreach ($courses as $c) {
            $conn->table('s1m4k_mastermk')->updateOrInsert(
                ['id_matkul' => $c['id_matkul']],
                array_merge($c, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'metode_kuliah' => 'Campuran (Blended)',
                    'ada_sap' => '1',
                    'ada_silabus' => '1',
                    'ada_bahan_ajar' => '1',
                    'ada_acara_praktek' => '1',
                    'ada_diktat' => '1',
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
            // Semester 20261 (Aktif)
            [
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF501',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-001',
                'id_ruang' => 'LAB-KOMP-1',
                'hari' => 'Senin',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-502A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF502',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-004',
                'id_ruang' => 'RUANG-101',
                'hari' => 'Selasa',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-503A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF503',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-002',
                'id_ruang' => 'LAB-KOMP-2',
                'hari' => 'Rabu',
                'jammulai' => '13:00:00',
                'jamselesai' => '15:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-504A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF504',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-007',
                'id_ruang' => 'RUANG-102',
                'hari' => 'Kamis',
                'jammulai' => '08:00:00',
                'jamselesai' => '09:40:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-505A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF505',
                'nama_kelas' => '5A',
                'dosen1' => 'DOSEN-005',
                'id_ruang' => 'LAB-KOMP-2',
                'hari' => 'Jumat',
                'jammulai' => '08:30:00',
                'jamselesai' => '11:00:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-101A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF101',
                'nama_kelas' => '1A',
                'dosen1' => 'DOSEN-001',
                'id_ruang' => 'LAB-KOMP-1',
                'hari' => 'Rabu',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-702A',
                'id_kalender' => '20261',
                'id_matkul' => 'MK-TIF702',
                'nama_kelas' => '7A',
                'dosen1' => 'DOSEN-003',
                'id_ruang' => 'RUANG-SEM',
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
                'dosen1' => 'DOSEN-001',
                'id_ruang' => 'RUANG-SEM',
                'hari' => 'Senin',
                'jammulai' => '09:00:00',
                'jamselesai' => '12:00:00',
                'jml_mahasiswa' => 1,
                'input_nilai' => 'N',
            ],

            // Semester 20252 (Genap Lalu - Selesai & Ada Nilai)
            [
                'id_jadwal' => 'JADWAL-TI-401A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF401',
                'nama_kelas' => '4A',
                'dosen1' => 'DOSEN-001',
                'id_ruang' => 'RUANG-101',
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
                'hari' => 'Selasa',
                'jammulai' => '10:00:00',
                'jamselesai' => '12:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'Y',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-302A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF302',
                'nama_kelas' => '4A',
                'dosen1' => 'DOSEN-003',
                'id_ruang' => 'RUANG-102',
                'hari' => 'Rabu',
                'jammulai' => '08:00:00',
                'jamselesai' => '10:30:00',
                'jml_mahasiswa' => 2,
                'input_nilai' => 'Y',
            ],
            [
                'id_jadwal' => 'JADWAL-TI-801A-252',
                'id_kalender' => '20252',
                'id_matkul' => 'MK-TIF801',
                'nama_kelas' => '8A',
                'dosen1' => 'DOSEN-003',
                'id_ruang' => 'RUANG-SEM',
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'kampus_merdeka' => '0',
                ])
            );
        }

        // =========================================================================
        // 4. KRS & KHS Nilai Mahasiswa (s1m4k_krs)
        // =========================================================================
        $krsRecords = [
            // --- Mahasiswa MHS-003 (Kevin Pratama, Semester 5 Aktif) ---
            [
                'id_krs' => 'KRS-20261-003-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_matkul' => 'MK-TIF501',
                'status_krs' => 'Setuju',
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
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-003-03',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-503A',
                'id_matkul' => 'MK-TIF503',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-003-04',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-504A',
                'id_matkul' => 'MK-TIF504',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-003-05',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-505A',
                'id_matkul' => 'MK-TIF505',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-004 (Putri Ayu Lestari, Semester 5 Aktif) ---
            [
                'id_krs' => 'KRS-20261-004-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-501A',
                'id_matkul' => 'MK-TIF501',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-004-02',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-502A',
                'id_matkul' => 'MK-TIF502',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-004-03',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-503A',
                'id_matkul' => 'MK-TIF503',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-001 (Ahmad Fauzi, Semester 1 Baru) ---
            [
                'id_krs' => 'KRS-20261-001-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-001',
                'id_jadwal' => 'JADWAL-TI-101A',
                'id_matkul' => 'MK-TIF101',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Mahasiswa MHS-005 (Rizky Ramadhan, Semester 7 Tugas Akhir) ---
            [
                'id_krs' => 'KRS-20261-005-01',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-005',
                'id_jadwal' => 'JADWAL-TI-702A',
                'id_matkul' => 'MK-TIF702',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],
            [
                'id_krs' => 'KRS-20261-005-02',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'id_mahasiswa' => 'MHS-005',
                'id_jadwal' => 'JADWAL-TI-801A',
                'id_matkul' => 'MK-TIF801',
                'status_krs' => 'Setuju',
                'nilai_angka' => 0.00,
                'nilai_bobot' => 0.00,
                'nilai_huruf' => 'E',
                'input_nilai' => 'N',
            ],

            // --- Riwayat KHS Semester 20252 (Genap Lalu) dengan Nilai Final ---
            // Kevin Pratama (MHS-003)
            [
                'id_krs' => 'KRS-20252-003-01',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-401A-252',
                'id_matkul' => 'MK-TIF401',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 90.00,
                'nilai_tugas' => 88.00,
                'nilai_quiz' => 85.00,
                'nilai_uts' => 88.00,
                'nilai_uas' => 90.00,
                'nilai_angka' => 88.50,
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
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 80.00,
                'nilai_tugas' => 82.00,
                'nilai_quiz' => 80.00,
                'nilai_uts' => 84.00,
                'nilai_uas' => 82.00,
                'nilai_angka' => 82.00,
                'nilai_bobot' => 3.50,
                'nilai_huruf' => 'B+',
                'input_nilai' => 'Y',
            ],
            [
                'id_krs' => 'KRS-20252-003-03',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-003',
                'id_jadwal' => 'JADWAL-TI-302A-252',
                'id_matkul' => 'MK-TIF302',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 85.00,
                'nilai_tugas' => 86.00,
                'nilai_quiz' => 88.00,
                'nilai_uts' => 85.00,
                'nilai_uas' => 87.00,
                'nilai_angka' => 86.00,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],

            // Putri Ayu Lestari (MHS-004)
            [
                'id_krs' => 'KRS-20252-004-01',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-401A-252',
                'id_matkul' => 'MK-TIF401',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 95.00,
                'nilai_tugas' => 92.00,
                'nilai_quiz' => 90.00,
                'nilai_uts' => 92.00,
                'nilai_uas' => 91.00,
                'nilai_angka' => 91.80,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],
            [
                'id_krs' => 'KRS-20252-004-02',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-004',
                'id_jadwal' => 'JADWAL-TI-402A-252',
                'id_matkul' => 'MK-TIF402',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 90.00,
                'nilai_tugas' => 88.00,
                'nilai_quiz' => 85.00,
                'nilai_uts' => 86.00,
                'nilai_uas' => 88.00,
                'nilai_angka' => 87.40,
                'nilai_bobot' => 4.00,
                'nilai_huruf' => 'A',
                'input_nilai' => 'Y',
            ],

            // Dewi Kartika (MHS-006 - Calon Wisudawan)
            [
                'id_krs' => 'KRS-20252-006-01',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'id_mahasiswa' => 'MHS-006',
                'id_jadwal' => 'JADWAL-TI-801A-252',
                'id_matkul' => 'MK-TIF801',
                'status_krs' => 'Setuju',
                'nilai_partisipatif' => 95.00,
                'nilai_tugas' => 96.00,
                'nilai_quiz' => 95.00,
                'nilai_uts' => 94.00,
                'nilai_uas' => 95.00,
                'nilai_angka' => 95.00,
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'krs_reguler' => 'Y',
                    'krs_mbkm' => 'N',
                    'isi_edom' => 'Y',
                    'flag_feeder' => 0,
                    'tgl_update' => '2026-08-10',
                ])
            );
        }

        // =========================================================================
        // 5. Pengesahan KRS (s1m4k_pengesahankrs)
        // =========================================================================
        $approvals = [
            [
                'id_pengesahan' => 'SAH-20261-001',
                'id_mahasiswa' => 'MHS-001',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-15',
                'yang_mengesahkan' => 'Dr. Budi Santoso, M.Kom.',
                'tgl_pengesahan_kpro' => '2026-08-16',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'SAH-20261-003',
                'id_mahasiswa' => 'MHS-003',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-12',
                'yang_mengesahkan' => 'Dian Kusuma, M.Cs.',
                'tgl_pengesahan_kpro' => '2026-08-14',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'SAH-20261-004',
                'id_mahasiswa' => 'MHS-004',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-12',
                'yang_mengesahkan' => 'Dian Kusuma, M.Cs.',
                'tgl_pengesahan_kpro' => '2026-08-14',
                'nama_kpro' => 'Dr. Budi Santoso, M.Kom.',
            ],
            [
                'id_pengesahan' => 'SAH-20261-005',
                'id_mahasiswa' => 'MHS-005',
                'id_kalender' => '20261',
                'tgl_pengesahan' => '2026-08-10',
                'yang_mengesahkan' => 'Dr. Siti Aminah, S.Kom., M.T.',
                'tgl_pengesahan_kpro' => '2026-08-11',
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
            [
                'id_ipmhs' => 'AKM-003-252',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-003',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ipk' => '3.78',
                'ips' => '3.83',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '18',
                'sks_semester' => '18',
                'sks_lulus' => '82',
            ],
            [
                'id_ipmhs' => 'AKM-003-261',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-003',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'ipk' => '3.78',
                'ips' => '0.00',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '14',
                'sks_semester' => '14',
                'sks_lulus' => '82',
            ],
            [
                'id_ipmhs' => 'AKM-004-252',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-004',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ipk' => '3.85',
                'ips' => '4.00',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '18',
                'sks_semester' => '18',
                'sks_lulus' => '82',
            ],
            [
                'id_ipmhs' => 'AKM-004-261',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-004',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'ipk' => '3.85',
                'ips' => '0.00',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '14',
                'sks_semester' => '14',
                'sks_lulus' => '82',
            ],
            [
                'id_ipmhs' => 'AKM-005-252',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-005',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ipk' => '3.65',
                'ips' => '3.60',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '16',
                'sks_semester' => '16',
                'sks_lulus' => '138',
            ],
            [
                'id_ipmhs' => 'AKM-005-261',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-005',
                'id_kalender' => '20261',
                'tahun' => '20261',
                'ipk' => '3.65',
                'ips' => '0.00',
                'status_mahasiswa' => 'Aktif',
                'sks_program' => '8',
                'sks_semester' => '8',
                'sks_lulus' => '138',
            ],
            [
                'id_ipmhs' => 'AKM-006-252',
                'id_prodi' => 'PRODI-TI',
                'id_mahasiswa' => 'MHS-006',
                'id_kalender' => '20252',
                'tahun' => '20252',
                'ipk' => '3.92',
                'ips' => '4.00',
                'status_mahasiswa' => 'Lulus',
                'sks_program' => '6',
                'sks_semester' => '6',
                'sks_lulus' => '146',
                'no_ijazah' => 'SIAKAD-2026-TI-001',
                'tgl_keluar' => '2026-08-01',
            ],
        ];

        foreach ($akmRecords as $akm) {
            $conn->table('s1m4k_akm')->updateOrInsert(
                ['id_ipmhs' => $akm['id_ipmhs']],
                array_merge($akm, [
                    'update_akm' => 1,
                    'simpan' => 1,
                    'flag_feeder' => 0,
                ])
            );
        }

        // =========================================================================
        // 7. Billing & Pembayaran UKT (s1m4k_billing, s1m4k_pembayaranukt)
        // =========================================================================
        $billings = [
            [
                'id_billing' => 'BILL-20261-001',
                'id_mahasiswa' => 'MHS-001',
                'trx_id' => 'TRX20261001',
                'billing' => 'UKT-20261-001',
                'nominal' => 5000000,
                'expire' => '2026-09-30 23:59:59',
                'tahun' => 20261,
            ],
            [
                'id_billing' => 'BILL-20261-002',
                'id_mahasiswa' => 'MHS-002',
                'trx_id' => 'TRX20261002',
                'billing' => 'UKT-20261-002',
                'nominal' => 5000000,
                'expire' => '2026-09-30 23:59:59',
                'tahun' => 20261,
            ],
            [
                'id_billing' => 'BILL-20261-003',
                'id_mahasiswa' => 'MHS-003',
                'trx_id' => 'TRX20261003',
                'billing' => 'UKT-20261-003',
                'nominal' => 6000000,
                'expire' => '2026-09-30 23:59:59',
                'tahun' => 20261,
            ],
            [
                'id_billing' => 'BILL-20261-004',
                'id_mahasiswa' => 'MHS-004',
                'trx_id' => 'TRX20261004',
                'billing' => 'UKT-20261-004',
                'nominal' => 6000000,
                'expire' => '2026-09-30 23:59:59',
                'tahun' => 20261,
            ],
            [
                'id_billing' => 'BILL-20261-005',
                'id_mahasiswa' => 'MHS-005',
                'trx_id' => 'TRX20261005',
                'billing' => 'UKT-20261-005',
                'nominal' => 5000000,
                'expire' => '2026-09-30 23:59:59',
                'tahun' => 20261,
            ],
        ];

        foreach ($billings as $b) {
            $conn->table('s1m4k_billing')->updateOrInsert(
                ['id_billing' => $b['id_billing']],
                $b
            );
        }

        $payments = [
            [
                'id' => 'PAY-20261-001',
                'id_mahasiswa' => 'MHS-001',
                'tahun' => '20261',
                'nama_bank' => 'Bank Mandiri',
                'trx_id' => 'TRX20261001',
                'no_va' => '88012026001001',
                'nominal' => 5000000,
                'tgl_expire' => '2026-09-30 23:59:59',
                'lunas' => 1,
                'tanggal_bayar' => '2026-08-05 10:15:30',
                'petugas' => 'MIDTRANS_GATEWAY',
            ],
            [
                'id' => 'PAY-20261-002',
                'id_mahasiswa' => 'MHS-002',
                'tahun' => '20261',
                'nama_bank' => 'Bank BNI',
                'trx_id' => 'TRX20261002',
                'no_va' => '88022026001002',
                'nominal' => 5000000,
                'tgl_expire' => '2026-09-30 23:59:59',
                'lunas' => 0,
                'tanggal_bayar' => null,
                'petugas' => 'SYSTEM',
            ],
            [
                'id' => 'PAY-20261-003',
                'id_mahasiswa' => 'MHS-003',
                'tahun' => '20261',
                'nama_bank' => 'Bank BCA',
                'trx_id' => 'TRX20261003',
                'no_va' => '88032024001003',
                'nominal' => 6000000,
                'tgl_expire' => '2026-09-30 23:59:59',
                'lunas' => 1,
                'tanggal_bayar' => '2026-08-02 14:22:10',
                'petugas' => 'MIDTRANS_GATEWAY',
            ],
            [
                'id' => 'PAY-20261-004',
                'id_mahasiswa' => 'MHS-004',
                'tahun' => '20261',
                'nama_bank' => 'Bank BRI',
                'trx_id' => 'TRX20261004',
                'no_va' => '88042024001004',
                'nominal' => 6000000,
                'tgl_expire' => '2026-09-30 23:59:59',
                'lunas' => 1,
                'tanggal_bayar' => '2026-08-03 09:12:45',
                'petugas' => 'MIDTRANS_GATEWAY',
            ],
            [
                'id' => 'PAY-20261-005',
                'id_mahasiswa' => 'MHS-005',
                'tahun' => '20261',
                'nama_bank' => 'Bank BSI',
                'trx_id' => 'TRX20261005',
                'no_va' => '88052022001005',
                'nominal' => 5000000,
                'tgl_expire' => '2026-09-30 23:59:59',
                'lunas' => 1,
                'tanggal_bayar' => '2026-08-01 11:05:00',
                'petugas' => 'MIDTRANS_GATEWAY',
            ],
        ];

        foreach ($payments as $p) {
            $conn->table('s1m4k_pembayaranukt')->updateOrInsert(
                ['id' => $p['id']],
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
            ],
        ];

        foreach ($camabaList as $cam) {
            $conn->table('s1m4k_camaba')->updateOrInsert(
                ['id' => $cam['id']],
                array_merge($cam, [
                    'id_perguruan_tinggi' => 'PT-001',
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                ])
            );
        }

        // =========================================================================
        // 9. E-Skripsi / Tugas Akhir (eskripsi_judul, eskripsi_skripsi, eskripsi_bimbingan, eskripsi_ujian)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('eskripsi_judul')) {
            $conn->table('eskripsi_judul')->updateOrInsert(
                ['id' => 'JUDUL-001'],
                [
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'id_mahasiswa' => 'MHS-005',
                    'id_proposal' => 'JUDUL-001',
                    'judul' => 'Rancang Bangun Sistem Informasi Akademik Terdistribusi Menggunakan PostgreSQL Concurrency Locking',
                    'pembimbing1' => 'DOSEN-001',
                    'pembimbing2' => 'DOSEN-002',
                    'penguji1' => 'DOSEN-003',
                    'penguji2' => 'DOSEN-004',
                    'penguji3' => 'DOSEN-005',
                    'status' => 2, // Siap Ujian
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $conn->table('eskripsi_skripsi')->updateOrInsert(
                ['id' => 'SKRIPSI-002'],
                [
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'id_mahasiswa' => 'MHS-006',
                    'id_proposal' => 'JUDUL-002',
                    'judul' => 'Implementasi Convolutional Neural Network untuk Klasifikasi Citra Medis Realtime',
                    'pembimbing1' => 'DOSEN-004',
                    'pembimbing2' => 'DOSEN-003',
                    'penguji1' => 'DOSEN-001',
                    'penguji2' => 'DOSEN-002',
                    'penguji3' => 'DOSEN-005',
                    'status' => 5, // Lulus Sidang
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::connection('simak_main')->hasTable('eskripsi_bimbingan')) {
            $bimbinganLogs = [
                ['id_bimbingan' => 'BIMB-001', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-001', 'pengirim' => 'mahasiswa', 'pesan' => 'Selamat pagi Pak Budi, saya melampirkan draf Bab 1 & 2 untuk direview.', 'waktu_kirim' => '2026-03-01 09:00:00'],
                ['id_bimbingan' => 'BIMB-002', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-001', 'pengirim' => 'dosen', 'pesan' => 'Draf sudah dibaca. Perbaiki rumusan masalah dan tambahkan kajian pustaka terkini.', 'waktu_kirim' => '2026-03-05 14:30:00'],
                ['id_bimbingan' => 'BIMB-003', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-001', 'pengirim' => 'mahasiswa', 'pesan' => 'Revisi rumusan masalah sudah diperbaiki dan ditambahkan perancangan arsitektur.', 'waktu_kirim' => '2026-03-20 10:15:00'],
                ['id_bimbingan' => 'BIMB-004', 'id_skripsi' => 'SKRIPSI-001', 'id_dosen_tujuan' => 'DOSEN-001', 'pengirim' => 'dosen', 'pesan' => 'Bagus, lanjutkan ke implementasi dan pengujian benchmark transaksi.', 'waktu_kirim' => '2026-03-25 11:00:00'],
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
                    'waktu_ujian' => '2026-07-15 09:00:00',
                    'ruang_ujian' => 'RUANG-SEM',
                    'nilaipembimbing1' => 94,
                    'nilaipembimbing2' => 95,
                    'nilaipenguji1' => 92,
                    'nilaipenguji2' => 96,
                    'nilaipenguji3' => 95,
                    'catatanpembimbing1' => 'Penelitian sangat inovatif dan berpotensi publikasi jurnal bereputasi.',
                    'catatanpembimbing2' => 'Hasil eksperimen konsisten.',
                    'catatanpenguji1' => 'Kemampuan argumentasi sangat baik.',
                    'catatanpenguji2' => 'Penyusunan naskah rapi.',
                    'catatanpenguji3' => 'Memenuhi seluruh kriteria kelulusan Cum Laude.',
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
            ['id_peserta' => 'WISUDA-20261-001'],
            [
                'id_periode' => '20261',
                'id_prodi' => 'PRODI-TI',
                'id_fakultas' => 'FK-01',
                'id_mahasiswa' => 'MHS-006',
                'bebas_spp' => 'Y',
                'bebas_perpus' => 'Y',
                'judul_skripsi' => 'Implementasi Convolutional Neural Network untuk Klasifikasi Citra Medis Realtime',
                'status' => 1,
                'tgl_daftar' => '2026-08-05',
                'petugas' => 'PANITIA_WISUDA',
                'lolos' => 'Y',
                'komentar' => 'Berkas yudisium lengkap dan memenuhi syarat predikat Dengan Pujian (Cum Laude).',
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
                    'id_fakultas' => 'FK-01',
                    'id_prodi' => 'PRODI-TI',
                    'tahun' => 2026,
                    'visi_keilmuan' => 'Menjadi pusat unggulan pengembangan perangkat lunak cerdas dan rekayasa data terdistribusi.',
                    'misi_prodi' => 'Menyelenggarakan pendidikan tinggi bertaraf internasional berbasis luaran (OBE).',
                    'status_kur' => 'Aktif',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if (Schema::connection('simak_main')->hasTable('ekur_profil_lulusan')) {
                $profiles = [
                    ['id_pl' => 1, 'kode_pl' => 'PL-01', 'profil_lulusan' => 'Software Engineer / Architect', 'aspek' => 'Profesional', 'deskripsi_pl' => 'Mampu merancang dan membangun arsitektur perangkat lunak skala besar.'],
                    ['id_pl' => 2, 'kode_pl' => 'PL-02', 'profil_lulusan' => 'AI & Data Science Specialist', 'aspek' => 'Profesional', 'deskripsi_pl' => 'Mampu mengimplementasikan model pembelajaran mesin dan rekayasa data.'],
                    ['id_pl' => 3, 'kode_pl' => 'PL-03', 'profil_lulusan' => 'Cybersecurity Analyst', 'aspek' => 'Profesional', 'deskripsi_pl' => 'Mampu mengamankan infrastruktur jaringan dan integritas transaksi digital.'],
                ];

                foreach ($profiles as $pl) {
                    $conn->table('ekur_profil_lulusan')->updateOrInsert(
                        ['id_pl' => $pl['id_pl']],
                        array_merge($pl, ['id_kur' => 1])
                    );
                }
            }

            if (Schema::connection('simak_main')->hasTable('ekur_cpl')) {
                $cplList = [
                    ['id_cpl' => 1, 'kode_cpl' => 'CPL-S1', 'aspek' => 'Sikap', 'deskripsi_cpl' => 'Menunjukkan integritas akademik, etika profesional, dan tanggung jawab sosial.'],
                    ['id_cpl' => 2, 'kode_cpl' => 'CPL-P1', 'aspek' => 'Pengetahuan', 'deskripsi_cpl' => 'Menguasai konsep teoretis sains komputasi, algoritma, dan rekayasa sistem terdistribusi.'],
                    ['id_cpl' => 3, 'kode_cpl' => 'CPL-KU1', 'aspek' => 'Keterampilan Umum', 'deskripsi_cpl' => 'Mampu menerapkan pemikiran logis, kritis, dan analitis dalam penyelesaian masalah komputasi.'],
                    ['id_cpl' => 4, 'kode_cpl' => 'CPL-KK1', 'aspek' => 'Keterampilan Khusus', 'deskripsi_cpl' => 'Mampu merekayasa arsitektur backend, middleware, dan antarmuka web modern yang scalable.'],
                ];

                foreach ($cplList as $cpl) {
                    $conn->table('ekur_cpl')->updateOrInsert(
                        ['id_cpl' => $cpl['id_cpl']],
                        array_merge($cpl, ['id_kur' => 1])
                    );
                }
            }
        }

        // =========================================================================
        // 12. LMS Virtual Classroom (lms_pertemuan, lms_materi_kuliah, lms_tugas)
        // =========================================================================
        if (Schema::connection('simak_main')->hasTable('lms_pertemuan')) {
            $sessions = [
                ['id_pertemuan' => 'SESI-TI501-01', 'pertemuan_ke' => 1, 'judul' => 'Kontrak Perkuliahan, Arsitektur Web Modern & RESTful API', 'tanggal_kuliah' => '2026-08-25', 'status_pertemuan' => 'selesai'],
                ['id_pertemuan' => 'SESI-TI501-02', 'pertemuan_ke' => 2, 'judul' => 'Advanced Service Layer & Form Request Validation', 'tanggal_kuliah' => '2026-09-01', 'status_pertemuan' => 'selesai'],
                ['id_pertemuan' => 'SESI-TI501-03', 'pertemuan_ke' => 3, 'judul' => 'Database Optimization: Zero N+1 Query & Eager Loading', 'tanggal_kuliah' => '2026-09-08', 'status_pertemuan' => 'selesai'],
                ['id_pertemuan' => 'SESI-TI501-04', 'pertemuan_ke' => 4, 'judul' => 'High-Concurrency Locking: Pessimistic Row Locking vs Optimistic', 'tanggal_kuliah' => '2026-09-15', 'status_pertemuan' => 'selesai'],
                ['id_pertemuan' => 'SESI-TI501-05', 'pertemuan_ke' => 5, 'judul' => 'Background Jobs, Redis Queues & Async Processing', 'tanggal_kuliah' => '2026-09-22', 'status_pertemuan' => 'selesai'],
                ['id_pertemuan' => 'SESI-TI501-06', 'pertemuan_ke' => 6, 'judul' => 'Payment Gateway Integration: Midtrans Webhook Idempotency', 'tanggal_kuliah' => '2026-09-29', 'status_pertemuan' => 'aktif'],
                ['id_pertemuan' => 'SESI-TI501-07', 'pertemuan_ke' => 7, 'judul' => 'Review Materi & Persiapan Ujian Tengah Semester (UTS)', 'tanggal_kuliah' => '2026-10-06', 'status_pertemuan' => 'terjadwal'],
            ];

            foreach ($sessions as $sesi) {
                $conn->table('lms_pertemuan')->updateOrInsert(
                    ['id_pertemuan' => $sesi['id_pertemuan']],
                    array_merge($sesi, [
                        'id_jadwal' => 'JADWAL-TI-501A',
                        'id_dosen' => 'DOSEN-001',
                        'waktu_mulai' => '08:00:00',
                        'waktu_selesai' => '10:30:00',
                        'absen_dibuka' => 1,
                        'tautan_video' => 'https://meet.siakad.ac.id/pemrograman-web-lanjut',
                        'dibuat_pada' => $now,
                        'diperbarui_pada' => $now,
                    ])
                );
            }
        }

        if (Schema::connection('simak_main')->hasTable('lms_materi_kuliah')) {
            $materials = [
                ['id_materi' => 'MAT-01', 'judul' => 'Slide Modul 1: Desain API Berbasis Kontrak', 'deskripsi' => 'Pengenalan prinsip OpenAPI 3.1, DTO, dan arsitektur thin controller.'],
                ['id_materi' => 'MAT-02', 'judul' => 'Slide Modul 3: Strategi Optimasi Eager Loading', 'deskripsi' => 'Analisis kueri SQL dengan EXPLAIN ANALYZE dan eliminasi query redundan.'],
                ['id_materi' => 'MAT-03', 'judul' => 'Slide Modul 4: Pessimistic Locking pada PostgreSQL', 'deskripsi' => 'Implementasi SELECT FOR UPDATE pada transaksi berbeban puncak.'],
            ];

            foreach ($materials as $mat) {
                $conn->table('lms_materi_kuliah')->updateOrInsert(
                    ['id_materi' => $mat['id_materi']],
                    array_merge($mat, [
                        'id_dosen' => 'DOSEN-001',
                        'id_matkul' => 'MK-TIF501',
                        'id_prodi' => 'PRODI-TI',
                        'nama_berkas' => 'modul-siakad.pdf',
                        'path_berkas' => '/storage/materi/modul-siakad.pdf',
                        'tipe_berkas' => 'application/pdf',
                        'ukuran_berkas' => 2048000,
                        'status_publikasi' => 'terbit',
                        'dibuat_pada' => $now,
                        'diperbarui_pada' => $now,
                    ])
                );
            }
        }

        if (Schema::connection('simak_main')->hasTable('lms_tugas')) {
            $assignments = [
                [
                    'id_tugas' => 'TUGAS-TI501-01',
                    'judul' => 'Tugas 1: Implementasi Service-Action Pattern & Validasi Form',
                    'deskripsi' => 'Buat implementasi controller tipis dengan Service dan Form Request terenkapsulasi penuh.',
                    'batas_pengumpulan' => '2026-09-10 23:59:00',
                    'status_tugas' => 'dibuka',
                ],
                [
                    'id_tugas' => 'TUGAS-TI501-02',
                    'judul' => 'Tugas 2: Concurrency Stress Test Menggunakan k6',
                    'deskripsi' => 'Lakukan pengujian 1.000 request bersamaan untuk membuktikan zero-overbooking pada alokasi kuota kelas.',
                    'batas_pengumpulan' => '2026-09-25 23:59:00',
                    'status_tugas' => 'dibuka',
                ],
            ];

            foreach ($assignments as $tugas) {
                $conn->table('lms_tugas')->updateOrInsert(
                    ['id_tugas' => $tugas['id_tugas']],
                    array_merge($tugas, [
                        'id_jadwal' => 'JADWAL-TI-501A',
                        'id_dosen' => 'DOSEN-001',
                        'dibuat_pada' => $now,
                        'diperbarui_pada' => $now,
                    ])
                );
            }
        }
    }
}
