@extends('layouts.simak')

@section('title', 'KRS Mahasiswa | SIAKAD')
@section('topbar_title', 'KRS Mahasiswa')

@section('content')
    @php($isReadOnlyRole = in_array($user->role, ['admin', 'pimuniv', 'pimfak', 'usertu', 'validasi', 'wisuda', 'regulang'], true))
    <div class="page-heading">
        <div>
            <p class="eyebrow">Akademik</p>
            <h1>KRS Mahasiswa</h1>
            <div class="module-alert" role="note">
                <p class="subtitle">{{ $isReadOnlyRole ? 'Akses ini hanya dapat melihat dan mencetak KRS; pemrograman maupun pengesahan tidak tersedia.' : 'Pemrograman mata kuliah dan pengesahan KRS mengikuti registrasi ulang, masa KRS, batas SKS, serta kewenangan mahasiswa, dosen PA, dan program studi.' }}</p>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="krs-alert success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="krs-alert error" role="alert">{{ $errors->first() }}</div>
    @endif

    @if ($user->role !== 'mahasiswa' && !$isStudentForm)
        @if ($user->role !== 'dosen')
            <form class="krs-search" method="GET" action="{{ route('krs.index') }}">
                <div>
                    <label for="krs-search">Cari NPM atau nama mahasiswa</label>
                    <input id="krs-search" type="search" name="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Ketik NPM atau nama mahasiswa">
                </div>
                <div class="krs-search-actions">
                    <button class="button button-primary" type="submit"><span data-lucide="search"></span> Cari</button>
                    @if (filled($filters['search'] ?? null))
                        <a class="button" href="{{ route('krs.index') }}">Bersihkan</a>
                    @endif
                </div>
            </form>
        @endif

        @if ($user->role === 'dosen' && $students->isNotEmpty())
            <div class="krs-alert" role="note"><strong>Cari mahasiswa.</strong> Gunakan NPM atau nama untuk membuka detail KRS.</div>
        @endif

        @if ($students->isNotEmpty())
            <div class="table-wrap krs-student-list">
                <table @if ($user->role === 'dosen') data-datatable="krs-students" @else data-simple-search-table @endif>
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Program Studi</th>
                        <th>Penasehat Akademik</th>
                        @if ($user->role === 'dosen')
                            <th>Status Pengisian KRS</th>
                            <th>Registrasi Ulang</th>
                            <th>Status Pengesahan PA</th>
                        @endif
                        <th class="no-export">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($students as $listedStudent)
                        <tr>
                            <td class="krs-student-identity">{{ $listedStudent->nama_mahasiswa }}<small>{{ $listedStudent->nim }}</small></td>
                            <td>{{ $listedStudent->nama_program_studi ?: 'Program studi tidak ditemukan' }}</td>
                            <td>{{ \App\Support\LecturerName::format($listedStudent->nama_dosen_pa ?? null, $listedStudent->pa_gelar_depan ?? null, $listedStudent->pa_gelar_belakang ?? null) ?: '-' }}</td>
                            @if ($user->role === 'dosen')
                                <td>{{ $listedStudent->has_krs ? 'Telah isi KRS' : 'Belum isi KRS' }}</td>
                                <td>{{ $listedStudent->is_registered ? 'Telah registrasi' : 'Belum registrasi' }}</td>
                                <td>{{ $listedStudent->pa_approved ? 'Sudah disahkan PA' : 'Belum disahkan PA' }}</td>
                            @endif
                            <td class="krs-row-actions">
                                    <a class="table-icon-button" href="{{ route('krs.edit', ['student' => (string) $listedStudent->id_mahasiswa] + (filled($filters['search'] ?? null) ? ['search' => $filters['search']] : [])) }}" title="{{ $isReadOnlyRole ? 'Lihat KRS' : 'Isi / edit KRS' }} {{ $listedStudent->nama_mahasiswa }}" aria-label="{{ $isReadOnlyRole ? 'Lihat KRS' : 'Isi / edit KRS' }} {{ $listedStudent->nama_mahasiswa }}"><span data-lucide="{{ $isReadOnlyRole ? 'eye' : 'pencil' }}"></span></a>
                                    @if ($user->role === 'dosen' && $listedStudent->is_registered && $listedStudent->has_krs && !$listedStudent->pa_approved)
                                        <form id="krs-approve-pa-{{ $listedStudent->id_mahasiswa }}" method="POST" action="{{ route('krs.approve-pa', $listedStudent->id_mahasiswa) }}">
                                            @csrf
                                            <button class="table-icon-button" type="button" data-krs-confirm-open data-krs-form="krs-approve-pa-{{ $listedStudent->id_mahasiswa }}" data-krs-title="Sahkan KRS sebagai PA" data-krs-message="Sahkan KRS {{ $listedStudent->nama_mahasiswa }} sebagai dosen PA?" aria-label="Sahkan KRS {{ $listedStudent->nama_mahasiswa }}" title="Sahkan KRS"><span data-lucide="circle-check"></span></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($user->role !== 'dosen')
                <div class="krs-pagination">{{ $students->links('vendor.pagination.datatables') }}</div>
            @endif
        @elseif (filled($filters['search'] ?? null))
            <div class="master-empty-state"><strong>Mahasiswa tidak ditemukan</strong>Tidak ada mahasiswa yang cocok dengan pencarian dan cakupan akun ini.</div>
        @endif
    @endif

    @if ($student)
        <section class="panel">
            @if ($isStudentForm)
                <div class="krs-actions" style="margin-bottom:14px">
                    <a class="button" href="{{ route('krs.index', array_filter(['search' => $filters['search'] ?? null])) }}"><span data-lucide="arrow-left"></span> Kembali ke Daftar Mahasiswa</a>
                </div>
            @endif
            <div class="page-heading">
                <div>
                    <h2>{{ $student->nama_mahasiswa }}</h2>
                    <p class="subtitle">{{ $student->nim }} · {{ $student->nama_program_studi ?: 'Program studi tidak ditemukan' }}</p>
                </div>
            </div>

            @if ($calendars->isNotEmpty())
                <form class="krs-search" method="GET" action="{{ $user->role === 'mahasiswa' ? route('krs.index') : route('krs.edit', $student->id_mahasiswa) }}">
                    @if (filled($filters['search'] ?? null))
                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                    @endif
                    <div>
                        <label for="krs-calendar">Pilih tahun akademik</label>
                        <select id="krs-calendar" name="calendar" data-searchable-select="filter" required>
                            @foreach ($calendars as $calendarOption)
                                <option value="{{ $calendarOption->id_kalender }}" @selected((string) ($calendar?->id_kalender ?? '') === (string) $calendarOption->id_kalender)>
                                    {{ $calendarOption->namatahun ?: $calendarOption->tahun }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button class="button button-primary" type="submit"><span data-lucide="search"></span> Tampilkan KRS</button>
                </form>
            @endif

            @if (!$calendar)
                <div class="krs-alert error" role="alert">Belum tersedia kalender akademik aktif pada program studi mahasiswa.</div>
            @else
                <div class="krs-summary">
                    <div class="krs-summary-card"><small>Tahun Akademik</small><strong>{{ $calendar->namatahun ?: $calendar->tahun }}</strong></div>
                    <div class="krs-summary-card"><small>Registrasi Ulang</small><strong>{{ $registered ? 'Sudah registrasi' : 'Belum registrasi' }}</strong></div>
                    <div class="krs-summary-card"><small>SKS Diprogram</small><strong>{{ number_format($creditsTaken, 2) }} / {{ $maxCredits }} SKS</strong></div>
                    <div class="krs-summary-card"><small>Status Pengesahan</small><strong>{{ filled($approval?->nama_kpro) ? 'Disahkan prodi' : (filled($approval?->yang_mengesahkan) ? 'Menunggu prodi' : 'Belum disahkan PA') }}</strong></div>
                    <div class="krs-summary-card"><small>IPS Terakhir</small><strong>{{ $lastActiveIps === null ? 'Belum ada riwayat KRS' : number_format($lastActiveIps, 2) }}</strong>@if ($lastActiveTerm)<small>{{ $lastActiveTerm->namatahun ?: $lastActiveTerm->tahun }}</small>@endif</div>
                </div>

                <div class="krs-status">
                    <span class="status-chip {{ filled($approval?->yang_mengesahkan) ? 'active' : '' }}">PA: {{ filled($approval?->yang_mengesahkan) ? 'Disahkan oleh '.$approval->yang_mengesahkan : 'Belum disahkan' }}</span>
                    <span class="status-chip {{ filled($approval?->nama_kpro) ? 'active' : '' }}">Prodi: {{ filled($approval?->nama_kpro) ? 'Disahkan oleh '.$approval->nama_kpro : 'Belum disahkan' }}</span>
                </div>

                @if ($isStudentForm && $user->role === 'userprodi')
                    <div class="krs-bulk-approval" role="note">
                        <p>Pengesahan KRS Oleh Ketua Prodi Dapat Dilakukan Secara Bulk (Sahkan Semua KRS Yang Telah Disahkan Oleh Dosen PA),, Untuk Melakukan Pengesahan Semua Dapat Mengklik Tombol Berikut, Mohon Digunaakn Secara Bijak..!!</p>
                        <form id="krs-approve-all-form" method="POST" action="{{ route('krs.approve-program-all') }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="id_kalender" value="{{ $calendar->id_kalender }}">
                            <button class="button button-primary" type="button" data-krs-confirm-open data-krs-form="krs-approve-all-form" data-krs-title="Konfirmasi Pengesahan KRS Massal Prodi" data-krs-message="Pengesahan KRS Oleh Ketua Prodi Dapat Dilakukan Secara Bulk (Sahkan Semua KRS Yang Telah Disahkan Oleh Dosen PA),, Untuk Melakukan Pengesahan Semua Dapat Mengklik Tombol Berikut, Mohon Digunaakn Secara Bijak..!!"><span data-lucide="list-checks"></span> Sahkan Semua KRS Prodi</button>
                        </form>
                    </div>
                @endif

                @if ($canResetApproval)
                    <div class="krs-actions" style="margin:14px 0">
                        <form id="krs-reset-approval-form" class="krs-approval-form" method="POST" action="{{ route('krs.reset-approval', $student->id_mahasiswa) }}">@csrf
                            <button class="button" type="button" data-krs-confirm-open data-krs-form="krs-reset-approval-form" data-krs-title="Reset Pengesahan Dosen PA" data-krs-message="Reset hanya pengesahan dosen PA untuk {{ $student->nama_mahasiswa }}? Data KRS dan pengesahan program studi tetap dipertahankan."><span data-lucide="rotate-ccw"></span> Reset Pengesahan Dosen PA</button>
                        </form>
                    </div>
                @endif

                @if (!$registered)
                    <div class="krs-alert error" role="alert">Mahasiswa belum melakukan registrasi ulang pada {{ $calendar->namatahun ?: $calendar->tahun }}. Pemrograman KRS belum dapat dilakukan.</div>
                @elseif ($user->role === 'mahasiswa' && !$krsWindowOpen)
                    <div class="krs-alert" role="note">Masa pengisian KRS adalah {{ $calendar->tgl_mulai_krs ?: 'belum ditetapkan' }} s.d. {{ $calendar->tgl_akhir_krs ?: 'belum ditetapkan' }}. Perubahan KRS saat ini tidak tersedia.</div>
                @endif

                @if ($canApprovePa)
                    @if ($user->role === 'userprodi')
                        <div class="krs-alert" role="note">Untuk ambil alih pengesahan PA, klik tombol Sahkan PA.</div>
                    @endif
                @endif

                @if ($canApprovePa || $canApproveProgram)
                    <div class="krs-actions" style="margin:14px 0">
                        @if ($canApprovePa)
                            <form id="krs-approve-pa-form" class="krs-approval-form" method="POST" action="{{ route('krs.approve-pa', $student->id_mahasiswa) }}">@csrf
                                @if ($user->role === 'dosen')
                                    <button class="button button-primary" type="button" data-krs-confirm-open data-krs-form="krs-approve-pa-form" data-krs-title="Sahkan KRS sebagai PA" data-krs-message="Periksa seluruh mata kuliah KRS {{ $student->nama_mahasiswa }} sebelum mengesahkannya sebagai dosen PA."><span data-lucide="circle-check"></span> Sahkan Dosen PA</button>
                                @else
                                    <button class="button button-primary" type="button" data-krs-confirm-open data-krs-form="krs-approve-pa-form" data-krs-title="Ambil Alih Pengesahan PA" data-krs-message="Ambil alih pengesahan PA untuk KRS {{ $student->nama_mahasiswa }}?"><span data-lucide="circle-check"></span> Sahkan PA</button>
                                @endif
                            </form>
                        @endif
                        @if ($canApproveProgram)
                            <form id="krs-approve-program-form" class="krs-approval-form" method="POST" action="{{ route('krs.approve-program', $student->id_mahasiswa) }}">@csrf
                                <button class="button button-primary" type="button" data-krs-confirm-open data-krs-form="krs-approve-program-form" data-krs-title="Sahkan KRS Program Studi" data-krs-message="Sahkan KRS {{ $student->nama_mahasiswa }} atas nama program studi?"><span data-lucide="circle-check"></span> Sahkan Prodi</button>
                            </form>
                        @endif
                    </div>
                @endif

                @if ($krs->isNotEmpty())
                    <div class="krs-actions" style="justify-content:space-between">
                        <h3 class="krs-section-title">Mata Kuliah Terprogram</h3>
                        @if (filled($approval?->yang_mengesahkan) && filled($approval?->nama_kpro))
                            <a class="button button-primary" href="{{ route('krs.print', ['student' => $student->id_mahasiswa, 'calendar' => $calendar->id_kalender]) }}" target="_blank" rel="noopener"><span data-lucide="file-down"></span> Cetak KRS</a>
                        @endif
                    </div>
                    <div class="table-wrap">
                        <table data-simple-search-table>
                            <thead><tr><th>Kode MK</th><th>Mata Kuliah</th><th>B/U</th><th>Kelas</th><th>Jadwal</th><th>SKS</th></tr></thead>
                            <tbody>
                                @foreach ($krs as $row)
                                    <tr>
                                        <td>{{ $row->kode_mata_kuliah ?: '-' }}</td>
                                        <td>{{ $row->nama_mata_kuliah ?: 'Mata kuliah tidak ditemukan' }}<small class="subtext">Semester {{ $row->semester ?: '-' }}</small></td>
                                        <td>{{ $row->status_krs ?: '-' }}</td>
                                        <td>{{ $row->nama_kelas ?: '-' }}</td>
                                        <td>{{ ucfirst(strtolower((string) $row->hari)) }} · {{ substr((string) $row->jammulai, 0, 5) }}–{{ substr((string) $row->jamselesai, 0, 5) }}</td>
                                        <td>{{ number_format((float) $row->sks_mata_kuliah, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="master-empty-state"><strong>Belum ada mata kuliah diprogram</strong>Daftar KRS akan tampil setelah mata kuliah dipilih.</div>
                @endif

                @if ($canEdit)
                    <form id="krs-program-form" class="krs-program-form" method="POST" action="{{ route('krs.save', array_filter(['student' => $student->id_mahasiswa, 'search' => $filters['search'] ?? null])) }}">
                        @csrf
                        <h2>{{ $krs->isEmpty() ? 'Program KRS' : 'Ubah Pilihan KRS' }}</h2>
                        <div class="krs-alert" role="note">Pilih jadwal mata kuliah pada program studi dan kalender aktif. Batas pemrograman saat ini {{ $maxCredits }} SKS. @if ($user->role === 'userprodi') Perubahan oleh prodi tidak mengubah status pengesahan PA maupun prodi. @else Menyimpan pilihan baru mengganti daftar mata kuliah sebelumnya dan menghapus pengesahan yang sudah tercatat. @endif</div>
                        @if ($offerings->isEmpty())
                            <div class="master-empty-state"><strong>Jadwal belum tersedia</strong>Belum ada jadwal mata kuliah pada kalender akademik aktif.</div>
                        @else
                            @if (in_array($user->role, ['mahasiswa', 'dosen'], true) && $packageClasses->isNotEmpty())
                                <div class="krs-actions" style="margin:0 0 12px">
                                    <button class="button" type="button" data-krs-package-open><span data-lucide="list-checks"></span> Program KRS Paket</button>
                                </div>
                            @endif
                            <div class="krs-alert" role="status">
                                SKS dipilih: <strong><span data-krs-credit-total>{{ number_format($creditsTaken, 2) }}</span> / <span data-krs-credit-limit>{{ $maxCredits }}</span> SKS</strong>
                                <div class="krs-alert error" data-krs-credit-warning role="alert" hidden></div>
                            </div>
                            <div class="table-wrap">
                                <table class="krs-offering-table" data-datatable="krs-offerings">
                                    <thead><tr><th>Semester</th><th>Kode MK</th><th>Mata Kuliah</th><th>Hari / Jam</th><th>Kelas / Ruang</th><th>SKS</th><th>Pilih</th></tr></thead>
                                    <tbody>
                                        @foreach ($offerings as $offering)
                                            <tr @class(['krs-selected-row' => $krs->contains('id_jadwal', $offering->id_jadwal)])>
                                                <td class="krs-semester-cell">{{ $offering->semester ?: '-' }}</td>
                                                <td>{{ $offering->kode_mata_kuliah }}</td>
                                                <td>{{ $offering->nama_mata_kuliah }}</td>
                                                <td>{{ ucfirst(strtolower((string) $offering->hari)) }}<small class="subtext">{{ substr((string) $offering->jammulai, 0, 5) }}–{{ substr((string) $offering->jamselesai, 0, 5) }}</small></td>
                                                <td>{{ $offering->nama_kelas ?: '-' }}<small class="subtext">{{ $offering->nama_ruang ?: '-' }}</small></td>
                                                <td>{{ number_format((float) $offering->sks_mata_kuliah, 2) }}</td>
                                                <td class="krs-select-cell">
                                                    @if ($offering->is_full && !$krs->contains('id_jadwal', $offering->id_jadwal))
                                                        <span class="status-chip">Terisi Penuh ({{ $offering->enrolled_count }}/{{ $offering->kapasitas ?: 0 }})</span>
                                                    @else
                                                        <span class="status-chip active">{{ $offering->is_full ? 'Kelas Anda' : 'Tersedia' }} ({{ $offering->enrolled_count }}/{{ $offering->kapasitas ?: 0 }})</span>
                                                    @endif
                                                    <input type="checkbox" value="{{ $offering->id_jadwal }}" data-krs-schedule-checkbox data-course-id="{{ $offering->id_matkul }}" data-capacity-full="{{ $offering->is_full && !$krs->contains('id_jadwal', $offering->id_jadwal) ? 'true' : 'false' }}" data-sks="{{ (float) $offering->sks_mata_kuliah }}" @checked($krs->contains('id_jadwal', $offering->id_jadwal)) @disabled($offering->is_full && !$krs->contains('id_jadwal', $offering->id_jadwal)) aria-label="Pilih {{ $offering->nama_mata_kuliah }} kelas {{ $offering->nama_kelas }}">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="krs-actions">
                                <button class="button button-primary" type="button" data-krs-program-submit data-krs-confirm-open data-krs-form="krs-program-form" data-krs-title="Simpan KRS" data-krs-message="Simpan pilihan mata kuliah untuk {{ $student->nama_mahasiswa }}? Pilihan KRS lama akan diganti."><span data-lucide="save"></span> Simpan KRS</button>
                            </div>
                        @endif
                    </form>

                    @if (in_array($user->role, ['mahasiswa', 'dosen'], true) && $packageClasses->isNotEmpty())
                        <div class="calendar-modal-backdrop krs-modal-backdrop" data-krs-package-modal hidden>
                            <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="krs-package-title" tabindex="-1">
                                <div class="calendar-modal-head">
                                    <div>
                                        <h2 id="krs-package-title">Program KRS Paket</h2>
                                        <p class="krs-modal-copy">Pilih kelas paket semester {{ $packageSemester }}. KRS aktif akan diganti dengan semua jadwal kelas paket yang tersedia.</p>
                                    </div>
                                    <button class="calendar-modal-close" type="button" data-krs-package-close aria-label="Tutup dialog"><span data-lucide="x"></span></button>
                                </div>
                                <form id="krs-package-form" method="POST" action="{{ route('krs.package', $student->id_mahasiswa) }}">
                                    @csrf
                                    <label for="krs-package-class">Kelas paket</label>
                                    <select id="krs-package-class" name="kelas" data-searchable-select required>
                                        <option value="">Pilih kelas paket</option>
                                        @foreach ($packageClasses as $packageClass)
                                            <option value="{{ $packageClass->nama_kelas }}" @disabled($packageClass->is_full)>
                                                Kelas {{ $packageClass->nama_kelas }}{{ $packageClass->is_full ? ' (terisi penuh)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="krs-modal-actions">
                                        <button class="button" type="button" data-krs-package-close>Batal</button>
                                        <button class="button button-primary" type="button" data-krs-confirm-open data-krs-form="krs-package-form" data-krs-title="Program KRS Paket" data-krs-message="Programkan seluruh mata kuliah untuk kelas paket yang dipilih? KRS lama akan diganti."><span data-lucide="list-checks"></span> Program Paket</button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    @endif
                @endif

            @endif
        </section>
    @elseif ($user->role === 'mahasiswa')
        <div class="master-empty-state"><strong>Data mahasiswa tidak ditemukan</strong>Data akun mahasiswa tidak cocok dengan data akademik.</div>
    @elseif ($user->role === 'dosen' && $students->isEmpty())
        <div class="master-empty-state"><strong>Belum ada mahasiswa PA</strong>Daftar akan menampilkan mahasiswa aktif yang dosen PA-nya sesuai ID dosen akun ini.</div>
    @elseif ($user->role !== 'dosen' && blank($filters['search'] ?? null))
        <div class="master-empty-state"><strong>Cari mahasiswa</strong>Gunakan NPM atau nama untuk membuka detail KRS.</div>
    @endif

    <div class="calendar-modal-backdrop krs-modal-backdrop" data-krs-confirm-modal hidden>
        <section class="calendar-modal" role="dialog" aria-modal="true" aria-labelledby="krs-confirm-title" aria-describedby="krs-confirm-message" tabindex="-1">
            <div class="calendar-modal-head">
                <div>
                    <h2 id="krs-confirm-title" data-krs-confirm-title>Konfirmasi KRS</h2>
                    <p class="krs-modal-copy" id="krs-confirm-message" data-krs-confirm-message></p>
                </div>
                <button class="calendar-modal-close" type="button" data-krs-confirm-close aria-label="Tutup dialog"><span data-lucide="x"></span></button>
            </div>
            <div class="krs-modal-actions">
                <button class="button" type="button" data-krs-confirm-cancel>Batal</button>
                <button class="button button-primary" type="button" data-krs-confirm-submit>Ya, lanjutkan</button>
            </div>
        </section>
    </div>
@endsection
