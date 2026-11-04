@extends('layouts.simak')

@section('title', 'Absensi Pertemuan '.$meeting->pertemuan_ke.' | LMS Virtual Class')
@section('topbar_title', 'LMS Virtual Class')

@section('content')
    @include('lms._notifications')
    <div class="lms-page lms-attendance-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">LMS Virtual Class · Absensi</p>
                <h1>Pertemuan {{ $meeting->pertemuan_ke }} — {{ $meeting->judul }}</h1>
                <p class="subtitle">{{ $meeting->kode_mata_kuliah }} — {{ $meeting->nama_mata_kuliah }} · {{ $meeting->nama_kelas }} · {{ $meeting->tanggal_kuliah }} · {{ substr($meeting->waktu_mulai, 0, 5) }}–{{ substr($meeting->waktu_selesai, 0, 5) }} WIT</p>
            </div>
            <a class="button button-secondary" href="{{ route('lms.meetings.index') }}">Kembali ke Pertemuan Kuliah</a>
        </div>
        <section class="lms-panel">
            <p class="subtitle">Centang mahasiswa untuk mencatat kehadiran. Hilangkan centang untuk membatalkan absensi; perubahan disimpan otomatis.</p>
            @include('lms._attendance_roster')
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .lms-attendance-page .subtitle{color:#64748b}
        .lms-attendance-page .lms-panel{display:grid;gap:1rem;padding:1.25rem;background:#fff;border:1px solid #dbe3ee;border-radius:14px}
        .lms-attendance-toggle{position:relative;display:inline-flex;width:42px;height:24px;align-items:center;cursor:pointer}
        .lms-attendance-toggle input{position:absolute;width:1px;height:1px;opacity:0}
        .lms-attendance-slider{position:absolute;inset:0;border-radius:999px;background:#cbd5e1;transition:background .18s ease}
        .lms-attendance-slider::before{position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;content:"";box-shadow:0 1px 3px #0003;transition:transform .18s ease}
        .lms-attendance-toggle input:checked+.lms-attendance-slider{background:#27804a}
        .lms-attendance-toggle input:checked+.lms-attendance-slider::before{transform:translateX(18px)}
        .lms-attendance-toggle input:focus-visible+.lms-attendance-slider{outline:2px solid #2563eb;outline-offset:2px}
        .lms-attendance-toggle input:disabled+.lms-attendance-slider{opacity:.6;cursor:wait}
        .lms-attendance-page table.dataTable td:last-child{text-align:center}
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-lms-attendance-toggle]').forEach((toggle) => {
            toggle.addEventListener('change', async () => {
                const checked = toggle.checked;
                const statusCell = toggle.closest('tr').querySelector('[data-attendance-status]');
                let notificationText = 'Perubahan absensi gagal disimpan. Periksa koneksi Anda, lalu coba lagi.';
                toggle.disabled = true;
                try {
                    const response = await fetch(checked ? toggle.dataset.markUrl : toggle.dataset.cancelUrl, {
                        method: checked ? 'PUT' : 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: checked ? JSON.stringify({status_kehadiran: 'hadir'}) : undefined,
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const validationMessage = Object.values(payload.errors || {}).flat()[0];
                        const statusMessages = {
                            401: 'Sesi Anda telah berakhir. Silakan masuk kembali.',
                            403: 'Anda tidak memiliki akses untuk mengubah absensi ini.',
                            404: 'Data absensi tidak ditemukan.',
                            419: 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.',
                            429: 'Terlalu banyak permintaan. Silakan coba kembali beberapa saat lagi.',
                        };
                        notificationText = response.status >= 500
                            ? 'Absensi gagal disimpan karena terjadi gangguan pada server.'
                            : statusMessages[response.status]
                                || (response.status === 422 && validationMessage)
                                || 'Perubahan absensi gagal disimpan.';
                        throw new Error(notificationText);
                    }
                    statusCell.textContent = checked ? 'Hadir' : 'Belum absen';
                    toggle.setAttribute('aria-label', `${checked ? 'Batalkan absensi' : 'Tandai hadir'} ${toggle.dataset.studentName}`);
                    window.simakLmsNotify?.({icon: 'success', title: 'Absensi diperbarui', text: payload.message || 'Perubahan absensi berhasil disimpan.'});
                } catch {
                    toggle.checked = !checked;
                    window.simakLmsNotify?.({icon: 'error', title: 'Absensi gagal diperbarui', text: notificationText});
                } finally {
                    toggle.disabled = false;
                }
            });
        });
    </script>
@endpush
