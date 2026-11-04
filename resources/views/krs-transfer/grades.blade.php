@extends('layouts.simak')

@section('title', 'Input Nilai Transfer | SIAKAD')
@section('topbar_title', 'KRS Transfer')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">KRS Transfer</p><h1>Input Nilai Transfer</h1><p class="subtitle">{{ $student->nama_mahasiswa }} · {{ $student->nim }} · {{ $student->nama_program_studi }}</p></div>
    </div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="module-alert" role="note">
        <strong>Konversi nilai angka:</strong>
        @foreach ($gradeScales as $scale)
            {{ $scale->nilai_huruf }} = {{ $scale->bobot_minimum }} s/d {{ $scale->bobot_maksimum }}@if (!$loop->last) · @endif
        @endforeach
        <p class="subtitle">Nilai angka dibulatkan ke bilangan terdekat (0,5 dibulatkan ke atas), lalu dikonversi berdasarkan rentang bobot nilai.</p>
    </div>
    <form method="POST" action="{{ route('krs-transfer.grades.save', $student->id_mahasiswa) }}">
        @csrf
        @method('PUT')
        <div class="table-wrap">
            <table data-simple-search-table>
                <thead><tr><th>No</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>SKS MK Asal</th><th>Nilai Huruf Asal</th><th>Nilai Angka Diakui</th></tr></thead>
                <tbody>
                    @foreach ($courses as $course)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $course->kode_mata_kuliah }}</td>
                            <td>{{ $course->nama_mata_kuliah }}</td>
                            <td><input type="number" min="0" max="99" step="1" name="grades[{{ $course->id_krs }}][sks_asal]" value="{{ old("grades.{$course->id_krs}.sks_asal", $course->sks_asal) }}" aria-label="SKS asal {{ $course->nama_mata_kuliah }}" required></td>
                            <td><input type="text" maxlength="4" name="grades[{{ $course->id_krs }}][nilai_huruf_asal]" value="{{ old("grades.{$course->id_krs}.nilai_huruf_asal", $course->nilai_huruf_asal) }}" aria-label="Nilai huruf asal {{ $course->nama_mata_kuliah }}"></td>
                            <td><input type="number" min="0" max="100" step="0.01" name="grades[{{ $course->id_krs }}][nilai]" value="{{ old("grades.{$course->id_krs}.nilai", $course->nilai_angka) }}" aria-label="Nilai angka {{ $course->nama_mata_kuliah }}" required></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions" style="margin-top:16px">
            <a class="button" href="{{ route('krs-transfer.show', $student->id_mahasiswa) }}">Batal</a>
            <button class="button button-primary" type="submit">Simpan Nilai</button>
        </div>
    </form>
@endsection
