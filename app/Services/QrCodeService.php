<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;

class QrCodeService
{
    public function generate(string $data, string $prefix = 'simak-qr-'): string
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), $prefix);
        if ($temporaryFile === false) {
            throw new RuntimeException('Tidak dapat membuat file sementara QR.');
        }

        $pngPath = $temporaryFile.'.png';
        unlink($temporaryFile);

        try {
            $logoPath = app(InstitutionSettings::class)->logoFilePath();
            if (! is_file($logoPath)) {
                throw new RuntimeException('Logo institusi untuk QR tidak tersedia.');
            }

            (new Builder(
                writer: new PngWriter,
                data: $data,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 350,
                margin: 8,
                logoPath: $logoPath,
                logoResizeToWidth: 70,
                logoResizeToHeight: 70,
                logoPunchoutBackground: true,
            ))->build()->saveToFile($pngPath);

            if (! is_file($pngPath) || filesize($pngPath) === 0) {
                throw new RuntimeException('QR gagal dibuat.');
            }

            return $pngPath;
        } catch (\Throwable $exception) {
            if (is_file($pngPath)) {
                unlink($pngPath);
            }
            throw $exception;
        }
    }
}
