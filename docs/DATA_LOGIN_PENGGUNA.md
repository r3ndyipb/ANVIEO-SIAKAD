# Data Kredensial Login Pengguna (Seed Data) - SIAKAD

Dokumen ini memuat daftar akun uji coba (*seed data*) untuk berbagai peran dan tingkatan hak akses pada sistem **SIAKAD (Sistem Informasi Akademik)** berbasis database **PostgreSQL 16**.

---

## 1. Informasi Akses Sistem

- **URL Aplikasi**: [`http://127.0.0.1:8081`](http://127.0.0.1:8081)
- **Halaman Login**: [`http://127.0.0.1:8081/login`](http://127.0.0.1:8081/login) (atau klik tombol **Login / Masuk** di kanan atas halaman utama)
- **Kata Sandi Default (Semua Akun)**:
  ```text
  password123
  ```

---

## 2. Tabel Akun Uji Coba Berdasarkan Peran

| Kategori Akun | Identitas Login | Kata Sandi | Nama Lengkap | Role / Level | Lingkup Akses & Hak Modul |
|---|---|---|---|---|---|
| **Administrator** | `admin` | `password123` | Administrator Utama | `admin` | Akses penuh: manajemen user, konfigurasi sistem, migrasi, feeder, audit log |
| **Kaprodi** | `kaprodi` | `password123` | Kaprodi Teknik Informatika | `userprodi` | Pengelolaan kurikulum OBE, persetujuan KRS prodi, input nilai, plot dosen |
| **Tata Usaha** | `tu_prodi` | `password123` | Tata Usaha Teknik Informatika | `usertu` | Manajemen jadwal kelas, registrasi ulang (her-registrasi), monitoring KRS |
| **Keuangan** | `keuangan` | `password123` | Staf Keuangan & UKT | `keuangan` | Penetapan tagihan UKT/IPI, settlement Midtrans, rekonsiliasi pembayaran |
| **Pimpinan** | `pimpinan` | `password123` | Rektor Universitas SIAKAD | `pimpinan` | Executive Dashboard universitas, statistik mahasiswa, laporan kelulusan |
| **Dekan** | `dekan` | `password123` | Dekan Fakultas Sains & Teknologi | `pimpinan_fakultas` | Executive Dashboard fakultas & pemantauan kegiatan akademik fakultas |
| **Wisuda** | `wisuda` | `password123` | Pengelola Kelulusan & Wisuda | `wisuda` | Penetapan periode wisuda, kuota wisuda, validasi berkas calon wisudawan |
| **Validator** | `validator` | `password123` | Tim Validasi Akademik | `validasi` | Verifikasi keabsahan transkrip nilai, ijazah, dan berkas KRS |
| **Dosen** | **`0011223344`** | `password123` | Dr. Budi Santoso, M.Kom. | `dosen` | Presensi kelas, bimbingan PA, pembimbing skripsi, materi & ujian LMS |
| **Mahasiswa 1** | **`2026001001`** | `password123` | Ahmad Fauzi | `mahasiswa` | Pemrograman KRS, cetak KHS, tagihan UKT, bimbingan skripsi, LMS |
| **Mahasiswa 2** | **`2026001002`** | `password123` | Siti Nurhaliza | `mahasiswa` | Pemrograman KRS, cetak KHS, tagihan UKT, bimbingan skripsi, LMS |
| **Asisten Dosen** | **`ASDOS01`** | `password123` | Rian Pratama, S.Kom. | `asdos` | Asistensi kelas & input presensi perkuliahan |

---

## 3. Data Master Terkait (Organisasi & Akademik)

Akun-akun di atas telah dikaitkan dengan master data berikut:

- **Perguruan Tinggi**: Universitas SIAKAD (ID: `PT-001`, Kode: `001001`)
- **Fakultas**: Fakultas Sains dan Teknologi (ID: `FK-01`, Kode: `FT`)
- **Program Studi**: Teknik Informatika - S1 (ID: `PRODI-TI`, Kode: `55201`)
- **Tahun Akademik Aktif**: 2026/2027 Ganjil (ID: `20261`, Status: `Y` / Aktif)

---

## 4. Perintah Pengelolaan Kredensial

### A. Menjalankan Ulang Seeder
Jika database baru saja di-refresh atau ingin menyinkronkan kembali data pengguna awal:
```bash
docker compose exec app php artisan db:seed
```

### B. Mengubah Kata Sandi via Terminal (Tinker)
Jika ingin mengubah password akun tertentu, jalankan perintah berikut (ganti `'admin'` dan `'PasswordBaru'` sesuai kebutuhan):
```bash
docker compose exec app php artisan tinker --execute="DB::connection('simak_main')->table('s1m4k_admin')->where('username', 'admin')->update(['password' => Hash::make('PasswordBaru')]);"
```
