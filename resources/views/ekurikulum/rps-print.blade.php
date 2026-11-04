<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPS {{ $selectedCourse->kode_mk_live ?: $selectedCourse->kode_mk }} | SIAKAD</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { color: #17212b; font: 11px Arial, sans-serif; line-height: 1.45; margin: 0 auto; max-width: 900px; }
        h1, h2, h3 { margin: 0 0 8px; }
        h1 { font-size: 19px; text-align: center; }
        .institution-logo { display: block; width: 56px; height: 56px; margin: 0 auto 10px; object-fit: contain; }
        h2 { border-bottom: 1px solid #445; font-size: 14px; margin-top: 22px; padding-bottom: 4px; }
        h3 { font-size: 12px; margin-top: 14px; }
        p { margin: 4px 0; }
        table { border-collapse: collapse; margin: 8px 0 14px; width: 100%; }
        th, td { border: 1px solid #59636e; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #edf1f5; }
        .identity td:first-child { font-weight: bold; width: 22%; }
        .actions { margin: 16px 0; text-align: right; }
        button { background: #173b62; border: 0; border-radius: 4px; color: white; cursor: pointer; padding: 9px 14px; }
        @media print { .actions { display: none; } body { max-width: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button></div>
    <img class="institution-logo" src="{{ $siteConfiguration['logo_url'] }}" alt="Logo {{ $siteConfiguration['institution_name'] }}">
    <h1>RENCANA PEMBELAJARAN SEMESTER</h1>
    <p style="text-align:center">{{ $curriculumRecord->nama_program_studi }} · {{ $curriculumRecord->nama_kur }}</p>

    <h2>Identitas Mata Kuliah</h2>
    <table class="identity">
        <tbody>
            <tr><td>Nama mata kuliah</td><td>{{ $selectedCourse->nama_mk }}</td></tr>
            <tr><td>Kode mata kuliah</td><td>{{ $selectedCourse->kode_mk_live ?: $selectedCourse->kode_mk }}</td></tr>
            <tr><td>Program studi</td><td>{{ $curriculumRecord->nama_program_studi }}</td></tr>
            <tr><td>Kurikulum</td><td>{{ $curriculumRecord->nama_kur }} ({{ $curriculumRecord->tahun }})</td></tr>
            <tr><td>SKS / Semester</td><td>{{ $selectedCourse->sks }} SKS / {{ $selectedCourse->semester }}</td></tr>
        </tbody>
    </table>

    <h2>Capaian Pembelajaran Mata Kuliah</h2>
    <table>
        <thead><tr><th>Kode CPMK</th><th>Deskripsi</th><th>CPL terkait</th></tr></thead>
        <tbody>
            @forelse ($rpsData['cpmks'] as $cpmk)
                @php $mappedIds = $rpsData['cpmkMaps']->where('id_cpmk', $cpmk->id_cpmk)->pluck('id_cpl'); @endphp
                <tr><td>{{ $cpmk->kode_cpmk }}</td><td>{{ $cpmk->deskripsi_cpmk }}</td><td>{{ $cpls->whereIn('id_cpl', $mappedIds)->pluck('kode_cpl')->implode(', ') ?: '-' }}</td></tr>
            @empty
                <tr><td colspan="3">Belum ada CPMK.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Sub-CPMK dan Metode Penilaian</h2>
    <table>
        <thead><tr><th>Sub-CPMK</th><th>CPMK terkait</th><th>Partisipasi</th><th>Proyek</th><th>Tugas</th><th>Kuis</th><th>UTS</th><th>UAS</th></tr></thead>
        <tbody>
            @forelse ($rpsData['subcpmks'] as $subcpmk)
                @php
                    $mappedIds = $rpsData['subcpmkMaps']->where('id_subcpmk', $subcpmk->id_subcpmk)->pluck('id_cpmk');
                    $weights = $rpsData['assessments']->get($subcpmk->id_subcpmk);
                @endphp
                <tr>
                    <td>{{ $subcpmk->kode_subcpmk }} — {{ $subcpmk->deskripsi_subcpmk }}</td>
                    <td>{{ $cpmks->whereIn('id_cpmk', $mappedIds)->pluck('kode_cpmk')->implode(', ') ?: '-' }}</td>
                    @foreach (['partisipasi', 'project', 'tugas', 'kuis', 'uts', 'uas'] as $field)
                        <td>{{ $weights?->{$field} ?? 0 }}%</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="8">Belum ada Sub-CPMK.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($rpsData['completeness'])
        <h2>Kelengkapan dan Otorisasi</h2>
        <table class="identity"><tbody>
            @foreach ([
                'rumpun_mk' => 'Rumpun mata kuliah',
                'deskripsi_singkat' => 'Deskripsi singkat',
                'dosen_pengampu' => 'Dosen pengampu',
                'bahan_kajian' => 'Bahan kajian',
                'pustaka_utama' => 'Pustaka utama',
                'pustaka_pendukung' => 'Pustaka pendukung',
                'dsn_pengembang' => 'Dosen pengembang',
                'koor_rmk' => 'Koordinator RMK',
                'koor_prodi' => 'Koordinator program studi',
                'wd1' => 'Wakil dekan I',
            ] as $field => $label)
                @php
                    $value = $rpsData['completeness']->{$field};
                    if ($field === 'dosen_pengampu') {
                        $value = collect(explode(',', (string) $value))->map(fn ($id) => $lecturers[trim($id)] ?? trim($id))->filter()->implode(', ');
                    } elseif (in_array($field, ['dsn_pengembang', 'koor_rmk', 'koor_prodi', 'wd1'], true)) {
                        $value = $lecturers[(string) $value] ?? $value;
                    }
                @endphp
                <tr><td>{{ $label }}</td><td>{{ $value ?: '-' }}</td></tr>
            @endforeach
        </tbody></table>
    @endif

    <h2>Rencana Pembelajaran Mingguan</h2>
    <table>
        <thead><tr><th>Minggu</th><th>Kemampuan akhir</th><th>Indikator / kriteria</th><th>Bentuk pembelajaran</th><th>Materi</th><th>Pustaka</th><th>Bobot</th></tr></thead>
        <tbody>
            @forelse ($rpsData['materials'] as $material)
                <tr>
                    <td>{{ $material->minggu_ke }}</td><td>{{ $material->kemampuan_akhir }}</td>
                    <td>{{ $material->indikator }}<br>{{ $material->kriteria_teknik }}</td>
                    <td>{{ $material->bentuk_pembelajaran }}</td><td>{{ $material->materi_pembelajaran }}</td>
                    <td>{{ $material->pustaka_pilihan }}</td><td>{{ $material->bobot_penilaian }}%</td>
                </tr>
            @empty
                <tr><td colspan="7">Belum ada rencana pembelajaran mingguan.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
