<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if (!User::where('Username', 'test_user')->exists()) {
            User::factory()->create([
                'FirstName' => 'Test',
                'LastName' => 'User',
                'Username' => 'test_user',
                'Email' => 'test@example.com',
                'Role' => 'Doctor',
            ]);
        }
    }
}
