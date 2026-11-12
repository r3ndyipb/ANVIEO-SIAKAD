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
