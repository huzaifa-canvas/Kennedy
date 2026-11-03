<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            BillCategorySeeder::class,
            AppSettingSeeder::class,
        ]);

        User::updateOrCreate(
            ['email' => 'admin@kennedy.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
    }
}
