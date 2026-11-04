@extends('layouts.simak')

@section('title', 'Hasil Survey EDOM | SIAKAD')
@section('topbar_title', 'Hasil Survey EDOM')

@push('styles')
    <style>
        .edom-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 340px)); align-items: end; gap: 12px; margin: 18px 0 22px; padding: 15px; border: 1px solid #e2e9e3; border-radius: 7px; background: #fbfcfa; }
        .edom-filters label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .edom-teacher-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)); gap: 14px; }
        .edom-teacher { min-width: 0; padding: 18px; border: 1px solid #e2e9e3; border-top: 3px solid #4b7c5e; border-radius: 7px; background: #fff; }
        .edom-teacher-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #edf0e8; }
        .edom-teacher-name { color: #2d4e38; font-size: 14px; font-weight: 800; line-height: 1.45; }
        .edom-teacher-stats { display: flex; flex-wrap: wrap; gap: 6px 12px; margin-top: 6px; color: #76806e; font-size: 9px; }
        .edom-download { display: inline-grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border: 1px solid #d5e2d5; border-radius: 5px; background: #f6faf4; color: #356b45; }
        .edom-aspect { margin-top: 12px; }
        .edom-aspect-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 5px; color: #52614e; font-size: 10px; }
        .edom-aspect-head strong { color: #315b3b; }
        .edom-aspect-track { height: 8px; overflow: hidden; border-radius: 6px; background: #edf1e9; }
        .edom-aspect-fill { height: 100%; border-radius: inherit; transition: width .35s ease; }
        .edom-aspect-count { display: block; margin-top: 3px; color: #89917c; font-size: 9px; }
        .edom-empty { padding: 34px 20px; border: 1px dashed #d4dfd4; border-radius: 7px; background: #fbfdfb; color: #68745f; font-size: 12px; line-height: 1.6; text-align: center; }
        .edom-loading { display: flex; min-height: 160px; align-items: center; justify-content: center; gap: 10px; color: #58705b; font-size: 12px; }
        .edom-loading-mark { width: 18px; height: 18px; border: 2px solid #d4e4d4; border-top-color: #4f865c; border-radius: 50%; animation: edom-spin .75s linear infinite; }
        @keyframes edom-spin { to { transform: rotate(360deg); } }
        @media (max-width: 620px) { .edom-filters { grid-template-columns: 1fr; } .edom-teacher { padding: 15px; } }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Evaluasi akademik</p>
            <h1>Hasil Survey EDOM</h1>
            <div class="module-alert" role="note"><p class="subtitle">Evaluasi dosen oleh mahasiswa dikelompokkan menurut program studi dan kalender akademik.</p></div>
        </div>
        @if (in_array($user->role, ['admin', 'pimuniv', 'pimfak'], true))
            <a class="button" href="{{ route('survey-edom.questions.index') }}"><span data-lucide="list-checks"></span> Instrumen Survey</a>
        @endif
    </div>

    <div
        data-edom-results
        data-data-url="{{ route('survey-edom.results.data') }}"
        data-selected-program="{{ $selectedProgram }}"
        data-selected-calendar="{{ $selectedCalendar }}"
        data-show-program-filter="{{ $showProgramFilter ? 'true' : 'false' }}"
    >
        <script type="application/json" data-edom-programs>@json($programs->map(fn ($program) => ['id' => (string) $program->id_prodi, 'label' => $program->nama_program_studi.($program->nama_jenjang_pendidikan ? ' ('.$program->nama_jenjang_pendidikan.')' : '')])->values())</script>
        <script type="application/json" data-edom-calendars>@json($calendarOptionsByProgram)</script>
        <div data-edom-results-mount></div>
    </div>
@endsection