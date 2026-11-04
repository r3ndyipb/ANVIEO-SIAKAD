<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <title>Layanan Web SIAKAD | Dokumentasi API</title>
    @vite(['resources/js/api-docs.js'])
    <style>
        :root { color-scheme: light; --brand-primary: {{ $siteConfiguration['primary_color'] }}; --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }}; --ink: #203a55; --muted: #61758a; --line: #d8e3ee; --forest: var(--brand-primary); --gold: #e0bf49; --danger: #9a4146; --sidebar: color-mix(in srgb, var(--brand-primary), #102f51 58%); font-family: "Avenir Next", Avenir, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-width: 320px; margin: 0; color: var(--ink); background: linear-gradient(128deg, #d9eaff 0%, #fff0bd 50%, #c8def8 100%); }
        button, input, select, textarea { font: inherit; }
        button { cursor: pointer; }
        .ws-topbar { display: flex; min-height: 70px; align-items: center; justify-content: space-between; gap: 20px; padding: 10px clamp(16px, 4vw, 52px); border-bottom: 1px solid var(--line); background: #fff; color: var(--ink); }
        .ws-brand { display: flex; align-items: center; gap: 13px; min-width: 0; color: inherit; text-decoration: none; }
        .ws-brand img { width: 42px; height: 42px; object-fit: contain; }
        .ws-brand strong { display: block; color: var(--ink); font-size: 16px; font-weight: 750; line-height: 1.2; }
        .ws-brand small { display: block; margin-top: 4px; color: var(--muted); font-size: 11px; }
        .ws-topbar-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; }
        .ws-topbar a { display: inline-flex; min-height: 40px; align-items: center; gap: 7px; padding: 0 14px; border: 1px solid var(--line); border-radius: 9px; background: #fff; color: #36465a; font-size: 11px; font-weight: 750; text-decoration: none; transition: background .2s ease, border-color .2s ease, box-shadow .2s ease; }
        .ws-topbar a:hover { border-color: #c5d2df; background: #f7f9fc; box-shadow: 0 2px 7px rgba(23, 38, 56, .08); }
        .ws-shell { display: grid; min-height: calc(100vh - 70px); grid-template-columns: 280px minmax(0, 1fr); }
        .ws-sidebar { position: sticky; top: 0; height: calc(100vh - 70px); overflow: auto; padding: 20px 14px; border-right: 1px solid var(--brand-primary); background: linear-gradient(160deg, color-mix(in srgb, var(--brand-primary), white 8%) 0%, var(--brand-primary) 56%, var(--sidebar) 100%); color: #f7fbff; }
        .ws-sidebar-title { margin: 2px 8px 12px; color: #dbeaff; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .ws-search { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; padding: 0 10px; border: 1px solid #42546a; border-radius: 7px; background: #172638; color: #aebdcd; }
        .ws-search input { width: 100%; min-width: 0; height: 38px; border: 0; outline: 0; background: transparent; color: #fff; font-size: 12px; }
        .ws-search input::placeholder { color: #aebdcd; }
        .ws-method-filter { display: flex; gap: 5px; margin: 0 6px 12px; }
        .ws-method-filter button { padding: 5px 7px; border: 1px solid rgba(255, 224, 138, .3); border-radius: 5px; background: rgba(255, 255, 255, .05); color: #dbeaff; font-size: 9px; font-weight: 700; }
        .ws-method-filter button[aria-pressed="true"] { border-color: #e0bf49; background: rgba(255, 224, 138, .15); color: #ffe08a; }
        .ws-method-legend { margin: 0 8px 12px; color: #b7cce0; font-size: 9px; line-height: 1.55; }
        .ws-operation-list { display: grid; gap: 3px; }
        .ws-operation { display: grid; grid-template-columns: 68px minmax(0, 1fr); align-items: center; gap: 8px; width: 100%; padding: 8px 7px; border: 1px solid transparent; border-radius: 5px; background: transparent; color: #e4eaf1; text-align: left; }
        .ws-operation:hover, .ws-operation[aria-current="true"] { border-color: rgba(255, 224, 138, .35); background: rgba(255, 224, 138, .14); }
        .ws-method { display: inline-block; padding: 4px 3px; border-radius: 3px; background: rgba(54, 190, 153, .16); color: #61dbad; font-size: 8px; font-weight: 900; text-align: center; white-space: nowrap; }
        .ws-method[data-method="POST"] { background: rgba(255, 224, 138, .17); color: #ffe08a; }
        .ws-method[data-method="PUT"], .ws-method[data-method="PATCH"] { background: rgba(157, 192, 247, .18); color: #b4d0ff; }
        .ws-method[data-method="DELETE"] { background: rgba(247, 168, 156, .17); color: #ffc0b4; }
        .ws-operation-name { overflow: hidden; font-size: 10px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
        .ws-empty { padding: 14px; color: #bac7d5; font-size: 11px; }
        .ws-main { min-width: 0; padding: clamp(18px, 3vw, 38px); background: linear-gradient(145deg, rgba(218, 235, 255, .68), rgba(255, 241, 198, .42)); }
        .ws-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 20px; padding: clamp(20px, 3vw, 38px); border: 1px solid #d2e3f4; border-radius: 9px; background: linear-gradient(115deg, #f1f7ff, #fff8e5); }
        .ws-kicker { margin: 0 0 7px; color: var(--brand-primary); font-size: 10px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; color: color-mix(in srgb, var(--brand-primary), #102f51 38%); font-size: clamp(26px, 3vw, 38px); font-weight: 750; letter-spacing: -.035em; line-height: 1.1; }
        .ws-hero-copy { max-width: 720px; margin: 9px 0 0; color: var(--muted); font-size: 12px; line-height: 1.65; }
        .ws-mode { display: inline-flex; padding: 4px; border: 1px solid var(--line); border-radius: 8px; background: #edf1f6; }
        .ws-mode button { min-height: 34px; padding: 0 12px; border: 1px solid transparent; border-radius: 6px; background: transparent; color: #596b80; font-size: 11px; font-weight: 700; }
        .ws-mode button[aria-pressed="true"] { border-color: #d8e3ee; background: #fff; color: #18558e; box-shadow: 0 1px 3px rgba(23, 38, 56, .09); }
        .ws-mode button[data-mode="production"][aria-pressed="true"] { color: var(--danger); }
        .ws-main-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(330px, .82fr); align-items: start; gap: 22px; margin-top: 20px; }
        .ws-section { min-width: 0; margin-bottom: 14px; padding: 17px; border: 1px solid var(--line); border-radius: 9px; background: #fff; }
        .ws-section h2 { margin: 0 0 10px; color: color-mix(in srgb, var(--brand-primary), #102f51 38%); font-size: 16px; font-weight: 700; line-height: 1.3; }
        .ws-section p { color: var(--muted); font-size: 11px; line-height: 1.6; }
        .ws-endpoint { display: flex; align-items: center; gap: 8px; padding: 10px 11px; border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; }
        .ws-endpoint code { overflow-wrap: anywhere; color: #214f79; font-size: 11px; }
        .ws-path-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 9px; margin-top: 10px; }
        .ws-request-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 9px; margin-top: 10px; }
        .ws-request-field { display: grid; min-width: 0; gap: 5px; color: #43566b; font-size: 10px; font-weight: 700; }
        .ws-request-field code { color: #74859a; font-size: 9px; font-weight: 500; }
        .ws-request-form-status { margin: 8px 0 0; color: var(--muted); font-size: 10px; }
        .ws-field { display: grid; gap: 5px; color: #43566b; font-size: 10px; font-weight: 700; }
        .ws-field input, .ws-field textarea, .ws-field select { width: 100%; min-width: 0; padding: 9px; border: 1px solid #b8cde0; border-radius: 5px; background: #fbfdff; color: var(--ink); font-size: 11px; }
        .ws-field textarea { min-height: 165px; resize: vertical; font: 11px/1.55 ui-monospace, SFMono-Regular, Menlo, monospace; }
        .ws-auth-panel { display: grid; gap: 10px; margin-bottom: 12px; padding: 13px; border: 1px solid #d2e3f4; border-left: 3px solid #e1bf43; border-radius: 6px; background: #f1f7ff; }
        .ws-auth-panel[hidden] { display: none; }
        .ws-auth-panel p { margin: 0; }
        .ws-auth-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .ws-token-status { color: #315b7b; font-size: 10px; }
        .ws-token-status.is-empty { color: #74859a; }
        .ws-warning { display: none; margin: 0 0 10px; padding: 10px 11px; border-left: 3px solid #ae4e3e; background: #f9eae6; color: #7f332a; font-size: 10px; line-height: 1.5; }
        body[data-api-mode="production"] .ws-warning { display: block; }
        .ws-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
        .ws-button { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; gap: 7px; padding: 0 14px; border: 1px solid #ddc65f; border-radius: 5px; background: #fff7d8; color: #21496f; font-size: 11px; font-weight: 800; transition: transform .2s ease, box-shadow .2s ease, background .2s ease; }
        .ws-button:hover { transform: translateY(-1px); border-color: #ddc65f; background: #fff0b5; box-shadow: 0 7px 18px rgba(39, 75, 110, .12); }
        .ws-button-primary { border-color: #d9be47; background: linear-gradient(110deg, color-mix(in srgb, var(--brand-primary), #102f51 24%), var(--brand-primary)); box-shadow: inset 0 -3px 0 #e0bf49; color: var(--brand-primary-foreground); }
        .ws-button-primary:hover { background: linear-gradient(110deg, color-mix(in srgb, var(--brand-primary), #102f51 42%), color-mix(in srgb, var(--brand-primary), #102f51 18%)); }
        .ws-button:focus-visible, .ws-topbar a:focus-visible, .ws-method-filter button:focus-visible, .ws-mode button:focus-visible, .ws-tabs button:focus-visible { outline: 3px solid rgba(53, 126, 190, .24); outline-offset: 2px; }
        .ws-response { min-height: 260px; max-height: 560px; overflow: auto; margin: 0; padding: 15px; border: 1px solid #174a78; border-radius: 7px; background: #102f51; color: #e4edf5; font: 11px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; overflow-wrap: anywhere; }
        .ws-response-meta { display: flex; justify-content: space-between; gap: 10px; margin: 0 0 8px; color: #74859a; font-size: 10px; }
        .ws-tabs { display: flex; gap: 13px; margin: 15px 0 9px; border-bottom: 1px solid var(--line); }
        .ws-tabs button { padding: 0 0 8px; border: 0; border-bottom: 2px solid transparent; background: transparent; color: #718197; font-size: 10px; font-weight: 700; }
        .ws-tabs button[aria-selected="true"] { border-color: var(--gold); color: #18558e; }
        .ws-reference { margin-top: 12px; }
        .ws-reference-table { width: 100%; border-collapse: collapse; color: #3d5065; font-size: 10px; text-align: left; }
        .ws-reference-table th, .ws-reference-table td { padding: 8px 9px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .ws-reference-table th { color: #74859a; font-size: 9px; text-transform: uppercase; }
        .ws-reference-table code { color: #24596a; font-size: 10px; }
        .ws-reference-copy { max-width: 800px; color: var(--muted); font-size: 11px; line-height: 1.6; }
        .ws-reference-json { overflow: auto; max-height: 440px; margin: 8px 0 0; padding: 14px; border: 1px solid var(--line); border-radius: 6px; background: #f8fafc; color: #30465e; font: 10px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre; }
        .ws-code { overflow: auto; margin: 0; padding: 14px; border: 1px solid var(--line); border-radius: 6px; background: #f8fafc; color: #30465e; font: 10px/1.6 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre; }
        .ws-notice { margin: 12px 0 0; padding: 10px 12px; border: 1px solid #efdcab; border-left: 3px solid var(--gold); border-radius: 6px; background: #fff8e8; color: #725c2e; font-size: 10px; line-height: 1.6; }
        .ws-warning { border: 1px solid #efd9a1; border-left: 3px solid #c99b32; border-radius: 6px; background: #fff8e8; color: #765c25; }
        body[data-api-mode="production"] .ws-warning { color: #765c25; }
        .ws-footer { padding: 25px 0 10px; color: #74859a; font-size: 10px; }
        @media (max-width: 1040px) { .ws-shell { grid-template-columns: 240px minmax(0, 1fr); } .ws-main-grid { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .ws-shell { display: block; } .ws-sidebar { position: static; width: auto; height: 230px; border-right: 0; border-bottom: 1px solid var(--line); } .ws-hero { grid-template-columns: 1fr; align-items: start; } .ws-topbar { align-items: flex-start; } .ws-topbar-actions { justify-content: flex-end; } .ws-auth-row { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; animation-duration: .01ms !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body data-api-base="{{ url('/api/v1') }}" data-operations-url="{{ $operationsUrl }}" data-token-url="{{ $tokenUrl }}" data-sso-url="{{ $ssoUrl }}" data-sandbox-url="{{ $sandboxUrl }}">
    <header class="ws-topbar">
        <a class="ws-brand" href="{{ route('public.home') }}"><img src="{{ $siteConfiguration['logo_url'] }}" alt=""><span><strong>Layanan Web SIAKAD</strong><small>Dokumentasi API {{ $siteConfiguration['institution_name'] }} · v1</small></span></a>
        <div class="ws-topbar-actions"><a href="{{ $guideUrl }}"><i data-lucide="book-open-check"></i> Panduan API (PDF)</a><a href="{{ $openApiUrl }}"><i data-lucide="file-down"></i> Spesifikasi OpenAPI (YAML)</a><span>API REST · JSON · Token</span></div>
    </header>
    <div class="ws-shell">
        <aside class="ws-sidebar" aria-label="Daftar operasi API">
            <p class="ws-sidebar-title">Jelajahi operasi</p>
            <label class="ws-search"><i data-lucide="search"></i><input type="search" placeholder="Cari fitur atau route" data-operation-search></label>
            <div class="ws-method-filter" aria-label="Filter metode">
                @foreach (['ALL' => 'Semua', 'GET' => 'GET', 'POST' => 'POST', 'PUT' => 'PUT', 'PATCH' => 'PATCH', 'DELETE' => 'DELETE'] as $method => $label)
                    <button type="button" data-method-filter="{{ $method }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <p class="ws-method-legend">GET · POST · PUT/PATCH · DELETE</p>
            <button class="ws-operation" type="button" data-sso-menu>
                <span class="ws-method" data-method="POST">POST</span>
                <span class="ws-operation-name">SSO Login SIAKAD</span>
            </button>
            <nav class="ws-operation-list" data-operation-list></nav>
            <p class="ws-empty" data-operation-empty hidden>Tidak ada operasi yang cocok.</p>
        </aside>
        <main class="ws-main">
            <div class="ws-hero"><div><p class="ws-kicker">Layanan Integrasi SIAKAD</p><h1>Dokumentasi API</h1><p class="ws-hero-copy">Jelajahi operasi yang diizinkan, periksa route dan parameter, lalu uji Request. Production menjalankan proses bisnis SIAKAD; Sandbox hanya memakai fixture simulasi.</p></div>
                <div class="ws-mode" role="group" aria-label="Request Mode"><button type="button" data-mode="sandbox" aria-pressed="true">Sandbox</button><button type="button" data-mode="production" aria-pressed="false">Production</button></div>
            </div>
            <div class="ws-main-grid">
                <div>
                    <section class="ws-section"><p class="ws-kicker">Endpoint</p><h2 data-operation-title>Pilih operasi API</h2><div class="ws-endpoint"><span class="ws-method" data-selected-method>GET</span><code data-selected-path>/api/v1</code></div><div class="ws-path-fields" data-path-fields></div></section>
                    <section class="ws-section"><h2 data-request-title>Request Body</h2><p>Sandbox tidak menyimpan perubahan. Production memakai controller, policy, scope, dan audit SIAKAD yang sama.</p>
                        <div class="ws-request-fields" data-request-fields></div>
                        <p class="ws-request-form-status" data-request-form-status></p>
                        <p class="ws-warning">Production dapat mengubah data resmi. Gunakan hanya data yang benar dan telah disetujui; tindakan eksternal dan file dikecualikan dari katalog API ini.</p>
                            <div class="ws-section" data-sso-panel><h2>SSO Login SIAKAD</h2><p>Verifikasi akun SIAKAD untuk mengambil ID, level, dan biodata pengguna.</p>
                                <div class="ws-auth-row"><label class="ws-field">Identitas login<input autocomplete="username" data-sso-login></label><label class="ws-field">Password akun SIAKAD<input type="password" autocomplete="current-password" data-sso-password></label></div>
                                <div class="ws-actions"><button class="ws-button ws-button-primary" type="button" data-sso-login-submit>Uji SSO Login</button><span class="ws-token-status" data-sso-status role="status"></span></div>
                            </div>
                            <div class="ws-auth-panel" data-auth-panel hidden><p><strong>Production Token</strong><br><span class="ws-token-status is-empty" data-token-status>Belum ada token di tab ini.</span></p>
                            <div class="ws-auth-row" data-token-form><label class="ws-field">Username admin_ws<input autocomplete="username" data-token-username></label><label class="ws-field">Password<input type="password" autocomplete="current-password" data-token-password></label></div>
                            <div class="ws-actions"><button class="ws-button ws-button-primary" type="button" data-token-issue><i data-lucide="key-round"></i> Ambil token</button><button class="ws-button" type="button" data-token-clear hidden>Hapus token pada tab ini</button></div>
                        </div>
                        <div class="ws-actions"><button class="ws-button ws-button-primary" type="button" data-send-request><i data-lucide="send"></i> Send Request</button><span class="ws-token-status" data-request-status role="status"></span></div>
                    </section>
                    <section class="ws-section"><h2>Contoh PHP</h2><div class="ws-tabs" role="tablist"><button type="button" data-code-tab="curl" aria-selected="true">cURL</button><button type="button" data-code-tab="stream" aria-selected="false">PHP Stream</button></div><pre class="ws-code" data-code-example></pre><p class="ws-notice">Token hanya disimpan pada sessionStorage tab browser dan tidak pernah disimpan oleh server konsol. Kredensial SSO hanya digunakan saat request dan tidak disimpan. Jangan membagikan token atau kredensial.</p></section>
                </div>
                <section class="ws-section" aria-label="API Response"><p class="ws-kicker">Response</p><h2>Response</h2><p class="ws-response-meta"><span data-response-status>Belum ada Request</span><span data-response-time></span></p><pre class="ws-response" data-response-output>Response akan tampil di sini.</pre>
                    <div class="ws-section"><h2>Ketentuan keamanan</h2><p>Token API berlaku 60 menit dan hanya akun aktif <code>admin_ws</code> yang dapat menerbitkannya. SSO Login memverifikasi kredensial akun SIAKAD, mengembalikan ID, level, dan biodata sesuai jenis akun, tanpa password atau data keluarga; endpoint tidak membuat sesi browser. Kedua endpoint dibatasi jumlah request dan tidak menyimpan kredensial. Sandbox tidak membaca atau menulis database SIAKAD.</p><p>Operasi yang memanggil Feeder/pembayaran, mengirim email, mengubah kredensial, atau mengirim file tidak tersedia pada API v1 ini.</p></div>
                </section>
            </div>
            <section class="ws-section ws-reference" aria-label="Operation reference">
                <p class="ws-kicker">Operation Reference</p>
                <h2>Parameters &amp; schema</h2>
                <div class="ws-tabs" role="tablist" aria-label="Operation reference">
                    <button type="button" data-reference-tab="parameters" aria-selected="true">Parameters</button>
                    <button type="button" data-reference-tab="request" aria-selected="false">Request Body</button>
                    <button type="button" data-reference-tab="response" aria-selected="false">Response</button>
                </div>
                <div data-reference-content></div>
            </section>
            <footer class="ws-footer">SIAKAD {{ $siteConfiguration['institution_name'] }} · Gunakan Production hanya dengan otorisasi.</footer>
        </main>
    </div>
    <script type="application/json" id="ws-operations-data">@json($operations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
</body>
</html>