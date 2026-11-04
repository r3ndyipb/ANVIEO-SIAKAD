@extends('layouts.simak')

@section('title', ($question ? 'Ubah' : 'Tambah').' Pertanyaan EDOM | SIAKAD')
@section('topbar_title', 'Survey EDOM')

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Evaluasi akademik · Instrumen</p>
            <h1>{{ $question ? 'Ubah Pertanyaan' : 'Tambah Pertanyaan' }}</h1>
        </div>
        <a class="button" href="{{ route('survey-edom.questions.index') }}"><span data-lucide="arrow-left"></span> Kembali</a>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif

    <section class="panel" style="max-width: 900px">
        <form method="POST" action="{{ $question ? route('survey-edom.questions.update', $question->id_survey) : route('survey-edom.questions.store') }}">
            @csrf
            @if ($question) @method('PUT') @endif
            <div>
                <label for="edom-question">Pertanyaan EDOM</label>
                <textarea id="edom-question" name="pertanyaan" rows="4" maxlength="2000" required>{{ old('pertanyaan', $question->pertanyaan ?? '') }}</textarea>
                @error('pertanyaan') <small class="field-error">{{ $message }}</small> @enderror
            </div>
            <div class="form-actions">
                <a class="button" href="{{ route('survey-edom.questions.index') }}">Batal</a>
                <button class="button button-primary" type="submit"><span data-lucide="save"></span> Simpan Pertanyaan</button>
            </div>
        </form>
    </section>
@endsection