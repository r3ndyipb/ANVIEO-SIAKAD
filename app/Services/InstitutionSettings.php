<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class InstitutionSettings
{
    private const DEFAULT_MOTTO = 'Motto Perguruan Tinggi';

    private const DEFAULT_HERO_IMAGES = [
        'hero-01.jpg',
        'hero-02.jpg',
        'hero-03.jpg',
        'hero-04.jpg',
        'hero-05.jpg',
        'hero-06.jpg',
        'hero-07.jpg',
    ];

    public function available(): bool
    {
        $schema = Schema::connection('simak_main');

        return $schema->hasTable('simak_site_settings')
            && $schema->hasTable('simak_hero_images')
            && $schema->hasTable('simak_mail_settings')
            && $schema->hasTable('simak_payment_gateways')
            && $schema->hasTable('simak_payment_transactions');
    }

    /**
     * @return array{institution_name: string, app_name: string, app_short_name: string, app_full_name: string, motto: string, ministry_name: string, primary_color: string, primary_foreground: string, logo_url: string, preloader_logo_url: string, hero_images: list<string>}
     */
    public function forDisplay(): array
    {
        $connection = DB::connection('simak_main');
        $settings = Schema::connection('simak_main')->hasTable('simak_site_settings')
            ? $connection->table('simak_site_settings')->where('id', 1)->first()
            : null;
        $institutionName = Schema::connection('simak_main')->hasTable('s1m4k_masterpt')
            ? (string) $connection->table('s1m4k_masterpt')
                ->whereNotNull('nama_perguruan_tinggi')
                ->value('nama_perguruan_tinggi')
            : '';
        $institutionName = trim($institutionName);
        if ($institutionName === '' || $institutionName === 'Perguruan Tinggi' || str_contains($institutionName, 'Universitas SIAKAD')) {
            $institutionName = 'ASEAN University International';
        }
        $primaryColor = is_string($settings?->primary_color ?? null)
            && preg_match('/^#[0-9A-Fa-f]{6}$/', $settings->primary_color) === 1
            ? strtolower($settings->primary_color)
            : '#2167a5';

        $heroPaths = Schema::connection('simak_main')->hasTable('simak_hero_images')
            ? $connection->table('simak_hero_images')->orderBy('slot')->pluck('image_path', 'slot')
            : collect();
        $heroImages = [];
        foreach (self::DEFAULT_HERO_IMAGES as $index => $image) {
            $path = $heroPaths->get($index + 1);
            $heroImages[] = filled($path)
                ? $this->publicImageUrl((string) $path, asset('images/landing/'.$image))
                : asset('images/landing/'.$image);
        }

        $logoUrl = $this->publicImageUrl($settings?->logo_path, asset('images/landing/default-campus-logo.png'));

        return [
            'institution_name' => $institutionName,
            'app_name' => 'SIAKAD '.$institutionName,
            'app_short_name' => 'SIAKAD',
            'app_full_name' => 'SIAKAD (Sistem Informasi Akademik)',
            'motto' => filled($settings?->motto) ? trim((string) $settings->motto) : self::DEFAULT_MOTTO,
            'ministry_name' => trim((string) ($settings?->ministry_name ?? '')) !== ''
                ? trim((string) $settings->ministry_name)
                : 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS DAN TEKNOLOGI',
            'primary_color' => $primaryColor,
            'primary_foreground' => $this->primaryForeground($primaryColor),
            'logo_url' => $logoUrl,
            'preloader_logo_url' => $this->publicImageUrl(
                $settings?->preloader_logo_path,
                file_exists(public_path('images/landing/icon.png')) ? asset('images/landing/icon.png') : $logoUrl
            ),
            'hero_images' => $heroImages,
        ];
    }

    public function ministryName(): string
    {
        return $this->forDisplay()['ministry_name'];
    }

    /**
     * @return list<string>
     */
    public function ministryNameLines(): array
    {
        return array_values(array_filter(
            preg_split('/\R/u', $this->ministryName()) ?: [],
            static fn (string $line): bool => trim($line) !== '',
        ));
    }

    public function institutionName(): string
    {
        return $this->forDisplay()['institution_name'];
    }

    public function cityName(): string
    {
        $city = Schema::connection('simak_main')->hasTable('s1m4k_masterpt')
            ? (string) DB::connection('simak_main')->table('s1m4k_masterpt')
                ->whereNotNull('nama_wilayah')
                ->value('nama_wilayah')
            : '';

        $city = trim($city);
        if ($city === '' || strcasecmp($city, 'Ternate') === 0) {
            return 'Jakarta Selatan';
        }

        return $city;
    }

    private function primaryForeground(string $color): string
    {
        $channels = array_map(
            static fn (string $channel): float => hexdec($channel) / 255,
            str_split(substr($color, 1), 2),
        );
        $luminance = array_sum(array_map(
            static fn (float $channel, int $index): float => [0.2126, 0.7152, 0.0722][$index]
                * ($channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2),
            $channels,
            array_keys($channels),
        ));

        return $luminance > 0.214 ? '#142235' : '#ffffff';
    }

    public function logoFilePath(): string
    {
        $settings = Schema::connection('simak_main')->hasTable('simak_site_settings')
            ? DB::connection('simak_main')->table('simak_site_settings')->where('id', 1)->first(['logo_path', 'preloader_logo_path'])
            : null;

        $candidates = array_filter([
            $settings?->logo_path,
            $settings?->preloader_logo_path,
        ]);

        foreach ($candidates as $candidate) {
            $cleaned = ltrim((string) $candidate, '/');
            $configuredPath = Storage::disk('public')->path($cleaned);
            if (is_file($configuredPath)) {
                if (strtolower(pathinfo($configuredPath, PATHINFO_EXTENSION)) === 'webp') {
                    return $this->convertWebpLogoForPdf($configuredPath);
                }

                return $configuredPath;
            }

            $publicCandidate = public_path($cleaned);
            if (is_file($publicCandidate)) {
                if (strtolower(pathinfo($publicCandidate, PATHINFO_EXTENSION)) === 'webp') {
                    return $this->convertWebpLogoForPdf($publicCandidate);
                }

                return $publicCandidate;
            }
        }

        if (is_file(public_path('images/landing/default-campus-logo.png'))) {
            return public_path('images/landing/default-campus-logo.png');
        }

        return public_path('images/landing/icon.png');
    }

    private function convertWebpLogoForPdf(string $sourcePath): string
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagepng')) {
            throw new RuntimeException('Dukungan WebP GD diperlukan untuk mencetak logo aplikasi.');
        }

        $modifiedAt = filemtime($sourcePath);
        $targetDirectory = storage_path('app/private/branding');
        $targetPath = $targetDirectory.'/application-logo-'.hash('sha256', $sourcePath.'|'.$modifiedAt).'.png';
        if (is_file($targetPath)) {
            return $targetPath;
        }

        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0750, true) && ! is_dir($targetDirectory)) {
            throw new RuntimeException('Direktori cache logo aplikasi tidak dapat dibuat.');
        }

        $image = imagecreatefromwebp($sourcePath);
        if ($image === false) {
            throw new RuntimeException('Logo WebP aplikasi tidak dapat dibaca untuk dicetak.');
        }

        $temporaryPath = $targetPath.'.'.bin2hex(random_bytes(6)).'.tmp';
        try {
            if (! imagepng($image, $temporaryPath)) {
                throw new RuntimeException('Logo WebP aplikasi tidak dapat dikonversi untuk dicetak.');
            }

            if (! rename($temporaryPath, $targetPath)) {
                throw new RuntimeException('Logo aplikasi hasil konversi tidak dapat disimpan.');
            }
        } finally {
            imagedestroy($image);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }

        return $targetPath;
    }

    private function publicImageUrl(?string $path, string $fallback): string
    {
        if (! filled($path)) {
            return $fallback;
        }

        $trimmed = trim((string) $path);

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }

        $cleanPath = ltrim($trimmed, '/');

        if (Storage::disk('public')->exists($cleanPath)) {
            return url('storage/'.$cleanPath);
        }

        if (file_exists(public_path($cleanPath))) {
            return asset($cleanPath);
        }

        return $fallback;
    }
}
