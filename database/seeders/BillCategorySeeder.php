<?php

namespace Database\Seeders;

use App\Models\BillCategory;
use Illuminate\Database\Seeder;

class BillCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rent/Mortgage', 'slug' => 'rent', 'icon' => 'tabler-home-dollar', 'color' => '#7C3AED', 'is_quick_pick' => true],
            ['name' => 'Utilities', 'slug' => 'utilities', 'icon' => 'tabler-bolt', 'color' => '#06B6D4', 'is_quick_pick' => true],
            ['name' => 'Phone', 'slug' => 'phone', 'icon' => 'tabler-phone', 'color' => '#DC2626', 'is_quick_pick' => true],
            ['name' => 'Internet', 'slug' => 'internet', 'icon' => 'tabler-wifi', 'color' => '#2563EB', 'is_quick_pick' => false],
            ['name' => 'Insurance', 'slug' => 'insurance', 'icon' => 'tabler-id', 'color' => '#059669', 'is_quick_pick' => false],
            ['name' => 'Streaming', 'slug' => 'streaming', 'icon' => 'tabler-device-tv', 'color' => '#DB2777', 'is_quick_pick' => false],
            ['name' => 'Car', 'slug' => 'car', 'icon' => 'tabler-car', 'color' => '#EA580C', 'is_quick_pick' => true],
            ['name' => 'Other', 'slug' => 'other', 'icon' => 'tabler-receipt', 'color' => '#64748B', 'is_quick_pick' => false],
        ];

        foreach ($categories as $index => $category) {
            BillCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
