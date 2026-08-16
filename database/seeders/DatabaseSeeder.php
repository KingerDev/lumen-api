<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds only the templates — deliberately no user.
     *
     * The account is created with `php artisan lumen:user`, so running the
     * seeder in production can never leave a test login behind.
     */
    public function run(): void
    {
        $this->call(TemplateSeeder::class);
    }
}
