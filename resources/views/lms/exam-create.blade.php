@extends('layouts.simak')

@section('title', 'Buat Ujian | LMS Virtual Class')
@section('topbar_title', 'LMS Virtual Class')

@section('content')
    @include('lms._notifications')
    <div class="lms-page">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Pertemuan Kuliah · Pertemuan {{ $meeting->pertemuan_ke }}</p>
                <h1>Buat ujian</h1>
                <p class="subtitle">{{ $schedule->kode_mata_kuliah }} — {{ $schedule->nama_mata_kuliah }} · Kelas {{ $schedule->nama_kelas }}</p>
            </div>
            <a class="button button-secondary" href="{{ route('lms.meetings.index') }}">Kembali ke pertemuan</a>
        </div>

        <form id="lms-exam-create-form" method="post" action="{{ route('lms.exams.store', $meeting->id_pertemuan) }}" class="lms-panel lms-exam-create-form">
            @csrf
            <label>Judul ujian<input name="judul" required maxlength="255" value="{{ old('judul') }}"></label>
            <label>Petunjuk<textarea name="petunjuk" rows="3">{{ old('petunjuk') }}</textarea></label>
            <label>Jenis ujian
                <select name="jenis_ujian" data-searchable-select required
                    data-multiple-choice-count="{{ $questions->where('jenis_soal', 'pilihan_ganda')->count() }}"
                    data-essay-count="{{ $questions->where('jenis_soal', 'essay')->count() }}">
                    <option value="">Pilih jenis ujian</option>
                    <option value="pilihan_ganda" @selected(old('jenis_ujian') === 'pilihan_ganda')>Pilihan ganda</option>
                    <option value="essay" @selected(old('jenis_ujian') === 'essay')>Essay</option>
                </select>
            </label>
            <div class="lms-inline-fields">
                <label>Jumlah soal<input name="jumlah_soal_disajikan" type="number" min="1" max="1000" required value="{{ old('jumlah_soal_disajikan') }}"></label>
                <label>Durasi (menit)<input name="durasi_menit" type="number" min="1" max="1440" required value="{{ old('durasi_menit') }}"></label>
            </div>
            <div class="lms-question-picker">
                <button class="button button-secondary" type="button" data-exam-open-questions disabled>Pilih soal</button>
                <div class="lms-info-alert" role="status" aria-live="polite">
                    <span class="lms-info-alert-icon" aria-hidden="true">i</span>
                    <p data-exam-question-summary>Pilih jenis ujian dan isi jumlah soal terlebih dahulu.</p>
                </div>
            </div>
            <div class="lms-inline-fields">
                <label>Urutan soal
                    <select name="urutan_soal" data-searchable-select required>
                        <option value="acak" @selected(old('urutan_soal', 'acak') === 'acak')>Acak untuk tiap mahasiswa</option>
                        <option value="berurutan" @selected(old('urutan_soal') === 'berurutan')>Berurutan</option>
                    </select>
                </label>
                <label data-exam-randomize-answer-field @if (old('jenis_ujian') !== 'pilihan_ganda') hidden @endif>Acak pilihan jawaban
                    <select name="acak_pilihan_jawaban" data-searchable-select required>
                        <option value="0" @selected(old('acak_pilihan_jawaban', '0') === '0')>Tidak</option>
                        <option value="1" @selected(old('acak_pilihan_jawaban') === '1')>Ya</option>
                    </select>
                </label>
            </div>
            <div class="lms-inline-fields">
                <label>Mulai<input name="batas_mulai" type="datetime-local" required value="{{ old('batas_mulai') }}"></label>
                <label>Selesai<input name="batas_selesai" type="datetime-local" required value="{{ old('batas_selesai') }}"></label>
            </div>
            <label data-exam-score-field @if (old('jenis_ujian') !== 'pilihan_ganda') hidden @endif>Nilai per soal pilihan ganda<input name="nilai_per_soal" type="number" step="0.01" min="0.01" value="{{ old('nilai_per_soal') }}"></label>
            <p class="subtitle">Ujian hanya dapat dikerjakan satu kali. Pilih minimal sebanyak jumlah soal yang akan disajikan.</p>
            <div class="lms-actions">
                <a class="button button-secondary" href="{{ route('lms.meetings.index') }}">Batal</a>
                <button class="button button-primary" type="submit" data-exam-submit disabled>Simpan ujian</button>
            </div>
                <div data-exam-selected-question-inputs hidden></div>
        </form>

        <dialog class="lms-dialog lms-exam-question-modal" id="lms-exam-question-modal" aria-labelledby="lms-exam-question-title">
            <div class="lms-dialog-heading">
                <div><p class="eyebrow">Bank Soal</p><h2 id="lms-exam-question-title">Pilih soal ujian</h2></div>
                <button class="table-icon-button" type="button" data-exam-close-questions aria-label="Tutup dialog">×</button>
            </div>
            <div class="lms-dialog-body">
                <div class="lms-info-alert" role="status" aria-live="polite">
                    <span class="lms-info-alert-icon" aria-hidden="true">i</span>
                    <p data-exam-modal-summary></p>
                </div>
                <div class="table-wrap lms-exam-question-table-wrap">
                    <table data-exam-question-table>
                        <thead>
                            <tr>
                                <th><label class="lms-exam-select-all"><input type="checkbox" data-exam-select-all> Pilih semua</label></th>
                                <th>Jenis</th>
                                <th>Soal</th>
                            </tr>
                        </thead>
                        <tbody>
                    @forelse ($questions as $question)
                                <tr data-question-type="{{ $question->jenis_soal }}">
                                    <td><input type="checkbox" value="{{ $question->id_soal }}" data-exam-question-checkbox @checked(in_array($question->id_soal, (array) old('id_soal', []), true)) aria-label="Pilih soal"></td>
                                    <td>{{ $question->jenis_soal }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit(strip_tags($question->pertanyaan), 240) }}</td>
                                </tr>
                    @empty
                                <tr><td colspan="3">Bank soal aktif untuk mata kuliah ini masih kosong.</td></tr>
                    @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="lms-dialog-actions">
                <button class="button button-secondary" type="button" data-exam-close-questions>Tutup</button>
                <button class="button button-primary" type="button" data-exam-confirm-questions disabled>Selesai memilih</button>
            </div>
        </dialog>
    </div>
@endsection

@push('scripts')
    <script>
        const questionType = document.querySelector('[name="jenis_ujian"]');
        const randomizeAnswerField = document.querySelector('[data-exam-randomize-answer-field]');
        const randomizeAnswerSelect = randomizeAnswerField.querySelector('[name="acak_pilihan_jawaban"]');
        const examScoreField = document.querySelector('[data-exam-score-field]');
        const examScoreInput = examScoreField.querySelector('[name="nilai_per_soal"]');
        const questionCount = document.querySelector('[name="jumlah_soal_disajikan"]');
        const questionModal = document.getElementById('lms-exam-question-modal');
        const questionPickerButton = document.querySelector('[data-exam-open-questions]');
        const questionSummary = document.querySelector('[data-exam-question-summary]');
        const modalSummary = document.querySelector('[data-exam-modal-summary]');
        const confirmQuestions = document.querySelector('[data-exam-confirm-questions]');
        const submitExam = document.querySelector('[data-exam-submit]');
        const questionTable = questionModal.querySelector('[data-exam-question-table]');
        const questionChoices = [...questionTable.querySelectorAll('tbody tr[data-question-type]')];
        const selectAllQuestions = questionModal.querySelector('[data-exam-select-all]');
        const selectedQuestionInputs = document.querySelector('[data-exam-selected-question-inputs]');
        const selectedQuestions = () => questionChoices.filter((row) => row.querySelector('[data-exam-question-checkbox]').checked);
        const requiredQuestionCount = () => Number.parseInt(questionCount.value, 10) || 0;
        let rejectedCountMessage = '';
        let selectionConfirmed = false;
        const syncRandomizeAnswerVisibility = () => {
            const isMultipleChoice = questionType.value === 'pilihan_ganda';
            randomizeAnswerField.hidden = !isMultipleChoice;
            randomizeAnswerSelect.required = isMultipleChoice;
            examScoreField.hidden = !isMultipleChoice;
            examScoreInput.required = isMultipleChoice;
        };
        const availableQuestionCount = () => questionType.value === 'essay'
            ? Number.parseInt(questionType.dataset.essayCount, 10) || 0
            : Number.parseInt(questionType.dataset.multipleChoiceCount, 10) || 0;
        const updateQuestionPicker = () => {
            const availableCount = questionType.value === '' ? 0 : availableQuestionCount();
            let requiredCount = requiredQuestionCount();
            if (questionType.value !== '' && requiredCount > availableCount) {
                rejectedCountMessage = 'Jumlah soal tidak boleh melebihi soal yang tersedia di bank soal.';
                questionCount.value = availableCount > 0 ? String(availableCount) : '';
                requiredCount = requiredQuestionCount();
            } else {
                rejectedCountMessage = '';
            }
            const hasTypeAndCount = questionType.value !== '' && requiredCount > 0;
            const countWithinAvailability = hasTypeAndCount && requiredCount <= availableCount;
            questionCount.max = questionType.value === '' ? '1000' : String(availableCount);
            questionCount.setCustomValidity('');
            questionChoices.forEach((row) => {
                if (row.dataset.questionType !== questionType.value) row.querySelector('[data-exam-question-checkbox]').checked = false;
            });
            selectedQuestionInputs.replaceChildren(...selectedQuestions().map((row) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id_soal[]';
                input.value = row.querySelector('[data-exam-question-checkbox]').value;
                return input;
            }));
            const selectedCount = selectedQuestions().length;
            const currentTypeRows = questionChoices.filter((row) => row.dataset.questionType === questionType.value);
            const selectedCurrentTypeCount = currentTypeRows.filter((row) => row.querySelector('[data-exam-question-checkbox]').checked).length;
            selectAllQuestions.checked = currentTypeRows.length > 0 && selectedCurrentTypeCount === currentTypeRows.length;
            selectAllQuestions.indeterminate = selectedCurrentTypeCount > 0 && !selectAllQuestions.checked;
            questionPickerButton.disabled = !countWithinAvailability;
            confirmQuestions.disabled = !countWithinAvailability || selectedCount < requiredCount;
            submitExam.disabled = !countWithinAvailability || selectedCount < requiredCount;
            const countText = `${selectedCount} soal dipilih dari minimal ${requiredCount || '—'} soal.`;
            const selectedQuestionShortage = Math.max(0, requiredCount - selectedCount);
            const shortageMessage = `Soal yang dipilih masih kurang ${selectedQuestionShortage} soal dari jumlah soal ujian. Pilih soal tambahan.`;
            const availabilityMessage = availableCount === 0
                ? 'Belum ada soal aktif untuk jenis ini pada mata kuliah pertemuan.'
                : `Tersedia ${availableCount} soal aktif untuk jenis ini pada mata kuliah pertemuan.`;
            const selectionMessage = selectedCount < requiredCount
                ? shortageMessage
                : selectionConfirmed
                    ? `Soal berhasil dipilih: ${selectedCount} soal siap dimasukkan ke ujian.`
                    : `Pilih soal lalu klik Selesai memilih. ${countText}`;
            questionSummary.textContent = questionType.value === ''
                ? 'Pilih jenis ujian dan isi jumlah soal terlebih dahulu.'
                : rejectedCountMessage || (!countWithinAvailability
                    ? availabilityMessage
                    : selectionMessage);
            questionSummary.parentElement.classList.toggle('is-error', rejectedCountMessage !== '');
            questionSummary.parentElement.classList.toggle('is-warning', countWithinAvailability && selectedCount < requiredCount);
            questionSummary.parentElement.classList.toggle('is-success', countWithinAvailability && selectedCount >= requiredCount && selectionConfirmed);
            modalSummary.textContent = questionType.value === ''
                ? 'Pilih jenis ujian dan isi jumlah soal terlebih dahulu.'
                : rejectedCountMessage || (!countWithinAvailability
                    ? availabilityMessage
                    : selectedCount < requiredCount
                        ? shortageMessage
                        : `Pilih minimal ${requiredCount} soal ${questionType.value === 'essay' ? 'essay' : 'pilihan ganda'}. ${countText}`);
            modalSummary.parentElement.classList.toggle('is-error', rejectedCountMessage !== '');
            modalSummary.parentElement.classList.toggle('is-warning', countWithinAvailability && selectedCount < requiredCount);
            modalSummary.parentElement.classList.remove('is-success');
            if (countWithinAvailability && selectedCount < requiredCount) {
                confirmQuestions.title = `Pilih setidaknya ${requiredCount} soal untuk melanjutkan.`;
            } else {
                confirmQuestions.removeAttribute('title');
            }
        };
        questionType.addEventListener('change', () => {
            selectionConfirmed = false;
            syncRandomizeAnswerVisibility();
            updateQuestionPicker();
        });
        questionCount.addEventListener('input', () => {
            selectionConfirmed = false;
            syncRandomizeAnswerVisibility();
            updateQuestionPicker();
        });
        questionChoices.forEach((row) => row.querySelector('[data-exam-question-checkbox]').addEventListener('change', () => {
            selectionConfirmed = false;
            updateQuestionPicker();
        }));
        selectAllQuestions.addEventListener('change', () => {
            questionChoices.forEach((row) => {
                if (row.dataset.questionType === questionType.value) {
                    row.querySelector('[data-exam-question-checkbox]').checked = selectAllQuestions.checked;
                }
            });
            selectionConfirmed = false;
            updateQuestionPicker();
        });
        questionPickerButton.addEventListener('click', () => {
            questionTable.dataset.datatable = 'lms-exam-question-picker';
            questionTable.dataset.datatableFilterColumn = '1';
            questionTable.dataset.datatableFilterValue = questionType.value;
            questionModal.showModal();
            document.dispatchEvent(new CustomEvent('simak:initialize-searchable-selects', {detail: {root: questionModal}}));
        });
        document.querySelectorAll('[data-exam-close-questions]').forEach((button) => button.addEventListener('click', () => questionModal.close()));
        confirmQuestions.addEventListener('click', () => {
            selectionConfirmed = true;
            updateQuestionPicker();
            questionModal.close();
        });
        updateQuestionPicker();
    </script>
@endpush
