@extends('layouts.simak')

@section('title', 'Buat '.$calendarLabel.' | SIAKAD')
@section('topbar_title', 'Buat '.$calendarLabel)

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Data Akademik</p>
            <h1>Buat {{ $calendarLabel }}</h1>
            <div class="module-alert" role="note"><p class="subtitle">Periode baru dibuat untuk prodi aktif yang belum memiliki kode tahun tersebut.</p></div>
        </div>
        <a class="button" href="{{ route($routePrefix.'.index') }}">Kembali ke daftar</a>
    </div>
    @if ($isShortSemester)
        @include('components.short-semester-feeder-alert')
    @endif
    <form method="POST" action="{{ route($routePrefix.'.store') }}">
        @csrf
        <section class="form-section">
            <h2>Periode</h2>
            <label for="periode">Periode akademik</label>
            <select id="periode" name="periode" required>
                <option value="">Pilih periode</option>
                @foreach ($periodOptions as $period => $data)
                    <option value="{{ $period }}" @selected(old('periode') === $period)>{{ $data['label'] }}</option>
                @endforeach
            </select>
            @error('periode') <p class="field-error" role="alert">{{ $message }}</p> @enderror
        </section>
        <section class="form-section">
            <h2>Jadwal akademik</h2>
            <div class="field-grid">
                @foreach ([
                    'tgl_mulai_krs' => 'Mulai input KRS',
                    'tgl_akhir_krs' => 'Selesai input KRS',
                    'tgl_mulai_isinilai' => 'Mulai input nilai',
                    'tgl_akhir_isinilai' => 'Selesai input nilai',
                ] as $field => $label)
                    <div>
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field) }}" required>
                        @error($field) <p class="field-error" role="alert">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>
        <div class="form-actions">
            <a class="button" href="{{ route($routePrefix.'.index') }}">Batal</a>
            <button class="button button-primary" type="submit">{{ $isShortSemester ? 'Buat periode SP' : 'Buat kalender' }}</button>
        </div>
    </form>
@endsection