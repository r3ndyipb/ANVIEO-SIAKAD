<?php

declare(strict_types=1);

$requiredPhpVersion = '8.3.0';
$requiredExtensions = [
    'bcmath' => 'BCMath',
    'ctype' => 'Ctype',
    'curl' => 'cURL',
    'fileinfo' => 'Fileinfo',
    'gd' => 'GD',
    'iconv' => 'Iconv',
    'mbstring' => 'Mbstring',
    'openssl' => 'OpenSSL',
    'pdo_mysql' => 'PDO MySQL',
    'tokenizer' => 'Tokenizer',
    'xml' => 'XML',
    'zip' => 'ZIP',
];

$checks = [];
$checks[] = [
    'name' => 'PHP',
    'required' => '>= '.$requiredPhpVersion,
    'actual' => PHP_VERSION,
    'ok' => version_compare(PHP_VERSION, $requiredPhpVersion, '>='),
];

foreach ($requiredExtensions as $extension => $label) {
    $enabled = extension_loaded($extension);
    if ($extension === 'pdo_mysql' && class_exists(PDO::class)) {
        $enabled = $enabled && in_array('mysql', PDO::getAvailableDrivers(), true);
    }

    $checks[] = [
        'name' => $label,
        'required' => 'Aktif',
        'actual' => $enabled ? 'Aktif' : 'Tidak aktif',
        'ok' => $enabled,
    ];
}

$allPassed = count(array_filter($checks, static fn (array $check): bool => ! $check['ok'])) === 0;
$serverSoftware = (string) ($_SERVER['SERVER_SOFTWARE'] ?? '');
$isLiteSpeed = stripos($serverSoftware, 'LiteSpeed') !== false;
$httpsEnabled = (
    (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
);

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pemeriksaan Persyaratan SIAKAD</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #203a55; background: #f4f8fb; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px 16px; }
        main { width: min(100%, 760px); margin: 0 auto; padding: clamp(20px, 5vw, 36px); border: 1px solid #d9e5ef; border-radius: 14px; background: #fff; box-shadow: 0 18px 50px rgba(32, 58, 85, .09); }
        h1 { margin: 0; font-size: clamp(22px, 5vw, 30px); }
        .intro { margin: 9px 0 22px; color: #63798d; line-height: 1.6; }
        .result { margin: 0 0 20px; padding: 13px 15px; border-radius: 8px; font-weight: 750; }
        .result.pass { background: #e8f6ed; color: #22613b; }
        .result.fail { background: #fff2e1; color: #87511c; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 11px 9px; border-bottom: 1px solid #e8eef3; font-size: 14px; }
        th { color: #5f7385; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .status { font-weight: 750; }
        .status.pass { color: #267245; }
        .status.fail { color: #a34332; }
        .info { margin: 19px 0 0; padding-top: 16px; border-top: 1px solid #e8eef3; color: #526a7e; font-size: 14px; line-height: 1.65; }
        .notice { margin-top: 18px; padding: 12px 14px; border-left: 4px solid #d9ae2f; background: #fff9e8; color: #6d5921; font-size: 13px; line-height: 1.55; }
    </style>
</head>
<body>
<main>
    <h1>Pemeriksaan Persyaratan SIAKAD</h1>
    <p class="intro">Pemeriksaan ini menggunakan PHP dan ekstensi pada web server, bukan PHP CLI.</p>
    <p class="result <?= $allPassed ? 'pass' : 'fail' ?>">
        <?= $allPassed ? 'LULUS — persyaratan PHP dan ekstensi terpenuhi.' : 'BELUM LULUS — aktifkan atau perbarui komponen yang berstatus Tidak aktif.' ?>
    </p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Komponen</th><th>Persyaratan</th><th>Terpasang</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($checks as $check) { ?>
                <tr>
                    <td><?= escapeHtml($check['name']) ?></td>
                    <td><?= escapeHtml($check['required']) ?></td>
                    <td><?= escapeHtml($check['actual']) ?></td>
                    <td class="status <?= $check['ok'] ? 'pass' : 'fail' ?>"><?= $check['ok'] ? 'OK' : 'Perlu tindakan' ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <p class="info">
        Web server: <strong><?= $isLiteSpeed ? 'LiteSpeed terdeteksi' : 'LiteSpeed tidak terdeteksi' ?></strong><br>
        HTTPS: <strong><?= $httpsEnabled ? 'Aktif' : 'Belum terdeteksi' ?></strong>
    </p>
    <p class="notice"><strong>Penting:</strong> hapus file PHP ini dari document root segera setelah pemeriksaan. Jangan biarkan skrip pemeriksaan server tersedia untuk publik.</p>
</main>
</body>
</html>
