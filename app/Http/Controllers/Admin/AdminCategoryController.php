<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(
            Category::withCount(['news' => fn ($query) => $query->published()])->orderBy('name')->paginate(50),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedCategory($request);
        $validated['slug'] = $validated['slug'] ?? $this->uniqueSlug($validated['name']);

        return (new CategoryResource(Category::create($validated)))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category): CategoryResource
    {
        $category->update($this->validatedCategory($request, $category));

        return new CategoryResource($category->refresh());
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function validatedCategory(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => [$category ? 'sometimes' : 'required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($category?->id)],
            'slug' => ['sometimes', 'string', 'max:140', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}