<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="application-name" content="siakad smartkampus.id">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.simakLocale = @json(app()->getLocale());
        window.simakTranslationsUrl = @json(route('locale.translations', ['v' => md5_file(lang_path('en/ui.php'))]));
    </script>
    <link rel="icon" type="image/png" href="{{ $siteConfiguration['logo_url'] }}">
    <title>@yield('title', $siteConfiguration['app_name'])</title>
    <style>
        :root {
            color-scheme: light;
            --brand-primary: {{ $siteConfiguration['primary_color'] }};
            --brand-primary-foreground: {{ $siteConfiguration['primary_foreground'] }};
            --ink: #24382d;
            --muted: #657365;
            --green: #356b45;
            --green-strong: #214f36;
            --green-deep: #183a2b;
            --green-pale: #e4eed5;
            --yellow: #efca47;
            --yellow-bright: #f7d856;
            --yellow-pale: #fff3b6;
            --surface: #fff;
            --line: #e3e9e0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            font-synthesis: none;
            text-rendering: optimizeLegibility;
        }
        * { box-sizing: border-box; }
        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(128deg, #c8ddc9 0%, #e3eddf 45%, #b9d3c2 100%);
            background-size: 150% 150%;
            animation: wash 20s ease-in-out infinite alternate;
        }
        button, input, select { font: inherit; }
        a { color: inherit; }
        .simak-shell { display: grid; grid-template-columns: 286px minmax(0, 1fr); min-height: 100vh; }
        .simak-sidebar {
            position: sticky;
            top: 0;
            display: flex;
            flex-direction: column;
            width: 286px;
            height: 100vh;
            overflow: hidden;
            padding: 22px 16px 16px;
            background: linear-gradient(160deg, #2e6842 0%, var(--green-deep) 82%);
            color: #f8f5dc;
            box-shadow: 8px 0 30px rgba(39, 69, 40, .12);
            z-index: 30;
        }
        .brand-link { display: flex; align-items: center; gap: 12px; padding: 4px 8px 20px; color: inherit; text-decoration: none; }
        .brand-link img { width: 46px; height: 46px; object-fit: contain; }
        .brand-title { display: block; font-size: 13px; font-weight: 800; }
        .brand-subtitle { display: block; margin-top: 4px; color: #d4dfb8; font-size: 11px; font-weight: 500; }
        .account-mark { display: flex; align-items: center; gap: 11px; margin: 0 2px 20px; padding: 13px 10px; border-top: 1px solid rgba(247, 216, 86, .26); border-bottom: 1px solid rgba(247, 216, 86, .26); }
        .account-avatar { display: grid; width: 36px; height: 36px; flex: 0 0 36px; place-items: center; border: 1px solid rgba(247, 216, 86, .8); border-radius: 50%; background: rgba(247, 216, 86, .16); color: var(--yellow-bright); font-family: Georgia, serif; font-size: 17px; }
        .account-name { overflow: hidden; color: #fffdf1; font-size: 12px; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
        .account-role { display: block; margin-top: 4px; color: #d4dfb8; font-size: 10px; }
        .side-nav { flex: 1; overflow-y: auto; padding: 0 2px 8px; scrollbar-color: rgba(241, 220, 125, .38) transparent; scrollbar-width: thin; }
        .nav-group { margin-bottom: 7px; }
        .nav-group-toggle { display: flex; width: 100%; min-height: 35px; align-items: center; justify-content: space-between; padding: 0 9px; border: 0; background: transparent; color: #d5dfa9; font-size: 10px; font-weight: 800; text-align: left; text-transform: uppercase; cursor: pointer; }
        .nav-group-label { display: inline-flex; min-width: 0; align-items: center; gap: 8px; }
        .nav-group-icon { display: inline-flex; flex: 0 0 15px; color: #f4dc82; }
        .nav-group-icon svg { width: 15px; height: 15px; }
        .nav-group-toggle::after { width: 6px; height: 6px; border-right: 1px solid currentColor; border-bottom: 1px solid currentColor; content: ""; transform: rotate(45deg) translateY(-2px); transition: transform .22s ease; }
        .nav-group-toggle[aria-expanded="false"]::after { transform: rotate(-45deg); }
        .nav-group-items { display: grid; gap: 3px; overflow: hidden; max-height: 1000px; opacity: 1; transition: max-height .26s ease, opacity .2s ease; }
        .nav-group-items.is-collapsed { max-height: 0; opacity: 0; pointer-events: none; }
        .nav-link { position: relative; display: flex; min-height: 37px; align-items: center; justify-content: space-between; gap: 10px; padding: 7px 9px 7px 25px; border-radius: 5px; color: #e6ead7; font-size: 12px; text-decoration: none; transition: background .2s ease, color .2s ease, transform .2s ease; }
        .nav-link::before { position: absolute; left: 10px; width: 5px; height: 5px; border-radius: 50%; background: #b5c98e; content: ""; transition: transform .2s ease, background .2s ease; }
        .nav-link:hover { transform: translateX(2px); background: rgba(255, 242, 183, .1); color: #fff9d3; }
        .nav-link[aria-current="page"] { background: linear-gradient(100deg, #d9ead5, #c9e0ce); color: #254832; font-weight: 800; box-shadow: 0 5px 14px rgba(19, 42, 24, .16); }
        .nav-link[aria-current="page"]::before { background: var(--green-strong); transform: scale(1.35); }
        .nav-link-disabled { color: rgba(230, 234, 215, .65); cursor: default; }
        .nav-link-disabled::before { background: #8eaa7d; }
        .nav-status { flex: 0 0 auto; color: #f4dc82; font-size: 9px; font-weight: 700; }
        .sidebar-foot { padding: 12px 9px 2px; border-top: 1px solid rgba(247, 216, 86, .22); color: #c4d3af; font-size: 10px; }
        .site-footer { padding: 0 24px 20px; color: #536b58; font-size: 11px; text-align: center; }
        .profile-link { color: var(--green-strong); font-size: 11px; font-weight: 700; text-decoration: none; }
        .profile-link:hover { text-decoration: underline; }
        .topbar-account-menu { position: relative; }
        .topbar-account-menu > summary { list-style: none; }
        .topbar-account-menu > summary::-webkit-details-marker { display: none; }
        .topbar-account-trigger { display: flex; min-width: 0; align-items: center; gap: 11px; padding: 7px 10px; border: 1px solid #dbe4e7; border-radius: 10px; background: #fff; color: #24382d; box-shadow: 0 2px 6px rgba(31, 69, 44, .05); cursor: pointer; }
        .topbar-account-trigger:focus-visible { outline: 3px solid rgba(53, 107, 69, .25); outline-offset: 2px; }
        .topbar-account-avatar { display: grid; width: 38px; height: 38px; flex: 0 0 38px; place-items: center; border: 1px solid color-mix(in srgb, var(--brand-primary), white 55%); border-radius: 9px; background: var(--brand-primary); color: var(--brand-primary-foreground); font: 700 15px Georgia, serif; }
        .topbar-account-trigger-copy { display: grid; min-width: 0; gap: 2px; text-align: left; }
        .topbar-account-trigger-copy strong { max-width: 235px; overflow: hidden; color: #263b50; font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }
        .topbar-account-trigger-copy small { color: #718096; font-size: 10px; white-space: nowrap; }
        .topbar-account-chevron { width: 16px; height: 16px; flex: 0 0 16px; color: #8492a6; transition: transform .18s ease; }
        .topbar-account-menu[open] .topbar-account-chevron { transform: rotate(180deg); }
        .topbar-account-panel { position: absolute; top: calc(100% + 8px); right: 0; z-index: 50; width: min(350px, calc(100vw - 24px)); overflow: hidden; border: 1px solid #dce4ec; border-radius: 10px; background: #fff; box-shadow: 0 14px 32px rgba(24, 42, 69, .16); }
        .topbar-account-identity { padding: 16px 18px; border-bottom: 1px solid #e7edf2; background: #fbfcfd; }
        .topbar-account-identity strong { display: block; color: #19283d; font-size: 14px; line-height: 1.35; overflow-wrap: anywhere; }
        .topbar-account-identity span { display: block; margin-top: 5px; color: #65758b; font-size: 11px; line-height: 1.45; overflow-wrap: anywhere; }
        .topbar-account-role { display: inline-flex !important; margin-top: 10px !important; padding: 5px 9px; border: 1px solid #a7ebca; border-radius: 6px; background: #effcf5; color: #087c56 !important; font-size: 10px !important; font-weight: 700; }
        .topbar-account-actions { display: grid; gap: 3px; padding: 8px 12px; }
        .topbar-account-actions form { margin: 0; }
        .topbar-account-action { display: flex; width: 100%; min-height: 42px; align-items: center; gap: 10px; padding: 6px 9px; border: 0; border-radius: 7px; background: transparent; color: var(--green-strong); font: inherit; font-size: 12px; font-weight: 700; text-align: left; text-decoration: none; cursor: pointer; }
        .topbar-account-action:hover { background: #f2f7f2; }
        .topbar-account-action svg { width: 19px; height: 19px; flex: 0 0 19px; }
        .topbar-account-action.is-logout { color: #bd2445; }
        .topbar-account-action.is-logout:hover { background: #fff4f5; }
        .account-profile-modal-backdrop { position: fixed; inset: 0; z-index: 130; display: grid; overflow-y: auto; padding: clamp(20px, 6vh, 56px) 16px 20px; place-items: start center; background: rgba(20, 43, 29, .5); backdrop-filter: blur(3px); }
        .account-profile-modal-backdrop[hidden] { display: none; }
        .account-profile-modal { width: min(100%, 680px); max-height: calc(100dvh - clamp(20px, 6vh, 56px) - 20px); overflow-y: auto; padding: clamp(18px, 3vw, 28px); border: 1px solid #dfe8df; border-radius: 10px; background: #fff; box-shadow: 0 22px 64px rgba(16, 41, 25, .25); }
        .account-profile-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
        .account-profile-modal-head h2 { margin: 0; color: #244a32; font-size: 25px; font-weight: 650; line-height: 1.2; letter-spacing: -.025em; }
        .account-profile-modal-head p { margin: 6px 0 0; color: #69745c; font-size: 11px; line-height: 1.5; }
        .account-profile-modal-close { display: grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border: 1px solid #dce5dc; border-radius: 50%; background: #fff; color: #315b3b; cursor: pointer; }
        .account-profile-modal-close svg { width: 17px; height: 17px; }
        .account-profile-modal-content .profile-edit-form { margin: 0; }
        .account-profile-modal-content .calendar-modal-actions { margin-top: 18px; }
        .simak-workspace { min-width: 0; background: linear-gradient(145deg, rgba(228, 239, 225, .55), rgba(212, 231, 216, .34)); }
        .topbar { position: sticky; top: 0; z-index: 20; display: flex; min-height: 74px; align-items: center; justify-content: space-between; gap: 20px; padding: 12px clamp(20px, 4vw, 60px); border-bottom: 1px solid #e0e8df; background: rgba(255, 255, 255, .96); box-shadow: 0 3px 14px rgba(26, 57, 36, .06); backdrop-filter: blur(16px); }
        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .topbar-kicker { color: #76805c; font-size: 10px; font-weight: 800; letter-spacing: 0; text-transform: uppercase; }
        .topbar-title { margin-top: 4px; color: #284734; font-size: 14px; font-weight: 700; }
        .topbar-user { display: flex; align-items: center; gap: 14px; }
        .language-switcher { display: flex; align-items: center; gap: 4px; padding: 3px; border: 1px solid #dbe4e7; border-radius: 9px; background: #fff; }
        .language-switcher form { margin: 0; }
        .language-switcher button { display: inline-flex; min-height: 32px; align-items: center; gap: 5px; padding: 0 8px; border: 0; border-radius: 6px; background: transparent; color: #536579; font-size: 10px; font-weight: 800; cursor: pointer; }
        .language-switcher button[aria-pressed="true"] { background: var(--green-pale); color: var(--green-strong); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--brand-primary), white 74%); }
        .language-switcher button:focus-visible { outline: 2px solid #478055; outline-offset: 2px; }
        .notification-center { position: relative; flex: 0 0 auto; }
        .notification-trigger { position: relative; display: grid; width: 48px; height: 48px; place-items: center; border: 1px solid #dce4ec; border-radius: 12px; background: #f8fafc; color: #536174; cursor: pointer; }
        .notification-trigger:hover, .notification-trigger:focus-visible { border-color: #b7c8dc; background: #eef3f8; outline: 0; }
        .notification-trigger svg { width: 21px; height: 21px; fill: currentColor; }
        .notification-trigger.has-unread { color: #273b56; }
        .notification-count { position: absolute; top: -5px; right: -5px; display: grid; min-width: 19px; height: 19px; place-items: center; padding: 0 5px; border: 2px solid #fff; border-radius: 20px; background: #dc3545; color: #fff; font-size: 9px; font-weight: 900; }
        .notification-count[hidden] { display: none; }
        .notification-panel { position: absolute; top: calc(100% + 14px); right: 0; z-index: 70; display: grid; grid-template-rows: auto minmax(120px, 1fr) auto; width: min(640px, calc(100vw - 24px)); max-height: min(520px, calc(100dvh - 100px)); overflow: hidden; border: 1px solid #dce4ec; border-radius: 20px; background: #fff; box-shadow: 0 18px 48px rgba(24, 42, 69, .2); }
        .notification-panel[hidden] { display: none; }
        .notification-panel-head, .notification-panel-foot { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 17px 22px; background: #f8fafc; }
        .notification-panel-head { border-bottom: 1px solid #edf1f5; }
        .notification-panel-head h2 { display: flex; align-items: center; gap: 10px; margin: 0; color: #172338; font-size: 16px; font-weight: 700; line-height: 1.25; }
        .notification-panel-head h2 svg { width: 19px; height: 19px; fill: #23438f; color: #23438f; }
        .notification-mark-button { min-height: 38px; padding: 0 14px; border: 1px solid #9aebc8; border-radius: 24px; background: #f4fcf8; color: #4e5968; font-size: 11px; font-weight: 800; cursor: pointer; }
        .notification-mark-button:disabled { opacity: .6; cursor: wait; }
        .notification-list { min-height: 0; overflow-y: auto; }
        .notification-item { position: relative; display: flex; align-items: flex-start; gap: 11px; padding: 16px 22px; border-bottom: 1px solid #edf1f5; color: #536174; text-decoration: none; text-align: left; }
        .notification-item-content { display: flex; flex: 1 1 auto; min-width: 0; color: inherit; text-align: left; text-decoration: none; }
        .notification-item-content:hover .notification-item-copy strong { text-decoration: underline; }
        .notification-panel, .notification-panel-head, .notification-panel-foot, .notification-item, .notification-item-copy, .notification-item-copy > * { text-align: left; }
        .notification-item:hover { background: #f8fbff; }
        .notification-item.is-unread { background: #fffafa; color: #253247; }
        .notification-item-copy { display: grid; flex: 1; gap: 5px; min-width: 0; text-align: left; }
        .notification-item-copy strong { color: #1c2b41; font-size: 12px; line-height: 1.4; }
        .notification-item.is-unread .notification-item-copy strong { color: #a72b3a; }
        .notification-item-copy > span { font-size: 11px; line-height: 1.55; overflow-wrap: anywhere; }
        .notification-item-copy small { color: #8190a1; font-size: 9px; }
        .notification-unread-dot { width: 9px; height: 9px; flex: 0 0 9px; margin: 5px 0 0; border-radius: 50%; background: #dc3545; }
        .notification-item-action { flex: 0 0 auto; padding: 6px 10px; border: 1px solid #d8e2ec; border-radius: 7px; background: #fff; color: #23438f; font-size: 10px; font-weight: 800; cursor: pointer; }
        .notification-item-action:hover { border-color: #a9c1df; background: #f2f7fc; }
        .notification-item-action:disabled { opacity: .6; cursor: wait; }
        .notification-empty { display: grid; min-height: 220px; align-content: center; justify-items: center; gap: 12px; padding: 26px; color: #6f8098; text-align: center; }
        .notification-empty p { margin: 0; font-size: 15px; }
        .notification-empty-check { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 50%; background: #10b981; color: #fff; font-size: 28px; font-weight: 800; }
        .notification-error { margin: 0; padding: 22px; color: #9d2838; font-size: 12px; line-height: 1.6; }
        .notification-panel-foot { justify-content: center; border-top: 1px solid #edf1f5; }
        .notification-panel-foot .notification-mark-button { border: 0; background: transparent; color: #23438f; font-size: 13px; }
        .topbar-user-name { color: #354b37; font-size: 12px; font-weight: 700; }
        .topbar-role { display: block; margin-top: 3px; color: #77805f; font-size: 10px; text-align: right; }
        .signout { min-height: 38px; padding: 0 13px; border: 1px solid #b3bf8e; border-radius: 5px; background: #fffdf0; color: #315b3b; font-size: 11px; font-weight: 800; cursor: pointer; transition: background .2s ease, border-color .2s ease; }
        .signout:hover { border-color: var(--green); background: #edf3d0; }
        .mobile-toggle { display: none; min-height: 38px; padding: 0 12px; border: 1px solid #bbc78f; border-radius: 5px; background: #fffdf0; color: #315b3b; font-size: 11px; font-weight: 800; cursor: pointer; }
        .simak-content { width: auto; min-height: calc(100vh - 112px); margin: 20px 26px 30px; padding: clamp(25px, 3vw, 42px) clamp(20px, 3.2vw, 48px) 48px; border: 1px solid #e3e9e2; border-radius: 10px; background: #fff; box-shadow: 0 12px 34px rgba(31, 69, 44, .1); animation: content-arrive .42s ease-out both; }
        .page-heading { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 25px; }
        .page-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; }
        .eyebrow { margin: 0 0 7px; color: #68873c; font-size: 10px; font-weight: 800; letter-spacing: 0; text-transform: uppercase; }
        h1 { margin: 0; color: #244a32; font-size: 40px; font-weight: 650; line-height: 1.12; letter-spacing: -.035em; }
        h2 { color: #2a4b33; }
        .subtitle { margin: 10px 0 0; color: #69745c; font-size: 13px; line-height: 1.6; }
        .module-alert { margin-top: 12px; padding: 10px 13px; border: 1px solid #d9e6d5; border-left: 3px solid #7fa276; border-radius: 5px; background: #f3f8f0; }
        .module-alert .subtitle { margin: 0; font-size: 12px; }
        .button { display: inline-flex; min-height: 40px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid #aeba82; border-radius: 5px; background: #fffcec; color: #315b3b; font-size: 12px; font-weight: 800; text-decoration: none; cursor: pointer; transition: transform .2s ease, box-shadow .2s ease, background .2s ease; }
        .button:hover { transform: translateY(-1px); box-shadow: 0 7px 18px rgba(50, 77, 41, .12); background: #fff6c9; }
        .button-primary { border-color: #367347; background: linear-gradient(110deg, #4e914e, #39734a); color: #fffbea; }
        .button-primary:hover { background: linear-gradient(110deg, #5a9e53, #3d7f4c); }
        .button-small { min-height: 31px; padding: 0 10px; font-size: 10px; }
        .form-section { padding: 21px 0; border-top: 1px solid var(--line); }
        .form-section h2 { margin: 0 0 15px; font-size: 13px; }
        .field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 20px; }
        label { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        input, select { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #c8cc9d; border-radius: 5px; background: #fffef6; color: #293c2c; font: inherit; font-size: 12px; }
        input:focus, select:focus { outline: 3px solid rgba(221, 187, 52, .28); border-color: #8a9e3e; }
        .field-error { margin: 6px 0 0; color: #9d3e28; font-size: 11px; }
        .form-actions { display: flex; justify-content: flex-end; gap: 9px; padding-top: 18px; border-top: 1px solid var(--line); }
        .master-data-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); align-items: end; gap: 10px; margin-bottom: 20px; padding: 15px; border: 1px solid #e3e9e2; border-radius: 7px; background: #fbfdfb; }
        .master-data-filters .button { justify-self: start; }
        .master-identity-card { margin-bottom: 22px; padding: clamp(20px, 3vw, 32px); border: 1px solid #e3e9e2; border-radius: 10px; background: #fff; box-shadow: 0 10px 28px rgba(31, 69, 44, .08); }
        .master-identity-heading { padding-bottom: 18px; border-bottom: 1px solid #e5ebe4; }
        .master-identity-heading h2 { margin: 0; font-size: clamp(24px, 3vw, 32px); font-weight: 650; line-height: 1.2; letter-spacing: -.025em; }
        .master-identity-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 18px 28px; margin: 0; padding-top: 21px; }
        .master-identity-grid dt { margin-bottom: 5px; color: #738069; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .master-identity-grid dd { margin: 0; color: #2e4d36; font-size: 12px; font-weight: 700; overflow-wrap: anywhere; }
        .master-empty-state { padding: 42px 16px; border: 1px dashed #d5dfd3; border-radius: 8px; color: #6b745e; text-align: center; }
        .master-data-modal { max-width: 760px; }
        .master-data-field-grid { padding: 2px 5px 12px 0; }
        .master-checkbox-heading { display: block; margin-bottom: 7px; color: #43543c; font-size: 11px; font-weight: 800; }
        .master-checkbox-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px 12px; max-height: 190px; overflow-y: auto; padding: 10px; border: 1px solid #c8cc9d; border-radius: 5px; background: #fffef6; }
        .master-checkbox-option { display: flex; align-items: flex-start; gap: 7px; margin: 0; color: #293c2c; font-size: 11px; font-weight: 500; }
        .master-checkbox-option input[type="checkbox"] { flex: 0 0 auto; width: 15px; min-height: 15px; height: 15px; margin: 0; padding: 0; accent-color: #39734a; }
        .master-checkbox-empty, .master-checkbox-help { color: #6b745e; font-size: 10px; }
        .master-checkbox-help { display: block; margin-top: 5px; }
        .period-strip { padding: 13px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); color: #48633b; font-size: 12px; font-weight: 800; }
        .table-wrap { overflow-x: auto; border-top: 1px solid #e5e9e2; border-bottom: 1px solid #e5e9e2; background: #fff; }
        table { width: 100%; min-width: 850px; border-collapse: collapse; }
        th, td { padding: 12px 11px; border-bottom: 1px solid rgba(92, 102, 61, .13); text-align: left; vertical-align: middle; font-size: 11px; }
        th { color: #6a7651; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr { transition: background .18s ease; }
        tbody tr:hover { background: #f0f6f0; }
        .period { color: #345c3a; font-weight: 800; }
        .subtext { display: block; margin-top: 4px; color: #79806a; font-size: 10px; }
        .status-chip { display: inline-flex; min-width: 65px; justify-content: center; padding: 5px 7px; border-radius: 99px; background: #ecebd5; color: #6b704d; font-size: 9px; font-weight: 800; }
        .status-chip.active { background: #deedc5; color: #30653c; }
        .row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
        .empty-state { padding: 48px 16px; color: #6b745e; text-align: center; }
        .empty-state strong { display: block; margin-bottom: 7px; color: #31583a; font-size: 23px; font-weight: 650; letter-spacing: -.02em; }
        .pagination { display: flex; justify-content: flex-end; align-items: center; gap: 14px; padding: 13px 0; color: #71805b; font-size: 10px; }
        .pagination a { color: #376d42; font-weight: 800; text-decoration: none; }
        .dt-paging { display: flex; justify-content: flex-end; align-items: center; gap: 4px; padding: 13px 0; }
        .dt-paging-button { min-width: 32px; min-height: 30px; margin-left: 4px; padding: 0 9px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; color: #1e64a1; font-size: 10px; text-decoration: none; }
        .dt-paging-button.current { border-color: #8eb8d9; background: #eaf3fb; color: #1e64a1; font-weight: 700; }
        .dt-paging-button.disabled { color: #a5afa2; cursor: default; }
        .sidebar-scrim { display: none; }
        @keyframes wash { from { background-position: 0% 20%; } to { background-position: 100% 80%; } }
        @keyframes content-arrive { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toast-in { from { opacity: 0; transform: translateY(-12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes toast-out { to { opacity: 0; transform: translateY(-8px) scale(.98); } }
        @media (max-width: 1100px), (hover: none) and (pointer: coarse) and (max-width: 1400px) {
            .simak-shell { display: block; }
            .simak-sidebar { position: fixed; left: 0; width: min(292px, calc(100vw - 54px)); transform: translateX(-103%); transition: transform .3s cubic-bezier(.2,.8,.2,1); }
            .simak-shell.is-nav-open .simak-sidebar { transform: translateX(0); }
            .sidebar-scrim { position: fixed; inset: 0; z-index: 25; display: block; background: rgba(23, 47, 31, .42); opacity: 0; pointer-events: none; transition: opacity .25s ease; }
            .simak-shell.is-nav-open .sidebar-scrim { opacity: 1; pointer-events: auto; }
            .mobile-toggle { display: inline-flex; align-items: center; gap: 8px; }
            .simak-content { min-height: calc(100vh - 92px); margin: 14px 14px 24px; padding: 25px 20px 36px; border-radius: 8px; }
            .notification-panel { position: fixed; top: calc(74px + env(safe-area-inset-top, 0px)); right: 12px; width: min(640px, calc(100vw - 24px)); max-height: min(520px, calc(100dvh - 90px - env(safe-area-inset-top, 0px))); }
        }
        @media (max-width: 580px) {
            .topbar { min-height: 64px; gap: 10px; padding: 10px 14px; }
            .topbar-left { gap: 9px; }
            .topbar-kicker { display: none; }
            .topbar-title { margin: 0; font-size: 11px; }
            .topbar-user { gap: 7px; }
            .topbar-account-trigger { gap: 7px; padding: 5px 7px; }
            .topbar-account-avatar { width: 34px; height: 34px; flex-basis: 34px; }
            .topbar-account-trigger-copy strong { max-width: 115px; font-size: 10px; }
            .topbar-account-trigger-copy small { max-width: 115px; overflow: hidden; font-size: 9px; text-overflow: ellipsis; }
            .topbar-account-chevron { width: 13px; height: 13px; flex-basis: 13px; }
            .topbar-account-panel { right: -5px; width: min(350px, calc(100vw - 24px)); }
            .account-profile-modal-backdrop { padding: 8vh 12px 12px; }
            .account-profile-modal { max-height: 86dvh; }
            .topbar-user-name { max-width: 90px; overflow: hidden; font-size: 10px; text-overflow: ellipsis; white-space: nowrap; }
            .topbar-role { display: none; }
            .signout { min-height: 35px; padding: 0 9px; font-size: 10px; }
            .mobile-toggle { min-height: 35px; padding: 0 9px; font-size: 10px; }
            .page-heading { align-items: flex-start; flex-direction: column; gap: 15px; }
            h1 { font-size: 34px; }
            .field-grid { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
    @stack('styles')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .dt-container { color: var(--ink); font: inherit; font-size: 11px; }
        .dt-layout-row { display: flex; align-items: center; gap: 12px; margin: 0 0 12px !important; }
        .dt-layout-cell { min-width: 0; }
        .dt-container .dt-search, .dt-container .dt-length { display: flex; align-items: center; gap: 8px; color: #5e6c50; font-size: 10px; }
        .dt-container .dt-search input, .dt-container .dt-length select { min-height: 36px; margin: 0; padding: 0 10px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; color: var(--ink); font: inherit; font-size: 11px; }
        .dt-container .dt-search input { width: min(270px, 42vw); }
        .dt-buttons { display: flex; flex-wrap: wrap; gap: 7px; }
        .dt-container .dt-button.dt-export-button { display: inline-flex; min-height: 35px; align-items: center; gap: 7px; padding: 0 11px; border: 1px solid #d1ddd0; border-radius: 5px; background: #fff; background-image: none; color: #315d3b; font: inherit; font-size: 10px; font-weight: 800; box-shadow: none; transition: background .18s ease, border-color .18s ease; }
        .dt-container .dt-button.dt-export-button:hover { border-color: #83a38b; background: #f2f7f2; }
        .dt-export-button svg { width: 15px; height: 15px; }
        .dt-container .dt-info { padding-top: 0; color: #728070; font-size: 10px; }
        .dt-container .dt-paging .dt-paging-button { min-width: 32px; min-height: 30px; margin-left: 4px; padding: 0 9px; border: 1px solid #d5dfd3; border-radius: 5px; background: #fff; background-image: none; color: #386345 !important; font-size: 10px; box-shadow: none; }
        .dt-container .dt-paging .dt-paging-button.current { border-color: #73a17b; background: #e8f2e7; color: #315b3b !important; }
        .dt-container .dt-paging .dt-paging-button:hover { border-color: #83a38b; background: #f2f7f2; color: #315b3b !important; }
        .table-wrap table.dataTable thead th { border-bottom: 1px solid #e2e9e2; }
        .table-wrap table.dataTable tbody td { border-bottom: 1px solid #edf1ed; }
        .table-icon-button { display: inline-grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--green-pale); border-radius: 6px; background: #fff; color: var(--green); text-decoration: none; cursor: pointer; transition: transform .18s ease, background .18s ease, border-color .18s ease; }
        .table-icon-button:hover { transform: translateY(-1px); border-color: #78a282; background: #eef5ee; }
        .table-icon-button.activate { border-color: #bbd39e; background: #eff6e7; color: #31633b; }
        .table-icon-button.reset { border-color: #e5c7bd; background: #fff7f3; color: #9b4535; }
        .table-icon-button.reset:hover { border-color: #c9806d; background: #fff0e9; }
        .table-icon-button svg { width: 16px; height: 16px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
        .calendar-modal-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; overflow-y: auto; padding: clamp(24px, 7vh, 64px) 24px 24px; place-items: start center; background: rgba(20, 43, 29, .52); backdrop-filter: blur(3px); }
        .calendar-modal { width: min(100%, 650px); max-height: calc(100dvh - clamp(24px, 7vh, 64px) - 24px); overflow-y: auto; padding: clamp(22px, 4vw, 36px); border: 1px solid #dfe8df; border-radius: 12px; background: #fff; box-shadow: 0 24px 80px rgba(16, 41, 25, .28); animation: content-arrive .18s ease-out both; }
        .calendar-modal h2 { margin: 0; font-size: 27px; font-weight: 650; line-height: 1.2; letter-spacing: -.025em; }
        .calendar-modal-copy { margin: 8px 0 22px; color: var(--muted); font-size: 12px; line-height: 1.6; }
        .calendar-modal-head { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .calendar-modal-close { display: grid; width: 34px; height: 34px; flex: 0 0 34px; place-items: center; border: 1px solid var(--line); border-radius: 50%; background: #fff; color: var(--green-strong); font-size: 20px; cursor: pointer; }
        .calendar-modal-error { margin: 14px 0 0; padding: 11px 13px; border: 1px solid #f0d0c7; border-radius: 6px; background: #fff5f2; color: #8c3c2c; font-size: 12px; line-height: 1.5; }
        .calendar-process-progress { margin-top: 18px; padding: 14px; border: 1px solid #dce7da; border-radius: 10px; background: #fbfdf9; color: #294633; }
        .calendar-process-progress-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 9px; font-size: 12px; }
        .calendar-process-progress-head > span { display: inline-flex; align-items: center; gap: 8px; }
        .calendar-process-progress-head strong { font-size: 14px; }
        .calendar-process-track { overflow: hidden; height: 12px; border-radius: 99px; background: #e8eee6; }
        .calendar-process-bar { position: relative; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #356b45, #8cb64e); transition: width .35s ease; }
        .calendar-process-bar::after { position: absolute; inset: 0; background: linear-gradient(100deg, transparent 20%, rgba(255,255,255,.55) 50%, transparent 80%); content: ""; transform: translateX(-100%); }
        .calendar-process-progress.is-active .calendar-process-bar::after { animation: calendar-process-shimmer 1.4s ease-in-out infinite; }
        .calendar-process-spinner { display: none; width: 14px; height: 14px; flex: 0 0 14px; border: 2px solid #cbd9c7; border-top-color: #356b45; border-radius: 50%; animation: calendar-process-spin .8s linear infinite; }
        .calendar-process-progress.is-active .calendar-process-spinner { display: inline-block; }
        .calendar-process-progress p { margin: 8px 0 0; color: var(--muted); font-size: 11px; }
        @keyframes calendar-process-spin { to { transform: rotate(360deg); } }
        @keyframes calendar-process-shimmer { to { transform: translateX(100%); } }
        .calendar-confirm-detail { margin: 16px 0; padding: 13px 15px; border-left: 3px solid #6f9e73; background: #f2f7f1; color: #3e5942; font-size: 12px; line-height: 1.6; }
        .calendar-modal-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 22px; padding-top: 17px; border-top: 1px solid var(--line); }
        .button-danger { border-color: #a74637; background: #a74637; color: #fff; }
        .button-danger:hover { border-color: #8f382b; background: #8f382b; }
        .flash-toast { position: fixed; top: 88px; right: 24px; z-index: 120; display: flex; width: min(420px, calc(100vw - 36px)); align-items: flex-start; gap: 11px; padding: 14px 15px; border: 2px solid #367347; border-radius: 9px; background: linear-gradient(115deg, #e5f4d8, #cbe8ce); color: #214b2d; box-shadow: 0 16px 42px rgba(33, 72, 39, .3); animation: toast-in .25s ease-out both; }
        .flash-toast.is-error { border-color: #a74637; background: linear-gradient(115deg, #fff0d8, #f7d4c9); color: #752b24; }
        .flash-mark { display: grid; width: 24px; height: 24px; flex: 0 0 24px; place-items: center; border-radius: 50%; background: #b8d99d; color: #214b2d; font-size: 11px; font-weight: 900; }
        .flash-toast.is-error .flash-mark { background: #edb9a9; color: #752b24; }
        .flash-message { flex: 1; margin: 2px 0 0; font-size: 12px; font-weight: 700; line-height: 1.55; }
        .flash-close { width: 28px; height: 28px; flex: 0 0 28px; border: 0; border-radius: 50%; background: transparent; color: inherit; font-size: 19px; cursor: pointer; }
        .flash-toast.is-leaving { animation: toast-out .2s ease-in both; }
        .ts-wrapper { width: 100%; }
        .ts-wrapper .ts-control { min-height: 44px; padding: 9px 12px; border: 1px solid #c8cc9d; border-radius: 5px; background: #fffef6; color: #293c2c; font: inherit; font-size: 12px; }
        .ts-dropdown { z-index: 120; border-color: #c8cc9d; background: #fffef6; color: #293c2c; }
        @media (max-width: 760px) { .dt-layout-row { flex-wrap: wrap; } .dt-container .dt-search input { width: min(220px, 55vw); } }
        @media (prefers-reduced-motion: reduce) { .calendar-process-bar { transition: none; } .calendar-process-progress.is-active .calendar-process-spinner { animation-duration: 2s; } .calendar-process-progress.is-active .calendar-process-bar::after { animation-duration: 3s; } }
        @media (max-width: 580px) { .calendar-modal-backdrop { padding: 7vh 12px 12px; } .calendar-modal { max-height: 86dvh; border-radius: 12px; } .flash-toast { top: 74px; right: 12px; } .page-actions { width: 100%; } .notification-trigger { width: 42px; height: 42px; } .notification-panel { top: calc(68px + env(safe-area-inset-top, 0px)); right: 10px; width: calc(100vw - 20px); max-height: calc(100dvh - 82px - env(safe-area-inset-top, 0px)); border-radius: 14px; } .notification-panel-head, .notification-panel-foot { padding: 13px 15px; } .notification-item { padding: 14px 15px; } }
    </style>
    <style>
        :root {
            --ink: #203a55;
            --muted: #61758a;
            --green: var(--brand-primary);
            --green-strong: color-mix(in srgb, var(--brand-primary), #102f51 24%);
            --green-deep: color-mix(in srgb, var(--brand-primary), #102f51 58%);
            --green-pale: color-mix(in srgb, var(--brand-primary), white 84%);
            --line: #d8e3ee;
        }
        body { background: linear-gradient(128deg, color-mix(in srgb, var(--brand-primary), white 83%) 0%, #fff0bd 50%, color-mix(in srgb, var(--brand-primary), white 75%) 100%); }
        .simak-sidebar { background: linear-gradient(160deg, color-mix(in srgb, var(--brand-primary), white 8%) 0%, var(--green-strong) 56%, var(--green-deep) 100%); color: #f7fbff; }
        .brand-subtitle, .account-role { color: #dbeaff; }
        .nav-group-toggle { color: #ffe08a; }
        .nav-link::before { background: #f2d36c; }
        .nav-link:hover { background: rgba(255, 224, 138, .14); color: #fff7db; }
        .nav-link[aria-current="page"] { background: linear-gradient(100deg, #fff0b2, var(--green-pale)); color: var(--green-deep); }
        .nav-link[aria-current="page"]::before { background: var(--green-strong); }
        .nav-status { color: #ffe08a; }
        .sidebar-foot { border-color: rgba(255, 224, 138, .3); color: #d5e7f8; }
        .profile-link, .topbar-title, h1, h2 { color: var(--green-deep); }
        .topbar-kicker, .eyebrow { color: var(--green-strong); }
        .topbar-role, .subtitle { color: #62768b; }
        .simak-workspace { background: linear-gradient(145deg, rgba(218, 235, 255, .68), rgba(255, 241, 198, .42)); }
        .module-alert, .akm-alert { border-color: var(--green-pale); border-left-color: #e1bf43; background: color-mix(in srgb, var(--brand-primary), white 94%); color: var(--green-deep); }
        .button { border-color: #ddc65f; background: #fff7d8; color: var(--green-deep); }
        .button:hover { background: #fff0b5; }
        .button-primary { border-color: var(--green-strong); background: linear-gradient(110deg, var(--green-strong), var(--brand-primary)); color: var(--brand-primary-foreground); }
        .button-primary:hover { background: linear-gradient(110deg, var(--green-deep), var(--green-strong)); }
        label { color: #294d70; }
        input, select { border-color: #b8cde0; background: #fbfdff; color: #243c55; }
        input:focus, select:focus { outline-color: color-mix(in srgb, var(--brand-primary), transparent 78%); border-color: var(--brand-primary); }
        .field-grid input:focus, .field-grid select:focus { outline: 3px solid color-mix(in srgb, var(--brand-primary), transparent 78%); }
        .master-identity-grid dt, .subtext { color: #6d7f92; }
        .master-identity-grid dd, .period { color: #214f79; }
        .master-empty-state, .empty-state { border-color: #cbdced; color: #647a90; }
        .empty-state strong { color: #245b8c; }
        table th { color: #52718f; }
        tbody tr:hover { background: color-mix(in srgb, var(--brand-primary), white 94%); }
        .status-chip { background: #edf2f7; color: #52667a; }
        .status-chip.active { background: var(--green-pale); color: var(--green-strong); }
        .pagination, .student-status-pagination { color: #647b91; }
        .pagination a { color: #1e64a1; }
        .dt-container .dt-search, .dt-container .dt-length { color: #526d87; }
        .dt-container .dt-paging .dt-paging-button { border-color: var(--green-pale); color: var(--green-strong) !important; }
        .dt-container .dt-paging .dt-paging-button.current { border-color: color-mix(in srgb, var(--brand-primary), white 55%); background: var(--green-pale); color: var(--green-deep) !important; }
        .dt-container .dt-paging .dt-paging-button:hover { border-color: color-mix(in srgb, var(--brand-primary), white 42%); background: color-mix(in srgb, var(--brand-primary), white 93%); color: var(--green-deep) !important; }
        .table-icon-button { border-color: var(--green-pale); color: var(--green); }
        .table-icon-button:hover { border-color: color-mix(in srgb, var(--brand-primary), white 42%); background: color-mix(in srgb, var(--brand-primary), white 93%); }
        .table-icon-button.activate { border-color: #e2cc67; background: #fff8dc; color: #725c16; }
        .calendar-confirm-detail { border-left-color: #d4b744; background: #f0f6ff; color: #2b4d6d; }
        .calendar-modal { border-color: #d5e2ee; }
        .calendar-modal-close { color: var(--green-strong); }
        .flash-toast { border-color: #2f76ae; background: linear-gradient(115deg, #d8eaff, #fff0b8); color: #173f65; }
        .flash-mark { background: #f2d46c; color: #284a6b; }
        .ts-wrapper .ts-control, input, select { accent-color: var(--brand-primary); }
        .ts-wrapper .ts-control { border-color: #b8cde0; background: #fbfdff; color: #243c55; }
        .ts-dropdown { border-color: #b8cde0; background: #fff; color: #243c55; }
    </style>
</head>
<body data-route-name="{{ request()->route()?->getName() }}" data-user-id="{{ auth()->id() }}">
    <div class="simak-page-loader" data-page-loader role="status" aria-live="polite" aria-label="loading..." aria-hidden="true" hidden>
        <span class="simak-page-loader-mark"><img src="{{ $siteConfiguration['preloader_logo_url'] }}" alt=""><span class="simak-page-loader-orbit" aria-hidden="true"></span></span>
        <span class="simak-page-loader-label">loading<span class="simak-page-loader-dots"></span></span>
    </div>
    @php
        $currentUser = auth()->user();
        $currentRole = (string) $currentUser->role;
        $accountType = isset($currentUser->legacy_type) ? (string) $currentUser->legacy_type : 'admin';
        $accountLogin = (string) (data_get($currentUser, 'login') ?? data_get($currentUser, 'legacy_id') ?? '');
        $degreeTrack = in_array($currentRole, ['mahasiswa', 'userprodi', 'usertu'], true) ? '' : null;
        if ($currentRole === 'mahasiswa') {
            $degreeTrack = \Illuminate\Support\Facades\DB::connection('simak_main')
                ->table('s1m4k_mastermhs as student')
                ->join('s1m4k_masterprodi as program', 'student.id_prodi', '=', 'program.id_prodi')
                ->where('student.id_mahasiswa', (string) data_get($currentUser, 'legacy_id'))
                ->value('program.nama_jenjang_pendidikan');
        } elseif (in_array($currentRole, ['userprodi', 'usertu'], true)) {
            $degreeTrack = \Illuminate\Support\Facades\DB::connection('simak_main')
                ->table('s1m4k_masterprodi')
                ->where('id_prodi', (string) data_get($currentUser, 'id_prodi'))
                ->value('nama_jenjang_pendidikan');
        }
        $degreeTrack = $degreeTrack === null ? null : strtoupper(trim((string) $degreeTrack));
        $roleLabel = app()->getLocale() === 'en'
            ? trans('ui.roles.'.$currentRole, [], 'en')
            : config('simak.role_labels.'.$currentRole, ucfirst($currentRole));
        $menuGroups = collect(config('simak.navigation.'.$currentRole, []))
            ->map(function (array $group) use ($degreeTrack): array {
                $group['items'] = array_values(array_filter(
                    $group['items'],
                    static function (array $item) use ($degreeTrack): bool {
                        $route = (string) ($item['route'] ?? '');
                        if (str_starts_with($route, 'theses.')) {
                            return $degreeTrack === null || in_array($degreeTrack, ['S1', 'D3'], true);
                        }
                        if (str_starts_with($route, 'graduate_theses.')) {
                            return $degreeTrack === null || in_array($degreeTrack, ['S2', 'S3'], true);
                        }

                        return true;
                    }
                ));

                return $group;
            })
            ->filter(static fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
        $navigationGroupIcons = config('simak.navigation_group_icons', []);
        $accountEmail = isset($currentUser->email) ? (string) $currentUser->email : '';
        $accountIdentifierLabel = match ($accountType) {
            'dosen' => 'NIDN',
            'asdos' => 'Kode asisten',
            'mahasiswa' => 'NIM',
            default => 'Username',
        };
        $accountName = (string) (data_get($currentUser, 'name') ?: $accountLogin ?: 'Pengguna SIAKAD');
        $accountInitials = collect(preg_split('/\s+/', trim((string) $accountName)))
            ->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    @endphp
    <div class="simak-shell" id="simak-shell">
        <button class="sidebar-scrim" type="button" data-close-navigation aria-label="Tutup navigasi"></button>
        <aside class="simak-sidebar" id="simak-navigation" aria-label="Navigasi SIAKAD">
            <a class="brand-link" href="{{ route('dashboard') }}">
                <img src="{{ $siteConfiguration['logo_url'] }}" alt="">
                <span><span class="brand-title">{{ $siteConfiguration['app_name'] }}</span><span class="brand-subtitle">{{ $siteConfiguration['app_full_name'] }}</span></span>
            </a>
            <div class="account-mark">
                <span class="account-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($accountName, 0, 1)) }}</span>
                <span><span class="account-name">{{ $accountName }}</span><span class="account-role">{{ $roleLabel }}</span></span>
            </div>
            <nav class="side-nav">
                @php
                    $isActiveNavigationItem = static function (array $item): bool {
                        if (! isset($item['route'])) {
                            return false;
                        }

                        return request()->routeIs($item['route'])
                            || request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.').'.*')
                            || ($item['route'] === 'academic-calendars.index' && request()->routeIs('academic-calendars.*'))
                            || ($item['route'] === 're-enrollment.index' && request()->routeIs('re-enrollment.*'))
                            || ($item['route'] === 'krs-transfer.index' && request()->routeIs('krs-transfer.*'))
                            || ($item['route'] === 'khs.index' && request()->routeIs('khs.*'))
                            || ($item['route'] === 'student-status.index' && request()->routeIs('student-status.*'))
                            || ($item['route'] === 'transcripts.index' && request()->routeIs('transcripts.*'))
                            || ($item['route'] === 'course-removals.index' && request()->routeIs('course-removals.*'));
                    };
                @endphp
                @foreach ($menuGroups as $group)
                    @php
                        $groupHasActiveItem = collect($group['items'])->contains($isActiveNavigationItem);
                    @endphp
                    <section class="nav-group">
                        <button class="nav-group-toggle" type="button" aria-expanded="{{ $groupHasActiveItem ? 'true' : 'false' }}" aria-controls="nav-group-{{ $loop->index }}" data-nav-toggle>
                            <span class="nav-group-label"><span class="nav-group-icon"><span data-lucide="{{ $navigationGroupIcons[$group['label']] ?? 'layout-dashboard' }}"></span></span><span>{{ $group['label'] }}</span></span>
                        </button>
                        <div class="nav-group-items @if (! $groupHasActiveItem)is-collapsed @endif" id="nav-group-{{ $loop->index }}" data-nav-items @unless ($groupHasActiveItem)inert @endunless>
                            @foreach ($group['items'] as $item)
                                @if (isset($item['route']))
                                    <a class="nav-link" href="{{ route($item['route'], $item['parameters'] ?? []) }}" @if ($isActiveNavigationItem($item)) aria-current="page" @endif>{{ $item['label'] }}</a>
                                @else
                                    <span class="nav-link nav-link-disabled" aria-disabled="true" title="{{ $item['status'] ?? 'Dalam pengembangan' }}">
                                        <span>{{ $item['label'] }}</span><span class="nav-status">{{ $item['status'] ?? 'Segera' }}</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </nav>
            <div class="sidebar-foot">Akses disusun berdasarkan role SIAKAD.</div>
        </aside>
        <div class="simak-workspace">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-toggle" type="button" aria-controls="simak-navigation" aria-expanded="false" data-open-navigation>Menu</button>
                    <div><div class="topbar-kicker">Sistem Informasi Akademik</div><div class="topbar-title">@yield('topbar_title', $siteConfiguration['app_name'])</div></div>
                </div>
                <div class="topbar-user">
                    <div class="language-switcher" role="group" aria-label="Bahasa / Language">
                        <form method="POST" action="{{ route('locale.update') }}">
                            @csrf
                            <input type="hidden" name="locale" value="id">
                            <button type="submit" aria-label="Bahasa Indonesia" aria-pressed="{{ app()->getLocale() === 'id' ? 'true' : 'false' }}" title="Bahasa Indonesia"><span aria-hidden="true">🇮🇩</span><span>ID</span></button>
                        </form>
                        <form method="POST" action="{{ route('locale.update') }}">
                            @csrf
                            <input type="hidden" name="locale" value="en">
                            <button type="submit" aria-label="English" aria-pressed="{{ app()->getLocale() === 'en' ? 'true' : 'false' }}" title="English"><span aria-hidden="true">🇬🇧</span><span>EN</span></button>
                        </form>
                    </div>
                    <div class="notification-center"
                        data-notification-center
                        data-notification-label="Notifikasi SIAKAD"
                        data-empty-message="Belum ada notifikasi SIAKAD"
                        data-notifications-url="{{ route('theses.notifications.index') }}"
                        data-mark-all-read-url="{{ route('theses.notifications.read-all') }}"
                        data-mark-read-url-template="{{ route('theses.notifications.read', ['notification' => '__NOTIFICATION__']) }}">
                        <button class="notification-trigger" type="button" data-notification-trigger aria-expanded="false" aria-controls="simak-notification-panel" aria-label="Notifikasi SIAKAD">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.12-1.62h-4.24A2.2 2.2 0 0 0 12 22Zm7-5.1-1.7-2.02V10a5.3 5.3 0 0 0-4.3-5.2V4a1 1 0 1 0-2 0v.8A5.3 5.3 0 0 0 6.7 10v4.88L5 16.9V19h14v-2.1Z"/></svg>
                            <span class="notification-count" data-notification-count hidden></span>
                        </button>
                        <section class="notification-panel" id="simak-notification-panel" data-notification-panel hidden aria-label="Notifikasi aplikasi">
                            <header class="notification-panel-head">
                                <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22a2.2 2.2 0 0 0 2.12-1.62h-4.24A2.2 2.2 0 0 0 12 22Zm7-5.1-1.7-2.02V10a5.3 5.3 0 0 0-4.3-5.2V4a1 1 0 1 0-2 0v.8A5.3 5.3 0 0 0 6.7 10v4.88L5 16.9V19h14v-2.1Z"/></svg>Notifikasi SIAKAD</h2>
                                <button class="notification-mark-button" type="button" data-notification-mark-all>Semua dibaca</button>
                            </header>
                            <div class="notification-list" data-notification-list aria-live="polite"><p class="notification-error">Memuat notifikasi…</p></div>
                            <footer class="notification-panel-foot"><button class="notification-mark-button" type="button" data-notification-mark-all>Tandai Semua Dibaca</button></footer>
                        </section>
                    </div>
                    <div class="notification-center"
                        data-notification-center
                        data-notification-label="Log Aktivitas"
                        data-empty-message="Belum ada aktivitas yang tercatat"
                        data-notifications-url="{{ route('activity-notifications.index') }}"
                        data-mark-all-read-url="{{ route('activity-notifications.read-all') }}"
                        data-mark-read-url-template="{{ route('activity-notifications.read', ['activity' => '__NOTIFICATION__']) }}">
                        <button class="notification-trigger" type="button" data-notification-trigger aria-expanded="false" aria-controls="simak-activity-log-panel" aria-label="Log Aktivitas">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h16v18H4V3Zm3 4v2h10V7H7Zm0 4v2h10v-2H7Zm0 4v2h7v-2H7Z"/></svg>
                            <span class="notification-count" data-notification-count hidden></span>
                        </button>
                        <section class="notification-panel" id="simak-activity-log-panel" data-notification-panel hidden aria-label="Log aktivitas pengguna">
                            <header class="notification-panel-head">
                                <h2><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h16v18H4V3Zm3 4v2h10V7H7Zm0 4v2h10v-2H7Zm0 4v2h7v-2H7Z"/></svg>Log Aktivitas Saya</h2>
                                <button class="notification-mark-button" type="button" data-notification-mark-all>Semua dibaca</button>
                            </header>
                            <div class="notification-list" data-notification-list aria-live="polite"><p class="notification-error">Memuat log aktivitas…</p></div>
                            <footer class="notification-panel-foot"><button class="notification-mark-button" type="button" data-notification-mark-all>Tandai Semua Dibaca</button></footer>
                        </section>
                    </div>
                    @if ($currentUser->role !== 'admin')
                        <details class="topbar-account-menu" data-account-menu>
                            <summary class="topbar-account-trigger" aria-label="Menu akun {{ $accountName }}">
                                <span class="topbar-account-avatar" aria-hidden="true">{{ $accountInitials }}</span>
                                <span class="topbar-account-trigger-copy">
                                    <strong>{{ $accountName }}</strong>
                                    <small>{{ $accountIdentifierLabel }}: {{ $accountLogin ?: '-' }}</small>
                                </span>
                                <svg class="topbar-account-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <div class="topbar-account-panel">
                                <div class="topbar-account-identity">
                                    <strong>{{ $accountName }}</strong>
                                    <span>{{ $accountIdentifierLabel }}: {{ $accountLogin ?: '-' }}</span>
                                    @if ($accountEmail !== '')<span>{{ $accountEmail }}</span>@endif
                                    <span class="topbar-account-role">{{ $roleLabel }}</span>
                                </div>
                                <div class="topbar-account-actions">
                                    <a class="topbar-account-action" href="{{ route('user-profile.edit') }}" data-account-edit><i data-lucide="user-round-pen"></i><span>Edit Profil</span></a>
                                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="topbar-account-action is-logout" type="submit"><i data-lucide="log-out"></i><span>Keluar dari SIAKAD</span></button></form>
                                </div>
                            </div>
                        </details>
                    @else
                        <div><div class="topbar-user-name">{{ $accountName }}</div><span class="topbar-role">{{ $roleLabel }}</span></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="signout" type="submit">Keluar</button></form>
                    @endif
                </div>
            </header>
            @include('partials.flash-notice')
            <main class="simak-content">@yield('content')</main>
            <footer class="site-footer">© {{ date('Y') }} {{ $siteConfiguration['institution_name'] }} · SIAKAD (Sistem Informasi Akademik) · <a class="profile-link" href="https://smartkampus.id/" target="_blank" rel="noopener noreferrer">siakad smartkampus.id</a></footer>
        </div>
    </div>
    @if ($currentUser->role !== 'admin')
        <div class="account-profile-modal-backdrop" data-profile-modal hidden>
            <section class="account-profile-modal" role="dialog" aria-modal="true" aria-labelledby="account-profile-modal-title" tabindex="-1">
                <header class="account-profile-modal-head">
                    <div>
                        <h2 id="account-profile-modal-title">Edit Profil</h2>
                        <p>Perbarui informasi akun Anda.</p>
                    </div>
                    <button class="account-profile-modal-close" type="button" data-profile-close aria-label="Tutup modal"><i data-lucide="x"></i></button>
                </header>
                <div class="account-profile-modal-content" data-profile-modal-content></div>
            </section>
        </div>
    @endif
    <script>
        (() => {
            window.addEventListener('storage', (event) => {
                if (event.key === 'simak-auth-changed') window.location.reload();
            });
            @if (session('auth_changed'))
                try {
                    window.localStorage.setItem('simak-auth-changed', String(Date.now()));
                } catch (error) {
                    console.error('Authentication changes could not be synchronized across tabs.', error);
                }
            @endif
            const shell = document.getElementById('simak-shell');
            const sidebar = document.getElementById('simak-navigation');
            const openButton = document.querySelector('[data-open-navigation]');
            const closeButton = document.querySelector('[data-close-navigation]');
            const mobileNavigation = window.matchMedia('(max-width: 1100px), (hover: none) and (pointer: coarse) and (max-width: 1400px)');

            const syncSidebarState = () => {
                sidebar.inert = mobileNavigation.matches && !shell.classList.contains('is-nav-open');
                if (!mobileNavigation.matches) {
                    shell.classList.remove('is-nav-open');
                    openButton?.setAttribute('aria-expanded', 'false');
                }
            };
            const closeSidebarAfterRotation = () => {
                shell.classList.remove('is-nav-open');
                sidebar.inert = mobileNavigation.matches;
                openButton?.setAttribute('aria-expanded', 'false');
            };

            syncSidebarState();
            mobileNavigation.addEventListener('change', syncSidebarState);
            window.addEventListener('orientationchange', closeSidebarAfterRotation);

            openButton?.addEventListener('click', () => {
                shell.classList.add('is-nav-open');
                sidebar.inert = false;
                openButton.setAttribute('aria-expanded', 'true');
                sidebar.querySelector('.nav-link:not(.nav-link-disabled)')?.focus();
            });

            closeButton?.addEventListener('click', () => {
                shell.classList.remove('is-nav-open');
                sidebar.inert = mobileNavigation.matches;
                openButton?.setAttribute('aria-expanded', 'false');
                openButton?.focus();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && shell.classList.contains('is-nav-open')) {
                    closeButton?.click();
                }
            });

            document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const expanded = button.getAttribute('aria-expanded') === 'true';
                    const items = button.nextElementSibling;
                    button.setAttribute('aria-expanded', String(!expanded));
                    items.classList.toggle('is-collapsed', expanded);
                    items.inert = expanded;
                });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>