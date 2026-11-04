<?php

namespace Tests\Feature;

use App\Services\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    public function test_generates_a_readable_branded_png_qr_code(): void
    {
        $path = app(QrCodeService::class)->generate('https://simak.example.test/verify/qr-test');

        try {
            $this->assertFileExists($path);
            $this->assertSame('image/png', mime_content_type($path));
            $image = getimagesize($path);
            $this->assertNotFalse($image);
            $this->assertGreaterThanOrEqual(300, $image[0]);
            $this->assertSame($image[0], $image[1]);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
