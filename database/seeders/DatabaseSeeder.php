<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Dados de demonstração só fora de produção.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
