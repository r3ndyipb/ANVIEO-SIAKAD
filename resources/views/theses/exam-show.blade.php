@extends('layouts.simak')

@section('title', 'Detail Ujian Skripsi | SIAKAD')
@section('topbar_title', 'Manajemen Ujian Skripsi')

@push('styles')
    <style>
        .thesis-revision-field { grid-column: 1 / -1; }
        .thesis-revision-field textarea { width: 100%; min-height: 190px; padding: 14px 16px; border: 1px solid #bdcddd; border-radius: 10px; background: linear-gradient(145deg, #fff, #f7faff); color: #23384c; font: 500 14px/1.7 system-ui, sans-serif; resize: vertical; box-shadow: inset 0 1px 2px rgb(28 69 105 / 5%); transition: border-color .18s ease, box-shadow .18s ease; }
        .thesis-revision-field textarea:focus { border-color: #3985c2; outline: 0; box-shadow: 0 0 0 4px rgb(57 133 194 / 14%); }
        .thesis-revision-field small { display: block; margin-top: 6px; color: #6d7f90; }
    </style>
@endpush

@section('content')
    <div class="page-heading"><div><p class="eyebrow">{{ $exam->nama_program_studi }} · {{ $statusLabel }}</p><h1>Ujian {{ strtoupper($exam->jenis_ujian) }}</h1><p class="subtitle">{{ $exam->nim }} · {{ $exam->nama_mahasiswa }}</p></div><a class="button" href="{{ route('theses.exams.index') }}">Kembali</a></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    <section class="thesis-section"><h2>{{ $exam->judul }}</h2><p class="subtitle">{{ $exam->waktu_ujian ?: 'Belum dijadwalkan' }} · {{ $exam->ruang_ujian ?: 'Ruang belum ditentukan' }}</p></section>

    @if ($canSchedule)
        <section class="thesis-section"><h2>Jadwalkan Ujian</h2>
            <form method="POST" action="{{ route('theses.exams.schedule', $exam->id_ujian) }}">@csrf @method('PUT')
                <div class="field-grid"><div><label for="exam-time">Waktu ujian</label><input id="exam-time" type="datetime-local" name="waktu_ujian" value="{{ $exam->waktu_ujian ? date('Y-m-d\TH:i', strtotime($exam->waktu_ujian)) : '' }}" required></div><div><label for="exam-room">Ruang ujian</label><input id="exam-room" name="ruang_ujian" maxlength="100" value="{{ $exam->ruang_ujian }}" required></div></div>
                <div class="form-actions"><button class="button button-primary" type="submit">Simpan Jadwal</button></div>
            </form>
        </section>
    @endif

    @if ($canGrade)
        <section class="thesis-section"><h2>Penilaian dan Revisi</h2><p class="subtitle">Catatan yang disimpan akan masuk ke ruang bimbingan mahasiswa.</p>
            <form method="POST" action="{{ route('theses.exams.grade', $exam->id_ujian) }}">@csrf @method('PUT')
                <div class="field-grid"><div><label for="exam-score">Nilai angka (0–100)</label><input id="exam-score" type="number" name="nilai" min="0" max="100" value="{{ $currentScore }}" required></div><div class="thesis-revision-field"><label for="exam-note">Catatan revisi</label><textarea id="exam-note" name="catatan" rows="7" maxlength="5000" placeholder="Tuliskan butir revisi, bagian yang harus diperbaiki, dan arahan penyelesaian secara jelas." required>{{ $currentNote }}</textarea><small>Catatan ini akan dikirim ke riwayat bimbingan mahasiswa.</small></div></div>
                <div class="form-actions"><button class="button button-primary" type="submit">Simpan & Kirim Revisi</button></div>
            </form>
        </section>
    @else
        <section class="thesis-section"><h2>Nilai dan Catatan</h2><div class="table-wrap"><table><thead><tr><th>Peran</th><th>Nilai</th><th>Catatan revisi</th></tr></thead><tbody>
            @foreach ([['Pembimbing 1','nilaipembimbing1','catatanpembimbing1'],['Pembimbing 2','nilaipembimbing2','catatanpembimbing2'],['Penguji 1','nilaipenguji1','catatanpenguji1'],['Penguji 2','nilaipenguji2','catatanpenguji2'],['Penguji 3','nilaipenguji3','catatanpenguji3']] as [$label,$score,$note])
                <tr><td>{{ $label }}</td><td>{{ $exam->{$score} ?: 'Belum dinilai' }}</td><td>{{ $exam->{$note} ?: '-' }}</td></tr>
            @endforeach
        </tbody></table></div></section>
    @endif
@endsection