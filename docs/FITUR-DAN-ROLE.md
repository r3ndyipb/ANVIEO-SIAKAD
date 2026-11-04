# Fitur dan Matriks Role SIAKAD

Dokumen ini merangkum modul yang tersedia pada aplikasi serta peran utama
penggunanya. Daftar menu dapat berbeda menurut role, jenjang studi, dan scope
organisasi. **Menu bukan pengganti otorisasi**: akses operasi dan data juga
diperiksa oleh route/controller.

## Ringkasan modul

| Area | Modul dan kemampuan |
|---|---|
| Portal publik | Halaman utama perguruan tinggi, profil Fakultas dan Program Studi, serta ringkasan akademik publik. |
| Dashboard | Dashboard master, akademik, LMS, keuangan, E-Skripsi, dan E-Tesis/Disertasi sesuai role. |
| Data master | Perguruan tinggi, Fakultas, Program Studi, Dosen, Asisten Dosen, Mahasiswa, Mata Kuliah, Ruang, dan Bobot Nilai. |
| Kalender dan jadwal | Kalender akademik, jadwal kuliah, jadwal semester pendek, dan informasi kelas. |
| Administrasi akademik | Registrasi ulang, KRS, monitoring KRS, KRS transfer, transfer kelas, transfer nilai MBKM, dan penghapusan Mata Kuliah. |
| Perkuliahan | Absensi, input/validasi nilai, aktivitas mengajar, aktivitas kuliah mahasiswa, KHS, transkrip, status lulus/cuti/DO, dan hasil EDOM. |
| Semester pendek | Kurikulum, jadwal, dan nilai semester pendek. |
| LMS / Virtual Class | Materi, pertemuan, tugas, bank soal, diskusi, serta aktivitas pembelajaran sesuai hak akses. |
| E-Skripsi | Usulan dan validasi judul, penugasan dosen, bimbingan, ujian, upload data, dan verifikasi dokumen. |
| E-Tesis & Disertasi | Usulan judul, penugasan, bimbingan, ujian, upload data, dan dashboard untuk program pascasarjana. |
| KKN/Kubermas | Periode, usulan peserta, validasi, lokasi, peserta, dan plotting penempatan. |
| Wisuda | Periode, pendaftaran, dokumen peserta, validasi, serta ekspor peserta yang lolos ke Excel/PDF/PPT. |
| Keuangan | Periode dan kategori UKT, pembuatan tagihan UKT/IPI, billing manual Midtrans, riwayat transaksi, cek status, dan rekonsiliasi status pembayaran. |
| PDDIKTI/Feeder | Pengaturan koneksi, inisialisasi bertahap dengan checkpoint, pemeriksaan AKM, ekspor Feeder, dan sinkronisasi terjadwal. |
| Administrasi sistem | Pengguna SIMAK, pengaturan institusi dan branding, pengaturan SMTP/payment gateway, log aktivitas, notifikasi, dan bahasa Indonesia/Inggris. |
| Web Service API | Endpoint `/api/v1` untuk operasi API yang diizinkan, dengan akun layanan dan token terpisah dari login web. |

Integrasi Midtrans, SMTP, dan Feeder memerlukan konfigurasi serta credential
masing-masing institusi. Tidak ada credential bersama yang disertakan.

## Matriks role

| Role | Pengguna | Fitur utama |
|---|---|---|
| `admin` | Administrator kampus | Administrasi pengguna dan sistem, seluruh data master, kalender dan proses akademik, LMS, E-Skripsi/E-Tesis, keuangan, KKN/Kubermas, wisuda, validasi, serta integrasi Feeder. Beberapa operasi tetap memiliki validasi dan pembatasan khusus. |
| `pimuniv` | Pimpinan universitas | Dashboard universitas/akademik dan pemantauan, data master serta laporan sesuai hak akses, kalender, jadwal, KRS/nilai, LMS, E-Skripsi/E-Tesis, dan dashboard keuangan. Akses laporan umumnya berorientasi baca. |
| `pimfak` | Pimpinan fakultas | Dashboard dan pemantauan akademik dalam scope Fakultas, data master/laporan yang diizinkan, kalender, jadwal, KRS/nilai, LMS, dan proses skripsi/tesis terkait. |
| `userprodi` | Pengelola Program Studi | Data master dan proses akademik dalam scope Prodi, kalender, jadwal, KRS, absensi/nilai, KHS/transkrip, transfer, status mahasiswa, EDOM, E-Skripsi/E-Tesis, KKN, wisuda, Feeder, serta E-Kurikulum OBE/RPS. |
| `usertu` | Operator Prodi | Operasional data master Prodi dan akademik: kalender, jadwal, KRS, monitoring/transfer, absensi/nilai, aktivitas mahasiswa/mengajar, KHS/transkrip, semester pendek, E-Skripsi/E-Tesis, survei, KKN, wisuda, dan ekspor Feeder sesuai scope. |
| `regulang` | Petugas registrasi ulang | Registrasi ulang, verifikasi calon mahasiswa baru, data Mahasiswa/Mata Kuliah tertentu, KRS dan KHS yang diperlukan proses registrasi. |
| `keuangan` | Petugas keuangan | Dashboard keuangan, periode/kategori UKT, tagihan dan pembayaran UKT/IPI, billing manual Midtrans, riwayat transaksi Sandbox/Production, serta cek status pembayaran. |
| `wisuda` | Operator wisuda | Periode dan peserta wisuda, validasi peserta, serta akses baca ke KRS, KHS, dan transkrip sesuai kebijakan aplikasi. |
| `validasi` | Petugas validasi | Validasi nilai, KKN, peserta wisuda, dan KRS sesuai modul yang diberikan. |
| `lppm` | Operator LPPM | Lokasi dan peserta/plotting KKN serta validasi yang berkaitan dengan kegiatan LPPM. |
| `dosen` | Dosen | Jadwal dan absensi, input nilai, KRS mahasiswa bimbingan akademik, KHS/transkrip mahasiswa sesuai hak, LMS, EDOM, semester pendek, bimbingan/ujian skripsi atau tesis sesuai jenjang. |
| `asdos` | Asisten dosen | Jadwal, absensi, nilai/aktivitas perkuliahan, hasil EDOM, dan nilai semester pendek sesuai penugasan. |
| `mahasiswa` | Mahasiswa | KRS dan jadwal sendiri, KHS/transkrip, materi/tugas/diskusi LMS, tagihan UKT/IPI, pendaftaran wisuda, KKN, usulan judul dan bimbingan skripsi/tesis sesuai jenjang. |
| `timkur` | Tim kurikulum | Profil dan pengelolaan kurikulum berbasis OBE serta RPS pada Prodi yang ditugaskan; tidak mengelola akun tim kurikulum. |
| `admin_ws` | Akun layanan API | Bukan role login web. Akun layanan khusus untuk memperoleh bearer token dan mengakses operasi API yang diizinkan. |

## Identitas login

- Admin dan akun operator (`regulang`, `pimuniv`, `pimfak`, `userprodi`,
  `usertu`, `keuangan`, `wisuda`, `validasi`, `lppm`, `timkur`) masuk memakai
  username yang diberikan administrator.
- Dosen masuk menggunakan identitas dosen yang dikonfigurasi (umumnya NIDN).
- Asisten dosen masuk menggunakan kode asisten.
- Mahasiswa masuk menggunakan NIM.
- `admin_ws` memakai mekanisme token API, bukan form login web.

Pada clean install, administrator pertama dibuat oleh
`php artisan siakad:bootstrap-admin`. Akun dosen, asisten, mahasiswa, dan akun
operator lain belum dibuat otomatis; mereka perlu ditambahkan atau diimpor
sesuai prosedur institusi.

## Scope dan batas akses

- Data pengguna, Fakultas, dan Program Studi dapat membatasi apa yang dapat
  dilihat/dikelola role tersebut.
- Beberapa menu hanya muncul untuk jenjang tertentu; E-Skripsi untuk jenjang
  sarjana/diploma dan E-Tesis/Disertasi untuk jenjang pascasarjana.
- Keuangan hanya melihat proses keuangan yang diotorisasi. Settlement billing
  manual tidak menandai pembayaran UKT/IPI.
- Ekspor wisuda hanya memuat peserta yang statusnya lolos.
- API menggunakan token dan izin operasi tersendiri.
- Administrator institusi sebaiknya membuat akun berdasarkan tugas dan scope
  yang diperlukan, bukan membagikan satu akun bersama.

Daftar ini adalah ringkasan produk, bukan matriks legal/operasional kampus.
Tinjau akses yang diterapkan pada endpoint dan kebijakan institusi sebelum
mengaktifkan role untuk penggunaan nyata.
