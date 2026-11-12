<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Dokumen KRS | SIAKAD</title>
    <style>
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; background: #4070f4; color: #333; font: 15px Arial, sans-serif; }
        main { width: min(100%, 560px); padding: 32px; border-radius: 12px; background: #fff; box-shadow: 0 5px 16px #0002; }
        .institution-logo { display: block; width: 64px; height: 64px; margin: 0 auto 20px; object-fit: contain; }
        .mark { width: 72px; height: 72px; display: grid; place-items: center; margin: 0 auto 20px; border-radius: 50%; background: {{ $valid ? '#16803c' : '#b42318' }}; color: #fff; font-size: 42px; }
        h1 { margin: 0 0 24px; text-align: center; font-size: 22px; }
        dl { display: grid; grid-template-columns: minmax(130px, 1fr) 2fr; gap: 14px 12px; margin: 0; }
        dt { color: #596579; } dd { margin: 0; font-weight: 700; }
        @media (max-width: 480px) { main { padding: 24px 18px; } dl { grid-template-columns: 1fr; gap: 6px; } dd { margin-bottom: 10px; } }
    </style>
</head>
<body>
    <main>
        <img class="institution-logo" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/default-campus-logo.png') }}';">
        <div class="mark" aria-hidden="true">{{ $valid ? '✓' : '×' }}</div>
        <h1>{{ $valid ? 'Verifikasi Dokumen Valid' : 'Dokumen Tidak Valid' }}</h1>
        @if ($valid)
            <dl>
                <dt>Dokumen</dt><dd>Kartu Rencana Studi (KRS)</dd>
                <dt>Tahun Akademik</dt><dd>{{ $approval->namatahun }}</dd>
                <dt>Mahasiswa</dt><dd>{{ $approval->nim }} - {{ $approval->nama_mahasiswa }}</dd>
                <dt>Pengesahan Dosen PA</dt><dd>{{ $approval->yang_mengesahkan }} · {{ $approval->tgl_pengesahan }}</dd>
                <dt>Pengesahan Program Studi</dt><dd>{{ $approval->nama_kpro }} · {{ $approval->tgl_pengesahan_kpro }}</dd>
            </dl>
        @else
            <p>Nomor pengesahan tidak ditemukan atau dokumen belum disahkan lengkap oleh Dosen PA dan program studi.</p>
        @endif
    </main>
</body>
</html>
