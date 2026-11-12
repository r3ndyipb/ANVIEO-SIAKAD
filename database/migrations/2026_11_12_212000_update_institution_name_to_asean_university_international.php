<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection('simak_main');
        if (Schema::connection('simak_main')->hasTable('s1m4k_masterpt')) {
            $connection->table('s1m4k_masterpt')
                ->where('id_perguruan_tinggi', 'PT-001')
                ->orWhere('nama_perguruan_tinggi', 'like', '%Universitas SIAKAD%')
                ->update([
                    'nama_perguruan_tinggi' => 'ASEAN University International',
                    'email' => 'info@asean.university',
                    'website' => 'https://asean.university',
                    'nama_wilayah' => 'Jakarta Selatan',
                ]);
        }

        if (Schema::connection('simak_main')->hasTable('simak_site_settings')) {
            $siteSettings = $connection->table('simak_site_settings')->where('id', 1)->first();
            $updates = [
                'updated_at' => now(),
            ];
            if ($siteSettings === null || ! filled($siteSettings->logo_path) || str_contains((string) $siteSettings->logo_path, 'logo-siakad')) {
                $updates['logo_path'] = 'branding/aui-logo.png';
            }
            if ($siteSettings === null || ! filled($siteSettings->preloader_logo_path) || str_contains((string) $siteSettings->preloader_logo_path, 'logo-siakad')) {
                $updates['preloader_logo_path'] = 'branding/aui-logo.png';
            }
            if ($siteSettings === null || ! filled($siteSettings->ministry_name)) {
                $updates['ministry_name'] = 'Kementerian Pendidikan Tinggi, Sains, dan Teknologi';
            }
            $connection->table('simak_site_settings')->updateOrInsert(['id' => 1], $updates);
        }
    }

    public function down(): void
    {
        $connection = DB::connection('simak_main');
        if (Schema::connection('simak_main')->hasTable('s1m4k_masterpt')) {
            $connection->table('s1m4k_masterpt')
                ->where('id_perguruan_tinggi', 'PT-001')
                ->update([
                    'nama_perguruan_tinggi' => 'ASEAN University International',
                ]);
        }
    }
};
