<div class="lms-exam-page" data-student-exam-page>
    <header class="exam-page-heading">
        <div>
            <p class="eyebrow">LMS VIRTUAL CLASS · {{ $exam->jenis_ujian === 'essay' ? 'UJIAN ESSAY' : 'UJIAN PILIHAN GANDA' }}</p>
            <h1>{{ $exam->judul }}</h1>
            <p class="exam-course-line">{{ $exam->kode_mata_kuliah }} — {{ $exam->nama_mata_kuliah }} <span>{{ $exam->nama_kelas }} · Pertemuan {{ $exam->pertemuan_ke }}</span></p>
        </div>
        <a class="button button-secondary" href="{{ route('lms.meetings.index') }}">Kembali ke pertemuan</a>
    </header>

    <section class="exam-overview" aria-label="Detail ujian">
        <div class="exam-overview-heading">
            <span class="exam-state-mark"><span class="exam-state-dot"></span>{{ $exam->status_ujian === 'terbit' ? 'Terjadwal' : ucfirst($exam->status_ujian) }}</span>
            <p>{{ $exam->petunjuk ?: 'Baca setiap soal dengan teliti sebelum memilih atau menulis jawaban.' }}</p>
        </div>
        <dl class="exam-detail-grid">
            <div><dt>Jumlah soal</dt><dd>{{ $exam->jumlah_soal_disajikan }} <small>soal</small></dd></div>
            <div><dt>Durasi</dt><dd>{{ $exam->durasi_menit }} <small>menit</small></dd></div>
            <div><dt>Mulai</dt><dd>{{ \Illuminate\Support\Carbon::parse($exam->batas_mulai, 'Asia/Jayapura')->format('d/m/Y H:i') }} <small>WIT</small></dd></div>
            <div><dt>Batas selesai</dt><dd>{{ \Illuminate\Support\Carbon::parse($exam->batas_selesai, 'Asia/Jayapura')->format('d/m/Y H:i') }} <small>WIT</small></dd></div>
        </dl>
    </section>

    @if ($result === null)
        <section class="exam-state-panel exam-ready-panel">
            <div class="exam-state-illustration" aria-hidden="true"><span data-lucide="clipboard-check"></span></div>
            <div class="exam-state-copy">
                @if ($examCanStart)
                    <p class="exam-state-kicker">SIAP DIKERJAKAN</p>
                    <h2>Pastikan Anda siap sebelum mulai</h2>
                    <p>Waktu pengerjaan dimulai setelah ujian dibuka. Ujian hanya dapat dikumpulkan satu kali.</p>
                @elseif ($exam->status_ujian !== 'terbit')
                    <p class="exam-state-kicker">BELUM TERSEDIA</p>
                    <h2>Ujian belum diterbitkan</h2>
                    <p>Ujian akan dapat dikerjakan setelah dosen menerbitkannya.</p>
                @elseif (now('Asia/Jayapura')->lt(\Illuminate\Support\Carbon::parse($exam->batas_mulai, 'Asia/Jayapura')))
                    <p class="exam-state-kicker">SEGERA DIBUKA</p>
                    <h2>Ujian belum dimulai</h2>
                    <p>Ujian dibuka pada {{ \Illuminate\Support\Carbon::parse($exam->batas_mulai, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT.</p>
                @else
                    <p class="exam-state-kicker">WAKTU BERAKHIR</p>
                    <h2>Ujian telah ditutup</h2>
                    <p>Batas akhir ujian adalah {{ \Illuminate\Support\Carbon::parse($exam->batas_selesai, 'Asia/Jayapura')->format('d/m/Y H:i') }} WIT.</p>
                @endif
            </div>
            <button class="button button-primary exam-start-button" type="button" data-lms-open="lms-start-modal" @disabled(! $examCanStart)>
                <span data-lucide="clipboard-check" aria-hidden="true"></span><span>Mulai ujian</span>
            </button>
        </section>
    @elseif ($result->status_hasil === 'berlangsung')
        <form id="lms-exam-answer-form" method="post" action="{{ route('lms.exams.submit', $exam->id_ujian) }}" class="exam-active-workspace" data-exam-answer-form data-save-url="{{ route('lms.exams.answers', $exam->id_ujian) }}" data-deadline-ms="{{ $deadline->getTimestamp() * 1000 }}" data-server-now-ms="{{ $serverNowAt }}" data-storage-key="simak:lms-exam:{{ $result->id_hasil_ujian }}">
            @csrf
            <div class="exam-live-toolbar">
                <div class="exam-live-copy">
                    <span class="exam-live-pill"><span class="exam-state-dot"></span>Ujian berlangsung</span>
                    <p data-exam-save-status role="status" aria-live="polite">Jawaban tersimpan otomatis.</p>
                </div>
                <div class="exam-countdown" role="timer" aria-live="off" aria-label="Sisa waktu ujian">
                    <span>SISA WAKTU</span><strong data-exam-countdown>--:--:--</strong>
                </div>
            </div>
            <div class="exam-connectivity-row">
                <span class="exam-connection-indicator" data-exam-connection><span></span><span data-exam-connection-label>Online</span></span>
                <span data-exam-progress>0 dari {{ $details->count() }} soal dijawab</span>
            </div>
            <div class="exam-progress-track" role="progressbar" aria-label="Progress menjawab soal" aria-valuemin="0" aria-valuemax="{{ $details->count() }}" aria-valuenow="0" data-exam-progress-bar><span data-exam-progress-fill></span></div>

            <div class="exam-question-list">
                @foreach ($details as $detail)
                    <fieldset class="exam-question-block" data-exam-question>
                        <legend><span>SOAL {{ str_pad((string) $detail->urutan_tampil, 2, '0', STR_PAD_LEFT) }}</span><small>{{ $detail->snapshot['jenis_soal'] === 'essay' ? 'Essay' : 'Pilihan ganda' }}</small></legend>
                        <div class="exam-question-prompt">{!! nl2br(e($detail->snapshot['pertanyaan'])) !!}</div>
                        @if ($detail->snapshot['jenis_soal'] === 'pilihan_ganda')
                            <div class="exam-answer-options">
                                @foreach ($detail->snapshot['opsi'] as $key => $option)
                                    @if ($option !== null)
                                        <label class="exam-answer-option"><input type="radio" name="jawaban[{{ $detail->id_detail_hasil }}]" value="{{ $key }}" data-exam-answer @checked($detail->jawaban_mahasiswa === $key)><span class="exam-option-key">{{ $key }}</span><span class="exam-option-text">{{ $option }}</span></label>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <label class="exam-essay-answer"><span>Jawaban Anda</span><textarea name="jawaban[{{ $detail->id_detail_hasil }}]" rows="6" maxlength="10000" data-exam-answer placeholder="Tulis jawaban Anda di sini...">{{ $detail->jawaban_mahasiswa }}</textarea></label>
                        @endif
                    </fieldset>
                @endforeach
            </div>

            <footer class="exam-submit-bar">
                <p>Pastikan semua jawaban sudah tersimpan sebelum mengumpulkan ujian.</p>
                <button class="button button-primary exam-submit-button" type="button" data-exam-open-submit><span>Kumpulkan ujian</span><span data-lucide="circle-check" aria-hidden="true"></span></button>
            </footer>
        </form>

        <dialog class="exam-confirm-dialog" id="lms-submit-confirmation" aria-labelledby="exam-submit-title">
            <div class="exam-confirm-icon" aria-hidden="true"><span data-lucide="circle-check"></span></div>
            <h2 id="exam-submit-title">Kumpulkan ujian?</h2>
            <p>Jawaban akan dikunci dan tidak dapat diubah setelah dikumpulkan.</p>
            <div class="exam-confirm-actions">
                <button class="button button-secondary" type="button" data-close-submit>Batal</button>
                <button class="button button-primary" type="button" data-exam-final-submit>Kumpulkan sekarang</button>
            </div>
        </dialog>
    @else
        <section class="exam-state-panel exam-complete-panel">
            <div class="exam-state-illustration" aria-hidden="true"><span data-lucide="circle-check"></span></div>
            <div class="exam-state-copy">
                <p class="exam-state-kicker">UJIAN SELESAI</p>
                <h2>Jawaban berhasil dikumpulkan</h2>
                <p>Dikumpulkan pada {{ $result->dikumpulkan_pada ? \Illuminate\Support\Carbon::parse($result->dikumpulkan_pada, 'Asia/Jayapura')->format('d/m/Y H:i') : '—' }} WIT.</p>
            </div>
            @if ($exam->jenis_ujian === 'essay' && $result->status_hasil === 'terkumpul')
                <div class="exam-result-score exam-result-pending"><span>HASIL UJIAN</span><strong>Sedang diperiksa oleh dosen</strong></div>
            @elseif ($result->nilai_akhir !== null)
                <div class="exam-result-score"><span>NILAI AKHIR</span><strong>{{ number_format((float) $result->nilai_akhir, 2, ',', '.') }}</strong></div>
            @endif
        </section>
        <section class="exam-review" aria-labelledby="exam-review-title">
            <div class="exam-review-heading">
                <div><p class="exam-state-kicker">TINJAU JAWABAN</p><h2 id="exam-review-title">Hasil kerja Anda</h2></div>
                <p>Jawaban ditampilkan tanpa penanda benar atau salah dan tanpa nilai per soal.</p>
            </div>
            <div class="exam-question-list">
                @foreach ($details as $detail)
                    <section class="exam-question-block">
                        <h3><span>SOAL {{ str_pad((string) $detail->urutan_tampil, 2, '0', STR_PAD_LEFT) }}</span><small>{{ $detail->snapshot['jenis_soal'] === 'essay' ? 'Essay' : 'Pilihan ganda' }}</small></h3>
                        <div class="exam-question-prompt">{!! nl2br(e($detail->snapshot['pertanyaan'])) !!}</div>
                        <div class="exam-review-answer">
                            <strong>Jawaban Anda</strong>
                            @if (blank($detail->jawaban_mahasiswa))
                                <p class="exam-review-unanswered">Tidak dijawab</p>
                            @elseif ($detail->snapshot['jenis_soal'] === 'pilihan_ganda')
                                <p>{{ $detail->jawaban_mahasiswa }}{{ isset($detail->snapshot['opsi'][$detail->jawaban_mahasiswa]) ? ' — '.$detail->snapshot['opsi'][$detail->jawaban_mahasiswa] : '' }}</p>
                            @else
                                <p>{!! nl2br(e($detail->jawaban_mahasiswa)) !!}</p>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </section>
        @if ($result->komentar_dosen)<section class="exam-feedback"><span>CATATAN DOSEN</span><p>{{ $result->komentar_dosen }}</p></section>@endif
    @endif
</div>

@if ($result === null)
    <dialog class="exam-confirm-dialog" id="lms-start-modal" aria-labelledby="exam-start-title">
        <div class="exam-confirm-icon" aria-hidden="true"><span data-lucide="clipboard-check"></span></div>
        <h2 id="exam-start-title">Mulai ujian sekarang?</h2>
        <p>Waktu {{ $exam->durasi_menit }} menit langsung berjalan dan ujian hanya dapat dikerjakan satu kali. Pastikan koneksi Anda stabil.</p>
        <div class="exam-confirm-actions">
            <button class="button button-secondary" type="button" data-lms-close>Batal</button>
            <form method="post" action="{{ route('lms.exams.start', $exam->id_ujian) }}">@csrf<button class="button button-primary" type="submit">Mulai sekarang</button></form>
        </div>
    </dialog>
@endif



@push('scripts')
<script>
    document.querySelectorAll('[data-lms-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.lmsOpen)?.showModal()));
    document.querySelectorAll('[data-lms-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    const examForm = document.querySelector('[data-exam-answer-form]');
    if (examForm) {
        const saveStatus = document.querySelector('[data-exam-save-status]');
        const connectionIndicator = document.querySelector('[data-exam-connection]');
        const connectionLabel = document.querySelector('[data-exam-connection-label]');
        const countdown = document.querySelector('[data-exam-countdown]');
        const countdownPanel = countdown.closest('.exam-countdown');
        const progressLabel = document.querySelector('[data-exam-progress]');
        const progressBar = document.querySelector('[data-exam-progress-bar]');
        const progressFill = document.querySelector('[data-exam-progress-fill]');
        const submitDialog = document.getElementById('lms-submit-confirmation');
        const storageKey = examForm.dataset.storageKey;
        const deadlineAt = Number(examForm.dataset.deadlineMs);
        const serverNowAt = Number(examForm.dataset.serverNowMs);
        const monotonicStart = performance.now();
        const answerFields = [...examForm.querySelectorAll('[data-exam-answer]')];
        let editVersion = 0;
        let saveTimer;
        let retryTimer;
        let clockTimer;
        let saveInProgress = false;
        let saveQueued = false;
        let locked = false;
        let submitting = false;

        const setSaveStatus = (message, state = 'saved') => {
            saveStatus.textContent = message;
            saveStatus.dataset.saveState = state;
        };
        const answerDetailId = (name) => name.match(/^jawaban\[([^\]]+)\]$/)?.[1];
        const readAnswers = () => {
            const answers = {};
            new FormData(examForm).forEach((value, name) => {
                const detailId = answerDetailId(name);
                if (detailId) answers[detailId] = value;
            });
            return answers;
        };
        const persistDraft = () => {
            try {
                localStorage.setItem(storageKey, JSON.stringify({answers: readAnswers(), savedAt: Date.now()}));
                return true;
            } catch {
                return false;
            }
        };
        const restoreDraft = () => {
            try {
                const draft = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (!draft?.answers || typeof draft.answers !== 'object') return false;
                answerFields.forEach((field) => {
                    const detailId = answerDetailId(field.name);
                    const answer = detailId ? draft.answers[detailId] : undefined;
                    if (answer === undefined) return;
                    if (field.type === 'radio') field.checked = field.value === answer;
                    else field.value = answer;
                });
                return Object.keys(draft.answers).length > 0;
            } catch {
                return false;
            }
        };
        const updateProgress = () => {
            const total = Number(progressBar.getAttribute('aria-valuemax')) || 0;
            const answered = new Set(answerFields.filter((field) => field.type === 'radio' ? field.checked : field.value.trim() !== '').map((field) => field.name)).size;
            progressLabel.textContent = `${answered} dari ${total} soal dijawab`;
            progressBar.setAttribute('aria-valuenow', String(answered));
            progressFill.style.width = `${total > 0 ? (answered / total) * 100 : 0}%`;
        };
        const updateConnection = () => {
            const offline = !navigator.onLine;
            connectionIndicator.classList.toggle('is-offline', offline);
            connectionLabel.textContent = offline ? 'Offline · draft tersimpan di perangkat' : 'Online';
            if (offline && !locked) setSaveStatus('Koneksi terputus. Jawaban tersimpan sementara di perangkat dan akan disinkronkan saat online.', 'offline');
        };
        const serverAlignedNow = () => serverNowAt + (performance.now() - monotonicStart);
        const lockAndFinalize = () => {
            if (locked) return;
            locked = true;
            clearTimeout(saveTimer);
            clearTimeout(retryTimer);
            clearInterval(clockTimer);
            persistDraft();
            answerFields.forEach((field) => { field.disabled = true; });
            examForm.querySelector('[data-exam-open-submit]').disabled = true;
            setSaveStatus('Waktu ujian berakhir. Sistem sedang menutup ujian dan menyimpan semua jawaban yang sudah tersinkron.', 'error');
            submitExpiredAttempt();
        };
        const renderCountdown = () => {
            const remainingSeconds = Math.ceil((deadlineAt - serverAlignedNow()) / 1000);
            if (remainingSeconds <= 0) {
                countdown.textContent = '00:00:00';
                lockAndFinalize();
                return;
            }
            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            countdown.textContent = [hours, minutes, seconds].map(value => String(value).padStart(2, '0')).join(':');
            countdownPanel.classList.toggle('is-warning', remainingSeconds <= 300);
            countdownPanel.classList.toggle('is-danger', remainingSeconds <= 60);
        };
        const submitExpiredAttempt = async () => {
            if (submitting) return;
            submitting = true;
            const csrfToken = examForm.querySelector('[name="_token"]').value;
            try {
                const response = await fetch(examForm.action, {
                    method: 'POST',
                    headers: {'Accept': 'text/html', 'X-CSRF-TOKEN': csrfToken},
                    body: new FormData(examForm),
                });
                if (response.redirected) window.location.assign(response.url);
                else window.location.reload();
            } catch {
                submitting = false;
                setSaveStatus('Waktu habis. Ujian dikunci; sambungkan kembali internet untuk menyelesaikan pengumpulan.', 'error');
                if (navigator.onLine) retryTimer = setTimeout(submitExpiredAttempt, 3000);
            }
        };
        const saveAnswers = async () => {
            if (locked || submitting) return false;
            clearTimeout(retryTimer);
            persistDraft();
            if (!navigator.onLine) {
                updateConnection();
                return false;
            }
            if (saveInProgress) {
                saveQueued = true;
                return false;
            }
            saveInProgress = true;
            const savedVersion = editVersion;
            const csrfToken = examForm.querySelector('[name="_token"]').value;
            setSaveStatus('Menyimpan jawaban...', 'pending');
            try {
                const response = await fetch(examForm.dataset.saveUrl, {
                    method: 'PUT',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({jawaban: readAnswers()}),
                });
                const payload = await response.json().catch(() => ({}));
                if (response.status === 409) {
                    setSaveStatus(payload.message || 'Waktu ujian telah berakhir.', 'error');
                    if (serverAlignedNow() >= deadlineAt) lockAndFinalize();
                    else window.location.reload();
                    return false;
                }
                if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || 'Jawaban belum tersimpan di server.');
                if (savedVersion === editVersion) {
                    try { localStorage.removeItem(storageKey); } catch {}
                    setSaveStatus('Semua perubahan tersimpan otomatis.', 'saved');
                } else {
                    saveQueued = true;
                }
                return true;
            } catch (error) {
                setSaveStatus(`${error.message || 'Sinkronisasi gagal.'} Draft masih tersimpan di perangkat.`, 'error');
                if (navigator.onLine) retryTimer = setTimeout(saveAnswers, 2500);
                return false;
            } finally {
                saveInProgress = false;
                if (saveQueued && !locked) {
                    saveQueued = false;
                    queueSave(100);
                }
            }
        };
        const queueSave = (delay = 450) => {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(saveAnswers, delay);
        };
        const recordAnswerChange = () => {
            if (locked) return;
            editVersion += 1;
            persistDraft();
            updateProgress();
            setSaveStatus(navigator.onLine ? 'Perubahan tersimpan di perangkat. Menyinkronkan ke server...' : 'Offline · perubahan tersimpan di perangkat.', navigator.onLine ? 'pending' : 'offline');
            queueSave();
        };

        const restoredDraft = restoreDraft();
        updateProgress();
        updateConnection();
        if (restoredDraft) {
            editVersion += 1;
            setSaveStatus('Draft jawaban dipulihkan dari perangkat. Menyinkronkan...', 'pending');
            queueSave(50);
        }
        answerFields.forEach((field) => {
            field.addEventListener('input', recordAnswerChange);
            field.addEventListener('change', recordAnswerChange);
        });
        window.addEventListener('online', () => {
            updateConnection();
            if (locked && serverAlignedNow() >= deadlineAt) {
                submitExpiredAttempt();
                return;
            }
            if (editVersion > 0 && !locked) queueSave(50);
        });
        window.addEventListener('offline', updateConnection);
        window.addEventListener('pagehide', () => {
            persistDraft();
            if (!locked && !submitting && editVersion > 0 && navigator.onLine) {
                fetch(examForm.dataset.saveUrl, {
                    method: 'PUT',
                    keepalive: true,
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': examForm.querySelector('[name="_token"]').value},
                    body: JSON.stringify({jawaban: readAnswers()}),
                }).catch(() => {});
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') renderCountdown();
            else if (editVersion > 0 && !locked) saveAnswers();
        });
        clockTimer = setInterval(renderCountdown, 250);
        renderCountdown();

        document.querySelector('[data-exam-open-submit]').addEventListener('click', () => submitDialog.showModal());
        document.querySelector('[data-close-submit]').addEventListener('click', () => submitDialog.close());
        document.querySelector('[data-exam-final-submit]').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            button.disabled = true;
            clearTimeout(saveTimer);
            persistDraft();
            setSaveStatus('Menyimpan jawaban terakhir sebelum dikumpulkan...', 'pending');
            await saveAnswers();
            submitDialog.close();
            submitting = true;
            examForm.requestSubmit();
        });
        examForm.addEventListener('submit', () => {
            submitting = true;
            clearTimeout(saveTimer);
            persistDraft();
            setSaveStatus('Mengumpulkan ujian...', 'pending');
        });
    }
</script>
@endpush
