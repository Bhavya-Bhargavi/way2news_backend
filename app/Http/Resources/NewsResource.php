<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'image_url' => $this->image_url,
            'article_url' => $this->article_url,
            'source_name' => $this->source_name,
            'published_at' => $this->published_at,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}