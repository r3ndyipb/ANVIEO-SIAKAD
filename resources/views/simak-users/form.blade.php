@extends('layouts.simak')

@php
    $isEditing = $account !== null;
    $isProgramAccount = in_array($type, ['userprodi', 'usertu'], true);
    $selectedLevel = old('level', $account->level ?? ($type === 'userpim' ? 'pimuniv' : ''));
@endphp

@section('title', ($isEditing ? 'Ubah' : 'Tambah').' '.$typeLabel.' | SIAKAD')
@section('topbar_title', 'Pengguna SIAKAD')

@push('styles')
    <style>
        .simak-user-form { max-width: 920px; }
        .simak-user-form .field-grid { margin-bottom: 18px; }
        .simak-user-form .field-error { display: block; margin-top: 5px; color: #a33b30; font-size: 11px; }
    </style>
@endpush

@section('content')
    <div class="page-heading">
        <div>
            <p class="eyebrow">Administrasi · {{ $typeLabel }}</p>
            <h1>{{ $isEditing ? 'Ubah Pengguna' : 'Tambah Pengguna' }}</h1>
        </div>
        <a class="button" href="{{ route('simak-users.index', ['type' => $type]) }}"><span data-lucide="arrow-left"></span> Kembali</a>
    </div>

    @if ($errors->any()) <div class="calendar-modal-error" role="alert">{{ $errors->first() }}</div> @endif

    <section class="panel simak-user-form">
        @include('simak-users.fields')
    </section>
@endsection
