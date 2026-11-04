# Panduan Instalasi dan Menjalankan SIAKAD di Komputer Lokal

Panduan ini ditujukan untuk pengguna yang baru pertama kali memasang aplikasi
Laravel. Langkah instalasi di sini membuat database **baru dan kosong**. Jangan
mengikuti langkah migrasi pada database kampus yang sudah berisi data.

## 1. Yang perlu disiapkan

Siapkan:

1. **PHP CLI 8.4.1 atau lebih baru**. `composer.json` menyatakan PHP `^8.3`,
   tetapi jalur kerja terbaru telah diuji pada PHP CLI 8.4.12. XAMPP/MAMP dapat
   menyediakan PHP Apache yang berbeda dari PHP pada Terminal; pastikan
   `php -v` menunjukkan versi CLI yang ingin dipakai.
2. **Composer 2** untuk memasang pustaka PHP.
3. **Node.js dan npm** untuk membuat file frontend. Gunakan Node.js 20 atau
   lebih baru.
4. **MySQL atau MariaDB** yang aktif dan dapat diakses melalui PDO MySQL.
   XAMPP, MAMP, atau instalasi database lokal lain dapat digunakan.
5. Git untuk mengambil source code, atau arsip source dari GitHub.

Ekstensi PHP yang diperlukan meliputi BCMath, Ctype, cURL, Fileinfo, GD,
Iconv, Mbstring, OpenSSL, PDO MySQL, Tokenizer, XML, dan ZIP. Setelah dependency
terpasang, periksa PHP CLI dengan `composer check-platform-reqs`.

### Memeriksa PHP pada web server

PHP untuk Apache/LiteSpeed dapat berbeda dari PHP CLI yang dipakai Composer dan
Artisan. File pemeriksaan mandiri
[`cek-persyaratan-simak.php`](cek-persyaratan-simak.php) memeriksa PHP minimal
8.3 dan ekstensi web server yang dibutuhkan, serta menampilkan deteksi
LiteSpeed dan HTTPS.

Untuk pemeriksaan lokal, salin sementara file tersebut ke document root yang
dijalankan Apache (misalnya `public/cek-persyaratan-simak.php`), lalu buka
`http://127.0.0.1/cek-persyaratan-simak.php` sesuai alamat lokal Anda. Pada
hosting, unggah file sementara ke document root domain, buka URL-nya melalui
browser, lalu **hapus file tersebut segera setelah pemeriksaan selesai**.
Jangan simpan file ini di document root atau biarkan dapat diakses publik
setelah setup. Jika ada komponen berstatus “Perlu tindakan”, aktifkan ekstensi
pada konfigurasi PHP web server, restart layanan bila diperlukan, lalu ulangi
pemeriksaan. File ini tidak menguji koneksi database, Composer, Node.js, atau
konfigurasi `.env`; gunakan langkah panduan berikut untuk hal-hal tersebut.

## 2. Ambil source code dan pasang dependency

Di Terminal macOS/Linux atau PowerShell/Git Bash:

```bash
git clone <URL-REPOSITORY>
cd siakad
cp .env.example .env
composer install
npm ci
```

Jika mengunduh ZIP dari GitHub, ekstrak ZIP, buka Terminal pada folder project,
dan lanjutkan dari `composer install`. Jangan mengunggah folder `vendor/` atau
`node_modules/` ke GitHub; keduanya dibuat ulang melalui perintah di atas.

Pada Windows PowerShell, salin file environment menggunakan:

```powershell
Copy-Item .env.example .env
```

## 3. Buat database MySQL kosong

1. Jalankan MySQL/MariaDB dari XAMPP/MAMP atau layanan database lokal.
2. Buka phpMyAdmin, pilih **New/Buat database**, dan buat database baru bernama
   `siakad` dengan collation `utf8mb4_unicode_ci`.
3. Catat host, port, nama pengguna, dan password database lokal. Nilai umum
   untuk XAMPP adalah host `127.0.0.1` dan port `3306`; password bergantung
   pada konfigurasi lokal Anda.

Alternatif melalui klien MySQL:

```sql
CREATE DATABASE siakad
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Gunakan akun database khusus untuk instalasi lokal jika memungkinkan. Jangan
menggunakan koneksi/credential database production pada pengujian komputer
pribadi.

## 4. Atur `.env`

Buka `.env` dengan editor teks. `.env.example` tidak berisi password database
atau credential integrasi. Isi koneksi utama SIAKAD berikut sesuai MySQL lokal:

```dotenv
APP_NAME=SIAKAD
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Koneksi default Laravel.
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=siakad
DB_USERNAME=root
DB_PASSWORD=

# Koneksi database utama SIAKAD (MySQL/MariaDB).
SIMAK_DB_HOST=127.0.0.1
SIMAK_DB_PORT=3306
SIMAK_DB_DATABASE=siakad
SIMAK_DB_USERNAME=root
SIMAK_DB_PASSWORD=
```

Ganti username dan password dengan konfigurasi database di komputer Anda.
Untuk pemasangan sederhana, isi `DB_*` dan `SIMAK_DB_*` dengan host, port,
database, user, dan password yang sama. `SIMAK_DB_*` adalah koneksi utama akun
dan data akademik SIAKAD. Semua perintah skema pada panduan ini memakai
koneksi bernama `simak_main`; jangan mengubahnya menjadi database
lama/production.

Simpan `.env`, lalu buat APP_KEY:

```bash
php artisan key:generate
```

**Jangan mengganti APP_KEY setelah aplikasi menyimpan credential terenkripsi**
(misalnya credential Feeder atau payment gateway). APP_KEY diperlukan untuk
membaca kembali credential tersebut.

> `.env.example` sudah menggunakan MySQL. Jika Anda sengaja mengganti
> `DB_CONNECTION` menjadi `sqlite` untuk penggunaan lanjutan, buat file kosong
> `database/database.sqlite` dan tetap arahkan `SIMAK_DB_*` ke MySQL.

## 5. Buat struktur tabel dan akun admin pertama

Pastikan database `siakad` benar-benar baru dan kosong. Jalankan:

```bash
php artisan migrate --database=simak_main --schema-path=database/schema/siakad_main_schema.sql
```

Opsi `--schema-path` menunjuk langsung ke file baseline bernama baru sehingga
perintah tetap jelas meskipun nama koneksi Laravel adalah `simak_main`. Baseline
ini membuat struktur tabel clean-install, tetapi tidak memasukkan data master,
mahasiswa, atau akun admin. Jangan menghilangkan opsi tersebut atau menggantinya
dengan file schema lain. Baseline ini sudah memuat seluruh 95 tabel aplikasi;
tidak perlu mengimpor file SQL tambahan dari folder manual.

Setelah migrasi berhasil, buat satu admin awal secara interaktif:

```bash
php artisan siakad:bootstrap-admin
```

1. Konfirmasi pembuatan akun untuk database yang ditampilkan.
2. Masukkan password admin minimal 8 karakter pada prompt tersembunyi.
3. Masukkan ulang password untuk konfirmasi.

Username login adalah `admin`. Password yang Anda masukkan disimpan sebagai
hash. Perintah menolak berjalan jika tabel `s1m4k_admin` sudah memiliki akun;
perintah tidak menghapus atau mengubah akun lama.

Tidak ada username/password default `admin/admin` di schema atau seeder. Jika
login pertama gagal, periksa konfigurasi koneksi `SIMAK_DB_*` dan pastikan
perintah bootstrap sukses pada database yang benar.

## 6. Buat file frontend dan jalankan aplikasi

Bangun aset frontend:

```bash
npm run build
```

Siapkan public storage (diperlukan untuk logo dan file yang dikelola aplikasi):

```bash
php artisan storage:link
```

Jalankan server development:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Buka <http://127.0.0.1:8000>, lalu masuk menggunakan username `admin` dan
password yang Anda buat. Hentikan server dengan `Ctrl+C`.

Jika ingin mengembangkan CSS/JavaScript dan melihat perubahan secara langsung,
buka Terminal kedua dan jalankan:

```bash
npm run dev
```

Jika Node/Vite tidak dijalankan, jalankan kembali `npm run build` setelah
mengubah aset frontend.

## 7. Data awal dan pengaturan institusi

Database fresh install hanya memiliki struktur tabel. Sebelum mengimpor data
Feeder:

1. Masuk sebagai admin, buka **Data Master → Fakultas**, dan siapkan data
   fakultas yang sesuai kampus. Import Program Studi memerlukan data Fakultas
   yang telah tersedia.
2. Jika sudah ada Program Studi lokal, buka **Data Master → Program Studi** dan
   pastikan setiap Prodi dipetakan ke Fakultas yang benar. Tahap Feeder tidak
   dapat berlanjut bila ada Prodi tanpa pemetaan Fakultas yang valid.
3. Atur nama perguruan tinggi, logo, warna utama, dan branding melalui data
   perguruan tinggi/pengaturan sistem yang tersedia.
4. Buat akun role lain melalui **Administrasi & Tools → Pengguna SIMAK**.
   Untuk dosen/mahasiswa, identitas login mengikuti data master (NIDN/NIM/kode
   asisten), bukan akun demo bawaan.

Nama menu dapat berubah mengikuti versi UI dan role yang sedang login.

## 8. Inisialisasi data dari PDDIKTI/Feeder

### Sebelum memulai

- Pastikan layanan Feeder kampus dapat diakses dari komputer aplikasi.
- Siapkan URL WebService, username, dan password Feeder yang sah.
- Pastikan database target adalah database lokal/baru yang memang disiapkan
  untuk impor. Impor dapat menambah atau memperbarui data dalam jumlah besar;
  lakukan backup sebelum mengulang proses pada database yang sudah berisi data.
- Siapkan data Fakultas dan pemetaan Fakultas untuk seluruh Program Studi
  terlebih dahulu, sesuai prasyarat di bagian sebelumnya.
- Jangan menaruh password pada command history, tiket, screenshot, atau Git.

### Mulai proses

Di Terminal, dari root project:

```bash
php artisan siakad:initialize-feeder
```

Perintah menguji koneksi, lalu menjalankan tahapan berurutan. Jika URL, username,
atau password belum tersimpan di pengaturan Master PT, command meminta URL dan
username pada prompt biasa dan password pada prompt tersembunyi. URL dan
username dapat diberikan secara eksplisit:

```bash
php artisan siakad:initialize-feeder \
  --url=https://feeder.example.ac.id/ws \
  --username=USERNAME_FEEDER
```

Password tetap diminta melalui prompt tersembunyi. Opsi password **tidak**
disediakan pada command line agar tidak tersimpan pada shell history atau
daftar proses. Untuk otomasi non-interaktif saja, `FEEDER_PASSWORD` dapat
disediakan di `.env` privat bersama `--url`, `--username`, dan `--yes`; jangan
pernah memasukkan nilai rahasia ke `.env.example` atau Git.

Sebelum command mengimpor data, aplikasi menampilkan konfirmasi. Periksa pesan
tersebut, pastikan backup dan database target benar, lalu jawab `yes`.

### Tahapan yang dijalankan

Command terminal berjalan otomatis sampai selesai, dengan checkpoint per batch:

1. Koneksi Feeder
2. Master referensi
3. Profil Master PT dan konfigurasi Feeder
4. Program Studi
5. Dosen dan penugasan
6. Mahasiswa dan riwayat pendidikan
7. Mata Kuliah
8. Bobot Nilai
9. Seluruh Perguruan Tinggi
10. Seluruh Program Studi
11. Kalender Akademik
12. Nilai Mahasiswa

Tahapan dan jumlah data yang diproses dicetak ke Terminal. Proses dapat berjalan
lama untuk basis data besar; jangan menutup Terminal kecuali memang perlu
menghentikan proses.

Pada tahap Dosen, profil dan penugasan diambil per halaman maksimal 100 baris.
Penugasan diurutkan dari tahun ajaran terbaru agar setiap dosen memakai
penugasan terbarunya yang memiliki pemetaan Program Studi/Fakultas valid.

### Lanjutkan setelah terputus/gagal

Jika proses berhenti karena jaringan, error, atau Terminal tertutup, command
menyimpan checkpoint terenkripsi di cache. Setelah penyebab diperbaiki,
jalankan:

```bash
php artisan siakad:initialize-feeder --resume
```

Checkpoint tersedia selama tujuh hari. Cache harus tetap persisten antara
proses; konfigurasi default `CACHE_STORE=file` cocok untuk development lokal.
Jaga APP_KEY tetap sama supaya checkpoint/kredensial terenkripsi tetap dapat
dibaca. Bila checkpoint tidak ada atau telah kedaluwarsa, perintah tidak dapat
melanjutkan dan perlu pemeriksaan ulang sebelum memulai proses baru.

### Ulangi tahap yang sedang aktif

Jika perlu mengulang **seluruh tahap checkpoint saat ini** dari awal, misalnya
setelah memperbaiki data pemetaan atau error yang melewati sebagian batch:

```bash
php artisan siakad:initialize-feeder --resume --restart-current-stage
```

Perintah meminta konfirmasi karena data tahap itu dapat diperbarui lagi. Untuk
melewati konfirmasi dalam otomasi yang memang disengaja:

```bash
php artisan siakad:initialize-feeder --resume --restart-current-stage --yes
```

Gunakan `--yes` dengan sangat hati-hati. Tahap koneksi dan tahap selesai tidak
dapat diulang dengan opsi ini. `--resume` saja melanjutkan posisi/cursor checkpoint
yang tersimpan; `--restart-current-stage` mereset cursor tahap aktif.

Jika proses gagal, baca pesan Terminal dan `storage/logs/laravel.log`, perbaiki
penyebab, lalu pilih `--resume` atau ulangi tahap aktif. Jangan menghapus cache
atau membuat command baru sebelum memahami keadaan impor.

Jika impor lama sudah selesai sebelum perbaikan tahap penugasan Dosen, tunggu
hingga tidak ada proses inisialisasi/sinkronisasi lain yang berjalan, perbarui
kode, lalu jalankan sinkronisasi Dosen untuk memperbarui roster dan penugasan
terbaru tanpa mengulang semua tahap Feeder:

```bash
php artisan siakad:sync-feeder-lecturers
```

Periksa ringkasan hasilnya, terutama jumlah dosen tanpa homebase. Data Dosen
akan menggunakan penugasan terbaru yang Program Studi dan Fakultasnya tersedia
di master lokal. Data Dosen yang tidak mempunyai penugasan Feeder yang cocok,
atau tidak memiliki satu pun Prodi/Fakultas terpetakan, tidak dapat dilengkapi
otomatis; perbaiki pemetaan tersebut sebelum mengulangi sinkronisasi.

## 9. Sinkronisasi Feeder terjadwal

Setelah koneksi Feeder dan konfigurasi kampus selesai, aplikasi menyediakan
sinkronisasi terjadwal untuk dosen dan registrasi mahasiswa. Untuk melihat
jadwal:

```bash
php artisan schedule:list
```

Jadwal yang terdaftar menggunakan zona waktu `Asia/Jayapura`: sinkronisasi
dosen pukul 00:00 dan ID registrasi mahasiswa pukul 00:30. Pada development
lokal, jalankan scheduler di Terminal tersendiri:

```bash
php artisan schedule:work
```

Scheduler hanya berjalan selama proses Terminal tersebut hidup. Di server,
konfigurasikan scheduler/cron sesuai panduan deployment. Command sinkronisasi
langsung dapat memproses data kampus; jangan menjalankannya ke database
operasional tanpa backup dan persetujuan.

## 10. Konfigurasi fitur opsional

- **Midtrans:** admin mengisi credential dan memilih Sandbox/Production melalui
  Pengaturan Sistem. Credential dienkripsi memakai APP_KEY. Uji Sandbox dan
  callback terlebih dahulu; jangan aktifkan transaksi production sebelum
  URL callback HTTPS dan konfigurasi gateway diverifikasi.
- **SMTP:** admin mengisi SMTP melalui Pengaturan Sistem jika email diperlukan.
  Untuk development, gunakan server uji atau mailer `log`.
- **API:** Web Service tersedia di `/api/v1`. Buat akun layanan/token sesuai
  panduan API aplikasi; token bukan password login web.
- **Logo dan file publik:** jalankan `php artisan storage:link` setelah setup.

Integrasi ini opsional untuk memulai aplikasi, tetapi memerlukan credential dan
konfigurasi yang disediakan oleh institusi sendiri.

## 11. Pemecahan masalah

| Gejala | Yang perlu diperiksa |
|---|---|
| Composer menolak versi PHP | Jalankan `php -v` dan pastikan Composer memakai PHP CLI yang benar (gunakan PHP 8.4.1+). |
| `could not find driver` / PDO MySQL | Aktifkan ekstensi `pdo_mysql` pada PHP CLI, lalu restart Terminal. |
| Koneksi ditolak atau access denied | Periksa service MySQL, host, port, nama database, username, dan password `SIMAK_DB_*`. |
| Tidak bisa login setelah instalasi | Pastikan migrasi dan `siakad:bootstrap-admin` berhasil; admin baru tidak memakai password bawaan. |
| File logo tidak terbuka | Jalankan `php artisan storage:link` dan pastikan folder `storage/` dapat ditulis oleh PHP. |
| Frontend tidak termuat | Jalankan `npm ci` lalu `npm run build`; pastikan `public/build/` terbentuk. |
| Feeder gagal di tahap Program Studi | Pastikan Master Fakultas sudah diisi dan semua Program Studi mempunyai Fakultas valid. |
| Checkpoint tidak dapat dilanjutkan | Pastikan cache store dan APP_KEY tidak berubah; checkpoint terminal berlaku tujuh hari. |

## 12. Batas database dan pengujian

- Schema
  [`database/schema/siakad_main_schema.sql`](../database/schema/siakad_main_schema.sql)
  dan `php artisan migrate --database=simak_main --schema-path=database/schema/siakad_main_schema.sql`
  hanya untuk database clean install yang kosong.
- File `database/database.sqlite` merupakan database kerja lokal dan tidak
  boleh diunggah ke GitHub.
- Jangan memakai `php artisan migrate:fresh`, `migrate:refresh`, atau import
  baseline untuk upgrade database yang telah berisi data.
- Jangan arahkan `.env` development ke database produksi. Pisahkan database,
  user, dan credential untuk tiap instalasi.
- Jalankan tes hanya dengan konfigurasi database uji yang terisolasi. Jangan
  jalankan seluruh suite pada database lokal yang berisi data kampus; sejumlah
  tes dapat membuat dan mengubah tabel/data.
- Backup database dan file `.env`/APP_KEY sebelum operasi maintenance.
