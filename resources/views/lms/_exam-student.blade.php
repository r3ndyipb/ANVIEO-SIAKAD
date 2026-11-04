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

@push('styles')
<style>
    .lms-exam-page{display:grid;gap:1.15rem;max-width:1080px;margin:0 auto 2rem;color:#20352c}
    .exam-page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1.25rem;padding:1rem 0 .6rem;border-bottom:1px solid #cad9d0}
    .exam-page-heading .eyebrow{margin:0 0 .45rem;color:#537260;font-size:.7rem;font-weight:800;letter-spacing:.09em}
    .exam-page-heading h1{margin:0;color:#173e30;font-family:Georgia,"Times New Roman",serif;font-size:clamp(1.55rem,3vw,2.25rem);font-weight:500;line-height:1.15}
    .exam-course-line{display:flex;flex-wrap:wrap;gap:.25rem .8rem;margin:.65rem 0 0;color:#456052;font-size:.94rem;font-weight:650}
    .exam-course-line span{color:#687c70;font-size:.82rem;font-weight:500}
    .exam-overview{overflow:hidden;border:1px solid #d5e1d8;border-radius:10px;background:#fff;box-shadow:0 8px 22px #193b2a0a}
    .exam-overview-heading{display:flex;align-items:flex-start;gap:.9rem;padding:1rem 1.1rem;border-bottom:1px solid #e6eee8}
    .exam-overview-heading p{margin:.15rem 0 0;color:#4f6658;line-height:1.55}
    .exam-state-mark,.exam-live-pill{display:inline-flex;flex:0 0 auto;align-items:center;gap:.45rem;padding:.34rem .58rem;border:1px solid #cde3d3;border-radius:999px;background:#f3faf4;color:#27633a;font-size:.7rem;font-weight:750;white-space:nowrap}
    .exam-state-dot{width:.48rem;height:.48rem;border-radius:50%;background:#26934c;box-shadow:0 0 0 3px #26934c20}
    .exam-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0}
    .exam-detail-grid>div{padding:.9rem 1.1rem;border-right:1px solid #e6eee8}
    .exam-detail-grid>div:last-child{border-right:0}
    .exam-detail-grid dt,.exam-result-score span{color:#6b7f72;font-size:.66rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
    .exam-detail-grid dd{margin:.36rem 0 0;color:#193e2e;font-size:1.02rem;font-weight:750}
    .exam-detail-grid dd small{color:#728378;font-size:.72rem;font-weight:500}
    .exam-state-panel{display:flex;align-items:center;gap:1.2rem;padding:1.35rem;border:1px solid #d5e1d8;border-radius:10px;background:#fbfdfb}
    .exam-review{display:grid;gap:.9rem}
    .exam-review-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem}
    .exam-review-heading h2{margin:0;color:#1d3e2d;font-size:1.1rem}
    .exam-review-heading>p{margin:0;color:#617369;font-size:.82rem}
    .exam-question-block h3{display:flex;align-items:center;gap:.65rem;margin:0 0 .75rem;color:#215537;font-size:.68rem;letter-spacing:.08em}
    .exam-question-block h3 small{padding:.25rem .45rem;border-radius:4px;background:#edf4ee;color:#65796a;font-size:.62rem;font-weight:650;letter-spacing:0}
    .exam-review-answer{display:grid;gap:.35rem;padding:.75rem;border-radius:6px;background:#f5f8f5;color:#243a2e}
    .exam-review-answer strong{font-size:.75rem;color:#4f6658}
    .exam-review-answer p{margin:0;line-height:1.6;white-space:pre-wrap}
    .exam-review-unanswered{color:#748278;font-style:italic}
    .exam-state-illustration{display:grid;width:3rem;height:3rem;flex:0 0 3rem;place-items:center;border-radius:9px;background:#e8f2e8;color:#2b7042}
    .exam-state-illustration svg{width:1.5rem;height:1.5rem}
    .exam-state-copy{flex:1 1 auto;min-width:0}
    .exam-state-kicker{margin:0 0 .35rem;color:#4b7658;font-size:.65rem;font-weight:850;letter-spacing:.09em}
    .exam-state-copy h2{margin:0;color:#1d3e2d;font-size:1.1rem;line-height:1.3}
    .exam-state-copy p:last-child{margin:.35rem 0 0;color:#617369;line-height:1.5}
    .exam-start-button,.exam-submit-button{display:inline-flex;align-items:center;gap:.55rem}
    .exam-start-button svg,.exam-submit-button svg{width:1rem;height:1rem}
    .exam-start-button:disabled{opacity:.5;cursor:not-allowed}
    .exam-active-workspace{display:grid;gap:.9rem}
    .exam-live-toolbar{position:sticky;top:84px;z-index:5;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border:1px solid #d6e1d9;border-radius:9px;background:#ffffffed;box-shadow:0 8px 24px #17392714;backdrop-filter:blur(10px)}
    .exam-live-copy{display:grid;gap:.45rem;min-width:0}
    .exam-live-pill{width:max-content;border-color:#b9ddc2;background:#eefaf0}
    .exam-live-copy p{margin:0;color:#617267;font-size:.78rem}
    .exam-live-copy p[data-save-state="pending"]{color:#976314}
    .exam-live-copy p[data-save-state="offline"]{color:#a04b19}
    .exam-live-copy p[data-save-state="error"]{color:#a52222}
    .exam-countdown{display:grid;min-width:130px;justify-items:end;gap:.1rem;padding:.45rem .7rem;border-left:1px solid #dfe8e1}
    .exam-countdown span{color:#668071;font-size:.6rem;font-weight:850;letter-spacing:.1em}
    .exam-countdown strong{color:#173e30;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:1.4rem;font-variant-numeric:tabular-nums;letter-spacing:.04em}
    .exam-countdown.is-warning strong{color:#a35f0b}
    .exam-countdown.is-danger strong{color:#b4232f}
    .exam-connectivity-row{display:flex;justify-content:space-between;gap:1rem;color:#66786c;font-size:.73rem}
    .exam-connection-indicator{display:inline-flex;align-items:center;gap:.4rem}
    .exam-connection-indicator>span:first-child{width:.45rem;height:.45rem;border-radius:50%;background:#228b45}
    .exam-connection-indicator.is-offline>span:first-child{background:#c0711a}
    .exam-progress-track{height:5px;overflow:hidden;border-radius:999px;background:#dce7df}
    .exam-progress-track>span{display:block;width:0;height:100%;border-radius:inherit;background:#2b7844;transition:width .18s ease}
    .exam-question-list{display:grid;gap:.85rem}
    .exam-question-block{min-width:0;margin:0;padding:1rem 1.1rem;border:1px solid #d9e3dc;border-radius:9px;background:#fff;box-shadow:0 3px 12px #17392708}
    .exam-question-block legend{display:flex;align-items:center;gap:.65rem;padding:0 .3rem;color:#215537;font-size:.68rem;font-weight:850;letter-spacing:.08em}
    .exam-question-block legend small{padding:.25rem .45rem;border-radius:4px;background:#edf4ee;color:#65796a;font-size:.62rem;font-weight:650;letter-spacing:0}
    .exam-question-prompt{margin:.2rem 0 1rem;color:#243a2e;font-size:1rem;line-height:1.7;overflow-wrap:anywhere}
    .exam-answer-options{display:grid;gap:.45rem}
    .exam-answer-option{display:flex!important;align-items:flex-start;gap:.7rem;padding:.7rem .75rem;border:1px solid #dce6de;border-radius:6px;background:#fff;cursor:pointer;transition:background .15s,border-color .15s}
    .exam-answer-option:has(input:checked){border-color:#72a881;background:#f2faf3}
    .exam-answer-option input{width:1rem;height:1rem;flex:0 0 1rem;margin:.22rem 0 0;accent-color:#287741}
    .exam-option-key{display:grid;width:1.4rem;height:1.4rem;flex:0 0 1.4rem;place-items:center;border:1px solid #cddbd0;border-radius:50%;color:#476451;font-size:.7rem;font-weight:800}
    .exam-answer-option:has(input:checked) .exam-option-key{border-color:#468358;background:#34794a;color:#fff}
    .exam-option-text{color:#344d3c;line-height:1.5}
    .exam-essay-answer{display:grid!important;gap:.45rem!important;color:#536b5a;font-size:.78rem;font-weight:700}
    .exam-essay-answer textarea{width:100%;min-height:9rem;resize:vertical;padding:.8rem;border:1px solid #cad9ce;border-radius:6px;background:#fcfefc;color:#243a2e;font:inherit;font-weight:450;line-height:1.65}
    .exam-essay-answer textarea:focus{border-color:#51865e;outline:3px solid #51865e24}
    .exam-submit-bar{position:sticky;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 1rem;border:1px solid #d6e1d9;border-radius:9px;background:#ffffffed;box-shadow:0 -8px 24px #17392710;backdrop-filter:blur(10px)}
    .exam-submit-bar p{margin:0;color:#66786c;font-size:.78rem}
    .exam-submit-button{min-height:42px}
    .exam-confirm-dialog{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);width:min(420px,calc(100vw - 2rem));margin:0;padding:1.5rem;border:1px solid #d5e1d8;border-radius:10px;background:#fff;color:#243a2e;box-shadow:0 24px 80px #102d1e35}
    .exam-confirm-dialog::backdrop{background:#112a1d70;backdrop-filter:blur(3px)}
    .exam-confirm-icon{display:grid;width:2.6rem;height:2.6rem;place-items:center;border-radius:8px;background:#eaf4eb;color:#2b7042}
    .exam-confirm-icon svg{width:1.25rem;height:1.25rem}
    .exam-confirm-dialog h2{margin:.85rem 0 .4rem;color:#1d3e2d;font-size:1.15rem}
    .exam-confirm-dialog p{margin:0;color:#607166;line-height:1.6}
    .exam-confirm-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.2rem}
    .exam-complete-panel{border-color:#b9ddc2;background:#f5fbf5}
    .exam-result-score{display:grid;justify-items:end;gap:.2rem;padding-left:1rem;border-left:1px solid #cfe0d1}
    .exam-result-score strong{color:#1c6b36;font-size:1.6rem;font-variant-numeric:tabular-nums}
    .exam-result-pending{max-width:260px}
    .exam-result-pending strong{font-size:.9rem;line-height:1.4;text-align:right}
    .exam-feedback{padding:1rem 1.1rem;border-left:3px solid #5b8c64;background:#f7faf7}
    .exam-feedback span{color:#5c7562;font-size:.66rem;font-weight:850;letter-spacing:.08em}
    .exam-feedback p{margin:.4rem 0 0;color:#304738;line-height:1.6}
    .exam-local-draft{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border:1px solid #f3d29c;border-left:4px solid #c47818;border-radius:7px;background:#fffbf2;color:#71470d}
    .exam-local-draft[hidden]{display:none}
    .exam-local-draft p{margin:.3rem 0 0;color:#846331;font-size:.85rem;line-height:1.5}
    .exam-results-panel{display:grid;gap:1rem;padding:1.2rem;border:1px solid #d5e1d8;border-radius:9px;background:#fff}
    .exam-results-heading{display:flex;align-items:end;justify-content:space-between;gap:1rem}
    .exam-results-heading h2{margin:0;color:#1d3e2d;font-size:1.15rem}
    .exam-results-heading>span{color:#64786b;font-size:.78rem}
    .lms-dialog{position:fixed;inset:50% auto auto 50%;transform:translate(-50%,-50%);width:min(440px,calc(100vw - 2rem));margin:0;padding:1.25rem;border:1px solid #d5e1d8;border-radius:10px;background:#fff;color:#243a2e;box-shadow:0 24px 80px #102d1e35}
    .lms-dialog::backdrop{background:#112a1d70;backdrop-filter:blur(3px)}
    .lms-dialog-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1.1rem}
    @media(max-width:700px){.exam-page-heading{align-items:flex-start;flex-direction:column}.exam-page-heading>.button{width:100%;justify-content:center}.exam-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.exam-detail-grid>div:nth-child(2){border-right:0}.exam-detail-grid>div:nth-child(-n+2){border-bottom:1px solid #e6eee8}.exam-state-panel{align-items:flex-start;flex-wrap:wrap}.exam-state-copy{flex-basis:calc(100% - 4.5rem)}.exam-start-button{width:100%;justify-content:center}.exam-live-toolbar{top:70px}.exam-countdown{min-width:110px}.exam-submit-bar{align-items:stretch;flex-direction:column}.exam-submit-button{justify-content:center}.exam-results-heading{align-items:flex-start;flex-direction:column}.exam-review-heading{align-items:flex-start;flex-direction:column}}
</style>
@endpush

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
