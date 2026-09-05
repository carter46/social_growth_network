<?php

namespace Database\Seeders;

use App\Enums\PlatformProductType;
use App\Models\PlatformCategory;
use Illuminate\Database\Seeder;

class PlatformCategorySeeder extends Seeder
{
    public function run(): void
    {
        $trees = [
            PlatformProductType::SocialService->value => [
                ['name' => 'Growth', 'slug' => 'social-growth'],
                ['name' => 'Engagement', 'slug' => 'social-engagement'],
            ],
        ];

        foreach ($trees as $type => $children) {
            $sort = 0;
            foreach ($children as $child) {
                PlatformCategory::firstOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'product_type' => $type,
                        'parent_id' => null,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
