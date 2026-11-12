@extends('layouts.simak')

@section('title', 'Pilih Mata Kuliah Transfer | SIAKAD')
@section('topbar_title', 'KRS Transfer')

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">KRS Transfer</p><h1>Pilih Mata Kuliah yang Diakui</h1><p class="subtitle">{{ $student->nama_mahasiswa }} · {{ $student->nim }} · {{ $student->nama_program_studi }}</p></div>
    </div>
    @if ($errors->any())<div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('krs-transfer.courses.save', $student->id_mahasiswa) }}">
        @csrf
        @method('PUT')
        <div class="transfer-course-toolbar">
            <div><strong>Maksimum {{ number_format($maximumCredits, 0) }} SKS diakui</strong><div class="subtitle">SKS dipilih: <span id="transfer-credit-total" data-maximum-credits="{{ $maximumCredits }}">0</span> / {{ number_format($maximumCredits, 0) }}</div></div>
            <div class="transfer-toolbar">
                <a class="button" href="{{ route('krs-transfer.show', $student->id_mahasiswa) }}">Batal</a>
                <button class="button button-primary" type="submit">Simpan Mata Kuliah</button>
            </div>
        </div>
        @if ($courses->isNotEmpty())
            <div class="table-wrap">
                <table id="transfer-course-table" data-simple-search-table>
                    <thead><tr><th>Semester</th><th>Kode MK</th><th>Nama Mata Kuliah</th><th>SKS</th><th>Pilih</th></tr></thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr data-transfer-course-row>
                                <td>{{ $course->semester ?: '-' }}</td>
                                <td>{{ $course->kode_mata_kuliah }}</td>
                                <td>{{ $course->nama_mata_kuliah }}</td>
                                <td>{{ number_format((float) $course->sks_mata_kuliah, 0) }}</td>
                                <td><input type="checkbox" name="course_ids[]" value="{{ $course->id_matkul }}" data-transfer-credit="{{ (float) $course->sks_mata_kuliah }}" @checked(in_array((string) $course->id_matkul, $selectedIds, true)) aria-label="Pilih {{ $course->nama_mata_kuliah }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="master-empty-state"><strong>Tidak ada mata kuliah aktif</strong>Program studi mahasiswa belum memiliki mata kuliah aktif untuk dikonversi.</div>
        @endif
        <div class="transfer-course-toolbar">
            <span class="subtitle">Total SKS pilihan harus tidak melebihi batas SKS yang diakui.</span>
            <button class="button button-primary" type="submit" @disabled($courses->isEmpty())>Simpan Mata Kuliah</button>
        </div>
    </form>
@endsection
