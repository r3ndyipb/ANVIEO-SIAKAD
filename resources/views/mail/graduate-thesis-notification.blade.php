<p>Yth. {{ $recipientName }},</p>
<p>{{ $intro }}</p>
<table role="presentation" cellpadding="6" cellspacing="0" style="border-left:4px solid #4b7ca8;background:#f5f8fa;margin:18px 0">
    @foreach ($details as $label => $value)
        <tr><td><strong>{{ $label }}:</strong></td><td>{{ $value }}</td></tr>
    @endforeach
</table>
@if ($messageText)
    <p><strong>Pesan/catatan:</strong></p>
    <blockquote style="margin:0;padding:10px 14px;background:#f8faf7;white-space:pre-wrap">{!! \App\Support\ChatMessageText::toSafeHtml($messageText) !!}</blockquote>
@endif
<p>Silakan masuk ke SIAKAD untuk membuka ruang bimbingan E-Tesis & Disertasi.</p>
<p>Salam,<br>SIAKAD {{ $siteConfiguration['institution_name'] }}</p>