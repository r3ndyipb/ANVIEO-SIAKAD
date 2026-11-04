@extends('layouts.simak')

@section('title', 'Dashboard Akademik | SIAKAD')
@section('topbar_title', 'Dashboard Akademik')

@push('styles')
    <style>
        .academic-dashboard-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 20px; padding: 30px clamp(20px, 4vw, 38px); border: 1px solid #d7e3dd; border-radius: 8px; background: linear-gradient(115deg, #edf5ef, #fbf5df); }
        .academic-dashboard-head h1 { font-size: 36px; }
        .academic-dashboard-head p { margin: 10px 0 0; color: #60715f; font-size: 12px; line-height: 1.6; }
        .academic-scope { flex: 0 0 auto; min-width: 190px; padding-left: 18px; border-left: 1px solid #d0ded4; }
        .academic-scope span { display: block; color: #75815e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .academic-scope strong { display: block; margin-top: 5px; color: #315b3b; font-size: 12px; line-height: 1.5; }
        .academic-filter { display: grid; grid-template-columns: minmax(240px, 440px) auto; align-items: end; gap: 12px; margin: 18px 0 22px; padding: 16px; border: 1px solid #e1e8e1; border-radius: 7px; background: #fbfcfa; }
        .academic-filter label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .academic-filter-note { grid-column: 1 / -1; margin: 0; color: #75806d; font-size: 10px; }
        .academic-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 150px), 1fr)); gap: 10px; margin: 0 0 20px; }
        .academic-card { min-width: 0; padding: 16px; border: 1px solid #e0e8e0; border-top: 3px solid #689070; border-radius: 6px; background: #fff; }
        .academic-card:nth-child(2) { border-top-color: #d0a83d; }
        .academic-card:nth-child(3) { border-top-color: #4c7eae; }
        .academic-card:nth-child(4) { border-top-color: #7b8e55; }
        .academic-card:nth-child(5) { border-top-color: #b87056; }
        .academic-card:nth-child(6) { border-top-color: #278f89; }
        .academic-card-label { color: #69745c; font-size: 9px; font-weight: 800; line-height: 1.4; text-transform: uppercase; }
        .academic-card-value { display: block; margin-top: 9px; color: #244a32; font: 400 29px/1 Georgia, serif; }
        .academic-card-detail { display: block; margin-top: 7px; color: #7a8475; font-size: 9px; line-height: 1.45; }
        .academic-section { margin-top: 18px; padding: 20px; border: 1px solid #e1e8e1; border-radius: 7px; background: #fff; }
        .academic-section-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
        .academic-section h2 { margin: 0; color: #294c35; font: 400 22px/1.2 Georgia, serif; }
        .academic-section-head p { margin: 6px 0 0; color: #77806b; font-size: 10px; line-height: 1.5; }
        .academic-term-chip { flex: 0 0 auto; padding: 6px 9px; border: 1px solid #d8e5d5; border-radius: 5px; background: #f1f7ee; color: #376347; font-size: 10px; font-weight: 800; }
        .academic-table-wrap { overflow-x: auto; }
        .academic-table { width: 100%; min-width: 1120px; border-collapse: collapse; }
        .academic-table th, .academic-table td { padding: 10px 9px; border-bottom: 1px solid #edf0e8; text-align: right; font-size: 10px; }
        .academic-table th { color: #738069; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .academic-table td:first-child, .academic-table th:first-child { text-align: left; }
        .academic-table td:first-child strong, .academic-table td:first-child small { display: block; }
        .academic-table td:first-child strong { color: #31533b; font-size: 10px; }
        .academic-table td:first-child small { margin-top: 3px; color: #89917c; font-size: 9px; }
        .academic-dtps-trigger { padding: 2px 4px; border: 0; border-radius: 3px; background: transparent; color: #2d6841; font: inherit; font-weight: 800; text-decoration: underline; cursor: pointer; }
        .academic-number-link { color: #2d6841; font-weight: 800; text-decoration: underline; text-underline-offset: 2px; }
        .academic-number-link:hover, .academic-number-link:focus-visible { color: #1d4c30; }
        .academic-dtps-backdrop { position: fixed; z-index: 1200; inset: 0; display: grid; place-items: center; overflow: auto; padding: 20px; background: #18271db3; }
        .academic-dtps-dialog { width: min(900px, 100%); max-height: min(82vh, 760px); overflow: auto; padding: 22px; border: 1px solid #dce7d7; border-radius: 8px; background: #fff; box-shadow: 0 18px 55px #203c2638; }
        .academic-dtps-dialog > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .academic-dtps-dialog > header span { color: #71805e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .academic-dtps-dialog h2 { margin: 5px 0 0; color: #294c35; font: 400 22px/1.25 Georgia, serif; }
        .academic-dtps-dialog > header button { width: 34px; height: 34px; border: 1px solid #dce7d7; border-radius: 50%; background: #fff; color: #405340; font-size: 21px; cursor: pointer; }
        .academic-dtps-table { min-width: 600px; }
        .academic-dtps-table ul { display: grid; gap: 4px; margin: 0; padding-left: 18px; text-align: left; }
        .academic-rate { min-width: 105px; color: #364e3c; font-weight: 800; }
        .academic-rate-track { height: 5px; overflow: hidden; margin-top: 5px; border-radius: 5px; background: #edf1e9; }
        .academic-rate-bar { height: 100%; border-radius: inherit; background: #5c9670; }
        .academic-rate-bar.is-gold { background: #c9a13b; }
        .academic-rate-bar.is-blue { background: #537da8; }
        .academic-rate-bar.is-rust { background: #b86c56; }
        .academic-trend-table { min-width: 620px; }
        .academic-trend-table td:not(:first-child), .academic-trend-table th:not(:first-child) { text-align: center; }
        .academic-empty { padding: 28px 18px; border: 1px dashed #d5dfd3; border-radius: 6px; background: #fbfdfb; color: #68745f; font-size: 12px; line-height: 1.6; text-align: center; }
        .academic-chart-scroll { position: relative; overflow-x: auto; }
        .academic-chart { display: block; min-width: 760px; width: 100%; }
        .academic-chart-grid { stroke: #e6ece6; stroke-width: 1; }
        .academic-chart-label { fill: #75806e; font: 10px "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        .academic-chart-line { fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
        .academic-chart-point { stroke: #fff; stroke-width: 2; cursor: help; }
        .academic-chart-point:focus { outline: none; stroke: #263e2b; stroke-width: 3; }
        .academic-chart-tooltip { position: absolute; z-index: 4; max-width: min(320px, calc(100vw - 48px)); padding: 8px 10px; border: 1px solid #dbe5d9; border-radius: 5px; background: #fff; box-shadow: 0 6px 20px #203c2630; color: #344a36; font-size: 10px; line-height: 1.5; pointer-events: none; }
        .academic-chart-legend { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
        .academic-chart-legend button { display: inline-flex; align-items: center; gap: 7px; padding: 6px 9px; border: 1px solid #e1e8e1; border-radius: 5px; background: #fff; color: #4d604d; font-size: 10px; cursor: pointer; }
        .academic-chart-legend button[aria-pressed="false"] { opacity: .45; text-decoration: line-through; }
        .academic-chart-swatch { width: 9px; height: 9px; flex: 0 0 9px; border-radius: 50%; }
        .academic-edom-chart { display: grid; gap: 10px; margin: 0 0 18px; }
        .academic-edom-row { display: grid; grid-template-columns: minmax(130px, 260px) minmax(80px, 1fr) auto; align-items: center; gap: 11px; color: #465b47; font-size: 10px; }
        .academic-edom-label { overflow-wrap: anywhere; }
        .academic-edom-track { height: 10px; overflow: hidden; border-radius: 99px; background: #edf1e9; }
        .academic-edom-bar { display: block; height: 100%; border-radius: inherit; background: #537da8; }
        .academic-edom-value { color: #315b3b; font-weight: 800; white-space: nowrap; }
        .academic-metric-toggle { display: inline-flex; gap: 4px; padding: 3px; border: 1px solid #e1e8e1; border-radius: 6px; background: #f8faf7; }
        .academic-metric-toggle button { min-height: 30px; padding: 0 11px; border: 0; border-radius: 4px; background: transparent; color: #5d6e5a; font-size: 10px; font-weight: 700; cursor: pointer; }
        .academic-metric-toggle button[aria-pressed="true"] { background: #e8f1e7; color: #2e6842; }
        .academic-progress-label { fill: #465b47; font: 10px "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        .academic-progress-value { fill: #465b47; font: 700 9px "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        .academic-loading { display: flex; min-height: 180px; align-items: center; justify-content: center; gap: 10px; color: #58705b; font-size: 12px; }
        .academic-loading-mark { width: 18px; height: 18px; border: 2px solid #d4e4d4; border-top-color: #4f865c; border-radius: 50%; animation: academic-spin .75s linear infinite; }
        @keyframes academic-spin { to { transform: rotate(360deg); } }
        @media (max-width: 1050px) { .academic-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 680px) {
            .academic-dashboard-head { align-items: stretch; flex-direction: column; gap: 16px; }
            .academic-dashboard-head h1 { font-size: 32px; }
            .academic-scope { padding: 12px 0 0; border-top: 1px solid #d0ded4; border-left: 0; }
            .academic-filter { grid-template-columns: 1fr; }
            .academic-filter-note { grid-column: auto; }
            .academic-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
            .academic-card { padding: 13px; }
            .academic-card-value { font-size: 25px; }
            .academic-section { padding: 15px 12px; }
            .academic-section-head { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

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