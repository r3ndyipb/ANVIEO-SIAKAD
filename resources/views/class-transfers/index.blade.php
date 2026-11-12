@extends('layouts.simak')

@section('title', 'Transfer Kelas | SIAKAD')
@section('topbar_title', 'Transfer Kelas')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>Transfer Kelas</h1>
            <div class="module-alert" role="note"><p class="subtitle">Pindahkan KRS mahasiswa ke kelas lain untuk mata kuliah yang sama pada kalender aktif.</p></div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form class="class-transfer-search" method="GET" action="{{ route('class-transfers.index') }}">
        <div>
            <label for="class-transfer-search">NPM mahasiswa</label>
            <input id="class-transfer-search" type="search" name="search" maxlength="24" required value="{{ $filters['search'] ?? '' }}" placeholder="Masukkan NPM">
        </div>
        <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari Mahasiswa</button>
    </form>

    @if (filled($filters['search'] ?? null) && ! $student)
        <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>NPM tidak ditemukan dalam cakupan akun ini.</div>
    @endif

    @if ($student)
        <section class="panel">
            <div class="page-heading">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: '-' }}</p>
                </div>
                @if ($calendar)
                    <span class="status-chip active">{{ $calendar->namatahun ?: $calendar->tahun }}</span>
                @endif
            </div>
            @if (! $calendar)
                <div class="master-empty-state"><strong>Kalender aktif belum tersedia</strong>Program studi mahasiswa belum memiliki kalender aktif.</div>
            @elseif (! $registered)
                <div class="calendar-modal-error" role="alert">Mahasiswa belum melakukan registrasi ulang pada kalender aktif.</div>
            @elseif ($rows->isEmpty())
                <div class="master-empty-state"><strong>KRS belum tersedia</strong>Mahasiswa belum memiliki mata kuliah terprogram pada semester aktif.</div>
            @else
                <div class="table-wrap">
                    <table data-simple-search-table>
                        <thead><tr><th>No</th><th>Kode MK</th><th>Mata Kuliah</th><th>Kelas Diprogram</th><th>Kelas Tujuan</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $row->kode_mata_kuliah }}</td>
                                    <td>{{ $row->nama_mata_kuliah }}<small class="subtext">Semester {{ $row->semester ?: '-' }}</small></td>
                                    <td>{{ $row->current_class ?: 'Kelas tidak ditemukan' }}</td>
                                    <td>
                                        <div class="class-transfer-actions">
                                            <form method="POST" action="{{ route('class-transfers.update', ['student' => $student->id_mahasiswa, 'krs' => $row->id_krs]) }}" onsubmit="return window.confirm('Pindahkan {{ addslashes($row->nama_mata_kuliah) }} ke kelas yang dipilih?')">
                                                @csrf
                                                @method('PUT')
                                                <select name="id_jadwal" required data-searchable-select="filter" data-class-transfer-select aria-label="Kelas tujuan untuk {{ $row->nama_mata_kuliah }}">
                                                    <option value="">Pilih kelas tujuan</option>
                                                    @foreach ($row->target_classes as $targetClass)
                                                        <option value="{{ $targetClass->id_jadwal }}" @selected((string) $row->id_jadwal === (string) $targetClass->id_jadwal)>
                                                            {{ $targetClass->nama_kelas }}{{ (string) $row->id_jadwal === (string) $targetClass->id_jadwal ? ' (saat ini)' : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="table-icon-button" type="submit" title="Pindahkan kelas" aria-label="Pindahkan kelas"><span data-lucide="rotate-ccw"></span></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection