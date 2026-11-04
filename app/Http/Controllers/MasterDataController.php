<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFeederService;
use App\Support\LecturerName;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class MasterDataController extends Controller
{
    private const MODULES = [
        'perguruan-tinggi' => [
            'title' => 'Perguruan Tinggi',
            'table' => 's1m4k_masterpt',
            'key' => 'id_perguruan_tinggi',
            'nameColumn' => 'nama_perguruan_tinggi',
            'identityColumn' => 'kode_perguruan_tinggi',
            'roles' => ['admin', 'pimuniv', 'pimfak'],
            'note' => 'Data profil bersumber dari Feeder. Gunakan sinkronisasi untuk memperbarui data lokal.',
            'columns' => [
                'kode_perguruan_tinggi' => 'Kode',
                'nama_perguruan_tinggi' => 'Nama perguruan tinggi',
                'email' => 'Email',
                'telepon' => 'Telepon',
                'website' => 'Website',
                'jalan' => 'Alamat',
                'sk_pendirian' => 'SK pendirian',
                'status_perguruan_tinggi' => 'Status',
            ],
        ],
        'fakultas' => [
            'title' => 'Fakultas',
            'table' => 's1m4k_masterfakultas',
            'key' => 'id_fakultas',
            'identityColumn' => 'kode_fakultas',
            'roles' => ['admin', 'pimuniv', 'pimfak'],
            'facultyScoped' => true,
            'relations' => ['dekan', 'pd1', 'pd2', 'pd3'],
            'nameColumn' => 'nama_fakultas',
            'columns' => [
                'kode_fakultas' => 'Kode',
                'nama_fakultas' => 'Nama fakultas',
                'nama_dekan' => 'Dekan',
                'status' => 'Status',
            ],
            'edit' => [
                ['name' => 'nama_fakultas', 'label' => 'Nama fakultas', 'required' => true, 'max' => 50],
                ['name' => 'dekan', 'label' => 'Dekan', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd1', 'label' => 'Wakil dekan I', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd2', 'label' => 'Wakil dekan II', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd3', 'label' => 'Wakil dekan III', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'status', 'label' => 'Status Fakultas', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
            'create' => [
                ['name' => 'kode_fakultas', 'label' => 'Kode fakultas', 'required' => true, 'max' => 2],
                ['name' => 'nama_fakultas', 'label' => 'Nama fakultas', 'required' => true, 'max' => 50],
                ['name' => 'dekan', 'label' => 'Dekan', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd1', 'label' => 'Wakil dekan I', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd2', 'label' => 'Wakil dekan II', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'pd3', 'label' => 'Wakil dekan III', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'status', 'label' => 'Status Fakultas', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
        ],
        'program-studi' => [
            'title' => 'Program Studi',
            'table' => 's1m4k_masterprodi',
            'key' => 'id_prodi',
            'identityColumn' => 'kode_program_studi',
            'roles' => ['admin', 'pimuniv', 'pimfak'],
            'facultyScoped' => true,
            'note' => 'Sinkronisasi memperbarui daftar program studi di SIMAK dari Feeder.',
            'relations' => ['faculty', 'head'],
            'nameColumn' => 'nama_program_studi',
            'columns' => [
                'kode_program_studi' => 'Kode',
                'nama_program_studi' => 'Nama program studi',
                'nama_jenjang_pendidikan' => 'Jenjang',
                'nama_fakultas' => 'Fakultas',
                'nama_ketua_prodi' => 'Ketua prodi',
                'status' => 'Status',
            ],
            'edit' => [
                ['name' => 'id_fakultas', 'label' => 'Fakultas', 'type' => 'select', 'options' => 'faculties', 'required' => true],
                ['name' => 'ketua_prodi', 'label' => 'Ketua program studi', 'type' => 'select', 'options' => 'lecturers', 'relation' => 'head'],
            ],
        ],
        'dosen' => [
            'title' => 'Dosen',
            'table' => 's1m4k_masterdosen',
            'key' => 'id_dosen',
            'roles' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'facultyScoped' => true,
            'programScoped' => true,
            'relations' => ['program'],
            'nameColumn' => 'nama_dosen',
            'identityColumn' => 'nidn',
            'requiredName' => 'nidn',
            'columns' => [
                'nidn' => 'NIDN',
                'nama_dosen' => 'Nama dosen',
                'email' => 'Email',
                'nama_prodi' => 'Program studi',
                'nama_status_aktif' => 'Status',
            ],
            'edit' => [
                ['name' => 'gelar_depan', 'label' => 'Gelar depan', 'max' => 20],
                ['name' => 'gelar_belakang', 'label' => 'Gelar belakang', 'max' => 50],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 50],
                ['name' => 'nip', 'label' => 'NIP', 'max' => 50],
            ],
        ],
        'asisten-dosen' => [
            'title' => 'Asisten Dosen',
            'table' => 's1m4k_masterasdos',
            'key' => 'id_asdos',
            'roles' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'facultyScoped' => true,
            'programScoped' => true,
            'relations' => ['program'],
            'nameColumn' => 'nama',
            'identityColumn' => 'kdasisten',
            'columns' => [
                'kdasisten' => 'Kode asisten',
                'nama' => 'Nama',
                'email' => 'Email',
                'nama_prodi' => 'Program studi',
                'status' => 'Status',
            ],
            'create' => [
                ['name' => 'kdasisten', 'label' => 'Kode asisten', 'required' => true, 'max' => 10],
                ['name' => 'nama', 'label' => 'Nama', 'required' => true, 'max' => 100],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 60],
                ['name' => 'notlp', 'label' => 'Nomor telepon', 'max' => 15],
                ['name' => 'jkel', 'label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
                ['name' => 'id_prodi', 'label' => 'Program studi', 'type' => 'select', 'options' => 'programs', 'required' => true],
                ['name' => 'password', 'label' => 'Kata sandi awal', 'type' => 'password', 'required' => true, 'min' => 8],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
            'edit' => [
                ['name' => 'nama', 'label' => 'Nama', 'required' => true, 'max' => 100],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'max' => 60],
                ['name' => 'notlp', 'label' => 'Nomor telepon', 'max' => 15],
                ['name' => 'jkel', 'label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
        ],
        'mahasiswa' => [
            'title' => 'Mahasiswa',
            'table' => 's1m4k_mastermhs',
            'key' => 'id_mahasiswa',
            'roles' => ['admin', 'regulang', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'facultyScoped' => true,
            'programScoped' => true,
            'note' => 'Admin dapat mengubah seluruh data mahasiswa kecuali program studi. Userprodi dan TU hanya dapat mengubah negara, wilayah, status, dan dosen PA pada prodi sendiri, serta menyinkronkan mahasiswa dari Feeder berdasarkan NPM pada prodi sendiri.',
            'relations' => ['program'],
            'nameColumn' => 'nama_mahasiswa',
            'identityColumn' => 'nim',
            'requiredName' => 'nim',
            'edit' => [
                ['name' => 'nim', 'label' => 'NPM / NIM', 'required' => true, 'max' => 24],
                ['name' => 'nama_mahasiswa', 'label' => 'Nama mahasiswa', 'required' => true, 'max' => 100],
                ['name' => 'id_prodi', 'label' => 'Program studi', 'type' => 'select', 'options' => 'programs', 'required' => true, 'disabled' => true],
                ['name' => 'dosen_pa', 'label' => 'Dosen pembimbing akademik', 'type' => 'select', 'options' => 'lecturers'],
                ['name' => 'id_status_mahasiswa', 'label' => 'Status mahasiswa', 'type' => 'select', 'options' => 'reference:GetStatusMahasiswa', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 50],
                ['name' => 'jenis_kelamin', 'label' => 'Jenis kelamin', 'type' => 'select', 'options' => ['L' => 'Laki-laki', 'P' => 'Perempuan']],
                ['name' => 'tempat_lahir', 'label' => 'Tempat lahir', 'max' => 32],
                ['name' => 'tanggal_lahir', 'label' => 'Tanggal lahir', 'type' => 'date'],
                ['name' => 'id_agama', 'label' => 'Agama', 'type' => 'select', 'options' => 'reference:GetAgama'],
                ['name' => 'nik', 'label' => 'NIK', 'max' => 16],
                ['name' => 'nisn', 'label' => 'NISN', 'max' => 10],
                ['name' => 'npwp', 'label' => 'NPWP', 'max' => 15],
                ['name' => 'jalan', 'label' => 'Jalan', 'max' => 80],
                ['name' => 'dusun', 'label' => 'Dusun', 'max' => 60],
                ['name' => 'rt', 'label' => 'RT', 'max' => 2],
                ['name' => 'rw', 'label' => 'RW', 'max' => 2],
                ['name' => 'kelurahan', 'label' => 'Kelurahan', 'max' => 60],
                ['name' => 'id_negara', 'label' => 'Negara', 'type' => 'select', 'options' => 'reference:GetNegara', 'required' => true],
                ['name' => 'id_wilayah', 'label' => 'Wilayah', 'type' => 'select', 'options' => 'student-regions', 'required' => true, 'max' => 10],
                ['name' => 'kode_pos', 'label' => 'Kode pos', 'max' => 5],
                ['name' => 'id_jenis_tinggal', 'label' => 'Jenis tinggal', 'type' => 'select', 'options' => 'reference:GetJenisTinggal'],
                ['name' => 'telepon', 'label' => 'Telepon', 'max' => 20],
                ['name' => 'handphone', 'label' => 'Handphone', 'max' => 20],
                ['name' => 'penerima_kps', 'label' => 'Penerima KPS', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak']],
                ['name' => 'nomor_kps', 'label' => 'Nomor KPS', 'max' => 80],
                ['name' => 'id_alat_transportasi', 'label' => 'Alat transportasi', 'type' => 'select', 'options' => 'reference:GetAlatTransportasi'],
                ['name' => 'nama_ayah', 'label' => 'Nama ayah', 'max' => 100],
                ['name' => 'tanggal_lahir_ayah', 'label' => 'Tanggal lahir ayah', 'type' => 'date'],
                ['name' => 'nik_ayah', 'label' => 'NIK ayah', 'max' => 16],
                ['name' => 'id_pendidikan_ayah', 'label' => 'Pendidikan ayah', 'type' => 'select', 'options' => 'reference:GetJenjangPendidikan'],
                ['name' => 'id_pekerjaan_ayah', 'label' => 'Pekerjaan ayah', 'type' => 'select', 'options' => 'reference:GetPekerjaan'],
                ['name' => 'id_penghasilan_ayah', 'label' => 'Penghasilan ayah', 'type' => 'select', 'options' => 'reference:GetPenghasilan'],
                ['name' => 'id_kebutuhan_khusus_ayah', 'label' => 'Kebutuhan khusus ayah', 'type' => 'checkboxes', 'options' => 'reference:GetKebutuhanKhusus'],
                ['name' => 'nama_ibu', 'label' => 'Nama ibu', 'max' => 100],
                ['name' => 'tanggal_lahir_ibu', 'label' => 'Tanggal lahir ibu', 'type' => 'date'],
                ['name' => 'nik_ibu', 'label' => 'NIK ibu', 'max' => 16],
                ['name' => 'id_pendidikan_ibu', 'label' => 'Pendidikan ibu', 'type' => 'select', 'options' => 'reference:GetJenjangPendidikan'],
                ['name' => 'id_pekerjaan_ibu', 'label' => 'Pekerjaan ibu', 'type' => 'select', 'options' => 'reference:GetPekerjaan'],
                ['name' => 'id_penghasilan_ibu', 'label' => 'Penghasilan ibu', 'type' => 'select', 'options' => 'reference:GetPenghasilan'],
                ['name' => 'id_kebutuhan_khusus_ibu', 'label' => 'Kebutuhan khusus ibu', 'type' => 'checkboxes', 'options' => 'reference:GetKebutuhanKhusus'],
                ['name' => 'nama_wali', 'label' => 'Nama wali', 'max' => 100],
                ['name' => 'tanggal_lahir_wali', 'label' => 'Tanggal lahir wali', 'type' => 'date'],
                ['name' => 'id_pendidikan_wali', 'label' => 'Pendidikan wali', 'type' => 'select', 'options' => 'reference:GetJenjangPendidikan'],
                ['name' => 'id_pekerjaan_wali', 'label' => 'Pekerjaan wali', 'type' => 'select', 'options' => 'reference:GetPekerjaan'],
                ['name' => 'id_penghasilan_wali', 'label' => 'Penghasilan wali', 'type' => 'select', 'options' => 'reference:GetPenghasilan'],
                ['name' => 'id_kebutuhan_khusus_mahasiswa', 'label' => 'Kebutuhan khusus mahasiswa', 'type' => 'checkboxes', 'options' => 'reference:GetKebutuhanKhusus'],
                ['name' => 'id_jenis_daftar', 'label' => 'Jenis pendaftaran', 'type' => 'select', 'options' => 'reference:GetJenisPendaftaran', 'required' => true],
                ['name' => 'id_jalur_daftar', 'label' => 'Jalur pendaftaran', 'type' => 'select', 'options' => 'reference:GetJalurMasuk', 'required' => true],
                ['name' => 'id_periode_masuk', 'label' => 'Periode masuk', 'required' => true, 'max' => 5],
                ['name' => 'tanggal_daftar', 'label' => 'Tanggal daftar', 'type' => 'date', 'required' => true],
                ['name' => 'sks_diakui', 'label' => 'SKS diakui', 'type' => 'number', 'max' => 3],
                ['name' => 'id_perguruan_tinggi_asal', 'label' => 'Perguruan tinggi asal', 'type' => 'select', 'options' => 'origin-universities'],
                ['name' => 'id_prodi_asal', 'label' => 'Program studi asal', 'type' => 'select', 'options' => 'origin-programs'],
                ['name' => 'id_pembiayaan', 'label' => 'Pembiayaan awal', 'type' => 'select', 'options' => 'reference:GetPembiayaan', 'required' => true],
                ['name' => 'biaya_masuk_kuliah', 'label' => 'Biaya masuk kuliah', 'type' => 'currency', 'required' => true, 'max' => 9],
            ],
            'columns' => [
                'nim' => 'NPM / NIM',
                'nama_mahasiswa' => 'Nama mahasiswa',
                'nama_status_mahasiswa' => 'Status',
                'nama_prodi' => 'Program studi',
                'email' => 'Email',
            ],
            'statusScoped' => true,
            'resettable' => true,
        ],
        'mata-kuliah' => [
            'title' => 'Mata Kuliah',
            'table' => 's1m4k_mastermk',
            'key' => 'id_matkul',
            'roles' => ['admin', 'regulang', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'facultyScoped' => true,
            'programScoped' => true,
            'note' => 'Pembuatan, perubahan, dan impor mata kuliah memerlukan koneksi ke Feeder.',
            'relations' => ['program'],
            'nameColumn' => 'nama_mata_kuliah',
            'identityColumn' => 'kode_mata_kuliah',
            'columns' => [
                'kode_mata_kuliah' => 'Kode mata kuliah',
                'nama_mata_kuliah' => 'Nama mata kuliah',
                'semester' => 'Semester',
                'sks_mata_kuliah' => 'SKS',
                'nama_prodi' => 'Program studi',
                'status' => 'Status',
            ],
            'edit' => [
                ['name' => 'kode_mata_kuliah', 'label' => 'Kode mata kuliah', 'required' => true, 'max' => 20],
                ['name' => 'nama_mata_kuliah', 'label' => 'Nama mata kuliah', 'required' => true, 'max' => 100],
                ['name' => 'semester', 'label' => 'Semester', 'type' => 'select', 'options' => [
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7',
                    '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12', '13' => '13', '14' => '14',
                ], 'required' => true],
                ['name' => 'id_kelompok_mata_kuliah', 'label' => 'Kelompok mata kuliah', 'type' => 'select', 'options' => ['A' => 'MPK', 'B' => 'MKK', 'C' => 'MKB', 'D' => 'MPB', 'E' => 'MBB', 'F' => 'MKDK / MKU', 'G' => 'MKDK', 'H' => 'MKK'], 'required' => true],
                ['name' => 'id_jenis_mata_kuliah', 'label' => 'Jenis mata kuliah', 'type' => 'select', 'options' => ['A' => 'Wajib', 'B' => 'Pilihan', 'C' => 'Wajib peminatan', 'D' => 'Pilihan peminatan', 'S' => 'Tugas akhir'], 'required' => true],
                ['name' => 'sks_mata_kuliah', 'label' => 'Total SKS', 'type' => 'decimal', 'required' => true],
                ['name' => 'sks_tatap_muka', 'label' => 'SKS tatap muka', 'type' => 'decimal', 'required' => true],
                ['name' => 'sks_praktek', 'label' => 'SKS praktik', 'type' => 'decimal', 'required' => true],
                ['name' => 'sks_praktek_lapangan', 'label' => 'SKS praktik lapangan', 'type' => 'decimal', 'required' => true],
                ['name' => 'sks_simulasi', 'label' => 'SKS simulasi', 'type' => 'decimal', 'required' => true],
                ['name' => 'ada_sap', 'label' => 'Ada SAP', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak'], 'required' => true],
                ['name' => 'ada_silabus', 'label' => 'Ada silabus', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak'], 'required' => true],
                ['name' => 'ada_bahan_ajar', 'label' => 'Ada bahan ajar', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak'], 'required' => true],
                ['name' => 'ada_acara_praktek', 'label' => 'Ada acara praktik', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak'], 'required' => true],
                ['name' => 'ada_diktat', 'label' => 'Ada diktat', 'type' => 'select', 'options' => ['1' => 'Ya', '0' => 'Tidak'], 'required' => true],
                ['name' => 'metode_kuliah', 'label' => 'Metode pembelajaran', 'max' => 50],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['A' => 'Aktif', 'B' => 'Alih Bentuk', 'H' => 'Hapus', 'K' => 'Alih Kelola', 'M' => 'Merger', 'N' => 'Nonaktif'], 'required' => true],
                ['name' => 'tanggal_mulai_efektif', 'label' => 'Tanggal mulai efektif', 'type' => 'date'],
                ['name' => 'tanggal_selesai_efektif', 'label' => 'Tanggal akhir efektif', 'type' => 'date'],
            ],
        ],
        'ruang' => [
            'title' => 'Ruang',
            'table' => 's1m4k_masterruang',
            'key' => 'id_ruang',
            'roles' => ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'],
            'facultyScoped' => true,
            'programScoped' => true,
            'relations' => ['program'],
            'nameColumn' => 'nama_ruang',
            'columns' => [
                'nama_ruang' => 'Nama ruang',
                'kapasitas' => 'Kapasitas',
                'nama_prodi' => 'Program studi',
                'status' => 'Status',
            ],
            'create' => [
                ['name' => 'nama_ruang', 'label' => 'Nama ruang', 'required' => true, 'max' => 100],
                ['name' => 'kapasitas', 'label' => 'Kapasitas', 'type' => 'number', 'max' => 4],
                ['name' => 'id_prodi', 'label' => 'Program studi', 'type' => 'select', 'options' => 'programs', 'required' => true],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
            'edit' => [
                ['name' => 'nama_ruang', 'label' => 'Nama ruang', 'required' => true, 'max' => 100],
                ['name' => 'kapasitas', 'label' => 'Kapasitas', 'type' => 'number', 'max' => 4],
                ['name' => 'id_prodi', 'label' => 'Program studi', 'type' => 'select', 'options' => 'programs', 'required' => true],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Aktif' => 'Aktif', 'Tidak Aktif' => 'Tidak Aktif'], 'required' => true],
            ],
        ],
        'bobot-nilai' => [
            'title' => 'Bobot Nilai',
            'table' => 's1m4k_masterbobotnilai',
            'key' => 'id_bobot_nilai',
            'roles' => ['admin', 'userprodi', 'usertu', 'pimuniv', 'pimfak'],
            'note' => 'Data bobot nilai ditampilkan untuk referensi. Pengelolaan data ini belum tersedia.',
            'columns' => [
                'nilai_huruf' => 'Nilai huruf',
                'nilai_indeks' => 'Indeks',
                'bobot_minimum' => 'Bobot minimum',
                'bobot_maksimum' => 'Bobot maksimum',
                'status' => 'Status',
                'tanggal_mulai_efektif' => 'Mulai efektif',
                'tanggal_akhir_efektif' => 'Akhir efektif',
            ],
        ],
    ];

    public function index(Request $request): View
    {
        $module = (string) $request->route('module');
        $definition = $this->definition($module);
        $user = $request->user();
        abort_unless(in_array($user->role, $definition['roles'], true), 403);

        $selectedProgram = $request->query('prodi');
        $showProgramFilter = ! empty($definition['programScoped'])
            && ! in_array($user->role, ['userprodi', 'usertu'], true);
        $selectedStatus = $request->query('status');
        $showStatusFilter = ! empty($definition['statusScoped']);
        $records = $module === 'perguruan-tinggi'
            ? $this->dataQuery($definition)
                ->whereNotNull('master.nama_perguruan_tinggi')
                ->whereRaw("TRIM(master.nama_perguruan_tinggi) <> ''")
                ->limit(1)
                ->get()
            : collect();

        $programManager = in_array($user->role, ['userprodi', 'usertu'], true);
        $programOptions = $showProgramFilter ? $this->fieldsFor([[
            'name' => 'prodi',
            'options' => 'programs',
        ]], $user)[0]['options'] : [];
        $syncProgramId = $programManager
            ? trim((string) data_get($user, 'id_prodi'))
            : (is_string($selectedProgram) ? trim($selectedProgram) : '');
        $syncProgramOptions = $module === 'mahasiswa' && $user->role === 'admin'
            ? $this->fieldsFor([['name' => 'id_prodi', 'options' => 'programs']], $user)[0]['options']
            : [];
        $canSyncStudent = $module === 'mahasiswa'
            && ($programManager || in_array($user->role, ['admin', 'regulang'], true))
            && ($programManager && $syncProgramId !== ''
                || ($user->role === 'admin' && $syncProgramOptions !== [])
                || ($user->role === 'regulang' && $syncProgramId !== '' && array_key_exists($syncProgramId, $programOptions)));
        $syncFields = $module === 'mata-kuliah'
                ? [
                    ['name' => 'kode_mata_kuliah', 'label' => 'Kode mata kuliah', 'required' => true, 'max' => 20],
                    ...($programManager ? [] : [['name' => 'id_prodi', 'label' => 'Program studi', 'type' => 'select', 'options' => $this->fieldsFor([['name' => 'id_prodi', 'options' => 'programs']], $user)[0]['options'], 'required' => true]]),
                ]
                : ($module === 'mahasiswa'
                    ? [
                        ...($user->role === 'admin' ? [[
                            'name' => 'id_prodi',
                            'label' => 'Program studi',
                            'type' => 'select',
                            'options' => $syncProgramOptions,
                            'required' => true,
                        ]] : []),
                        ['name' => 'npm', 'label' => 'NPM', 'required' => true, 'max' => 24],
                    ]
                    : []);
        $createFields = $this->fieldsFor($definition['create'] ?? [], $user);
        $editableFields = $this->editableFields($module, $definition, $user);
        $editFields = $this->fieldsFor($editableFields, $user);
        $canResetPassword = in_array($module, ['dosen', 'asisten-dosen'], true)
            || ! empty($definition['resettable']);
        $resetRoles = $module === 'dosen'
            ? ['admin', 'userprodi']
            : ['admin', 'regulang', 'userprodi', 'usertu'];
        $canResetPassword = $canResetPassword && in_array($user->role, $resetRoles, true);
        $canViewCourseEvaluations = $module === 'mata-kuliah'
            && in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'], true);
        $canDeleteCourseEvaluations = $module === 'mata-kuliah'
            && in_array($user->role, ['admin', 'userprodi', 'usertu'], true);

        return view('master-data.index', [
            'module' => $module,
            'title' => $definition['title'],
            'note' => $definition['note'] ?? null,
            'columns' => $definition['columns'],
            'records' => $records,
            'createFields' => $createFields,
            'editFields' => $editFields,
            'canCreate' => ($user->role === 'admin'
                || ($programManager && in_array($module, ['asisten-dosen', 'ruang'], true)))
                && $createFields !== [],
            'canEdit' => $this->canEditModule($module, $user)
                && $editFields !== [],
            'canManageCourseEvaluations' => $canViewCourseEvaluations,
            'canDeleteCourseEvaluations' => $canDeleteCourseEvaluations,
            'canResetPassword' => $canResetPassword,
            'canSync' => ($user->role === 'admin' && in_array($module, ['perguruan-tinggi', 'program-studi', 'mata-kuliah'], true))
                || ($programManager && $module === 'mata-kuliah')
                || $canSyncStudent,
            'canSyncByIdentity' => (in_array($module, ['mahasiswa', 'mata-kuliah'], true)
                    && ($user->role === 'admin' || $programManager || ($module === 'mahasiswa' && $user->role === 'regulang'))),
            'syncFields' => $syncFields,
            'syncProgramId' => $canSyncStudent && ($programManager || $user->role === 'regulang') ? $syncProgramId : null,
            'showProgramFilter' => $showProgramFilter,
            'programOptions' => $programOptions,
            'selectedProgram' => $selectedProgram,
            'showStatusFilter' => $showStatusFilter,
            'statusOptions' => $showStatusFilter
                ? DB::connection('simak_main')->table($definition['table'])
                    ->whereNotNull('nama_status_mahasiswa')
                    ->whereRaw("TRIM(nama_status_mahasiswa) <> ''")
                    ->whereNotNull('id_status_mahasiswa')
                    ->distinct()->orderBy('nama_status_mahasiswa')
                    ->pluck('nama_status_mahasiswa', 'id_status_mahasiswa')->all()
                : [],
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function exportStudents(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'userprodi'], true), 403);
        $request->validate([
            'prodi' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);
        $definition = $this->definition('mahasiswa');
        $fieldLabels = collect($definition['edit'])->pluck('label', 'name')->all();
        $columns = [];
        foreach (Schema::connection('simak_main')->getColumnListing($definition['table']) as $column) {
            if (in_array($column, [
                'password', 'passwordb', 'password_baru', 'id_mahasiswa', 'id_registrasi_mahasiswa', 'id_status_mahasiswa',
                'flag_feeder', 'kategori_ukt', 'nominal_ukt', 'update_ukt',
            ], true)) {
                continue;
            }
            if (str_starts_with($column, 'id_') && ! $this->studentExportRelation($column, $fieldLabels)) {
                continue;
            }

            $columns[$column] = match ($column) {
                'id_perguruan_tinggi' => 'Perguruan tinggi',
                'id_fakultas' => 'Fakultas',
                'id_prodi' => 'Program studi',
                'id_negara' => 'Negara',
                'id_wilayah' => 'Wilayah',
                'dosen_pa' => 'Dosen pembimbing akademik',
                'id_perguruan_tinggi_asal' => 'Perguruan tinggi asal',
                'id_prodi_asal' => 'Program studi asal',
                'nama_status_mahasiswa' => 'Status mahasiswa',
                default => $fieldLabels[$column] ?? $fieldLabels[substr($column, 3)] ?? Str::of($column)->replace('_', ' ')->title()->toString(),
            };
        }
        $relationLabels = $this->studentExportRelationLabels(array_keys($columns));
        $rows = $this->listingQuery($request, $definition, $user)
            ->whereIn('master.id_status_mahasiswa', ['A', 'N', 'C', 'M'])
            ->orderBy('master.nim')
            ->get(array_map(static fn (string $column): string => 'master.'.$column, array_keys($columns)));

        return response()->streamDownload(function () use ($rows, $columns, $relationLabels): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            foreach (array_values($columns) as $index => $header) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValueExplicit($column.'1', $header, DataType::TYPE_STRING);
            }
            foreach ($rows as $rowIndex => $row) {
                foreach (array_keys($columns) as $index => $field) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $value = $row->{$field} ?? '';
                    if (isset($relationLabels[$field])) {
                        $value = $this->studentExportRelationValue($field, (string) $value, $relationLabels[$field]);
                    }
                    $sheet->setCellValueExplicit(
                        $column.($rowIndex + 2),
                        (string) $value,
                        DataType::TYPE_STRING
                    );
                }
            }
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'data-mahasiswa.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function studentExportRelation(string $field, array $fieldLabels): bool
    {
        return isset($fieldLabels[$field])
            || in_array($field, [
                'id_perguruan_tinggi', 'id_fakultas', 'id_prodi', 'id_negara', 'id_wilayah', 'dosen_pa',
            ], true)
            || in_array($field, ['id_perguruan_tinggi_asal', 'id_prodi_asal'], true);
    }

    private function studentExportRelationLabels(array $columns): array
    {
        $connection = DB::connection('simak_main');
        $labels = [];
        $simpleRelations = [
            'id_perguruan_tinggi' => fn () => $connection->table('s1m4k_masterpt')->pluck('nama_perguruan_tinggi', 'id_perguruan_tinggi')->all(),
            'id_fakultas' => fn () => $connection->table('s1m4k_masterfakultas')->pluck('nama_fakultas', 'id_fakultas')->all(),
            'id_prodi' => fn () => $connection->table('s1m4k_masterprodi')->get(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan'])
                ->mapWithKeys(fn (object $program): array => [
                    $program->id_prodi => trim((string) $program->nama_program_studi.' '.(string) ($program->nama_jenjang_pendidikan ?? '')),
                ])->all(),
            'dosen_pa' => fn () => $connection->table('s1m4k_masterdosen')
                ->get(['id_dosen', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
                ->mapWithKeys(fn (object $lecturer): array => [
                    $lecturer->id_dosen => LecturerName::format(
                        (string) ($lecturer->nama_dosen ?? ''),
                        (string) ($lecturer->gelar_depan ?? ''),
                        (string) ($lecturer->gelar_belakang ?? ''),
                    ),
                ])->all(),
            'id_perguruan_tinggi_asal' => fn () => $connection->table('s1m4k_masterallpt')->pluck('nama_perguruan_tinggi', 'id_perguruan_tinggi')->all(),
            'id_prodi_asal' => fn () => $connection->table('s1m4k_masterallprodi')->pluck('nama_program_studi', 'id_prodi')->all(),
        ];
        foreach ($simpleRelations as $field => $resolve) {
            if (in_array($field, $columns, true)) {
                $labels[$field] = $resolve();
            }
        }

        if (in_array('id_wilayah', $columns, true)) {
            $labels['id_wilayah'] = $connection->table('s1m4k_kodereferensi')
                ->where('keterangan', 'GetWilayah')
                ->get(['kode_referensi', 'ket_referensi2', 'nama_provinsi'])
                ->mapWithKeys(fn (object $region): array => [
                    $region->kode_referensi => trim(implode(' - ', array_filter([
                        trim((string) ($region->ket_referensi2 ?? '')),
                        trim((string) ($region->nama_provinsi ?? '')),
                    ]))),
                ])->all();
        }

        $referenceFields = [];
        foreach (self::MODULES['mahasiswa']['edit'] as $field) {
            $options = $field['options'] ?? '';
            if (isset($field['name'], $options) && in_array($field['name'], $columns, true)
                && is_string($options) && str_starts_with($options, 'reference:')) {
                $referenceFields[$field['name']] = substr($options, strlen('reference:'));
            }
        }
        foreach ($referenceFields as $field => $reference) {
            if ($field === 'id_status_mahasiswa') {
                continue;
            }
            $labels[$field] = $connection->table('s1m4k_kodereferensi')
                ->where('keterangan', $reference)
                ->pluck('ket_referensi', 'kode_referensi')->all();
        }

        return $labels;
    }

    private function studentExportRelationValue(string $field, string $value, array $labels): string
    {
        if (in_array($field, ['id_kebutuhan_khusus_ayah', 'id_kebutuhan_khusus_ibu', 'id_kebutuhan_khusus_mahasiswa'], true)) {
            return collect(explode(',', $value))
                ->map(fn (string $id): string => trim((string) ($labels[trim($id)] ?? '')))
                ->filter()
                ->implode(', ');
        }

        return (string) ($labels[$value] ?? '');
    }

    public function tableData(Request $request): JsonResponse
    {
        $module = (string) $request->route('module');
        $definition = $this->definition($module);
        $user = $request->user();
        abort_unless(in_array($user->role, $definition['roles'], true), 403);
        abort_if($module === 'perguruan-tinggi', 404);
        $request->validate([
            'draw' => ['nullable', 'integer', 'min:0'],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search.value' => ['nullable', 'string', 'max:100'],
            'order.0.column' => ['nullable', 'integer', 'min:0'],
            'order.0.dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = $this->listingQuery($request, $definition, $user);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $this->applyTableSearch($query, $definition, $search);
        }
        $recordsFiltered = (clone $query)->count();

        $columns = array_keys($definition['columns']);
        $orderColumn = filter_var($request->input('order.0.column'), FILTER_VALIDATE_INT);
        $orderColumn = $orderColumn === false ? null : $orderColumn;
        $orderField = $orderColumn === null ? null : ($columns[$orderColumn] ?? null);
        $orderDirection = strtolower((string) $request->input('order.0.dir')) === 'desc' ? 'desc' : 'asc';
        if (is_string($orderField)) {
            $query->orderBy($this->tableColumnExpression($orderField), $orderDirection);
        } elseif ($module === 'bobot-nilai') {
            $query->orderBy('master.nilai_huruf');
        } else {
            $query->orderBy('master.'.$definition['key']);
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 25)));
        $records = $query->offset($start)->limit($length)->get()
            ->map(function ($record) use ($definition): array {
                return $this->recordPayload($record, $definition, false);
            });

        return response()->json([
            'draw' => max(0, (int) $request->input('draw', 0)),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $records,
        ]);
    }

    public function editData(Request $request, string $record): JsonResponse
    {
        $module = (string) $request->route('module');
        $definition = $this->definition($module);
        $user = $request->user();
        abort_unless($this->canEditModule($module, $user) && ! empty($definition['edit']), 403);

        $query = $this->dataQuery($definition);
        $this->applyScope($query, $definition, $user);
        $recordData = $query->where('master.'.$definition['key'], $record)->first();
        abort_if($recordData === null, 404);
        $editFields = $this->fieldsFor($this->editableFields($module, $definition, $user), $user);
        $payload = $this->recordPayload($recordData, $definition, true, $editFields);
        foreach ($editFields as $field) {
            if (($field['type'] ?? null) !== 'checkboxes') {
                continue;
            }

            $stored = trim((string) ($payload['values'][$field['name']] ?? ''));
            $payload['values'][$field['name']] = $stored === '' || $stored === '0'
                ? []
                : array_values(array_filter(explode(',', $stored), static fn (string $value): bool => $value !== '0'));
        }

        return response()->json([
            'record' => $payload,
        ]);
    }

    public function studentRegions(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'regulang', 'userprodi', 'usertu'], true), 403);
        $validated = $request->validate([
            'id_negara' => ['required', 'string', 'max:10'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $query = DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->select(['kode_referensi', 'ket_referensi2', 'nama_provinsi'])
            ->where('keterangan', 'GetWilayah')
            ->where('ket_referensi', $validated['id_negara']);
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('ket_referensi2', 'like', '%'.$search.'%')
                    ->orWhere('nama_provinsi', 'like', '%'.$search.'%');
            });
        }
        $data = $query->orderBy('ket_referensi2')->limit(50)->get()
            ->map(static fn (object $region): array => [
                'value' => (string) $region->kode_referensi,
                'text' => trim((string) $region->ket_referensi2).' - '.trim((string) $region->nama_provinsi),
            ]);

        return response()->json(['data' => $data]);
    }

    public function lecturers(Request $request): JsonResponse
    {
        $user = $request->user();
        $programManager = in_array($user->role, ['userprodi', 'usertu'], true);
        $studentManager = $user->role === 'regulang' && $request->query('module') === 'mahasiswa';
        abort_unless($user->role === 'admin' || $programManager || $studentManager, 403);
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:3', 'max:100'],
        ]);
        $search = trim($validated['search']);

        $query = DB::connection('simak_main')->table('s1m4k_masterdosen')
            ->select(['id_dosen', 'nidn', 'nama_dosen', 'gelar_depan', 'gelar_belakang'])
            ->whereNotNull('nama_dosen')
            ->whereRaw("TRIM(nama_dosen) <> ''")
            ->where(function (Builder $query) use ($search): void {
                $query->where('nama_dosen', 'like', '%'.$search.'%')
                    ->orWhere('nidn', 'like', '%'.$search.'%');
            });
        if ($programManager) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        }

        $records = $query->orderBy('nama_dosen')
            ->limit(30)
            ->get()
            ->map(function ($record): array {
                $name = LecturerName::format(
                    (string) $record->nama_dosen,
                    (string) ($record->gelar_depan ?? ''),
                    (string) ($record->gelar_belakang ?? ''),
                );

                return [
                    'value' => $record->id_dosen,
                    'text' => $name.' ('.$record->nidn.')',
                ];
            });

        return response()->json(['data' => $records]);
    }

    public function studentOrigins(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'regulang'], true), 403);
        $validated = $request->validate([
            'type' => ['required', 'in:university,program'],
            'search' => ['required', 'string', 'min:3', 'max:100'],
            'id_perguruan_tinggi' => ['nullable', 'string', 'max:50'],
        ]);
        $search = trim($validated['search']);

        if ($validated['type'] === 'university') {
            $records = DB::connection('simak_main')->table('s1m4k_masterallpt')
                ->select(['id_perguruan_tinggi as value', 'nama_perguruan_tinggi as text'])
                ->whereNotNull('nama_perguruan_tinggi')
                ->whereRaw("TRIM(nama_perguruan_tinggi) <> ''")
                ->where('nama_perguruan_tinggi', 'like', '%'.$search.'%')
                ->orderBy('nama_perguruan_tinggi')
                ->limit(30)
                ->get();
        } else {
            $programId = trim((string) ($validated['id_perguruan_tinggi'] ?? ''));
            abort_if($programId === '', 422, 'Pilih perguruan tinggi asal terlebih dahulu.');
            $records = DB::connection('simak_main')->table('s1m4k_masterallprodi')
                ->select(['id_prodi as value', 'nama_program_studi as text'])
                ->where('id_perguruan_tinggi', $programId)
                ->whereNotNull('nama_program_studi')
                ->whereRaw("TRIM(nama_program_studi) <> ''")
                ->where('nama_program_studi', 'like', '%'.$search.'%')
                ->orderBy('nama_program_studi')
                ->limit(30)
                ->get();
        }

        return response()->json(['data' => $records]);
    }

    public function resetPassword(Request $request, string $record, ActivityLogger $activityLogger): JsonResponse
    {
        $module = (string) $request->route('module');
        abort_unless(in_array($module, ['dosen', 'asisten-dosen', 'mahasiswa'], true), 404);
        $user = $request->user();
        $resetRoles = $module === 'dosen'
            ? ['admin', 'userprodi']
            : ['admin', 'regulang', 'userprodi', 'usertu'];
        abort_unless(in_array($user->role, $resetRoles, true), 403);

        $definition = $this->definition($module);
        $query = DB::connection('simak_main')->table($definition['table'])
            ->where($definition['key'], $record);
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        }

        $credentialColumn = match ($module) {
            'dosen' => 'nidn',
            'asisten-dosen' => 'kdasisten',
            'mahasiswa' => 'nim',
        };
        $targetColumns = array_values(array_unique(array_filter([
            $definition['key'],
            $credentialColumn,
            $definition['nameColumn'] ?? null,
            $definition['identityColumn'] ?? null,
        ])));
        $target = $query->first($targetColumns);
        abort_if($target === null, 404);
        $credential = trim((string) $target->{$credentialColumn});
        if ($credential === '') {
            throw ValidationException::withMessages([
                $credentialColumn => 'Kredensial default untuk reset password belum tersedia.',
            ]);
        }

        DB::connection('simak_main')->transaction(function () use ($query, $user, $definition, $record, $credential, $activityLogger, $target): void {
            $query->update(['password' => Hash::make($credential)]);
            $activityLogger->record($user, 'Data Master '.$definition['title'], 'Reset kata sandi', [
                'password_diubah' => true,
            ], $record, array_intersect_key(
                $this->recordIdentity($definition, (array) $target),
                array_flip(['jenis', 'nama'])
            ));
        });

        return response()->json(['message' => 'Password berhasil dikembalikan ke '.$credentialColumn.' yang terdaftar.']);
    }

    public function courseEvaluations(Request $request, string $record, LegacyFeederService $feeder): JsonResponse
    {
        $course = $this->authorizedCourse($request, $record, true);
        try {
            $evaluations = $feeder->courseEvaluations($record);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json([
            'data' => array_values(array_filter($evaluations, static fn ($evaluation): bool => is_array($evaluation))),
            'course' => trim((string) $course->nama_mata_kuliah),
        ]);
    }

    public function deleteCourseEvaluation(
        Request $request,
        string $record,
        string $evaluation,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $course = $this->authorizedCourse($request, $record);
        try {
            $evaluations = $feeder->courseEvaluations($record);
            $belongsToCourse = collect($evaluations)->contains(
                fn ($item): bool => is_array($item)
                    && (string) ($item['id_rencana_evaluasi'] ?? '') === $evaluation
                    && (string) ($item['id_matkul'] ?? '') === $record
            );
            abort_unless($belongsToCourse, 404);
            $feeder->deleteCourseEvaluation($evaluation);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $activityLogger->record($request->user(), 'Mata Kuliah', 'Hapus komponen evaluasi di Feeder', [
            'id_rencana_evaluasi' => $evaluation,
        ], $record, [
            'jenis' => 'Mata Kuliah',
            'nama' => (string) $course->nama_mata_kuliah,
        ]);

        return response()->json(['message' => 'Komponen evaluasi berhasil dihapus dari Feeder.']);
    }

    public function store(Request $request, ActivityLogger $activityLogger): JsonResponse
    {
        $module = (string) $request->route('module');
        $definition = $this->definition($module);
        $user = $request->user();
        $programManager = in_array($user->role, ['userprodi', 'usertu'], true);
        $canCreate = $user->role === 'admin'
            || ($programManager && in_array($module, ['asisten-dosen', 'ruang'], true));
        abort_unless($canCreate && ! empty($definition['create']), 403);

        $validated = $this->validateFields($request, $this->fieldsFor($definition['create'], $user));
        if ($programManager) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $validated['id_prodi'] = trim($programId);
        }

        if ($module === 'fakultas') {
            $existing = DB::connection('simak_main')->table($definition['table'])
                ->where('kode_fakultas', $validated['kode_fakultas'])->exists();
            if ($existing) {
                throw ValidationException::withMessages(['kode_fakultas' => 'Kode fakultas sudah digunakan.']);
            }

            $universityId = DB::connection('simak_main')->table('s1m4k_masterpt')
                ->value('id_perguruan_tinggi');
            if (! is_string($universityId) || trim($universityId) === '') {
                throw ValidationException::withMessages(['kode_fakultas' => 'Data perguruan tinggi harus tersedia sebelum fakultas ditambahkan.']);
            }

            $data = [
                'id_fakultas' => (string) Str::uuid(),
                'id_perguruan_tinggi' => $universityId,
                ...$validated,
            ];
        } elseif ($module === 'asisten-dosen') {
            $duplicate = DB::connection('simak_main')->table($definition['table'])
                ->where('kdasisten', $validated['kdasisten'])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['kdasisten' => 'Kode asisten sudah digunakan.']);
            }

            $program = $this->program($validated['id_prodi']);
            $data = [
                'id_asdos' => (string) Str::uuid(),
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                ...$validated,
                'password' => Hash::make($validated['password']),
            ];
            unset($data['passwordb']);
        } elseif ($module === 'ruang') {
            $program = $this->program($validated['id_prodi']);
            $data = [
                'id_ruang' => (string) Str::uuid(),
                'id_perguruan_tinggi' => $program->id_perguruan_tinggi,
                'id_fakultas' => $program->id_fakultas,
                ...$validated,
            ];
        } else {
            abort(403);
        }

        DB::connection('simak_main')->transaction(function () use ($definition, $data, $activityLogger, $user): void {
            DB::connection('simak_main')->table($definition['table'])->insert($data);
            $activityLogger->record($user, 'Data Master '.$definition['title'], 'Tambah data', [
                'data_baru' => $data,
            ], (string) $data[$definition['key']], $this->recordIdentity($definition, $data));
        });
        $record = $this->dataQuery($definition)
            ->where('master.'.$definition['key'], $data[$definition['key']])->first();

        return response()->json([
            'message' => $definition['title'].' berhasil ditambahkan.',
            'record' => $this->recordPayload($record, $definition),
        ], 201);
    }

    public function update(
        Request $request,
        string $record,
        LegacyFeederService $feeder,
        ActivityLogger $activityLogger
    ): JsonResponse {
        $module = (string) $request->route('module');
        $definition = $this->definition($module);
        $user = $request->user();
        $programManager = in_array($user->role, ['userprodi', 'usertu'], true);
        abort_unless($this->canEditModule($module, $user) && ! empty($definition['edit']), 403);

        if ($module === 'mahasiswa' && in_array($user->role, ['admin', 'regulang'], true) && $request->filled('id_prodi')) {
            $currentProgramId = DB::connection('simak_main')->table($definition['table'])
                ->where($definition['key'], $record)
                ->value('id_prodi');
            abort_if($currentProgramId === null, 404);
            if ((string) $request->input('id_prodi') !== (string) $currentProgramId) {
                throw ValidationException::withMessages([
                    'id_prodi' => 'Program studi mahasiswa tidak dapat diubah dari form ini.',
                ]);
            }
        }

        $editFields = $this->fieldsFor($this->editableFields($module, $definition, $user), $user);
        $validated = $this->validateFields($request, $editFields);
        if ($module === 'mahasiswa') {
            $this->validateStudentRegion($validated);
        }
        $query = DB::connection('simak_main')->table($definition['table'])
            ->where($definition['key'], $record);
        if ($programManager && in_array($module, ['asisten-dosen', 'dosen', 'mata-kuliah', 'ruang'], true)) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        } elseif ($module === 'mahasiswa' && $programManager) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        }
        $currentCourse = $module === 'mata-kuliah'
            ? $query->first()
            : null;
        $currentRecord = $module !== 'mata-kuliah' ? $query->first() : null;
        abort_unless($module === 'mata-kuliah' ? $currentCourse !== null : $currentRecord !== null, 404);

        if ($module === 'mahasiswa' && $programManager && ! empty($validated['dosen_pa'])) {
            $programId = trim((string) data_get($user, 'id_prodi'));
            $advisorExists = DB::connection('simak_main')->table('s1m4k_masterdosen')
                ->where('id_dosen', $validated['dosen_pa'])
                ->where('id_prodi', $programId)
                ->exists();
            if (! $advisorExists) {
                throw ValidationException::withMessages([
                    'dosen_pa' => 'Dosen pembimbing akademik harus berasal dari program studi akun Anda.',
                ]);
            }
        }

        if (in_array($module, ['ruang', 'asisten-dosen'], true)) {
            if ($programManager) {
                $validated['id_prodi'] = (string) data_get($user, 'id_prodi');
            } elseif ($module === 'asisten-dosen') {
                $validated['id_prodi'] = (string) $currentRecord->id_prodi;
            }
            $program = $this->program($validated['id_prodi']);
            $validated['id_fakultas'] = $program->id_fakultas;
            $validated['id_perguruan_tinggi'] = $program->id_perguruan_tinggi;
        }

        if ($module === 'mata-kuliah') {
            $totalSks = (float) $validated['sks_mata_kuliah'];
            $componentSks = (float) $validated['sks_tatap_muka']
                + (float) $validated['sks_praktek']
                + (float) $validated['sks_praktek_lapangan']
                + (float) $validated['sks_simulasi'];
            if ($totalSks < $componentSks) {
                throw ValidationException::withMessages([
                    'sks_mata_kuliah' => 'Total SKS tidak boleh kurang dari jumlah seluruh komponen SKS.',
                ]);
            }

            try {
                $feeder->updateCourse($record, [
                    'id_prodi' => $currentCourse->id_prodi,
                    'kode_mata_kuliah' => $validated['kode_mata_kuliah'],
                    'nama_mata_kuliah' => $validated['nama_mata_kuliah'],
                    'id_jenis_mata_kuliah' => $validated['id_jenis_mata_kuliah'],
                    'id_kelompok_mata_kuliah' => $validated['id_kelompok_mata_kuliah'],
                    'sks_mata_kuliah' => number_format((float) $validated['sks_mata_kuliah'], 2, '.', ''),
                    'sks_tatap_muka' => number_format((float) $validated['sks_tatap_muka'], 2, '.', ''),
                    'sks_praktek' => number_format((float) $validated['sks_praktek'], 2, '.', ''),
                    'sks_praktek_lapangan' => number_format((float) $validated['sks_praktek_lapangan'], 2, '.', ''),
                    'sks_simulasi' => number_format((float) $validated['sks_simulasi'], 2, '.', ''),
                    'ada_sap' => $validated['ada_sap'],
                    'ada_silabus' => $validated['ada_silabus'],
                    'ada_bahan_ajar' => $validated['ada_bahan_ajar'],
                    'ada_acara_praktek' => $validated['ada_acara_praktek'],
                    'ada_diktat' => $validated['ada_diktat'],
                    'metode_kuliah' => $validated['metode_kuliah'] ?? '',
                    'tanggal_mulai_efektif' => $validated['tanggal_mulai_efektif'] ?? '',
                ]);
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json(['message' => $exception->getMessage()], 502);
            }
        }

        if ($module === 'mahasiswa') {
            if ($programManager) {
                $status = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                    ->where('keterangan', 'GetStatusMahasiswa')
                    ->where('kode_referensi', $validated['id_status_mahasiswa'])
                    ->first(['ket_referensi']);
                if ($status === null) {
                    throw ValidationException::withMessages(['id_status_mahasiswa' => 'Status mahasiswa tidak valid.']);
                }

                $validated['nama_status_mahasiswa'] = $status->ket_referensi;
                $query->update($validated);
            } else {
                $duplicateNim = DB::connection('simak_main')->table($definition['table'])
                    ->where('nim', $validated['nim'])
                    ->where($definition['key'], '!=', $record)
                    ->exists();
                if ($duplicateNim) {
                    throw ValidationException::withMessages(['nim' => 'NPM/NIM sudah digunakan mahasiswa lain.']);
                }

                $program = $this->program($validated['id_prodi']);
                $status = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                    ->where('keterangan', 'GetStatusMahasiswa')
                    ->where('kode_referensi', $validated['id_status_mahasiswa'])
                    ->first(['ket_referensi']);
                if ($status === null) {
                    throw ValidationException::withMessages(['id_status_mahasiswa' => 'Status mahasiswa tidak valid.']);
                }

                $academicFields = [
                    'nim', 'id_jenis_daftar', 'id_jalur_daftar', 'id_periode_masuk',
                    'tanggal_daftar', 'sks_diakui', 'id_prodi', 'id_prodi_asal',
                    'id_pembiayaan',
                ];
                $academicRecord = array_intersect_key($validated, array_flip($academicFields));
                $academicRecord['id_perguruan_tinggi'] = $program->id_perguruan_tinggi;
                $academicRecord['biaya_masuk'] = $validated['biaya_masuk_kuliah'];
                $academicRecord = array_map(static fn (mixed $value): mixed => $value ?? '', $academicRecord);
                $feederFields = [
                    'nama_mahasiswa', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
                    'id_agama', 'nik', 'nisn', 'npwp', 'jalan', 'dusun', 'rt', 'rw',
                    'kelurahan', 'id_wilayah', 'kode_pos', 'id_jenis_tinggal',
                    'telepon', 'handphone', 'id_negara', 'email', 'penerima_kps',
                    'nomor_kps', 'id_alat_transportasi', 'nama_ayah',
                    'tanggal_lahir_ayah', 'nik_ayah', 'id_pendidikan_ayah',
                    'id_pekerjaan_ayah', 'id_penghasilan_ayah',
                    'id_kebutuhan_khusus_ayah', 'nama_ibu', 'tanggal_lahir_ibu',
                    'nik_ibu', 'id_pendidikan_ibu', 'id_pekerjaan_ibu',
                    'id_penghasilan_ibu', 'id_kebutuhan_khusus_ibu', 'nama_wali',
                    'tanggal_lahir_wali', 'id_pendidikan_wali', 'id_pekerjaan_wali',
                    'id_penghasilan_wali', 'id_kebutuhan_khusus_mahasiswa',
                ];

                try {
                    $biodataRecord = array_map(
                        static fn (mixed $value): mixed => $value ?? '',
                        array_intersect_key($validated, array_flip($feederFields))
                    );
                    foreach ([
                        'id_kebutuhan_khusus_ayah',
                        'id_kebutuhan_khusus_ibu',
                        'id_kebutuhan_khusus_mahasiswa',
                    ] as $specialNeedField) {
                        if (($biodataRecord[$specialNeedField] ?? '') === '') {
                            $biodataRecord[$specialNeedField] = '0';
                        }
                    }
                    $feeder->updateStudentBiodata((string) $currentRecord->id_mahasiswa, $biodataRecord);
                    $history = $feeder->updateStudentAcademicHistory(
                        (string) $currentRecord->id_mahasiswa,
                        (string) ($currentRecord->id_registrasi_mahasiswa ?? ''),
                        (string) $currentRecord->id_prodi,
                        $academicRecord,
                    );
                } catch (RuntimeException $exception) {
                    report($exception);

                    return response()->json(['message' => $exception->getMessage()], 502);
                }

                $validated['id_perguruan_tinggi'] = $program->id_perguruan_tinggi;
                $validated['id_fakultas'] = $program->id_fakultas;
                $validated['nama_status_mahasiswa'] = $status->ket_referensi;
                $validated['id_registrasi_mahasiswa'] = $history['id_registrasi_mahasiswa'];
                DB::connection('simak_main')->transaction(function () use (
                    $query,
                    $validated,
                    $currentRecord,
                    $history
                ): void {
                    $query->update($validated);
                    if ($history['new_registration']) {
                        DB::connection('simak_main')->table('s1m4k_krs')
                            ->where('id_mahasiswa', $currentRecord->id_mahasiswa)
                            ->update([
                                'id_mahasiswa' => 'dummy '.now()->format('Y-m-d H:i:s'),
                                'id_mahasiswa_old' => $currentRecord->id_mahasiswa,
                            ]);
                    }
                });
            }
        } else {
            $query->update($validated);
        }

        $updated = $this->dataQuery($definition)
            ->where('master.'.$definition['key'], $record)->first();
        if ($module === 'mata-kuliah') {
            $before = array_intersect_key((array) $currentCourse, $validated);
            $changes = $activityLogger->diff($before, $validated);
        } else {
            $before = array_intersect_key((array) $currentRecord, (array) $updated);
            $changes = $activityLogger->diff($before, array_intersect_key((array) $updated, $before));
        }
        if ($changes !== []) {
            $activityLogger->record(
                $user,
                'Data Master '.$definition['title'],
                $module === 'mata-kuliah' ? 'Ubah data di Feeder' : 'Ubah data',
                $changes,
                $record,
                $this->recordIdentity($definition, (array) $updated)
            );
        }

        return response()->json([
            'message' => $definition['title'].' berhasil diperbarui.',
            'record' => $this->recordPayload($updated, $definition, true, $editFields),
        ]);
    }

    public function sync(Request $request, LegacyFeederService $feeder, ActivityLogger $activityLogger): JsonResponse
    {
        $module = (string) $request->route('module');
        $user = $request->user();
        $role = $user->role;
        $programManager = in_array($role, ['userprodi', 'usertu'], true);
        $allowed = ($role === 'admin' && in_array($module, ['perguruan-tinggi', 'program-studi'], true))
            || ($role === 'admin' && in_array($module, ['mahasiswa', 'mata-kuliah'], true))
            || ($programManager && in_array($module, ['mahasiswa', 'mata-kuliah'], true))
            || ($role === 'regulang' && $module === 'mahasiswa');
        abort_unless($allowed, 403);

        $identity = match ($module) {
            'mahasiswa' => $request->validate([
                'npm' => ['required', 'string', 'max:24'],
                'id_prodi' => [$programManager ? 'nullable' : 'required', 'string', 'max:50'],
            ]),
            'mata-kuliah' => $request->validate([
                'kode_mata_kuliah' => ['required', 'string', 'max:20'],
                'id_prodi' => [$programManager ? 'nullable' : 'required', 'string', 'max:50'],
            ]),
            default => [],
        };
        if ($programManager && in_array($module, ['mahasiswa', 'mata-kuliah'], true)) {
            $identity['id_prodi'] = data_get($user, 'id_prodi');
            abort_unless(is_string($identity['id_prodi']) && trim($identity['id_prodi']) !== '', 403);
        }
        if ($module === 'mahasiswa' && ! $programManager) {
            $identity['id_prodi'] = trim((string) $identity['id_prodi']);
            if (! DB::connection('simak_main')->table('s1m4k_masterprodi')
                ->where('id_prodi', $identity['id_prodi'])->exists()) {
                throw ValidationException::withMessages([
                    'id_prodi' => 'Program studi yang dipilih tidak ditemukan.',
                ]);
            }
        }
        $beforeSync = $this->syncSnapshot($module, $identity);
        try {
            $syncResult = match ($module) {
                'perguruan-tinggi' => $feeder->refreshUniversityProfile(),
                'program-studi' => $feeder->syncPrograms(),
                'mahasiswa' => $feeder->syncStudent((string) $identity['npm'], (string) $identity['id_prodi']),
                default => $feeder->syncCourse((string) $identity['kode_mata_kuliah'], (string) $identity['id_prodi']),
            };
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $synced = $syncResult;
        $afterSync = $this->syncSnapshot($module, $identity);
        $syncChanges = $activityLogger->diff($beforeSync, $afterSync);
        if ($syncChanges !== []) {
            $changedRecord = collect($afterSync)->first();
            $activityLogger->record($user, 'Data Master '.$this->definition($module)['title'], 'Sinkronisasi Feeder', [
                'identitas' => $identity,
                'data_berubah' => $syncChanges,
            ], null, $changedRecord === null ? [] : $this->recordIdentity($this->definition($module), $changedRecord));
        }

        return response()->json([
            'message' => match ($module) {
                'perguruan-tinggi' => 'Profil perguruan tinggi berhasil disinkronkan dari Feeder.',
                'program-studi' => "{$synced} program studi berhasil disinkronkan dari Feeder.",
                'mahasiswa' => 'Data mahasiswa berhasil disinkronkan dari Feeder.',
                default => 'Mata kuliah berhasil disinkronkan dari Feeder.',
            },
            'synced' => $synced,
        ]);
    }

    private function definition(string $module): array
    {
        abort_unless(isset(self::MODULES[$module]), 404);

        return self::MODULES[$module];
    }

    private function recordIdentity(array $definition, array $record): array
    {
        $nameColumn = $definition['nameColumn'] ?? null;
        $identityColumn = $definition['identityColumn'] ?? null;

        return array_filter([
            'jenis' => $definition['title'],
            'nama' => $nameColumn !== null ? ($record[$nameColumn] ?? null) : null,
            'nomor_identitas' => $identityColumn !== null ? ($record[$identityColumn] ?? null) : null,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    private function syncSnapshot(string $module, array $identity): array
    {
        $definition = $this->definition($module);
        $query = DB::connection('simak_main')->table($definition['table']);
        if ($module === 'mahasiswa') {
            $query->where('nim', $identity['npm'])
                ->where('id_prodi', $identity['id_prodi']);
        } elseif ($module === 'mata-kuliah') {
            $query->where('kode_mata_kuliah', $identity['kode_mata_kuliah'])
                ->where('id_prodi', $identity['id_prodi']);
        }

        return $query->get()->mapWithKeys(
            fn (object $row): array => [(string) $row->{$definition['key']} => (array) $row]
        )->all();
    }

    private function dataQuery(array $definition): Builder
    {
        $query = DB::connection('simak_main')->table($definition['table'].' as master')
            ->select('master.*');
        $relations = $definition['relations'] ?? [];

        if (in_array('program', $relations, true)) {
            $query->leftJoin('s1m4k_masterprodi as rel_program', 'master.id_prodi', '=', 'rel_program.id_prodi')
                ->addSelect([
                    'rel_program.nama_program_studi as _program_name',
                    'rel_program.nama_jenjang_pendidikan as _program_degree',
                ]);
        }
        if (in_array('faculty', $relations, true)) {
            $query->leftJoin('s1m4k_masterfakultas as rel_faculty', 'master.id_fakultas', '=', 'rel_faculty.id_fakultas')
                ->addSelect('rel_faculty.nama_fakultas as _faculty_name');
        }
        foreach (['dekan' => 'dekan', 'head' => 'ketua_prodi'] as $relation => $foreignKey) {
            if (! in_array($relation, $relations, true)) {
                continue;
            }

            $alias = 'rel_'.$relation;
            $query->leftJoin('s1m4k_masterdosen as '.$alias, 'master.'.$foreignKey, '=', $alias.'.id_dosen')
                ->addSelect([
                    $alias.'.nama_dosen as _'.$relation.'_name',
                    $alias.'.gelar_depan as _'.$relation.'_prefix',
                    $alias.'.gelar_belakang as _'.$relation.'_suffix',
                ]);
        }
        foreach (['pd1', 'pd2', 'pd3'] as $relation) {
            if (! in_array($relation, $relations, true)) {
                continue;
            }

            $query->leftJoin('s1m4k_masterdosen as rel_'.$relation, 'master.'.$relation, '=', 'rel_'.$relation.'.id_dosen')
                ->addSelect([
                    'rel_'.$relation.'.nama_dosen as _'.$relation.'_name',
                    'rel_'.$relation.'.gelar_depan as _'.$relation.'_prefix',
                    'rel_'.$relation.'.gelar_belakang as _'.$relation.'_suffix',
                ]);
        }

        return $query;
    }

    private function listingQuery(Request $request, array $definition, object $user): Builder
    {
        $query = $this->dataQuery($definition);
        $this->applyScope($query, $definition, $user);

        if (! empty($definition['nameColumn'])) {
            $query->whereNotNull('master.'.$definition['nameColumn'])
                ->whereRaw('TRIM(master.'.$definition['nameColumn'].") <> ''");
        }
        if (! empty($definition['requiredName'])) {
            $query->whereNotNull('master.'.$definition['requiredName'])
                ->whereRaw('TRIM(master.'.$definition['requiredName'].") <> ''");
        }

        $program = $request->query('prodi');
        if (! empty($definition['programScoped'])
            && ! in_array($user->role, ['userprodi', 'usertu'], true)
            && is_string($program) && $program !== '') {
            $query->where('master.id_prodi', $program);
        }

        $status = $request->query('status');
        if (! empty($definition['statusScoped']) && is_string($status) && $status !== '') {
            $query->where('master.id_status_mahasiswa', $status);
        }

        return $query;
    }

    private function applyTableSearch(Builder $query, array $definition, string $search): void
    {
        $query->where(function (Builder $searchQuery) use ($definition, $search): void {
            foreach (array_keys($definition['columns']) as $field) {
                if ($field === 'nama_prodi' && in_array('program', $definition['relations'] ?? [], true)) {
                    $searchQuery->orWhere('rel_program.nama_program_studi', 'like', '%'.$search.'%')
                        ->orWhere('rel_program.nama_jenjang_pendidikan', 'like', '%'.$search.'%');
                } elseif ($field === 'nama_fakultas' && in_array('faculty', $definition['relations'] ?? [], true)) {
                    $searchQuery->orWhere('rel_faculty.nama_fakultas', 'like', '%'.$search.'%');
                } elseif ($field === 'nama_dekan' && in_array('dekan', $definition['relations'] ?? [], true)) {
                    $searchQuery->orWhere('rel_dekan.nama_dosen', 'like', '%'.$search.'%');
                } elseif ($field === 'nama_ketua_prodi' && in_array('head', $definition['relations'] ?? [], true)) {
                    $searchQuery->orWhere('rel_head.nama_dosen', 'like', '%'.$search.'%');
                } else {
                    $searchQuery->orWhere('master.'.$field, 'like', '%'.$search.'%');
                }
            }
        });
    }

    private function tableColumnExpression(string $field): string
    {
        return match ($field) {
            'nama_prodi' => 'rel_program.nama_program_studi',
            'nama_fakultas' => 'rel_faculty.nama_fakultas',
            'nama_dekan' => 'rel_dekan.nama_dosen',
            'nama_ketua_prodi' => 'rel_head.nama_dosen',
            default => 'master.'.$field,
        };
    }

    private function displayRecord(object $record, array $definition): object
    {
        foreach (array_keys($definition['columns']) as $column) {
            if ($column === 'nama_dosen' && ($definition['table'] ?? null) === 's1m4k_masterdosen') {
                $record->{$column} = LecturerName::format(
                    (string) ($record->{$column} ?? ''),
                    (string) ($record->gelar_depan ?? ''),
                    (string) ($record->gelar_belakang ?? ''),
                ) ?: '-';
            } elseif ($column === 'nama_prodi' && in_array('program', $definition['relations'] ?? [], true)) {
                $record->{$column} = trim(trim((string) ($record->_program_name ?? '')).' '.trim((string) ($record->_program_degree ?? ''))) ?: '-';
            } elseif ($column === 'nama_fakultas' && in_array('faculty', $definition['relations'] ?? [], true)) {
                $record->{$column} = trim((string) ($record->_faculty_name ?? '')) ?: '-';
            } elseif (in_array($column, ['nama_dekan', 'nama_ketua_prodi'], true)) {
                $relation = $column === 'nama_dekan' ? 'dekan' : 'head';
                $record->{$column} = $this->lecturerName($record, $relation) ?: '-';
            }
        }

        return $record;
    }

    private function lecturerName(object $record, string $relation): string
    {
        return LecturerName::format(
            (string) ($record->{'_'.$relation.'_name'} ?? ''),
            (string) ($record->{'_'.$relation.'_prefix'} ?? ''),
            (string) ($record->{'_'.$relation.'_suffix'} ?? ''),
        );
    }

    private function applyScope(Builder $query, array $definition, object $user): void
    {
        if (! empty($definition['facultyScoped']) && $user->role === 'pimfak') {
            $facultyId = data_get($user, 'id_fakultas');
            if (! is_string($facultyId) || trim($facultyId) === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('master.id_fakultas', trim($facultyId));
            }
        }

        if (! empty($definition['programScoped']) && in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = data_get($user, 'id_prodi');
            if (! is_string($programId) || trim($programId) === '') {
                $query->whereRaw('1 = 0');
            } else {
                $query->where('master.id_prodi', trim($programId));
            }
        }
    }

    private function canEditModule(string $module, object $user): bool
    {
        return $user->role === 'admin'
            || ($user->role === 'regulang' && $module === 'mahasiswa')
            || (in_array($user->role, ['userprodi', 'usertu'], true)
                && in_array($module, ['asisten-dosen', 'dosen', 'mahasiswa', 'mata-kuliah', 'ruang'], true));
    }

    private function editableFields(string $module, array $definition, object $user): array
    {
        if ($module !== 'mahasiswa' || ! in_array($user->role, ['userprodi', 'usertu'], true)) {
            return $definition['edit'] ?? [];
        }

        $allowedFields = ['id_negara', 'id_wilayah', 'id_status_mahasiswa', 'dosen_pa'];

        return array_values(array_filter(
            $definition['edit'] ?? [],
            static fn (array $field): bool => in_array($field['name'], $allowedFields, true)
        ));
    }

    private function validateStudentRegion(array $validated): void
    {
        $countryExists = DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->where('keterangan', 'GetNegara')
            ->where('kode_referensi', $validated['id_negara'])
            ->exists();
        if (! $countryExists) {
            throw ValidationException::withMessages(['id_negara' => 'Negara yang dipilih tidak valid.']);
        }

        $regionExists = DB::connection('simak_main')->table('s1m4k_kodereferensi')
            ->where('keterangan', 'GetWilayah')
            ->where('kode_referensi', $validated['id_wilayah'])
            ->where('ket_referensi', $validated['id_negara'])
            ->exists();
        if (! $regionExists) {
            throw ValidationException::withMessages(['id_wilayah' => 'Wilayah tidak sesuai dengan negara yang dipilih.']);
        }
    }

    private function fieldsFor(array $fields, object $user): array
    {
        $programManager = in_array($user->role, ['userprodi', 'usertu'], true);
        foreach ($fields as &$field) {
            if (($field['options'] ?? null) === 'faculties') {
                $field['options'] = DB::connection('simak_main')->table('s1m4k_masterfakultas')
                    ->whereNotNull('nama_fakultas')->whereRaw("TRIM(nama_fakultas) <> ''")
                    ->orderBy('nama_fakultas')->pluck('nama_fakultas', 'id_fakultas')->all();
            } elseif (($field['options'] ?? null) === 'programs') {
                $query = DB::connection('simak_main')->table('s1m4k_masterprodi')
                    ->select(['id_prodi', 'nama_program_studi', 'nama_jenjang_pendidikan'])
                    ->whereNotNull('nama_program_studi')->whereRaw("TRIM(nama_program_studi) <> ''")
                    ->orderBy('nama_program_studi');
                if ($user->role === 'pimfak' && is_string(data_get($user, 'id_fakultas'))) {
                    $query->where('id_fakultas', data_get($user, 'id_fakultas'));
                } elseif ($user->role === 'pimfak') {
                    $query->whereRaw('1 = 0');
                }
                $field['options'] = $query->get()->mapWithKeys(fn ($program) => [
                    $program->id_prodi => trim(($program->nama_program_studi ?? '').' '.($program->nama_jenjang_pendidikan ?? '')),
                ])->all();
            } elseif (($field['options'] ?? null) === 'universities') {
                $field['options'] = DB::connection('simak_main')->table('s1m4k_masterpt')
                    ->whereNotNull('nama_perguruan_tinggi')
                    ->whereRaw("TRIM(nama_perguruan_tinggi) <> ''")
                    ->orderBy('nama_perguruan_tinggi')
                    ->pluck('nama_perguruan_tinggi', 'id_perguruan_tinggi')
                    ->all();
            } elseif (is_string($field['options'] ?? null)
                && str_starts_with($field['options'], 'reference:')) {
                $reference = substr($field['options'], strlen('reference:'));
                $query = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                    ->where('keterangan', $reference);
                if ($reference === 'GetKebutuhanKhusus') {
                    $query->where('kode_referensi', '!=', '0');
                }
                $field['options'] = $query->orderBy('ket_referensi')
                    ->pluck('ket_referensi', 'kode_referensi')->all();
            }
        }
        unset($field);

        if ($programManager) {
            $fields = array_values(array_filter($fields, static fn (array $field): bool => $field['name'] !== 'id_prodi'));
        }

        return $fields;
    }

    private function validateFields(Request $request, array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $name = $field['name'];
            $rule = [empty($field['required']) ? 'nullable' : 'required'];

            if (($field['type'] ?? null) === 'checkboxes') {
                $optionValues = array_keys($field['options'] ?? []);
                if ($optionValues === []) {
                    throw ValidationException::withMessages([
                        $name => 'Referensi kebutuhan khusus belum tersedia.',
                    ]);
                }
                $rules[$name] = ['sometimes', 'array', 'max:'.count($optionValues)];
                $rules[$name.'.*'] = ['string', 'distinct', 'in:'.implode(',', $optionValues)];

                continue;
            } elseif (($field['type'] ?? null) === 'number') {
                $rule = [...$rule, 'integer', 'min:0', 'max:9999'];
            } elseif (($field['type'] ?? null) === 'decimal') {
                $rule = [...$rule, 'numeric', 'min:0', 'max:20'];
            } elseif (($field['type'] ?? null) === 'currency') {
                $rule = [...$rule, 'numeric', 'min:0', 'max:999999999'];
            } else {
                $rule[] = 'string';
            }

            if (isset($field['max']) && ! in_array(($field['type'] ?? null), ['number', 'decimal', 'currency'], true)) {
                $rule[] = 'max:'.$field['max'];
            }
            if (isset($field['min']) && ! in_array(($field['type'] ?? null), ['number', 'decimal', 'currency'], true)) {
                $rule[] = 'min:'.$field['min'];
            }
            if (($field['type'] ?? null) === 'email') {
                $rule[] = 'email';
            } elseif (($field['type'] ?? null) === 'select') {
                $options = is_array($field['options'] ?? null) ? array_keys($field['options']) : [];
                if ($options !== []) {
                    $rule[] = 'in:'.implode(',', array_map('strval', $options));
                }
            } elseif (($field['type'] ?? null) === 'date') {
                $rule[] = 'date_format:Y-m-d';
            }
            $rules[$name] = $rule;
        }

        $validated = $request->validate($rules);
        foreach ($fields as $field) {
            if (($field['type'] ?? null) !== 'checkboxes') {
                continue;
            }

            $selected = $validated[$field['name']] ?? [];
            $validated[$field['name']] = $selected === [] ? '0' : implode(',', $selected);
        }

        foreach ($fields as $field) {
            $optionSource = $field['options'] ?? null;
            if (($field['type'] ?? null) === 'checkboxes') {
                continue;
            }
            if (! is_string($optionSource)
                || ! array_key_exists($field['name'], $validated)
                || $validated[$field['name']] === null
                || $validated[$field['name']] === '') {
                continue;
            }

            [$table, $column] = match ($optionSource) {
                'faculties' => ['s1m4k_masterfakultas', 'id_fakultas'],
                'programs' => ['s1m4k_masterprodi', 'id_prodi'],
                'lecturers' => ['s1m4k_masterdosen', 'id_dosen'],
                'universities' => ['s1m4k_masterpt', 'id_perguruan_tinggi'],
                default => [null, null],
            };

            $optionQuery = $table === null
                ? null
                : DB::connection('simak_main')->table($table)->where($column, $validated[$field['name']]);
            if ($optionSource === 'origin-universities') {
                $optionQuery = DB::connection('simak_main')->table('s1m4k_masterallpt')
                    ->where('id_perguruan_tinggi', $validated[$field['name']]);
            } elseif ($optionSource === 'origin-programs') {
                $optionQuery = DB::connection('simak_main')->table('s1m4k_masterallprodi')
                    ->where('id_prodi', $validated[$field['name']])
                    ->where('id_perguruan_tinggi', $validated['id_perguruan_tinggi_asal'] ?? '');
            }
            if (is_string($optionSource) && str_starts_with($optionSource, 'reference:')) {
                [$referenceTable, $codeColumn] = ['s1m4k_kodereferensi', 'kode_referensi'];
                $optionQuery = DB::connection('simak_main')->table($referenceTable)
                    ->where($codeColumn, $validated[$field['name']])
                    ->where('keterangan', substr($optionSource, strlen('reference:')));
            }
            if ($optionSource === 'lecturers' && $optionQuery !== null) {
                $optionQuery->whereNotNull('nama_dosen')->whereRaw("TRIM(nama_dosen) <> ''");
            }
            if ($optionQuery !== null && ! $optionQuery->exists()) {
                throw ValidationException::withMessages([$field['name'] => 'Pilihan yang dikirim tidak valid.']);
            }
        }

        return $validated;
    }

    private function authorizedCourse(Request $request, string $record, bool $allowReadOnlyRoles = false): object
    {
        $user = $request->user();
        $roles = $allowReadOnlyRoles
            ? ['admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu']
            : ['admin', 'userprodi', 'usertu'];
        abort_unless(in_array($user->role, $roles, true), 403);
        $query = DB::connection('simak_main')->table('s1m4k_mastermk')
            ->where('id_matkul', $record);
        if (in_array($user->role, ['userprodi', 'usertu'], true)) {
            $programId = data_get($user, 'id_prodi');
            abort_unless(is_string($programId) && trim($programId) !== '', 403);
            $query->where('id_prodi', trim($programId));
        } elseif ($user->role === 'pimfak') {
            $facultyId = data_get($user, 'id_fakultas');
            abort_unless(is_string($facultyId) && trim($facultyId) !== '', 403);
            $query->where('id_fakultas', trim($facultyId));
        }
        $course = $query->first(['id_matkul', 'id_prodi', 'nama_mata_kuliah']);
        abort_if($course === null, 404);

        return $course;
    }

    private function program(string $programId): object
    {
        $program = DB::connection('simak_main')->table('s1m4k_masterprodi')
            ->where('id_prodi', $programId)
            ->first(['id_prodi', 'id_fakultas', 'id_perguruan_tinggi']);
        if ($program === null) {
            throw ValidationException::withMessages(['id_prodi' => 'Program studi tidak ditemukan.']);
        }

        return $program;
    }

    private function recordPayload(
        object $record,
        array $definition,
        bool $includeEditFields = true,
        ?array $editFields = null
    ): array {
        $record = $this->displayRecord($record, $definition);
        $values = [];
        foreach (array_keys($definition['columns']) as $column) {
            $values[$column] = $record->{$column} ?? null;
        }
        foreach ($includeEditFields ? ($editFields ?? ($definition['edit'] ?? [])) : [] as $field) {
            $values[$field['name']] = $record->{$field['name']} ?? null;
            if (($field['options'] ?? null) === 'lecturers') {
                $lecturer = DB::connection('simak_main')->table('s1m4k_masterdosen')
                    ->where('id_dosen', $record->{$field['name']} ?? null)
                    ->first(['nama_dosen', 'gelar_depan', 'gelar_belakang']);
                $values[$field['name'].'_label'] = $lecturer === null
                    ? ''
                    : LecturerName::format(
                        (string) $lecturer->nama_dosen,
                        (string) ($lecturer->gelar_depan ?? ''),
                        (string) ($lecturer->gelar_belakang ?? ''),
                    );
            } elseif (($field['options'] ?? null) === 'origin-universities') {
                $values[$field['name'].'_label'] = (string) DB::connection('simak_main')
                    ->table('s1m4k_masterallpt')->where('id_perguruan_tinggi', $record->{$field['name']} ?? null)
                    ->value('nama_perguruan_tinggi');
            } elseif (($field['options'] ?? null) === 'origin-programs') {
                $values[$field['name'].'_label'] = (string) DB::connection('simak_main')
                    ->table('s1m4k_masterallprodi')->where('id_prodi', $record->{$field['name']} ?? null)
                    ->value('nama_program_studi');
            } elseif (($field['options'] ?? null) === 'student-regions') {
                $region = DB::connection('simak_main')->table('s1m4k_kodereferensi')
                    ->where('keterangan', 'GetWilayah')
                    ->where('kode_referensi', $record->{$field['name']} ?? null)
                    ->first(['ket_referensi2', 'nama_provinsi']);
                $values[$field['name'].'_label'] = $region === null
                    ? ''
                    : trim((string) $region->ket_referensi2).' - '.trim((string) $region->nama_provinsi);
            }
        }

        return [
            'id' => (string) $record->{$definition['key']},
            'values' => $values,
        ];
    }
}
