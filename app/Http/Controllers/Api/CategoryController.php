<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(
            Category::where('is_active', true)->withCount(['news' => fn ($query) => $query->published()])->orderBy('name')->get(),
        );
    }
}