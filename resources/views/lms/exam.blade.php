@extends('layouts.simak')

@section('title', $exam->judul.' | Ujian LMS')
@section('topbar_title', 'Ujian LMS')

@section('content')
    @include('lms._notifications')
    @if ($user->role === 'mahasiswa')
        @include('lms._exam-student')
    @elseif (in_array($user->role, ['dosen', 'admin', 'pimuniv', 'pimfak', 'userprodi', 'usertu'], true))
        <div class="lms-exam lms-exam-results-page">
            <header class="page-heading">
                <div>
                    <p class="eyebrow">Ujian · {{ $exam->jenis_ujian === 'essay' ? 'Essay' : 'Pilihan ganda' }}</p>
                    <h1>{{ $exam->judul }}</h1>
                    <p class="subtitle">{{ $exam->petunjuk }}</p>
                </div>
                <a class="button button-secondary" href="{{ route('lms.meetings.index') }}">Kembali ke pertemuan</a>
                @if ($user->role === 'dosen')
                    <button class="button button-primary" type="button" data-lms-open="lms-exam-edit-modal">Edit ujian</button>
                @endif
            </header>
            <section class="lms-panel">
                <div class="lms-info-alert lms-exam-meta-alert" role="status">
                    <span class="lms-info-alert-icon" aria-hidden="true">i</span>
                    <p><strong>{{ $exam->jumlah_soal_disajikan }} soal</strong> · <strong>{{ $exam->durasi_menit }} menit</strong> · Waktu: {{ $exam->batas_mulai }} s.d. {{ $exam->batas_selesai }} WIT</p>
                </div>
                <h2>Hasil ujian mahasiswa</h2>
                <div class="table-wrap">
                    <table data-datatable="lms-exam-results">
                        <thead><tr><th>NPM</th><th>Nama</th><th>Status</th><th>Dikumpulkan</th><th>Pekerjaan / Penilaian</th></tr></thead>
                        <tbody>
                            @foreach ($examResults as $attempt)
                                <tr>
                                    <td>{{ $attempt->nim }}</td>
                                    <td>{{ $attempt->nama_mahasiswa }}</td>
                                    <td>{{ ucfirst($attempt->status_hasil) }}</td>
                                    <td>{{ $attempt->dikumpulkan_pada ?? '-' }}</td>
                                    <td>
                                        <details class="lms-attempt-review">
                                            <summary>Lihat pekerjaan</summary>
                                            @forelse ($attempt->answers as $answer)
                                                <article class="lms-answer">
                                                    <h3>Soal {{ $answer->urutan_tampil }}</h3>
                                                    <p class="lms-answer-question">{!! nl2br(e($answer->snapshot['pertanyaan'] ?? 'Soal tidak tersedia.')) !!}</p>
                                                    @if (($answer->snapshot['jenis_soal'] ?? '') === 'pilihan_ganda')
                                                        @php($selectedOption = $answer->jawaban_mahasiswa)
                                                        <p><strong>Jawaban mahasiswa:</strong>
                                                            @if (blank($selectedOption))
                                                                Tidak dijawab
                                                            @else
                                                                {{ $selectedOption }}{{ isset($answer->snapshot['opsi'][$selectedOption]) ? ' — '.$answer->snapshot['opsi'][$selectedOption] : '' }}
                                                            @endif
                                                        </p>
                                                    @else
                                                        <p><strong>Jawaban mahasiswa:</strong></p>
                                                        <p class="lms-answer-response">{!! blank($answer->jawaban_mahasiswa) ? 'Tidak dijawab' : nl2br(e($answer->jawaban_mahasiswa)) !!}</p>
                                                    @endif
                                                </article>
                                            @empty
                                                <p class="lms-answer-empty">Belum ada jawaban yang tersimpan.</p>
                                            @endforelse
                                            @if ($user->role === 'dosen' && $exam->jenis_ujian === 'essay' && $attempt->status_hasil === 'terkumpul')
                                                <form method="post" action="{{ route('lms.exams.grade', ['record' => $exam->id_ujian, 'result' => $attempt->id_hasil_ujian]) }}" class="lms-inline-form">
                                                    @csrf @method('PUT')
                                                    <label>Nilai<input type="number" name="nilai_akhir" min="0" max="100" step="0.01" required></label>
                                                    <label>Komentar<textarea name="komentar_dosen" rows="2"></textarea></label>
                                                    <button class="table-icon-button" type="submit" aria-label="Simpan nilai ujian" title="Simpan nilai ujian"><span data-lucide="save" aria-hidden="true"></span></button>
                                                </form>
                                            @endif
                                        </details>
                                        @if ($attempt->nilai_akhir !== null || $attempt->komentar_dosen)
                                            <p class="lms-attempt-grade"><strong>Nilai akhir:</strong> {{ $attempt->nilai_akhir ?? '—' }}{{ $attempt->komentar_dosen ? ' · '.$attempt->komentar_dosen : '' }}</p>
                                        @elseif ($exam->jenis_ujian === 'essay' && $attempt->status_hasil === 'terkumpul')
                                            <p class="lms-attempt-grade">Hasil ujian sedang diperiksa oleh dosen.</p>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            @if ($user->role === 'dosen')
                <dialog class="lms-dialog lms-exam-edit-modal" id="lms-exam-edit-modal" aria-labelledby="lms-exam-edit-title">
                    <form method="post" action="{{ route('lms.exams.update', $exam->id_ujian) }}" class="lms-dialog-form lms-exam-edit-form">
                        @csrf
                        @method('PUT')
                        <div class="lms-dialog-heading">
                            <div><p class="eyebrow">Pengaturan ujian</p><h2 id="lms-exam-edit-title">Edit ujian</h2></div>
                            <button class="table-icon-button" type="button" data-lms-close aria-label="Tutup form">×</button>
                        </div>
                        @if ($hasExamParticipants)
                            <div class="lms-info-alert" role="status"><p>Ujian sudah diikuti mahasiswa. Hanya waktu berakhir yang dapat diubah.</p></div>
                            <label>Waktu berakhir
                                <input type="datetime-local" name="batas_selesai" value="{{ \Illuminate\Support\Carbon::parse($exam->batas_selesai, 'Asia/Jayapura')->format('Y-m-d\TH:i') }}" required>
                            </label>
                        @else
                            <div class="lms-dialog-body">
                                <label>Judul ujian<input name="judul" required maxlength="255" value="{{ $exam->judul }}"></label>
                                <label>Petunjuk<textarea name="petunjuk" rows="3">{{ $exam->petunjuk }}</textarea></label>
                                <label>Jenis ujian
                                    <select name="jenis_ujian" data-searchable-select required>
                                        <option value="pilihan_ganda" @selected($exam->jenis_ujian === 'pilihan_ganda')>Pilihan ganda</option>
                                        <option value="essay" @selected($exam->jenis_ujian === 'essay')>Essay</option>
                                    </select>
                                </label>
                                <div class="lms-inline-fields">
                                    <label>Jumlah soal<input name="jumlah_soal_disajikan" type="number" min="1" max="1000" required value="{{ $exam->jumlah_soal_disajikan }}"></label>
                                    <label>Durasi (menit)<input name="durasi_menit" type="number" min="1" max="1440" required value="{{ $exam->durasi_menit }}"></label>
                                </div>
                                <label>Pilih soal ujian
                                    <select name="id_soal[]" data-searchable-select multiple required>
                                        @foreach ($examQuestions as $question)
                                            <option value="{{ $question->id_soal }}" data-question-type="{{ $question->jenis_soal }}" {{ $question->jenis_soal !== $exam->jenis_ujian ? 'disabled hidden' : '' }} @selected(in_array($question->id_soal, $selectedExamQuestionIds, true))>{{ strtoupper($question->jenis_soal) }} — {{ \Illuminate\Support\Str::limit(strip_tags($question->pertanyaan), 160) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="lms-info-alert" role="status">
                                    <span class="lms-info-alert-icon" aria-hidden="true">i</span>
                                    <p>Pilih soal minimal sebanyak jumlah soal yang akan disajikan. Ujian hanya dapat dikerjakan satu kali.</p>
                                </div>
                                <div class="lms-inline-fields">
                                    <label>Urutan soal
                                        <select name="urutan_soal" data-searchable-select required>
                                            <option value="acak" @selected($exam->urutan_soal === 'acak')>Acak untuk tiap mahasiswa</option>
                                            <option value="berurutan" @selected($exam->urutan_soal === 'berurutan')>Berurutan</option>
                                        </select>
                                    </label>
                                    <label data-exam-randomize-answer-field @if ($exam->jenis_ujian !== 'pilihan_ganda') hidden @endif>Acak pilihan jawaban
                                        <select name="acak_pilihan_jawaban" data-searchable-select required>
                                            <option value="0" @selected(! $exam->acak_pilihan_jawaban)>Tidak</option>
                                            <option value="1" @selected((bool) $exam->acak_pilihan_jawaban)>Ya</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="lms-inline-fields">
                                    <label>Mulai<input name="batas_mulai" type="datetime-local" value="{{ \Illuminate\Support\Carbon::parse($exam->batas_mulai, 'Asia/Jayapura')->format('Y-m-d\TH:i') }}" required></label>
                                    <label>Selesai<input name="batas_selesai" type="datetime-local" value="{{ \Illuminate\Support\Carbon::parse($exam->batas_selesai, 'Asia/Jayapura')->format('Y-m-d\TH:i') }}" required></label>
                                </div>
                                <label data-exam-score-field @if ($exam->jenis_ujian !== 'pilihan_ganda') hidden @endif>Nilai per soal pilihan ganda<input name="nilai_per_soal" type="number" step="0.01" min="0.01" value="{{ $exam->nilai_per_soal }}" @required($exam->jenis_ujian === 'pilihan_ganda')></label>
                                <label>Status ujian
                                    <select name="status_ujian" data-searchable-select required>
                                        <option value="draf" @selected($exam->status_ujian === 'draf')>Draf</option>
                                        <option value="terbit" @selected($exam->status_ujian === 'terbit')>Terbit</option>
                                        <option value="ditutup" @selected($exam->status_ujian === 'ditutup')>Ditutup</option>
                                    </select>
                                </label>
                            </div>
                        @endif
                        <div class="lms-dialog-actions">
                            <button class="button button-secondary" type="button" data-lms-close>Batal</button>
                            <button class="button button-primary" type="submit">Simpan perubahan</button>
                        </div>
                    </form>
                </dialog>
            @endif
        </div>
    @endif
@endsection

@push('styles')
<style>
    .lms-exam-results-page{display:grid;gap:1.25rem}.lms-exam-results-page .subtitle{color:#64748b}
    .lms-exam-results-page .lms-panel{display:grid;gap:1rem;padding:1.25rem;background:#fff;border:1px solid #dbe3ee;border-radius:10px}
    .lms-exam-results-page .lms-meta{display:flex;flex-wrap:wrap;gap:.75rem 1.5rem;color:#475569}
    .lms-exam-meta-alert{display:flex;align-items:flex-start;gap:.65rem;margin:0;padding:.75rem .9rem;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:8px;background:#f0f9ff;color:#0c4a6e;line-height:1.5}
    .lms-exam-meta-alert p{margin:0}
    .lms-exam-meta-alert .lms-info-alert-icon{display:grid;flex:0 0 1.15rem;width:1.15rem;height:1.15rem;place-items:center;margin-top:.08rem;border:1.5px solid currentColor;border-radius:50%;font-size:.75rem;font-weight:700;line-height:1}
    .lms-exam-edit-modal{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);margin:0;padding:0;width:min(900px,calc(100vw - 2rem));max-height:min(90vh,900px);overflow:hidden;border:1px solid #dbe3ee;border-radius:16px;background:#fff;color:#243447;box-shadow:0 24px 80px #0f172a40}
    .lms-exam-edit-modal::backdrop{background:#0f172a80;backdrop-filter:blur(2px)}
    .lms-exam-edit-modal[open]{display:flex;flex-direction:column}
    .lms-exam-edit-form{display:flex;min-height:0;flex-direction:column}
    .lms-exam-edit-form .lms-dialog-heading,.lms-exam-edit-form .lms-dialog-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.25rem}
    .lms-exam-edit-form .lms-dialog-heading{border-bottom:1px solid #e2e8f0}
    .lms-exam-edit-form .lms-dialog-heading h2{margin:0}
    .lms-exam-edit-form .lms-dialog-body{display:grid;min-height:0;gap:1rem;overflow:auto;padding:1.25rem}
    .lms-exam-edit-form [hidden]{display:none!important}
    .lms-exam-edit-form label{display:grid;min-width:0;gap:.4rem;color:#334155;font-weight:600}
    .lms-exam-edit-form input,.lms-exam-edit-form textarea,.lms-exam-edit-form select{box-sizing:border-box;width:100%;min-width:0;padding:.65rem .75rem;border:1px solid #cbd5e1;border-radius:8px;background:#fff;color:#0f172a;font:inherit}
    .lms-exam-edit-form textarea{resize:vertical}
    .lms-exam-edit-form .lms-inline-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}
    .lms-exam-edit-form .lms-info-alert{margin:1.25rem 1.25rem 0}
    .lms-exam-edit-form>label{margin:1.25rem 1.25rem 0}
    .lms-exam-edit-form .lms-info-alert{padding:.8rem 1rem;border:1px solid #bae6fd;border-left:4px solid #0284c7;border-radius:8px;background:#f0f9ff;color:#0c4a6e}
    .lms-exam-edit-form .lms-info-alert p{margin:0;line-height:1.5}
    .lms-exam-edit-form .lms-dialog-body>.lms-info-alert{display:flex;align-items:flex-start;gap:.65rem;margin:0;padding:.75rem .9rem}
    .lms-exam-edit-form .lms-dialog-body>.lms-info-alert .lms-info-alert-icon{display:grid;flex:0 0 1.15rem;width:1.15rem;height:1.15rem;place-items:center;margin-top:.08rem;border:1.5px solid currentColor;border-radius:50%;font-size:.75rem;font-weight:700;line-height:1}
    .lms-exam-edit-form .lms-dialog-actions{justify-content:flex-end;border-top:1px solid #e2e8f0}
    .lms-exam-edit-form .ts-wrapper{width:100%}
    .lms-exam-edit-form .ts-control{min-height:42px;border-color:#cbd5e1;border-radius:8px;padding:.55rem .7rem}
    .lms-exam-edit-modal .ts-dropdown{z-index:2}
    .lms-exam-edit-modal .ts-dropdown-content{max-height:min(240px,40vh);overflow-y:auto}
    .lms-attempt-review{min-width:180px}
    .lms-attempt-review>summary{color:#14532d;font-weight:700;cursor:pointer}
    .lms-answer{padding:.8rem 0;border-bottom:1px solid #e2e8f0;white-space:normal}
    .lms-answer h3{margin:0 0 .5rem;font-size:.95rem}
    .lms-answer p{margin:.4rem 0;line-height:1.5}
    .lms-answer-question,.lms-answer-response{overflow-wrap:anywhere}
    .lms-attempt-grade,.lms-answer-empty{margin:.8rem 0 0}
    .lms-inline-form{display:flex;flex-wrap:wrap;align-items:end;gap:.65rem}
    .lms-inline-form label{display:grid;gap:.3rem}
    .lms-inline-form input,.lms-inline-form textarea{padding:.55rem;border:1px solid #cbd5e1;border-radius:7px}
    @media(max-width:600px){.lms-exam-edit-modal{width:calc(100vw - 1rem);max-height:calc(100dvh - 1rem)}.lms-exam-edit-form .lms-dialog-heading,.lms-exam-edit-form .lms-dialog-body,.lms-exam-edit-form .lms-dialog-actions{padding:.85rem}.lms-exam-edit-form .lms-inline-fields{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('[data-lms-open]').forEach((button) => button.addEventListener('click', () => {
        const dialog = document.getElementById(button.dataset.lmsOpen);
        dialog?.showModal();
        if (dialog) {
            document.dispatchEvent(new CustomEvent('simak:initialize-searchable-selects', {detail: {root: dialog}}));
            syncExamQuestionOptions();
        }
    }));
    document.querySelectorAll('[data-lms-close]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
    const examType = document.querySelector('.lms-exam-edit-form [name="jenis_ujian"]');
    const examScore = document.querySelector('.lms-exam-edit-form [name="nilai_per_soal"]');
    const examScoreField = document.querySelector('[data-exam-score-field]');
    const examQuestionSelect = document.querySelector('.lms-exam-edit-form [name="id_soal[]"]');
    const randomizeAnswerField = document.querySelector('[data-exam-randomize-answer-field]');
    const randomizeAnswerSelect = randomizeAnswerField?.querySelector('[name="acak_pilihan_jawaban"]');
    const syncRandomizeAnswerVisibility = () => {
        if (randomizeAnswerField && examType) {
            const isMultipleChoice = examType.value === 'pilihan_ganda';
            randomizeAnswerField.hidden = !isMultipleChoice;
            if (randomizeAnswerSelect) randomizeAnswerSelect.required = isMultipleChoice;
        }
    };
    const syncExamQuestionOptions = () => {
        if (!examType || !examQuestionSelect) return;
        const questionPicker = examQuestionSelect.tomselect;
        const pickerOptions = new Map();
        [...examQuestionSelect.options].forEach((option) => {
            if (!option.value) return;
            pickerOptions.set(option.value, option);
            const isMatchingType = option.dataset.questionType === examType.value;
            option.hidden = !isMatchingType;
            option.disabled = !isMatchingType;
            if (questionPicker) {
                const pickerOption = questionPicker.options[option.value];
                if (pickerOption) {
                    questionPicker.updateOption(option.value, {...pickerOption, disabled: !isMatchingType});
                }
                if (!isMatchingType && (option.selected || questionPicker.items.includes(option.value))) {
                    questionPicker.removeItem(option.value, true);
                }
            } else if (!isMatchingType) {
                option.selected = false;
            }
        });
        if (questionPicker) {
            questionPicker.refreshOptions(false);
            questionPicker.dropdown.querySelectorAll('.option[data-value]').forEach((renderedOption) => {
                const option = pickerOptions.get(renderedOption.dataset.value);
                renderedOption.hidden = !option || option.hidden;
            });
        }
    };
    const syncExamScoreRequirement = () => {
        if (examType && examScore) {
            const isMultipleChoice = examType.value === 'pilihan_ganda';
            examScoreField.hidden = !isMultipleChoice;
            examScore.required = isMultipleChoice;
        }
    };
    examType?.addEventListener('change', () => {
        syncExamScoreRequirement();
        syncExamQuestionOptions();
        syncRandomizeAnswerVisibility();
    });
    syncExamQuestionOptions();
    syncRandomizeAnswerVisibility();
    syncExamScoreRequirement();
    const examForm = document.querySelector('[data-exam-answer-form]');
    if (examForm) {
        const status = document.querySelector('[data-exam-save-status]');
        let saveTimer;
        const saveAnswers = async () => {
            const answers = {};
            new FormData(examForm).forEach((value, key) => {
                const match = key.match(/^jawaban\[([^\]]+)\]$/);
                if (match) answers[match[1]] = value;
            });
            status.textContent = 'Menyimpan jawaban...';
            let notificationText = 'Jawaban gagal disimpan. Periksa koneksi Anda, lalu coba lagi.';
            try {
                const response = await fetch(examForm.dataset.saveUrl, {
                    method: 'PUT',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': examForm.elements._token.value},
                    body: JSON.stringify({jawaban: answers})
                });
                const payload = await response.json().catch(() => ({}));
                if (response.status === 409) {
                    status.textContent = payload.message || 'Waktu ujian habis.';
                    window.simakLmsNotify?.({icon: 'info', title: 'Ujian diperbarui', text: status.textContent});
                    window.setTimeout(() => window.location.reload(), 1000);
                    return;
                }
                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors || {}).flat()[0];
                    const statusMessages = {
                        401: 'Sesi Anda telah berakhir. Silakan masuk kembali.',
                        403: 'Anda tidak memiliki akses untuk menyimpan jawaban ujian ini.',
                        404: 'Ujian tidak ditemukan.',
                        419: 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba lagi.',
                        429: 'Terlalu banyak permintaan. Silakan coba kembali beberapa saat lagi.',
                    };
                    notificationText = response.status >= 500
                        ? 'Jawaban gagal disimpan karena terjadi gangguan pada server.'
                        : statusMessages[response.status]
                            || (response.status === 422 && validationMessage)
                            || 'Jawaban gagal disimpan.';
                    throw new Error(notificationText);
                }
                status.textContent = payload.message || 'Jawaban tersimpan.';
            } catch {
                status.textContent = notificationText;
                window.simakLmsNotify?.({icon: 'error', title: 'Jawaban gagal disimpan', text: notificationText});
            }
        };
        examForm.addEventListener('input', () => { clearTimeout(saveTimer); saveTimer = setTimeout(saveAnswers, 500); });
        examForm.addEventListener('change', () => { clearTimeout(saveTimer); saveTimer = setTimeout(saveAnswers, 250); });
        examForm.addEventListener('submit', () => clearTimeout(saveTimer));
    }
</script>
@endpush
