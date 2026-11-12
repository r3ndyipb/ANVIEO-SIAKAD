<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jadwal Perkuliahan {{ $programName }} | SIAKAD</title>
    <style>
        body { margin: 24px; color: #1f2933; font: 12px Arial, sans-serif; }
        .print-header { display: flex; align-items: center; gap: 18px; margin-bottom: 22px; border-bottom: 2px solid #0A2E52; padding-bottom: 12px; }
        .institution-logo { width: 68px; height: 68px; object-fit: contain; flex-shrink: 0; }
        .print-header-text { flex: 1; }
        .print-header-text h1, .print-header-text h2, .print-header-text p { margin: 2px 0; }
        .print-header-text h1 { font-size: 18px; color: #0A2E52; font-weight: 700; }
        .print-header-text h2 { font-size: 14px; color: #333; }
        .print-header-text p { font-size: 11px; font-weight: 700; color: #C9962E; }
        .print-actions { margin-bottom: 16px; text-align: right; }
        button { padding: 8px 14px; border: 0; border-radius: 4px; background: #0A2E52; color: white; cursor: pointer; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 7px 6px; border: 1px solid #333; vertical-align: top; }
        th { font-weight: 700; text-align: center; }
        @media print {
            body { margin: 0; }
            .print-actions { display: none; }
            @page { size: legal landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <header class="print-header">
        <img class="institution-logo" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}" onerror="this.onerror=null; this.src='{{ asset('images/landing/default-campus-logo.png') }}';">
        <div class="print-header-text">
            <h1>JADWAL PERKULIAHAN {{ strtoupper((string) ($calendar->namatahun ?: $calendar->tahun)) }}</h1>
            <h2>PROGRAM STUDI {{ strtoupper($programName) }}</h2>
            <p>{{ mb_strtoupper($siteConfiguration['institution_name']) }}</p>
        </div>
    </header>
    <table>
        <thead>
            <tr>
                <th>Hari</th>
                <th>Jam</th>
                <th>Mata Kuliah</th>
                <th>Ruang</th>
                <th>Kelas</th>
                <th>Program Studi</th>
                <th>Dosen Pengampu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($schedules as $schedule)
                <tr>
                    <td>{{ ucwords(strtolower((string) $schedule->hari)) }}</td>
                    <td>{{ substr((string) $schedule->jammulai, 0, 5) }}-{{ substr((string) $schedule->jamselesai, 0, 5) }}</td>
                    <td>{{ ucwords(strtolower((string) ($schedule->nama_mata_kuliah ?? 'Mata kuliah tidak ditemukan'))) }}</td>
                    <td>{{ ucwords(strtolower((string) ($schedule->nama_ruang ?? 'Ruang tidak ditemukan'))) }}</td>
                    <td>{{ ucwords(strtolower((string) $schedule->nama_kelas)) }}</td>
                    <td>{{ $schedule->nama_program_studi }} ({{ $schedule->nama_jenjang_pendidikan }})</td>
                    <td>{{ $schedule->teacher_names ?: 'Belum ditentukan' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada jadwal kuliah pada kalender ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
