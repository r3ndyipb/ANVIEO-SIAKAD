@extends('layouts.simak')

@section('title', 'Instrumen Survey EDOM | SIAKAD')
@section('topbar_title', 'Survey EDOM')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Evaluasi akademik</p>
            <h1>Instrumen Survey EDOM</h1>
            <div class="module-alert" role="note"><p class="subtitle">Pertanyaan ini ditampilkan kepada mahasiswa pada setiap mata kuliah yang diprogramkan.</p></div>
        </div>
        @if ($canEdit)
            <a class="button button-primary" href="{{ route('survey-edom.questions.create') }}"><span data-lucide="plus"></span> Tambah Pertanyaan</a>
        @endif
    </div>

    @if (session('status')) <div class="alert alert-success" role="status">{{ session('status') }}</div> @endif

    @if ($questions->isEmpty())
        <div class="master-empty-state"><strong>Instrumen EDOM belum tersedia</strong>Belum ada pertanyaan yang dapat dijawab mahasiswa.</div>
    @else
        <div class="table-wrap">
            <table class="edom-question-table" data-simple-search-table>
                <thead><tr><th>No</th><th>Pertanyaan</th><th>ID Aspek</th>@if ($canEdit)<th class="no-export">Aksi</th>@endif</tr></thead>
                <tbody>
                    @foreach ($questions as $question)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="edom-question-text">{{ $question->pertanyaan }}</td>
                            <td>{{ $question->id_survey }}</td>
                            @if ($canEdit)
                                <td><a class="table-icon-button" href="{{ route('survey-edom.questions.edit', $question->id_survey) }}" aria-label="Ubah pertanyaan {{ $question->id_survey }}" title="Ubah pertanyaan"><span data-lucide="pencil"></span></a></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    <p class="subtitle">Total pertanyaan: {{ number_format($questions->count()) }} · Aspek hasil mengikuti ID instrumen 1–6.</p>
@endsection