<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { color: #24384d; font-family: sans-serif; font-size: 10pt; line-height: 1.5; }
        h1, h2, h3 { color: {{ $siteConfiguration['primary_color'] }}; }
        h1 { margin: 0 0 7mm; font-size: 25pt; }
        h2 { margin: 9mm 0 3mm; padding-bottom: 2mm; border-bottom: 1px solid #d5e2ef; font-size: 16pt; }
        h3 { margin: 5mm 0 2mm; font-size: 11pt; }
        p { margin: 2mm 0; }
        li { margin: 1.5mm 0; }
        code { color: {{ $siteConfiguration['primary_color'] }}; font-family: monospace; font-size: 9pt; }
        pre { padding: 4mm; border: 1px solid #d5e2ef; background: #f4f8fc; font-family: monospace; font-size: 8pt; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 2.5mm; border: 1px solid #d5e2ef; text-align: left; vertical-align: top; }
        th { background: #eaf3fc; color: {{ $siteConfiguration['primary_color'] }}; }
        .cover { margin-top: 42mm; }
        .cover-logo { display: block; width: 24mm; height: 24mm; margin: 0 auto 8mm; }
        .eyebrow { color: #2774b3; font-size: 10pt; font-weight: bold; letter-spacing: 1pt; }
        .lead { color: #526b83; font-size: 13pt; }
        .note { padding: 3mm; border-left: 3px solid #d7b633; background: #fff8df; }
        .endpoint { padding: 2mm 3mm; background: #f4f8fc; }
        .muted { color: #60758a; font-size: 9pt; }
    </style>
</head>
<body>
    <section class="cover">
        @if (is_file($logoPath))
            <img class="cover-logo" src="file://{{ $logoPath }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
        @endif
        <p class="eyebrow">{{ mb_strtoupper($siteConfiguration['institution_name']) }} · SIAKAD</p>
        <h1>Panduan API<br>Web Service SIAKAD</h1>
        <p class="lead">Dokumentasi integrasi REST API versi 1</p>
        <p><strong>Base URL</strong></p>
        <p class="endpoint"><code>{{ $baseUrl }}</code></p>
        <p class="muted">Diterbitkan {{ $generatedAt }} · Gunakan HTTPS pada server produksi.</p>
        <p class="note">Panduan ini melengkapi katalog operasi dan spesifikasi OpenAPI yang tersedia pada Portal API WS. Aturan validasi akhir tetap mengikuti proses bisnis SIAKAD.</p>
    </section>

    <pagebreak />

    <h2>1. Gambaran umum</h2>
    <p>API SIAKAD v1 menyediakan akses JSON ke operasi yang diizinkan dari aplikasi SIAKAD. Request produksi diteruskan ke controller bisnis yang sudah ada; validasi, otorisasi, scope program studi, dan transaksi mengikuti aturan modul tersebut.</p>
    <p>API tidak menyediakan akses CRUD tabel bebas. Operasi yang menimbulkan efek eksternal—seperti sinkronisasi Feeder, pembayaran, upload berkas, pengiriman email, dan ekspor file—tidak tersedia melalui gateway v1.</p>
    <table>
        <thead><tr><th>Endpoint</th><th>Kegunaan</th></tr></thead>
        <tbody>
            <tr><td><code>GET /</code></td><td>Portal dokumentasi interaktif dan konsol request.</td></tr>
            <tr><td><code>GET /operations</code></td><td>Katalog operasi yang tersedia, termasuk metode dan route.</td></tr>
            <tr><td><code>GET /openapi.yaml</code></td><td>Unduh spesifikasi OpenAPI 3.1 (JSON valid sebagai subset YAML 1.2).</td></tr>
            <tr><td><code>GET /guide.pdf</code></td><td>Unduh panduan PDF ini.</td></tr>
            <tr><td><code>POST /auth/token</code></td><td>Menerbitkan bearer token akun integrasi <code>admin_ws</code>.</td></tr>
            <tr><td><code>POST /auth/sso</code></td><td>Memverifikasi login SIAKAD dan mengembalikan ID, level, serta biodata pengguna.</td></tr>
            <tr><td><code>POST /sandbox/execute</code></td><td>Simulasi operasi dengan fixture; tidak membaca atau mengubah database.</td></tr>
            <tr><td><code>/{route-SIAKAD}</code></td><td>Gateway operasi bisnis yang terdaftar, dengan autentikasi bearer token.</td></tr>
        </tbody>
    </table>

    <h2>2. Autentikasi token API</h2>
    <p>Gateway operasi bisnis memerlukan akun aktif pada SIAKAD dengan <code>level=admin_ws</code> dan <code>blokir=N</code>. Kredensial token berbeda dari SSO: gunakan properti <code>username</code> dan <code>password</code>.</p>
    <pre>curl -X POST "{{ $baseUrl }}/auth/token" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"username":"USERNAME_ADMIN_WS","password":"PASSWORD"}'</pre>
    <p>Respons sukses berisi <code>akses_token</code>, <code>tipe_token</code> bernilai <code>Bearer</code>, dan <code>kedaluwarsa_dalam</code> sebanyak 3600 detik. Token dienkripsi dengan APP_KEY, tidak disimpan pada tabel, dan akun diperiksa kembali setiap request.</p>
    <pre>{
  "akses_token": "&lt;token-rahasia&gt;",
  "tipe_token": "Bearer",
  "kedaluwarsa_dalam": 3600
}</pre>
    <p>Batas penerbitan token adalah 5 request per menit per client IP. Akun <code>admin_ws</code> hanya untuk integrasi dan tidak dapat dipakai untuk login web.</p>

    <h2>3. SSO Login SIAKAD</h2>
    <p>SSO memverifikasi kredensial akun SIAKAD yang berlaku (misalnya username operator, NIDN, kode asisten, atau NIM). Endpoint ini tidak menerbitkan bearer token dan tidak membuat sesi browser.</p>
    <pre>curl -X POST "{{ $baseUrl }}/auth/sso" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"login":"IDENTITAS_SIAKAD","password":"PASSWORD"}'</pre>
    <p>Respons berhasil memuat ID, level, dan biodata di bawah objek <code>user</code>. Biodata dosen, asisten dosen, dan mahasiswa dapat memuat informasi profil akademik dan kontak yang tersedia di SIAKAD; password dan data keluarga tidak dikembalikan.</p>
    <pre>{
  "success": true,
  "user": {
    "id": "&lt;id-pengguna&gt;",
    "level": "&lt;level-akun&gt;",
    "biodata": {
      "jenis_akun": "&lt;jenis-akun&gt;",
      "identitas_login": "&lt;identitas&gt;",
      "nama": "&lt;nama&gt;",
      "email": "&lt;email&gt;"
    }
  }
}</pre>
    <p>Batas request token dan SSO masing-masing 5 request per menit per client IP. Respons autentikasi tidak disimpan di cache.</p>

    <h2>4. Request operasi bisnis</h2>
    <p>Lihat katalog Portal API atau <code>/operations</code> untuk metode, nama operasi, dan path yang tersedia. Path operasi menggunakan route SIAKAD yang terdaftar. Sebagian route memiliki parameter path, query, atau body tertentu; spesifikasi OpenAPI dan form request pada portal menjadi rujukan skema.</p>
    <h3>Contoh GET</h3>
    <pre>curl "{{ $baseUrl }}/data-master/fakultas/data" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer TOKEN"</pre>
    <h3>Contoh mutasi JSON</h3>
    <pre>curl -X POST "{{ $baseUrl }}/ROUTE-OPERASI" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{"field":"nilai"}'</pre>
    <p>POST, PUT, PATCH, dan DELETE dapat mengubah data produksi. Uji bentuk request pada Sandbox terlebih dahulu dan kirim mutasi hanya setelah memahami dampaknya. Aksi mutasi pada konsol portal meminta konfirmasi tambahan.</p>
    <p>Respons GET mengikuti kontrak JSON controller. Mutasi yang pada UI menghasilkan redirect dinormalisasi menjadi JSON yang berisi status dan pesan. Gateway menghapus properti yang bernama password, token, secret, API key, authorization, atau session.</p>

    <h2>5. Sandbox dan Production</h2>
    <table>
        <thead><tr><th>Mode</th><th>Perilaku</th><th>Batas</th></tr></thead>
        <tbody>
            <tr><td>Sandbox</td><td>Simulasi menggunakan fixture; tidak mengakses database, Feeder, atau layanan eksternal dan tidak menyimpan perubahan.</td><td>Body maksimal 64 KB; 30 request per menit per client IP.</td></tr>
            <tr><td>Production</td><td>Memanggil operasi bisnis pada aplikasi SIAKAD dengan validasi dan hak akses yang berlaku.</td><td>60 request per menit per client IP; operasi mutasi dapat mengubah data sebenarnya.</td></tr>
        </tbody>
    </table>
    <p>Gunakan Sandbox untuk menguji bentuk request dan cara integrasi. Respons sukses Sandbox bukan bukti bahwa data Production telah dibuat.</p>

    <h2>6. Status HTTP dan penanganan error</h2>
    <table>
        <thead><tr><th>Status</th><th>Arti umum</th><th>Tindakan</th></tr></thead>
        <tbody>
            <tr><td>200 / 201 / 204</td><td>Berhasil dibaca, dibuat, atau diproses tanpa isi respons.</td><td>Periksa body respons bila tersedia.</td></tr>
            <tr><td>401</td><td>Token tidak valid/kedaluwarsa atau kredensial salah.</td><td>Terbitkan token baru atau periksa kredensial.</td></tr>
            <tr><td>403</td><td>Aksi tidak diizinkan oleh policy atau role.</td><td>Pastikan akun dan scope berhak melakukan operasi.</td></tr>
            <tr><td>404</td><td>Route atau data tidak ditemukan.</td><td>Periksa path, parameter, dan identitas data.</td></tr>
            <tr><td>409</td><td>Status data berkonflik dengan aksi.</td><td>Periksa status terkini sebelum mengulang mutasi.</td></tr>
            <tr><td>413</td><td>Body Sandbox melampaui 64 KB.</td><td>Kurangi ukuran body.</td></tr>
            <tr><td>422</td><td>Validasi request gagal.</td><td>Baca pesan dan rincian field pada response.</td></tr>
            <tr><td>429</td><td>Batas request terlampaui.</td><td>Tunggu sebelum mencoba kembali; gunakan backoff.</td></tr>
            <tr><td>500</td><td>Kesalahan internal.</td><td>Jangan mengandalkan detail internal; hubungi pengelola SIAKAD.</td></tr>
        </tbody>
    </table>

    <h2>7. Keamanan dan praktik integrasi</h2>
    <ul>
        <li>Gunakan HTTPS dan simpan password/token di secret store, bukan source code atau URL.</li>
        <li>Jangan kirim token melalui query string atau menulis token/password ke log.</li>
        <li>Gunakan token hanya untuk integrasi yang berwenang; segera blokir akun jika kredensial terpapar.</li>
        <li>Terapkan timeout, pemeriksaan status HTTP, validasi response, dan retry dengan jeda hanya untuk error sementara.</li>
        <li>Hindari mengulang mutasi secara otomatis bila hasil request tidak diketahui; periksa data terlebih dahulu agar tidak membuat duplikasi.</li>
        <li>Identitas dan otorisasi operasi bisnis tetap tunduk pada aturan controller SIAKAD.</li>
    </ul>
    <p class="note">Katalog operasi, formulir request, dan OpenAPI pada Portal API adalah rujukan untuk route dan field terkini. Untuk kendala akses atau perubahan kontrak, hubungi administrator SIAKAD.</p>
</body>
</html>
