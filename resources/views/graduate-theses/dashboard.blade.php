@extends('layouts.simak')

@section('title', 'Dashboard E-Tesis & Disertasi | SIAKAD')
@section('topbar_title', 'Dashboard E-Tesis & Disertasi')

@push('styles')
    <style>
        .thesis-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); gap: 10px; margin: 20px 0; }
        .thesis-status-item { padding: 14px; border: 1px solid #e0e8e0; border-top: 3px solid #4b7ca8; border-radius: 6px; background: #fff; }
        .thesis-status-item strong { display: block; color: #244a32; font: 400 25px/1.1 Georgia, serif; }
        .thesis-status-item span { display: block; margin-top: 7px; color: #68745f; font-size: 10px; font-weight: 700; }
        .thesis-section { margin-top: 18px; padding: 18px; border: 1px solid #e1e8e1; border-radius: 7px; background: #fff; }
        .thesis-section h2 { margin: 0 0 14px; color: #294c35; font: 400 21px/1.2 Georgia, serif; }
        .thesis-mini-bar { min-width: 80px; height: 7px; margin-top: 5px; border-radius: 5px; background: #edf1e9; overflow: hidden; }
        .thesis-mini-bar i { display: block; height: 100%; background: #4b7ca8; }
        .thesis-dashboard-chart { display: grid; gap: 10px; margin: 0 0 18px; }
        .thesis-dashboard-chart-row { display: grid; grid-template-columns: minmax(130px, 220px) minmax(80px, 1fr) auto; align-items: center; gap: 12px; color: #52634e; font-size: 11px; }
        .thesis-dashboard-chart-label { overflow-wrap: anywhere; }
        .thesis-dashboard-chart-track { display: flex; height: 12px; overflow: hidden; border-radius: 99px; background: #edf1e9; }
        .thesis-dashboard-chart-bar { display: block; min-width: 0; height: 100%; background: #4b7ca8; }
        .thesis-dashboard-chart-bar.is-green { background: #4f9467; }
        .thesis-dashboard-chart-bar.is-gold { background: #c9a13b; }
        .thesis-dashboard-chart-bar.is-rust { background: #b86c56; }
        .thesis-dashboard-chart-value { color: #354f3a; font-weight: 800; white-space: nowrap; }
        .thesis-chart-legend { display: flex; flex-wrap: wrap; gap: 8px 14px; margin: -4px 0 16px; color: #6c7865; font-size: 10px; }
        .thesis-chart-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .thesis-chart-legend i { width: 9px; height: 9px; border-radius: 50%; background: #4b7ca8; }
        .thesis-chart-legend .is-green { background: #4f9467; }
        .thesis-chart-legend .is-gold { background: #c9a13b; }
        .thesis-chart-legend .is-rust { background: #b86c56; }
        .thesis-dashboard-filter { display: grid; grid-template-columns: minmax(240px, 420px) auto; gap: 12px; align-items: end; margin: 18px 0; }
        @media(max-width:640px) { .thesis-dashboard-filter { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
    <style>
        .thesis-stagnation-week { background: #fff3cd !important; }
        .thesis-stagnation-month { background: #f8d7da !important; }
    </style>
    <div class="page-heading">
        <div>
            <p class="eyebrow">Pemantauan akademik</p>
            <h1>Dashboard E-Tesis & Disertasi</h1>
            <p class="subtitle">Status usulan, tahap tesis & disertasi, beban dosen, dan aktivitas bimbingan.</p>
        </div>
        <a class="button" href="{{ route('graduate_theses.bimbingan.index') }}">Bimbingan Tesis & Disertasi</a>
    </div>

    @if ($showProgramFilter)
        <form class="thesis-dashboard-filter" method="GET" action="{{ route('graduate_theses.dashboard') }}">
            <div>
                <label for="thesis-program">Program studi</label>
                <select id="thesis-program" name="program" data-searchable-select="filter">
                    <option value="">Semua program studi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected($programId === (string) $program->id_prodi)>{{ $program->nama_program_studi }}{{ $program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <button class="button button-primary" type="submit">Tampilkan</button>
        </form>
    @endif

    @if ($data === null)
        <div class="master-empty-state"><strong>Pilih program studi</strong>Pilih prodi untuk memuat ringkasan E-Tesis & Disertasi.</div>
    @else
        <section class="thesis-status-grid" aria-label="Jumlah mahasiswa per tahap tesis & disertasi">
            @foreach ($data['status_labels'] as $status => $label)
                <article class="thesis-status-item"><strong>{{ number_format($data['status_counts'][$status] ?? 0) }}</strong><span>{{ $label }}</span></article>
            @endforeach
        </section>

        <section class="thesis-section" aria-labelledby="thesis-stage-chart-heading">
            <h2 id="thesis-stage-chart-heading">Visualisasi Tahap Tesis & Disertasi</h2>
            @php($maxStageCount = max(1, ...array_values($data['status_counts'])))
            <div class="thesis-dashboard-chart" role="img" aria-label="Grafik jumlah tesis & disertasi per tahap">
                @foreach ($data['status_labels'] as $status => $label)
                    @php($count = (int) ($data['status_counts'][$status] ?? 0))
                    <div class="thesis-dashboard-chart-row">
                        <span class="thesis-dashboard-chart-label">{{ $label }}</span>
                        <span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar" style="width: {{ $count / $maxStageCount * 100 }}%"></i></span>
                        <strong class="thesis-dashboard-chart-value">{{ number_format($count) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="thesis-section">
            <h2>Status Usulan Judul</h2>
            <div class="thesis-status-grid">
                @foreach ([0 => 'Belum diverifikasi', 1 => 'Diterima', 2 => 'Ditolak'] as $status => $label)
                    <article class="thesis-status-item"><strong>{{ number_format($data['judul'][$status] ?? 0) }}</strong><span>{{ $label }}</span></article>
                @endforeach
            </div>
            @php($proposalCounts = [0 => (int) ($data['judul'][0] ?? 0), 1 => (int) ($data['judul'][1] ?? 0), 2 => (int) ($data['judul'][2] ?? 0)])
            @php($maxProposalCount = max(1, ...array_values($proposalCounts)))
            <div class="thesis-dashboard-chart" role="img" aria-label="Grafik status usulan judul">
                @foreach ([0 => ['Belum diverifikasi', ''], 1 => ['Diterima', 'is-green'], 2 => ['Ditolak', 'is-rust']] as $status => [$label, $color])
                    <div class="thesis-dashboard-chart-row">
                        <span class="thesis-dashboard-chart-label">{{ $label }}</span>
                        <span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar {{ $color }}" style="width: {{ $proposalCounts[$status] / $maxProposalCount * 100 }}%"></i></span>
                        <strong class="thesis-dashboard-chart-value">{{ number_format($proposalCounts[$status]) }}</strong>
                    </div>
                @endforeach
            </div>
            @if ($user->role !== 'dosen')
                <h2>Daftar Usulan Judul</h2>
                @if ($data['title_proposals']->isEmpty())
                    <div class="master-empty-state">Belum ada usulan judul pada cakupan ini.</div>
                @else
                    <div class="table-wrap"><table data-datatable="thesis-list" data-thesis-export-filename="data-e-tesis-disertasi">
                        <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Program Studi</th><th>Tanggal Usulan</th><th>Status</th></tr></thead>
                        <tbody>@foreach ($data['title_proposals'] as $proposal)
                            <tr>
                                <td>{{ $proposal->nim }}<small class="subtext">{{ $proposal->nama_mahasiswa }}</small></td>
                                <td>{{ $proposal->judul }}</td>
                                <td>{{ $proposal->nama_program_studi ?: '—' }}</td>
                                <td>{{ $proposal->tanggal_usul }}</td>
                                <td>{{ $proposal->status_label }}</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            @endif
        </section>

        @if ($user->role !== 'mahasiswa')
            <section class="thesis-section">
                <h2>Beban Bimbingan dan Ujian</h2>
                @if (count($data['workload']) === 0)
                    <div class="master-empty-state">Belum ada dosen yang mendapat penugasan aktif.</div>
                @else
                    @php($maxWorkload = max(1, ...array_map(static fn (array $row): int => max($row['supervisor'], $row['examiner']), $data['workload'])))
                    <div class="thesis-chart-legend" aria-label="Legenda grafik beban dosen">
                        <span><i class="is-green"></i>Bimbingan</span><span><i class="is-gold"></i>Ujian</span>
                    </div>
                    <div class="thesis-dashboard-chart" role="img" aria-label="Grafik jumlah mahasiswa bimbingan dan ujian per dosen">
                        @foreach ($data['workload'] as $row)
                            <div class="thesis-dashboard-chart-row">
                                <span class="thesis-dashboard-chart-label">{{ $row['name'] ?: 'Nama belum tersedia' }} · Bimbingan</span>
                                <span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar is-green" style="width: {{ $row['supervisor'] / $maxWorkload * 100 }}%"></i></span>
                                <strong class="thesis-dashboard-chart-value">{{ $row['supervisor'] }}</strong>
                            </div>
                            <div class="thesis-dashboard-chart-row">
                                <span class="thesis-dashboard-chart-label">{{ $row['name'] ?: 'Nama belum tersedia' }} · Ujian</span>
                                <span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar is-gold" style="width: {{ $row['examiner'] / $maxWorkload * 100 }}%"></i></span>
                                <strong class="thesis-dashboard-chart-value">{{ $row['examiner'] }}</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="table-wrap"><table data-datatable="thesis-list" data-thesis-export-filename="data-e-tesis-disertasi">
                        <thead><tr><th>Dosen</th><th>Mahasiswa bimbingan</th><th>Mahasiswa ujian</th></tr></thead>
                        <tbody>@foreach ($data['workload'] as $row)<tr><td>{{ $row['name'] ?: 'Nama belum tersedia' }}</td><td>{{ $row['supervisor'] }}</td><td>{{ $row['examiner'] }}</td></tr>@endforeach</tbody>
                    </table></div>
                @endif
            </section>
        @endif

        <section class="thesis-section">
            <h2>Monitoring Stagnasi Bimbingan</h2>
            <p class="subtitle">Mahasiswa aktif pada status 1–5 dengan bimbingan terakhir lebih dari tujuh hari lalu. Baris kuning menandai lebih dari satu minggu; baris merah menandai lebih dari satu bulan.</p>
            @if (count($data['stagnant']) === 0)
                <div class="master-empty-state">Tidak ada bimbingan yang melewati batas stagnasi.</div>
            @else
                @php($monthStagnant = count(array_filter($data['stagnant'], static fn (array $row): bool => $row['stagnation_level'] === 'month')))
                @php($weekStagnant = count($data['stagnant']) - $monthStagnant)
                @php($maxStagnant = max(1, count($data['stagnant'])))
                <div class="thesis-dashboard-chart" role="img" aria-label="Grafik mahasiswa yang mengalami stagnasi bimbingan">
                    <div class="thesis-dashboard-chart-row"><span class="thesis-dashboard-chart-label">Lebih dari 1 minggu</span><span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar is-gold" style="width: {{ $weekStagnant / $maxStagnant * 100 }}%"></i></span><strong class="thesis-dashboard-chart-value">{{ number_format($weekStagnant) }}</strong></div>
                    <div class="thesis-dashboard-chart-row"><span class="thesis-dashboard-chart-label">Lebih dari 1 bulan</span><span class="thesis-dashboard-chart-track"><i class="thesis-dashboard-chart-bar is-rust" style="width: {{ $monthStagnant / $maxStagnant * 100 }}%"></i></span><strong class="thesis-dashboard-chart-value">{{ number_format($monthStagnant) }}</strong></div>
                </div>
                <div class="table-wrap"><table data-datatable="thesis-list" data-thesis-export-filename="data-e-tesis-disertasi">
                    <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Status</th><th>Tanggal terakhir bimbingan</th><th>Aksi</th></tr></thead>
                    <tbody>@foreach ($data['stagnant'] as $row)<tr @class(['thesis-stagnation-month' => $row['stagnation_level'] === 'month', 'thesis-stagnation-week' => $row['stagnation_level'] === 'week'])>
                        <td>{{ $row['nim'] }}<small class="subtext">{{ $row['student'] }}</small></td>
                        <td>{{ $row['title'] }}</td><td>{{ $row['status'] }}</td>
                        <td>{{ $row['last_message_at'] ? \Illuminate\Support\Carbon::parse($row['last_message_at'])->format('d-m-Y H:i') : 'Belum ada riwayat' }}</td>
                        <td><a class="button button-small" href="{{ route('graduate_theses.bimbingan.show', $row['id']) }}">Buka bimbingan</a></td>
                    </tr>@endforeach</tbody>
                </table></div>
            @endif
        </section>
    @endif
@endsection