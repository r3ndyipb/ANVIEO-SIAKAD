@php
    $lmsNotification = null;
    if (session('success')) {
        $lmsNotification = ['icon' => 'success', 'title' => 'Berhasil', 'text' => session('success')];
    } elseif (session('error')) {
        $lmsNotification = ['icon' => 'error', 'title' => 'Gagal', 'text' => session('error')];
    } elseif ($errors->any()) {
        $lmsNotification = ['icon' => 'error', 'title' => 'Gagal', 'text' => $errors->first()];
    }
@endphp
@if ($lmsNotification)
    <script type="application/json" data-lms-notification>{!! json_encode($lmsNotification, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
@endif
