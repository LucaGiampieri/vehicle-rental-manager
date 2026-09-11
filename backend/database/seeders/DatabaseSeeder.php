<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Inserisce i dati dimostrativi soltanto negli ambienti sicuri.
     */
    public function run(): void
    {
        // Evita di creare account e dati demo in produzione
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
