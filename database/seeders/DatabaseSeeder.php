<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Base/ seeders here are needed in
     * every environment (plan §3 repo structure) — demo-only seeders live
     * under Demo/ and are never called from here.
     */
    public function run(): void
    {
        $this->call(BusinessRolesSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
