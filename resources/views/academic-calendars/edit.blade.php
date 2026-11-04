@extends('layouts.simak')

@section('title', 'Ubah Tanggal '.$calendarLabel.' | SIAKAD')
@section('topbar_title', 'Ubah Tanggal '.$calendarLabel)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Data Akademik</p>
            <h1>Ubah tanggal kalender</h1>
            <div class="module-alert" role="note"><p class="subtitle">Periode dan program studi tidak dapat diubah dari formulir ini.</p></div>
        </div>
        <a class="button" href="{{ route($routePrefix.'.index') }}">Kembali ke daftar</a>
    </div>
    <div class="period-strip">{{ $calendar->namatahun ?: $calendar->tahun }} · {{ $calendar->id_prodi }}</div>
    <form method="POST" action="{{ route($routePrefix.'.update', $calendar->id_kalender) }}">
        @csrf
        @method('PUT')
        <div class="field-grid" style="padding: 22px 0">
            @foreach ([
                'tgl_mulai_krs' => 'Mulai input KRS',
                'tgl_akhir_krs' => 'Selesai input KRS',
                'tgl_mulai_isinilai' => 'Mulai input nilai',
                'tgl_akhir_isinilai' => 'Selesai input nilai',
            ] as $field => $label)
                <div>
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $calendar->{$field}) }}" required>
                    @error($field) <p class="field-error" role="alert">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <div class="form-actions">
            <a class="button" href="{{ route($routePrefix.'.index') }}">Batal</a>
            <button class="button button-primary" type="submit">Simpan tanggal</button>
        </div>
    </form>
@endsection