<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\Category;
use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'string', 'exists:categories,slug'],
            'q' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = News::published()->with('category')->where(function ($query) {
            $query->whereDoesntHave('category')
                ->orWhereHas('category', fn ($category) => $category->where('is_active', true));
        });
        if (isset($validated['category'])) {
            $query->whereHas('category', fn ($category) => $category->where('slug', $validated['category']));
        }
        if (isset($validated['q'])) {
            $term = '%'.$validated['q'].'%';
            $query->where(fn ($search) => $search->where('title', 'like', $term)->orWhere('summary', 'like', $term));
        }

        return NewsResource::collection($query->orderByDesc('published_at')->paginate($validated['per_page'] ?? 20));
    }

    public function show(News $news): NewsResource
    {
        abort_unless($news->is_published && $news->published_at && $news->published_at->lte(now()), 404);
        abort_if($news->category && ! $news->category->is_active, 404);

        return new NewsResource($news->load('category'));
    }

    public function byCategory(Request $request, Category $category)
    {
        abort_unless($category->is_active, 404);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return NewsResource::collection(
            $category->news()->published()->with('category')->orderByDesc('published_at')->paginate($validated['per_page'] ?? 20),
        );
    }
}