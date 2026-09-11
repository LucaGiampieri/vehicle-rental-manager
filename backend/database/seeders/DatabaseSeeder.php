<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Inserisce i dati dimostrativi dell'applicazione.
     */
    public function run(): void
    {
        $this->call(DemoDataSeeder::class);
    }
}
