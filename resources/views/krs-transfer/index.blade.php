@extends('layouts.simak')

@section('title', 'KRS Transfer | SIAKAD')
@section('topbar_title', 'KRS Transfer')



@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>KRS Transfer</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">Cari mahasiswa pindahan/transfer untuk melihat dan mengelola mata kuliah yang diakui serta nilai transfer.</p>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <form class="transfer-search" method="GET" action="{{ route('krs-transfer.index') }}">
        <div>
            <label for="transfer-search">NPM atau nama mahasiswa pindahan</label>
            <input id="transfer-search" type="search" name="search" value="{{ old('search', $search) }}" placeholder="Masukkan NPM atau nama">
        </div>
        <div class="transfer-actions">
            <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
            @if (filled($search))
                <a class="button" href="{{ route('krs-transfer.index') }}">Bersihkan</a>
            @endif
        </div>
    </form>

    @if (filled($search) && $students->isNotEmpty())
        <div class="table-wrap">
            <table data-simple-search-table>
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th>SKS Diakui</th>
                        <th class="no-export">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td class="transfer-student-identity">{{ $student->nama_mahasiswa }}<small>{{ $student->nim }}</small></td>
                            <td>{{ $student->nama_program_studi ?: '-' }}</td>
                            <td>{{ $student->sks_diakui ?: 0 }} SKS</td>
                            <td><a class="table-icon-button" href="{{ route('krs-transfer.show', ['student' => $student->id_mahasiswa, 'search' => $search]) }}" title="Lihat KRS transfer {{ $student->nama_mahasiswa }}" aria-label="Lihat KRS transfer {{ $student->nama_mahasiswa }}"><span data-lucide="{{ $canManage ? 'pencil' : 'eye' }}"></span></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="transfer-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
    @elseif (filled($search))
        <div class="master-empty-state"><strong>Mahasiswa transfer tidak ditemukan</strong>Pastikan NPM/nama benar dan mahasiswa terdaftar sebagai mahasiswa pindahan/transfer.</div>
    @else
        <div class="master-empty-state"><strong>Cari mahasiswa transfer</strong>Masukkan NPM atau nama untuk menampilkan data mahasiswa pindahan.</div>
    @endif
@endsection
