<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Product $resource
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->slug,
            'type_name' => $this->type?->name,
            'platform' => $this->platform?->slug,
            'platform_name' => $this->platform?->name,
            'title' => $this->title,
            'description' => $this->description,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'step_quantity' => $this->step_quantity,
            'base_price' => $this->base_price,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn ($price): array => [
                'min_quantity' => $price->min_quantity,
                'max_quantity' => $price->max_quantity,
                'price' => $price->price,
            ])),
        ];
    }
}
