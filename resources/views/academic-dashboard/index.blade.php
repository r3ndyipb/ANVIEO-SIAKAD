@extends('layouts.simak')

@section('title', 'Dashboard Akademik | SIAKAD')
@section('topbar_title', 'Dashboard Akademik')

@section('content')
    <section class="academic-dashboard-head">
        <div>
            <p class="eyebrow">Pemantauan eksekutif · Akademik</p>
            <h1>Dashboard Akademik</h1>
            <p>Ringkasan proses akademik per kalender untuk mendukung pemantauan lintas program studi.</p>
        </div>
        <div class="academic-scope">
            <span>Cakupan akun</span>
            <strong>{{ $scopeLabel }}</strong>
        </div>
    </section>

    <div
        data-academic-dashboard
        data-data-url="{{ route('academic-dashboard.data') }}"
        data-export-url="{{ route('academic-dashboard.export.excel') }}"
        data-insights-url="{{ route('academic-dashboard.insights') }}"
        data-dtps-details-url="{{ route('academic-dashboard.dtps-details') }}"
        data-selected-year="{{ $selectedYear }}"
    >
        <script type="application/json" data-academic-calendar-options>@json($calendarOptions)</script>
        <div data-academic-dashboard-mount></div>
    </div>
@endsection