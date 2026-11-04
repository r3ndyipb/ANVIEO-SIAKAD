@php
    $hasErrors = $errors->any();
    $authNotification = session('auth_notification');
    $notice = session('status') ?: ($hasErrors ? $errors->first() : null);
@endphp

@if ($notice && ! $authNotification)
    <style>
        .flash-toast { position: fixed; top: {{ auth()->check() ? '88px' : '18px' }}; right: 24px; z-index: 120; display: flex; width: min(420px, calc(100vw - 36px)); align-items: flex-start; gap: 11px; padding: 14px 15px; border: 2px solid #367347; border-radius: 9px; background: linear-gradient(115deg, #e5f4d8, #cbe8ce); color: #214b2d; box-shadow: 0 16px 42px rgba(33, 72, 39, .3); animation: toast-in .25s ease-out both; }
        .flash-toast.is-error { border-color: #a74637; background: linear-gradient(115deg, #fff0d8, #f7d4c9); color: #752b24; }
        .flash-mark { display: grid; width: 24px; height: 24px; flex: 0 0 24px; place-items: center; border-radius: 50%; background: #b8d99d; color: #214b2d; font-size: 11px; font-weight: 900; }
        .flash-toast.is-error .flash-mark { background: #edb9a9; color: #752b24; }
        .flash-message { flex: 1; margin: 2px 0 0; font-size: 12px; font-weight: 700; line-height: 1.55; }
        .flash-close { width: 28px; height: 28px; flex: 0 0 28px; border: 0; border-radius: 50%; background: transparent; color: inherit; font-size: 19px; cursor: pointer; }
        .flash-toast.is-leaving { animation: toast-out .2s ease-in both; }
        @media (max-width: 580px) { .flash-toast { top: {{ auth()->check() ? '74px' : '12px' }}; right: 12px; } }
        @keyframes toast-in { from { opacity: 0; transform: translateY(-12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes toast-out { to { opacity: 0; transform: translateY(-8px) scale(.98); } }
    </style>
    <div class="flash-toast {{ $hasErrors ? 'is-error' : '' }}" role="{{ $hasErrors ? 'alert' : 'status' }}" aria-live="polite" data-flash-toast>
        <span class="flash-mark" aria-hidden="true">{{ $hasErrors ? '!' : 'OK' }}</span>
        <p class="flash-message">{{ $notice }}</p>
        <button class="flash-close" type="button" aria-label="Tutup notifikasi" data-dismiss-flash>&times;</button>
    </div>
    <script>
        (() => {
            const toast = document.querySelector('[data-flash-toast]');
            if (!toast) return;

            let timeout;
            const dismiss = () => {
                if (toast.classList.contains('is-leaving')) return;
                window.clearTimeout(timeout);
                toast.classList.add('is-leaving');
                window.setTimeout(() => {
                    toast.remove();
                }, 300);
            };

            toast.querySelector('[data-dismiss-flash]')?.addEventListener('click', dismiss);
            const scheduleDismiss = () => {
                window.clearTimeout(timeout);
                timeout = window.setTimeout(dismiss, 2000);
            };
            toast.addEventListener('mouseenter', () => window.clearTimeout(timeout));
            toast.addEventListener('mouseleave', scheduleDismiss);
            scheduleDismiss();
        })();
    </script>
@endif

@if ($authNotification)
    <script type="application/json" data-auth-notification>@json($authNotification, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endif