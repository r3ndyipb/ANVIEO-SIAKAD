<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validasi Berita Acara Perkuliahan</title>
    <style>
        body{margin:0;background:#f1f5f9;color:#0f172a;font-family:Arial,sans-serif}
        main{max-width:680px;margin:8vh auto;padding:2rem;background:#fff;border:1px solid #dbe3ee;border-radius:16px;box-shadow:0 12px 32px #0f172a12}
        .institution-logo{display:block;width:64px;height:64px;margin:0 auto 16px;object-fit:contain}
        .valid{color:#15803d;font-weight:700}.valid::before{content:"✓ ";font-size:1.2em}
        dl{display:grid;grid-template-columns:minmax(140px, .7fr) 1.3fr;gap:.7rem 1rem}dt{color:#64748b}dd{margin:0;font-weight:600}
        @media(max-width:520px){main{margin:1rem;padding:1.25rem}dl{grid-template-columns:1fr;gap:.3rem}dd{margin-bottom:.65rem}}
    </style>
</head>
<body>
    <main>
        <img class="institution-logo" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/default-campus-logo.png') }}';">
        <p class="valid">Dokumen terdaftar dan valid</p>
        <h1>Berita Acara Perkuliahan</h1>
        <p>Dokumen ini diterbitkan oleh sistem SIAKAD {{ $siteConfiguration['institution_name'] }}.</p>
        <dl>
            <dt>Mata kuliah</dt><dd>{{ $meeting->nama_mata_kuliah ?: '—' }}</dd>
            <dt>Program studi</dt><dd>{{ $meeting->nama_program_studi }}</dd>
            <dt>Kelas</dt><dd>{{ $meeting->nama_kelas }}</dd>
            <dt>Pertemuan</dt><dd>{{ $meeting->pertemuan_ke }} — {{ $meeting->judul }}</dd>
            <dt>Tanggal</dt><dd>{{ $meeting->tanggal_kuliah }}</dd>
            <dt>Waktu</dt><dd>{{ substr($meeting->waktu_mulai, 0, 5) }}–{{ substr($meeting->waktu_selesai, 0, 5) }} WIT</dd>
            <dt>Ruangan</dt><dd>{{ $meeting->nama_ruang ?: '—' }}</dd>
            <dt>Dosen pengampu utama</dt><dd>{{ \App\Support\LecturerName::format(ucwords(mb_strtolower((string) $meeting->nama_dosen)), $meeting->gelar_depan, $meeting->gelar_belakang) ?: '—' }}</dd>
            <dt>NIP</dt><dd>{{ $meeting->nip ?: '—' }}</dd>
            <dt>Tahun akademik</dt><dd>{{ $meeting->namatahun }}</dd>
        </dl>
    </main>
</body>
</html>
