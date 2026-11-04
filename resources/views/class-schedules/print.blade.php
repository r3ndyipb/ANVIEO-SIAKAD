<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jadwal Perkuliahan {{ $programName }} | SIAKAD</title>
    <style>
        body { margin: 24px; color: #1f2933; font: 12px Arial, sans-serif; }
        header { margin-bottom: 22px; text-align: center; }
        header h1, header h2, header p { margin: 5px 0; }
        header img { width: 56px; height: 56px; margin-bottom: 5px; object-fit: contain; }
        header h1 { font-size: 18px; }
        header h2 { font-size: 14px; }
        .print-actions { margin-bottom: 16px; text-align: right; }
        button { padding: 8px 14px; border: 0; border-radius: 4px; background: #356b45; color: white; cursor: pointer; }
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
    <header>
        <img src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
        <h1>JADWAL PERKULIAHAN {{ strtoupper((string) ($calendar->namatahun ?: $calendar->tahun)) }}</h1>
        <h2>PROGRAM STUDI {{ strtoupper($programName) }}</h2>
        <p>{{ mb_strtoupper($siteConfiguration['institution_name']) }}</p>
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
