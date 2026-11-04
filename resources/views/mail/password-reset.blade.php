<p>Yth. {{ $recipientName }},</p>
<p>Permintaan reset password akun SIAKAD Anda telah diterima.</p>
<p>Password sementara Anda: <strong>{{ $temporaryPassword }}</strong></p>
<p>Password sementara hanya dapat digunakan setelah aktivasi. Tautan aktivasi berlaku selama 30 menit dan hanya dapat digunakan satu kali:</p>
<p><a href="{{ $activationUrl }}">Aktifkan password SIAKAD</a></p>
<p>Jika Anda tidak meminta reset password, abaikan email ini dan jangan bagikan tautan atau password tersebut.</p>