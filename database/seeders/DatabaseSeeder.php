<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

require_once __DIR__.'/UserSeeder.php';
require_once __DIR__.'/AcademicSeeder.php';
require_once __DIR__.'/LegalPagesSeeder.php';
require_once __DIR__.'/LmsMateriSeeder.php';

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AcademicSeeder::class,
            LegalPagesSeeder::class,
            LmsMateriSeeder::class,
        ]);
    }
}
