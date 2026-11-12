@extends('layouts.simak')

@section('title', 'Ruang Bimbingan | SIAKAD')
@section('topbar_title', 'Ruang Bimbingan Skripsi')

@push('styles')
    <style>
        .thesis-chat-messages { height: min(58vh, 620px); overflow-y: auto; }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div><p class="eyebrow">{{ $thesis->nama_program_studi }}</p><h1>{{ $thesis->nama_mahasiswa }}</h1>
            <p class="subtitle">{{ $thesis->nim }} · {{ $statusLabel }}</p></div>
        <a class="button" href="{{ route('theses.bimbingan.index') }}">Kembali ke daftar</a>
    </div>
    <section class="thesis-section">
        <h2>{{ $thesis->judul }}</h2>
        <p class="subtitle">{{ $thesis->nama_fakultas }}</p>
        <div class="thesis-chat-root" data-thesis-chat
            data-thesis-id="{{ $thesis->id }}"
            data-role="{{ $user->role }}"
            data-teacher-id="{{ $teacherId }}"
            data-status="{{ $thesis->status }}"
            data-messages-url-template="{{ route('theses.bimbingan.messages', '__THESIS__') }}"
            data-send-url-template="{{ route('theses.bimbingan.send', '__THESIS__') }}">
            <script type="application/json" data-thesis-contacts>@json($contacts)</script>
            <div data-thesis-chat-mount></div>
        </div>
    </section>
@endsection