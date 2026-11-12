<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = File::get(base_path('supabase/seed.sql'));

        DB::transaction(fn () => DB::unprepared(preg_replace('/\A\s*begin;\s*|\s*commit;\s*\z/i', '', $catalog)));
    }
}
