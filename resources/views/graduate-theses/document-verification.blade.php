<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validasi Dokumen E-Tesis & Disertasi | SIAKAD</title>
    <style>
        * { box-sizing: border-box; }
        body { display: grid; min-height: 100vh; place-items: center; margin: 0; padding: 24px; background: #f1f5f9; color: #26384a; font: 15px/1.55 system-ui, sans-serif; }
        main { width: min(100%, 520px); overflow: hidden; border: 1px solid #dce5ed; border-radius: 18px; background: #fff; box-shadow: 0 18px 55px rgb(30 65 96 / 12%); }
        header { padding: 30px 24px; background: {{ $valid ? '#ecfdf5' : '#fef2f2' }}; text-align: center; border-bottom: 2px solid {{ $valid ? '#10b981' : '#ef4444' }}; }
        header img { width: 58px; height: 58px; object-fit: contain; }
        header h1 { margin: 14px 0 5px; color: {{ $valid ? '#047857' : '#b91c1c' }}; font-size: 23px; }
        header p { margin: 0; color: #536273; }
        dl { margin: 0; padding: 24px; }
        dl div { padding: 13px 0; border-bottom: 1px dashed #dce5ed; }
        dl div:last-child { border: 0; }
        dt { color: #718096; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        dd { margin: 4px 0 0; overflow-wrap: anywhere; font-weight: 600; }
        footer { padding: 14px 20px; background: #f8fafc; color: #718096; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<main>
    <header>
        <img src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
        <h1>{{ $valid ? 'Dokumen Terverifikasi' : 'Dokumen Tidak Valid' }}</h1>
        <p>{{ $valid ? 'Dokumen ini terdaftar pada sistem '.$siteConfiguration['institution_name'].'.' : 'Kode QR tidak dikenali atau dokumen tidak terdaftar.' }}</p>
    </header>
    @if ($valid)
        <dl>
            <div><dt>Jenis Dokumen</dt><dd>{{ ucwords(strtolower(str_replace('_', ' ', $document->jenis_dokumen))) }}</dd></div>
            <div><dt>Mahasiswa</dt><dd>{{ $document->nama_mahasiswa }}<br>NPM {{ $document->nim }}</dd></div>
            <div><dt>Program Studi</dt><dd>{{ $document->nama_program_studi }}</dd></div>
            <div><dt>Judul Tesis & Disertasi</dt><dd>{{ $document->judul }}</dd></div>
            <div><dt>Waktu Ujian</dt><dd>{{ $document->waktu_ujian ? \Illuminate\Support\Carbon::parse($document->waktu_ujian)->locale('id')->translatedFormat('d F Y H:i') : '-' }}</dd></div>
            <div><dt>Waktu Validasi</dt><dd>{{ \Illuminate\Support\Carbon::parse($document->created_at)->locale('id')->translatedFormat('d F Y H:i') }} WIT</dd></div>
        </dl>
    @endif
    <footer>{{ $siteConfiguration['institution_name'] }} · Verifikasi Dokumen Digital</footer>
</main>
</body>
</html>
