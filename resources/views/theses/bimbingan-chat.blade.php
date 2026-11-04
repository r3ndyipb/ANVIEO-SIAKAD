@extends('layouts.simak')

@section('title', 'Ruang Bimbingan | SIAKAD')
@section('topbar_title', 'Ruang Bimbingan Skripsi')

@push('styles')
    <style>
        .thesis-chat-root { min-height: 420px; }
        .thesis-chat-empty { padding: 32px 16px; color: #68745f; text-align: center; }
        .thesis-contact-tabs { display: flex; gap: 6px; overflow-x: auto; margin-bottom: 12px; border-bottom: 1px solid #dfe7dc; }
        .thesis-contact-tabs button { min-width: 150px; padding: 10px 12px; border: 0; border-bottom: 3px solid transparent; background: transparent; color: #52634b; text-align: left; cursor: pointer; }
        .thesis-contact-tabs button.is-active { border-bottom-color: #39734a; color: #244a32; }
        .thesis-contact-tabs span, .thesis-contact-tabs small { display: block; }
        .thesis-contact-tabs span { font-size: 11px; font-weight: 800; }
        .thesis-contact-tabs small { margin-top: 4px; color: #78826e; font-size: 10px; }
        .thesis-chat-heading { display: flex; justify-content: space-between; gap: 12px; padding: 10px 14px; border: 1px solid #e0e8e0; border-bottom: 0; background: #fbfcfa; }
        .thesis-chat-heading strong, .thesis-chat-heading small { display: block; }
        .thesis-chat-heading small, .thesis-chat-heading > span { color: #77806b; font-size: 10px; }
        .thesis-chat-messages { display: flex; box-sizing: border-box; width: 100%; height: min(58vh, 620px); min-height: 300px; max-height: 620px; flex: 0 0 auto; flex-direction: column; gap: 10px; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; padding: 16px; border: 1px solid #e0e8e0; background: #f3f7f2; }
        .thesis-message { display: flex; flex: 0 0 auto; justify-content: flex-start; }
        .thesis-message.is-own { justify-content: flex-end; }
        .thesis-message-bubble { max-width: min(78%, 680px); padding: 10px 12px; border: 1px solid #e0e8e0; border-radius: 8px 8px 8px 2px; background: #fff; overflow-wrap: anywhere; }
        .thesis-message.is-own .thesis-message-bubble { border-color: #c9e2ce; border-radius: 8px 8px 2px 8px; background: #eaf5e9; }
        .thesis-message-bubble strong { display: block; color: #315b3b; font-size: 10px; }
        .thesis-message-bubble p { margin: 6px 0; color: #334638; font-size: 12px; line-height: 1.55; white-space: pre-wrap; }
        .thesis-message-bubble a { color: #286997; font-size: 11px; }
        .thesis-message-bubble small, .thesis-approval small { display: block; margin-top: 6px; color: #7a8475; font-size: 9px; text-align: right; }
        .thesis-message.is-system { justify-content: center; }
        .thesis-approval { max-width: min(82%, 640px); padding: 9px 14px; border: 1px solid #b9d9c2; border-radius: 7px; background: #e9f6ec; color: #245a36; text-align: center; }
        .thesis-approval p { margin: 5px 0 0; font-size: 11px; white-space: pre-wrap; }
        .thesis-chat-compose { display: grid; gap: 9px; padding: 12px; border: 1px solid #e0e8e0; border-top: 0; border-radius: 0 0 6px 6px; background: #fbfcfa; }
        .thesis-chat-compose textarea { width: 100%; min-height: 72px; padding: 10px 12px; border: 1px solid #c8cc9d; border-radius: 5px; resize: vertical; }
        .thesis-chat-compose-footer { display: flex; gap: 8px; }
        .thesis-chat-compose-footer input { flex: 1; min-width: 0; }
        .thesis-chat-type { max-width: 440px; }
        .thesis-chat-readonly { padding: 12px; border: 1px solid #e0e8e0; border-top: 0; background: #f8faf7; color: #68745f; font-size: 11px; }
        @media(max-width: 640px) { .thesis-chat-heading { flex-direction: column; } .thesis-message-bubble { max-width: 90%; } .thesis-chat-compose-footer { flex-direction: column; } }
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