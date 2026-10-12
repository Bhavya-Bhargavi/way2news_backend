<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feed_contains_only_published_news(): void
    {
        $category = Category::create(['name' => 'Local', 'slug' => 'local']);
        News::create([
            'category_id' => $category->id,
            'title' => 'Published headline',
            'slug' => 'published-headline',
            'summary' => 'A published test story.',
            'article_url' => 'https://example.com/story',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);
        News::create([
            'category_id' => $category->id,
            'title' => 'Draft headline',
            'slug' => 'draft-headline',
            'summary' => 'A draft test story.',
            'article_url' => 'https://example.com/draft',
            'is_published' => false,
        ]);

        $this->getJson('/api/news')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Published headline')
            ->assertJsonCount(1, 'data');
    }

    public function test_news_detail_returns_the_outbound_article_url(): void
    {
        $category = Category::create(['name' => 'World', 'slug' => 'world']);
        News::create([
            'category_id' => $category->id,
            'title' => 'A headline',
            'slug' => 'a-headline',
            'summary' => 'A test summary.',
            'article_url' => 'https://example.com/article',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/news/a-headline')
            ->assertOk()
            ->assertJsonPath('data.article_url', 'https://example.com/article');
    }
}