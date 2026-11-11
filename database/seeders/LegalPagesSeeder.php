<?php

namespace Database\Seeders;

use App\Support\LegalPagesData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegalPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds static/legal page mockups into simak_main if table exists,
     * or logs the seed availability.
     */
    public function run(): void
    {
        $schema = Schema::connection('simak_main');

        if ($schema->hasTable('simak_static_pages')) {
            $pages = [
                LegalPagesData::privacy(),
                LegalPagesData::terms(),
                LegalPagesData::accessibility(),
            ];

            foreach ($pages as $page) {
                DB::connection('simak_main')->table('simak_static_pages')->updateOrInsert(
                    ['slug' => $page['key']],
                    [
                        'title' => $page['title'],
                        'subtitle' => $page['subtitle'],
                        'eyebrow' => $page['eyebrow'],
                        'lede' => $page['lede'],
                        'content_json' => json_encode($page, JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
