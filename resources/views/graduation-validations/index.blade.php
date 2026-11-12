@extends('layouts.simak')

@section('title', 'Validasi Wisuda | SIAKAD')
@section('topbar_title', 'Validasi Wisuda')

@section('content')
    <div class="page-heading"><div><p class="eyebrow">Wisuda</p><h1>{{ $canVerify ? 'Validasi Peserta Wisuda' : 'Laporan Wisuda' }}</h1><p class="subtitle">{{ $canVerify ? 'Periksa dokumen dan simpan keputusan verifikasi peserta.' : 'Daftar seluruh peserta yang telah lolos verifikasi.' }}</p></div></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif

    <form class="graduation-validation-filter" method="GET" action="{{ route('graduation-validations.index') }}">
        <div>
            <label for="graduation-validation-period">Periode Wisuda</label>
            <select id="graduation-validation-period" name="periode" required>
                <option value="">Pilih periode</option>
                @foreach ($periods as $item)
                    <option value="{{ $item->id_periode }}" @selected($period && (string) $period->id_periode === (string) $item->id_periode)>{{ $item->nama_periode }} · {{ $item->tanggal_mulai }} s.d. {{ $item->tanggal_akhir }}</option>
                @endforeach
            </select>
        </div>
        <button class="button button-primary" type="submit">Tampilkan</button>
    </form>

    @if ($period)
        <p class="graduation-validation-period-label">Periode dipilih: <strong>{{ $period->nama_periode }}</strong></p>
        <div class="graduation-validation-exports" aria-label="Unduh daftar peserta yang lolos verifikasi">
            <a class="button button-secondary" href="{{ route('graduation-validations.export.excel', $period->id_periode) }}" data-page-loader-skip><span data-lucide="sheet"></span> Download Excel</a>
            <a class="button button-secondary" href="{{ route('graduation-validations.export.pdf', $period->id_periode) }}" data-page-loader-skip><span data-lucide="file-text"></span> Download PDF</a>
            <a class="button button-secondary" href="{{ route('graduation-validations.export.ppt', $period->id_periode) }}" data-page-loader-skip><span data-lucide="presentation"></span> Download PPT</a>
        </div>
        <div class="table-wrap">
            <table @if ($rows?->isNotEmpty()) data-simple-search-table data-search-placeholder="Cari NPM, nama, prodi, atau status" @endif>
                <thead><tr><th>No.</th><th>Peserta</th><th>Fakultas / Program Studi</th><th>Kelengkapan Dokumen</th><th>Status Verifikasi</th><th>Petugas</th><th>{{ $canVerify ? 'Proses' : 'Detail' }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $rows->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $row->nim ?: '-' }}</strong><br>{{ $row->nama_mahasiswa ?: '-' }}</td>
                            <td>{{ $row->nama_fakultas ?: '-' }}<br>{{ $row->nama_program_studi ?: '-' }}</td>
                            <td><span class="graduation-validation-doc-count {{ $row->document_count === 4 ? 'is-complete' : '' }}">{{ $row->document_count }}/4 dokumen</span></td>
                            <td>
                                @if ($row->lolos === '1')<span class="graduation-validation-status is-pass">Lolos Verifikasi</span>
                                @elseif ($row->lolos === '0')<span class="graduation-validation-status is-fail">Tidak Lolos Verifikasi</span>
                                @else<span class="graduation-validation-status is-pending">Belum Verifikasi</span>@endif
                            </td>
                            <td>{{ $row->petugas ?: '-' }}</td>
                            <td><a class="button button-primary" href="{{ route('graduation-validations.show', [$period->id_periode, $row->id_peserta]) }}">{{ $canVerify ? 'Verifikasi Data' : 'Lihat Detail' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="graduation-validation-empty">Belum ada usulan peserta pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())<div class="kkn-pagination">{{ $rows->links('vendor.pagination.datatables') }}</div>@endif
    @elseif (count($periods) === 0)
        <div class="kkn-empty-state"><strong>Periode wisuda belum tersedia</strong>Belum ada periode wisuda untuk ditampilkan.</div>
    @endif
@endsection


