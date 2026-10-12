<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['name' => 'World', 'slug' => 'world'],
            ['name' => 'Technology', 'slug' => 'technology'],
            ['name' => 'Business', 'slug' => 'business'],
            ['name' => 'Sports', 'slug' => 'sports'],
        ])->mapWithKeys(fn (array $attributes) => [
            $attributes['slug'] => Category::firstOrCreate(['slug' => $attributes['slug']], $attributes),
        ]);

        if (app()->environment(['local', 'testing'])) {
            $category = $categories['technology'];
            News::firstOrCreate(
                ['slug' => 'demo-news-story'],
                [
                    'category_id' => $category->id,
                    'title' => 'Demo news story',
                    'summary' => 'A placeholder article for testing the Way2News API. Replace it with an actual editorial item.',
                    'image_url' => null,
                    'article_url' => 'https://example.com/',
                    'source_name' => 'Demo source',
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }

        $adminPhone = env('ADMIN_PHONE');
        if ($adminPhone) {
            User::firstOrCreate(
                ['phone' => $adminPhone],
                ['name' => env('ADMIN_NAME', 'Way2News Admin'), 'is_admin' => true],
            )->forceFill(['is_admin' => true])->save();
        }
    }
}
