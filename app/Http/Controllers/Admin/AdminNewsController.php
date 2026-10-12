<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\NewsResource;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminNewsController extends Controller
{
    public function index()
    {
        return NewsResource::collection(News::with('category')->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedNews($request);
        $validated['slug'] = $validated['slug'] ?? $this->uniqueSlug($validated['title']);

        return (new NewsResource(News::create($validated)->load('category')))->response()->setStatusCode(201);
    }

    public function show(News $news): NewsResource
    {
        return new NewsResource($news->load('category'));
    }

    public function update(Request $request, News $news): NewsResource
    {
        $news->update($this->validatedNews($request, $news));

        return new NewsResource($news->refresh()->load('category'));
    }

    public function destroy(News $news)
    {
        $news->delete();

        return response()->json(['message' => 'News deleted.']);
    }

    private function validatedNews(Request $request, ?News $news = null): array
    {
        return $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'title' => [$news ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', Rule::unique('news', 'slug')->ignore($news?->id)],
            'summary' => [$news ? 'sometimes' : 'required', 'string', 'max:5000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'article_url' => [$news ? 'sometimes' : 'required', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date', 'required_if:is_published,true'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'news';
        $slug = $base;
        $suffix = 2;

        while (News::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}