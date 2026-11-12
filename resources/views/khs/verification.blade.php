<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validasi KHS | SIAKAD</title>
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 24px; background: #edf3f7; color: #233746; font: 15px/1.55 Arial, sans-serif; }
        main { width: min(100%, 520px); overflow: hidden; border: 1px solid #d5e0e7; border-radius: 8px; background: #fff; box-shadow: 0 12px 36px rgb(26 56 74 / 12%); }
        header { padding: 28px 24px; text-align: center; }
        header.valid { background: #edf8f1; border-bottom: 2px solid #21834a; }
        header.invalid { background: #fff1f0; border-bottom: 2px solid #b42318; }
        header img { width: 58px; height: 58px; object-fit: contain; }
        header.valid h1 { color: #17643a; }
        header.invalid h1 { color: #a3221b; }
        header h1 { margin: 12px 0 4px; font-size: 22px; }
        header p { margin: 0; color: #536273; }
        dl { margin: 0; padding: 20px 24px; }
        dl div { padding: 12px 0; border-bottom: 1px dashed #d5e0e7; }
        dl div:last-child { border: 0; }
        dt { color: #607181; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        dd { margin: 4px 0 0; overflow-wrap: anywhere; font-weight: 600; }
        footer { padding: 13px 20px; background: #f7f9fa; color: #607181; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<main>
    <header class="{{ $valid ? 'valid' : 'invalid' }}">
        <img src="{{ $siteConfiguration['preloader_logo_url'] ?? $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/icon.png') }}';">
        <h1>{{ $valid ? 'Dokumen KHS Terverifikasi' : 'Dokumen KHS Tidak Valid' }}</h1>
        <p>{{ $valid ? 'Kartu Hasil Studi ini terdaftar pada sistem '.$siteConfiguration['institution_name'].'.' : 'Kode QR tidak dikenali atau data KHS tidak cocok.' }}</p>
    </header>
    @if ($valid)
        <dl>
            <div><dt>Dokumen</dt><dd>Kartu Hasil Studi (KHS)</dd></div>
            <div><dt>Mahasiswa</dt><dd>{{ $student->nama_mahasiswa }}<br>NPM {{ $student->nim }}</dd></div>
            <div><dt>Program Studi</dt><dd>{{ $student->nama_program_studi ?: '-' }}</dd></div>
            <div><dt>Tahun Akademik</dt><dd>{{ data_get($calendar, 'namatahun') ?: data_get($calendar, 'tahun') }}</dd></div>
            <div><dt>Wakil Dekan I Bidang Akademik</dt><dd>{{ $student->vice_dean_full_name ?: '-' }}<br>NIP. {{ $student->vice_dean_nip ?: '-' }}</dd></div>
            <div><dt>Waktu Validasi</dt><dd>{{ \Illuminate\Support\Carbon::parse(data_get($validation, 'created_at'))->locale('id')->translatedFormat('d F Y H:i') }} WIT</dd></div>
        </dl>
    @endif
    <footer>{{ $siteConfiguration['institution_name'] }} · Verifikasi Dokumen Digital</footer>
</main>
</body>
</html>
