@extends('layouts.simak')

@section('title', 'Log Aktivitas | SIAKAD')
@section('topbar_title', 'Log Aktivitas')

@push('styles')
    <style>
        .activity-log-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); align-items: end; gap: 12px; margin-bottom: 20px; padding: 15px; border: 1px solid #e3e9e2; border-radius: 7px; background: #fbfdfb; }
        .activity-log-wrap { overflow-x: auto; }
        .activity-log-table { width: 100%; min-width: 1050px; border-collapse: collapse; }
        .activity-log-table th, .activity-log-table td { padding: 11px 10px; border-bottom: 1px solid #edf0e8; text-align: left; vertical-align: top; font-size: 11px; }
        .activity-log-table th { color: #71805e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .activity-log-user { min-width: 150px; color: #315b3b; font-weight: 700; }
        .activity-log-user small { display: block; margin-top: 4px; color: #78816d; font-weight: 500; }
        .activity-log-module { min-width: 130px; }
        .activity-log-detail { min-width: 360px; }
        .activity-log-detail summary { color: #376d42; font-weight: 700; cursor: pointer; }
        .activity-log-identity { margin-top: 6px; color: #4d5d4b; line-height: 1.5; }
        .activity-log-identity strong { color: #293c2d; }
        .activity-log-identity small { display: block; color: #78816d; }
        .activity-log-detail pre { max-width: 620px; max-height: 420px; overflow: auto; margin: 9px 0 0; padding: 12px; border: 1px solid #e3e9e2; border-radius: 5px; background: #f8faf6; color: #344535; font: 11px/1.55 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
        .activity-log-pagination { display: flex; justify-content: flex-end; margin-top: 10px; }
        .activity-log-empty { padding: 34px; border: 1px dashed #dce5d8; border-radius: 8px; color: #71805e; text-align: center; }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi</p>
            <h1>Log Aktivitas</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">Riwayat perubahan data pengguna, modul, isi perubahan, dan waktu pencatatan.</p>
            </div>
        </div>
    </div>

    <form class="activity-log-filters" method="GET" action="{{ route('activity-logs.index') }}">
        <div>
            <label for="activity-search">Cari aktivitas</label>
            <input id="activity-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nama, ID, modul, atau isi perubahan">
        </div>
        <div>
            <label for="activity-module">Modul</label>
            <select id="activity-module" name="module" data-searchable-select="filter">
                <option value="">Semua modul</option>
                @foreach ($modules as $module)
                    <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="activity-from">Dari tanggal</label>
            <input id="activity-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div>
            <label for="activity-to">Sampai tanggal</label>
            <input id="activity-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <button class="button button-primary" type="submit">Terapkan Filter</button>
    </form>

    @if ($activities->isEmpty())
        <div class="activity-log-empty">Belum ada aktivitas yang cocok dengan filter.</div>
    @else
        <div class="activity-log-wrap">
            <table class="activity-log-table" data-simple-search-table>
                <thead>
                    <tr>
                        <th>Tanggal &amp; waktu (WIT)</th>
                        <th>Pengguna</th>
                        <th>Modul</th>
                        <th>Aktivitas dan perubahan data</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($activities as $activity)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($activity->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td class="activity-log-user">
                                {{ $activity->nama_user }}
                                <small>{{ $activity->level_user ?: 'Role tidak tercatat' }} · ID: {{ $activity->id_user ?: '-' }}</small>
                            </td>
                            <td class="activity-log-module">{{ $activity->modul }}</td>
                            <td class="activity-log-detail">
                                <details>
                                    <summary>Lihat rincian perubahan</summary>
                                    @if (is_array($activity->details['identitas_data'] ?? null))
                                        <div class="activity-log-identity">
                                            <strong>{{ $activity->details['identitas_data']['jenis'] ?? $activity->modul }}</strong>:
                                            {{ $activity->details['identitas_data']['nama'] ?? 'Nama tidak tercatat' }}
                                            @if (filled($activity->details['identitas_data']['nomor_identitas'] ?? null))
                                                <small>Identitas: {{ $activity->details['identitas_data']['nomor_identitas'] }}</small>
                                            @endif
                                        </div>
                                    @elseif (filled($activity->details['id_data'] ?? null))
                                        <div class="activity-log-identity">
                                            <small>ID data: {{ $activity->details['id_data'] }}</small>
                                        </div>
                                    @endif
                                    <pre>{{ json_encode($activity->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="activity-log-pagination">{{ $activities->links('vendor.pagination.datatables') }}</div>
    @endif
@endsection
