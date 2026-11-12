@extends('layouts.simak')

@section('title', $title.' | SIAKAD')
@section('topbar_title', $title)

@php($canMutate = auth()->user()?->role === 'keuangan')
@php($canUploadIpi = in_array(auth()->user()?->role, ['admin', 'keuangan'], true))

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Keuangan</p>
            <h1>{{ $title }}</h1>
        </div>
        <div class="page-actions">
            @if ($module === 'periods' && $canMutate)
                <button class="button button-primary" type="button" data-dialog-open="finance-period-create"><span data-lucide="plus"></span> Tambah periode</button>
            @elseif ($module === 'categories' && $canMutate)
                <button class="button button-primary" type="button" data-dialog-open="finance-category-upload"><span data-lucide="upload"></span> Upload data uang kuliah</button>
            @elseif ($module === 'ipi' && $canUploadIpi)
                <button class="button button-primary" type="button" data-dialog-open="finance-ipi-upload"><span data-lucide="upload"></span> Upload tagihan Iuran Pengembangan Institusi</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success finance-alert" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger finance-alert" role="alert">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger finance-alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($module === 'periods')
        <div class="table-wrap">
            <table class="finance-table" data-simple-search-table>
                <thead><tr><th>Nama periode</th><th>Tahun akademik</th><th>Periode pembayaran</th><th>Status</th>@if ($canMutate)<th>Aksi</th>@endif</tr></thead>
                <tbody>
                    @forelse ($records as $period)
                        <tr>
                            <td><strong>{{ $period->nama_periode }}</strong></td>
                            <td>{{ $period->tahun }}</td>
                            <td>{{ $period->tanggal_mulai }} s.d. {{ $period->tanggal_akhir }}</td>
                            <td><span class="finance-status {{ $period->aktif === 'Y' ? 'is-paid' : '' }}">{{ $period->aktif === 'Y' ? 'Aktif' : 'Tidak aktif' }}</span></td>
                            @if ($canMutate)<td><div class="finance-table-actions">
                                <button class="table-icon-button" type="button" data-dialog-open="finance-period-edit-{{ $period->id_periode }}" title="Ubah tanggal periode" aria-label="Ubah periode {{ $period->nama_periode }}"><span data-lucide="pencil"></span></button>
                                @if ($period->aktif !== 'Y')
                                    <form method="POST" action="{{ route('finance.periods.activate', $period->id_periode) }}" data-finance-confirm data-confirm-title="Aktifkan periode uang kuliah" data-confirm-message="Periode uang kuliah yang sedang aktif akan dinonaktifkan. Lanjutkan?">
                                        @csrf
                                        <button class="table-icon-button" type="submit" title="Aktifkan periode" aria-label="Aktifkan periode {{ $period->nama_periode }}"><span data-lucide="circle-check"></span></button>
                                    </form>
                                @endif
                            </div></td>@endif
                        </tr>
                        @if ($canMutate)<dialog class="finance-dialog" id="finance-period-edit-{{ $period->id_periode }}" aria-labelledby="finance-period-edit-title-{{ $period->id_periode }}">
                            <header class="finance-dialog-head"><h2 id="finance-period-edit-title-{{ $period->id_periode }}">Ubah periode {{ $period->nama_periode }}</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
                            <form method="POST" action="{{ route('finance.periods.update', $period->id_periode) }}">
                                @csrf @method('PUT')
                                <div class="finance-dialog-body">
                                    <label>Tanggal mulai<input type="date" name="tanggal_mulai" value="{{ $period->tanggal_mulai }}" required></label>
                                    <label>Tanggal akhir<input type="date" name="tanggal_akhir" value="{{ $period->tanggal_akhir }}" required></label>
                                </div>
                                <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan perubahan</button></footer>
                            </form>
                        </dialog>@endif
                    @empty
                        <tr><td colspan="{{ $canMutate ? 5 : 4 }}" class="finance-muted">Belum ada periode pembayaran uang kuliah.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="finance-pagination">{{ $records->links('vendor.pagination.datatables') }}</div>
        @if ($canMutate)<dialog class="finance-dialog" id="finance-period-create" aria-labelledby="finance-period-create-title">
            <header class="finance-dialog-head"><h2 id="finance-period-create-title">Tambah periode uang kuliah</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <form method="POST" action="{{ route('finance.periods.store') }}">
                @csrf
                <div class="finance-dialog-body">
                    <label>Tahun akademik
                        <select name="tahun" data-searchable-select data-tom-select-dropdown-parent="wrapper" required @disabled($calendarYears->isEmpty())>
                            <option value="">{{ $calendarYears->isEmpty() ? 'Belum ada data tahun akademik' : 'Pilih tahun akademik' }}</option>
                            @foreach ($calendarYears as $year)
                                <option value="{{ $year->tahun }}">{{ $year->namatahun ?: $year->tahun }} ({{ $year->tahun }})</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($calendarYears->isEmpty())
                        <p class="finance-muted">Buat kalender akademik terlebih dahulu agar tahun akademik dapat dipilih.</p>
                    @endif
                    <label>Tanggal mulai pembayaran<input type="date" name="tanggal_mulai" value="{{ today()->toDateString() }}" required></label>
                    <label>Tanggal akhir pembayaran<input type="date" name="tanggal_akhir" value="{{ today()->toDateString() }}" required></label>
                </div>
                <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit" @disabled($calendarYears->isEmpty())>Simpan periode</button></footer>
            </form>
        </dialog>@endif
    @elseif ($module === 'categories')
        <form class="finance-filters" method="GET" action="{{ route('finance.categories.index') }}">
            <div>
                <label for="finance-category-program">Program studi</label>
                <select id="finance-category-program" name="prodi" data-searchable-select="filter">
                    <option value="">Semua program studi</option>
                    <option value="none" @selected($selectedProgram === 'none')>Mahasiswa tanpa prodi</option>
                    @foreach ($programs as $program)
                        <option value="{{ $program->id_prodi }}" @selected($selectedProgram === $program->id_prodi)>{{ $program->nama_program_studi }} ({{ $program->nama_jenjang_pendidikan }})</option>
                    @endforeach
                </select>
            </div>
            <button class="button button-primary" type="submit">Tampilkan</button>
        </form>
        <div class="module-alert finance-alert" role="note"><p>Daftar hanya menampilkan mahasiswa Aktif, Cuti, Non Aktif, dan MBKM. Unduh template Excel, isi NPM pada kolom B, kategori pada kolom C, dan nominal pada kolom D.</p></div>
        <div class="table-wrap"><table class="finance-table" data-finance-table="categories" data-ajax-url="{{ route('finance.categories.data', request()->query()) }}">
            <thead><tr><th>Angkatan</th><th>NIM</th><th>Nama mahasiswa</th><th>Program studi</th><th>Status</th><th>Kategori uang kuliah</th><th>Nominal uang kuliah</th></tr></thead>
            <tbody></tbody>
        </table></div>
        <div class="module-alert finance-alert" role="note"><p class="subtitle">Nominal tagihan adalah bagian kampus. Jika biaya layanan Midtrans diaktifkan, biaya layanan dan total pembayaran Midtrans ditampilkan terpisah; biaya tersebut tidak digabungkan ke nominal UKT/IPI.</p></div>
        @if ($canMutate)<dialog class="finance-dialog" id="finance-category-upload" aria-labelledby="finance-category-upload-title">
            <header class="finance-dialog-head"><h2 id="finance-category-upload-title">Upload kategori uang kuliah</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <form method="POST" action="{{ route('finance.categories.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="finance-dialog-body">
                    <p class="finance-muted">Unduh template, isi data mahasiswa, lalu unggah berkas Excel yang telah dilengkapi.</p>
                    <a class="button button-secondary" href="{{ route('finance.categories.template') }}" data-page-loader-skip><span data-lucide="download"></span> Unduh template Excel</a>
                    <label class="finance-dropzone" data-finance-dropzone>
                        <span data-lucide="upload" aria-hidden="true"></span>
                        <strong>Seret file Excel ke sini atau klik untuk memilih</strong>
                        <span class="finance-dropzone-name" data-finance-file-name>Belum ada file dipilih</span>
                        <input type="file" name="file" accept=".xlsx,.csv" required>
                    </label>
                </div>
                <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Unggah data</button></footer>
            </form>
        </dialog>@endif
    @elseif (in_array($module, ['ukt', 'ipi'], true))
        @if ($module === 'ukt')
            <form class="finance-filters" method="GET" action="{{ route('finance.'.$module.'.index') }}">
                <div>
                    <label for="finance-period-year">Periode pembayaran</label>
                    <select id="finance-period-year" name="tahun" data-searchable-select="filter">
                        <option value="">Pilih periode</option>
                        @foreach ($periods as $period)
                            <option value="{{ $period->tahun }}" @selected(($filters['tahun'] ?? '') === $period->tahun)>{{ $period->nama_periode }} ({{ $period->tahun }})</option>
                        @endforeach
                    </select>
                </div>
                @if (filled($filters['tahun'] ?? null))
                    <div>
                        <label for="finance-faculty-select">Fakultas</label>
                        <select id="finance-faculty-select" name="id_fakultas" data-searchable-select="filter">
                            <option value="">Semua fakultas</option>
                            @foreach ($faculties as $faculty)
                                <option value="{{ $faculty->id_fakultas }}" @selected(($filters['id_fakultas'] ?? '') === $faculty->id_fakultas)>{{ $faculty->nama_fakultas }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="button button-primary" type="submit">Tampilkan</button>
            </form>
        @endif
        @if ($module === 'ukt' && filled($filters['tahun'] ?? null))
            @if ($canMutate)<div class="finance-actions">
                <form method="POST" action="{{ route('finance.ukt.generate') }}" data-finance-confirm data-confirm-title="Buat tagihan uang kuliah" data-confirm-message="Buat tagihan untuk seluruh mahasiswa berkategori uang kuliah yang memenuhi syarat di semua fakultas pada periode ini? Tagihan lunas dan transaksi Midtrans yang masih berjalan akan dipertahankan.">
                    @csrf <input type="hidden" name="tahun" value="{{ $filters['tahun'] }}">@if (filled($filters['id_fakultas'] ?? null))<input type="hidden" name="id_fakultas" value="{{ $filters['id_fakultas'] }}">@endif
                    <button class="button button-secondary" type="submit"><span data-lucide="refresh-cw"></span> Generate tagihan semua fakultas</button>
                </form>
            </div>@endif
        @endif
        @if ($module === 'ipi' || filled($filters['tahun'] ?? null))
            <div class="finance-actions">
                @if ($module === 'ipi' || filled($filters['tahun'] ?? null))
                    <a class="button button-secondary" href="{{ route('finance.'.$module.'.export', array_merge(request()->query(), ['lunas' => 1])) }}" data-page-loader-skip><span data-lucide="download"></span> Unduh Excel lunas</a>
                    <a class="button button-secondary" href="{{ route('finance.'.$module.'.export', array_merge(request()->query(), ['lunas' => 0])) }}" data-page-loader-skip><span data-lucide="download"></span> Unduh Excel belum lunas</a>
                @endif
            </div>
            <div class="table-wrap"><table class="finance-table" data-finance-table="{{ $module }}" data-can-mutate="{{ $canMutate ? 'true' : 'false' }}" data-ajax-url="{{ route('finance.'.$module.'.data', request()->query()) }}">
                <thead><tr><th>Angkatan</th><th>Mahasiswa / NIM</th><th>Fakultas / Prodi</th>@if ($module === 'ukt')<th>Tahun</th><th>Kategori uang kuliah</th>@endif<th>Transaksi / metode pembayaran</th><th>Nominal</th><th>Status tagihan</th><th>Tanggal bayar</th><th>Status Midtrans</th>@if ($canMutate)<th>Aksi</th>@endif</tr></thead>
                <tbody></tbody>
            </table></div>
        @elseif ($module === 'ukt')
            <div class="module-alert finance-alert" role="note"><p class="subtitle">Pilih periode pembayaran untuk menampilkan tagihan uang kuliah.</p></div>
        @endif
        @if ($module === 'ipi' && $canUploadIpi)
            <dialog class="finance-dialog" id="finance-ipi-upload" aria-labelledby="finance-ipi-upload-title">
                <header class="finance-dialog-head"><h2 id="finance-ipi-upload-title">Upload data Iuran Pengembangan Institusi</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
                <form method="POST" action="{{ route('finance.ipi.upload') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="finance-dialog-body">
                        <p class="finance-muted">Unduh template Excel, isi NPM pada kolom B dan nominal pada kolom C, lalu unggah kembali. Nama mahasiswa otomatis mengikuti data master SIMAK berdasarkan NPM. Status lunas diperbarui oleh Midtrans atau dicatat manual melalui aksi pembayaran.</p>
                        <a class="button button-secondary" href="{{ route('finance.ipi.template') }}" data-page-loader-skip><span data-lucide="download"></span> Unduh template Excel</a>
                        <label class="finance-dropzone" data-finance-dropzone>
                            <span data-lucide="upload" aria-hidden="true"></span>
                            <strong>Seret file Excel ke sini atau klik untuk memilih</strong>
                            <span class="finance-dropzone-name" data-finance-file-name>Belum ada file dipilih</span>
                            <input type="file" name="file" accept=".xlsx,.csv" required>
                        </label>
                    </div>
                    <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Unggah data</button></footer>
                </form>
            </dialog>
        @endif
        @if ($canMutate)<dialog class="finance-dialog" id="finance-payment-edit" aria-labelledby="finance-payment-edit-title">
            <header class="finance-dialog-head"><h2 id="finance-payment-edit-title">Ubah nominal</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <form method="POST" action="#" data-finance-edit-form>
                @csrf @method('PUT')
                <div class="finance-dialog-body"><p class="finance-muted" data-finance-edit-student></p><label>Nominal<input type="number" min="1" max="2147483647" step="1" name="nominal" required></label></div>
                <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Simpan nominal</button></footer>
            </form>
        </dialog>@endif
        @if ($canMutate)<dialog class="finance-dialog" id="finance-payment-manual" aria-labelledby="finance-payment-manual-title">
            <header class="finance-dialog-head"><h2 id="finance-payment-manual-title">Catat pembayaran manual</h2><button class="table-icon-button" type="button" data-dialog-close aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
            <form method="POST" action="#" data-finance-manual-paid-form>
                @csrf
                <div class="finance-dialog-body">
                    <p class="finance-muted" data-finance-manual-paid-student></p>
                    <p class="finance-muted">Gunakan untuk pembayaran yang telah diverifikasi di luar Midtrans, seperti beasiswa atau bantuan pemerintah daerah. Tagihan akan langsung dicatat lunas.</p>
                    <label>Skema pembayaran<select name="method" required><option value="beasiswa">Beasiswa</option><option value="pemda">Bantuan pemerintah daerah</option><option value="manual">Pembayaran manual lainnya</option></select></label>
                    <label>Nomor referensi (opsional)<input type="text" name="reference" maxlength="100" placeholder="Contoh: nomor bukti transfer atau surat penetapan"></label>
                </div>
                <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-dialog-close>Batal</button><button class="button button-primary" type="submit">Catat lunas</button></footer>
            </form>
        </dialog>@endif
    @endif

    @if ($canMutate)<dialog class="finance-dialog" data-finance-confirm-dialog aria-labelledby="finance-confirm-title" aria-describedby="finance-confirm-message">
        <header class="finance-dialog-head"><h2 id="finance-confirm-title">Konfirmasi tindakan</h2><button class="table-icon-button" type="button" data-finance-confirm-cancel aria-label="Tutup dialog"><span data-lucide="x"></span></button></header>
        <div class="finance-dialog-body"><p id="finance-confirm-message" class="finance-muted"></p></div>
        <footer class="finance-dialog-actions"><button class="button button-secondary" type="button" data-finance-confirm-cancel>Batal</button><button class="button button-primary" type="button" data-finance-confirm-submit>Ya, lanjutkan</button></footer>
    </dialog>@endif
@endsection
