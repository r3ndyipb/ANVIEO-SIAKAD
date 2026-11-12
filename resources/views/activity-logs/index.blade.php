@extends('layouts.simak')

@section('title', 'Log Aktivitas | SIAKAD')
@section('topbar_title', 'Log Aktivitas')

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
