@extends('layouts.simak')

@section('title', 'Periode KKN | SIAKAD')
@section('topbar_title', 'Periode KKN')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Program &amp; Laporan</p><h1>Periode KKN</h1><p class="subtitle">Atur masa pendaftaran dan periode akademik KKN.</p></div></div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

    <div class="kkn-list-actions"><button class="button button-primary" type="button" data-dialog-open="kkn-period-create-dialog"><span data-lucide="plus"></span> Tambah periode</button></div>

    <div class="table-wrap"><table data-simple-search-table><thead><tr><th>Tahun akademik</th><th>Status</th><th>Tanggal pendaftaran</th><th>Perbarui tanggal</th><th>Aksi</th></tr></thead><tbody>
        @forelse ($periods as $period)
            <tr>
                <td><strong>{{ $period->nama_periode }}</strong><div class="kkn-muted">{{ $period->tahun }}</div></td>
                <td><span class="kkn-status {{ $period->aktif === 'Y' ? 'is-active' : '' }}">{{ $period->aktif === 'Y' ? 'Aktif' : 'Tidak aktif' }}</span></td>
                <td>{{ $period->tanggal_mulai }} s.d. {{ $period->tanggal_akhir }}</td>
                <td><button class="table-icon-button" type="button" data-dialog-open="kkn-period-edit-dialog" data-kkn-period-edit data-period-id="{{ $period->id_periode }}" data-update-url="{{ route('kkn.periods.update', $period->id_periode) }}" data-start-date="{{ $period->tanggal_mulai }}" data-end-date="{{ $period->tanggal_akhir }}" title="Ubah periode" aria-label="Ubah periode {{ $period->nama_periode }}"><span data-lucide="pencil"></span></button></td>
                <td>@if ($period->aktif !== 'Y')<form method="POST" action="{{ route('kkn.periods.activate', $period->id_periode) }}">@csrf<button class="button button-small button-primary" type="submit">Aktifkan</button></form>@else<span class="kkn-muted">Periode berjalan</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="5" class="kkn-empty">Belum ada periode KKN.</td></tr>
        @endforelse
    </tbody></table></div>
    <div class="kkn-pagination">{{ $periods->links('vendor.pagination.datatables') }}</div>
    <dialog class="kkn-dialog" id="kkn-period-create-dialog" data-open-on-load="{{ $errors->any() && old('form') === 'period-create' ? 'true' : 'false' }}" aria-labelledby="kkn-period-create-title">
        <header class="kkn-dialog-head"><h2 id="kkn-period-create-title">Tambah periode KKN</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form class="kkn-form" method="POST" action="{{ route('kkn.periods.store') }}">@csrf<input type="hidden" name="form" value="period-create">
            <label><span>Tahun akademik</span><select name="tahun" data-searchable-select="filter" required><option value="">Pilih tahun</option>@foreach ($years as $year)<option value="{{ $year->tahun }}" @selected(old('tahun') === (string) $year->tahun)>{{ $year->namatahun ?: $year->tahun }}</option>@endforeach</select></label>
            <label><span>Pendaftaran dibuka</span><input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required></label>
            <label><span>Pendaftaran ditutup</span><input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir') }}" required></label>
            <div class="kkn-dialog-actions"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan periode</button></div>
        </form>
    </dialog>
    <dialog class="kkn-dialog" id="kkn-period-edit-dialog" data-open-on-load="{{ $errors->any() && old('form') === 'period-edit' ? 'true' : 'false' }}" aria-labelledby="kkn-period-edit-title">
        <header class="kkn-dialog-head"><h2 id="kkn-period-edit-title">Ubah tanggal periode</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <form class="kkn-form" id="kkn-period-edit-form" method="POST" action="{{ route('kkn.periods.update', old('period_id', '__PERIOD__')) }}">@csrf @method('PUT')<input type="hidden" name="form" value="period-edit"><input type="hidden" name="period_id" value="{{ old('period_id') }}">
            <label><span>Pendaftaran dibuka</span><input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" required></label>
            <label><span>Pendaftaran ditutup</span><input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir') }}" required></label>
            <div class="kkn-dialog-actions"><button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan tanggal</button></div>
        </form>
    </dialog>
@endsection


