@extends('layouts.simak')

@section('title', 'Hasil Survey EDOM | SIAKAD')
@section('topbar_title', 'Hasil Survey EDOM')

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