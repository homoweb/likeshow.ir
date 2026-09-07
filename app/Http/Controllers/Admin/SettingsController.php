<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductPlatform;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage the dynamic product taxonomies: platforms (اینستاگرام، تلگرام، …)
 * and service types (فالوور، لایک، …). Rows are soft-retired through
 * is_active so existing orders keep their taxonomy snapshots.
 */
class SettingsController extends Controller
{
    /**
     * The taxonomy management screen.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Settings/Taxonomies', [
            'platforms' => $this->platformRows(),
            'types' => $this->typeRows(),
        ]);
    }

    /**
     * Store a new platform.
     */
    public function storePlatform(Request $request): RedirectResponse
    {
        $validated = $this->validateTaxonomy($request, 'product_platforms');

        ProductPlatform::query()->create($validated);

        return back()->with('success', 'پلتفرم جدید اضافه شد.');
    }

    /**
     * Update an existing platform.
     */
    public function updatePlatform(Request $request, ProductPlatform $platform): RedirectResponse
    {
        $validated = $this->validateTaxonomy($request, 'product_platforms', $platform->getKey());

        $platform->update($validated);

        return back()->with('success', 'پلتفرم به‌روزرسانی شد.');
    }

    /**
     * Toggle a platform's availability.
     */
    public function togglePlatform(ProductPlatform $platform): RedirectResponse
    {
        $platform->forceFill(['is_active' => ! $platform->is_active])->save();

        return back()->with('success', 'وضعیت پلتفرم تغییر کرد.');
    }

    /**
     * Store a new service type.
     */
    public function storeType(Request $request): RedirectResponse
    {
        $validated = $this->validateTaxonomy($request, 'product_types');

        ProductType::query()->create($validated);

        return back()->with('success', 'نوع سرویس جدید اضافه شد.');
    }

    /**
     * Update an existing service type.
     */
    public function updateType(Request $request, ProductType $type): RedirectResponse
    {
        $validated = $this->validateTaxonomy($request, 'product_types', $type->getKey());

        $type->update($validated);

        return back()->with('success', 'نوع سرویس به‌روزرسانی شد.');
    }

    /**
     * Toggle a service type's availability.
     */
    public function toggleType(ProductType $type): RedirectResponse
    {
        $type->forceFill(['is_active' => ! $type->is_active])->save();

        return back()->with('success', 'وضعیت نوع سرویس تغییر کرد.');
    }

    /**
     * Shared taxonomy validation: unique Persian name and unique slug.
     *
     * @return array<string, mixed>
     */
    private function validateTaxonomy(Request $request, string $table, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/', Rule::unique($table, 'slug')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:64', Rule::unique($table, 'name')->ignore($ignoreId)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.required' => 'شناسه لاتین الزامی است.',
            'slug.unique' => 'این شناسه قبلاً ثبت شده است.',
            'slug.regex' => 'شناسه فقط می‌تواند حروف لاتین کوچک، عدد، خط تیره و آندرلاین باشد.',
            'name.required' => 'نام نمایشی الزامی است.',
            'name.unique' => 'این نام قبلاً ثبت شده است.',
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function platformRows(): array
    {
        return ProductPlatform::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductPlatform $platform): array => [
                'id' => $platform->id,
                'slug' => $platform->slug,
                'name' => $platform->name,
                'is_active' => $platform->is_active,
                'sort_order' => $platform->sort_order,
                'products_count' => $platform->products_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function typeRows(): array
    {
        return ProductType::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductType $type): array => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'is_active' => $type->is_active,
                'sort_order' => $type->sort_order,
                'products_count' => $type->products_count,
            ])
            ->all();
    }
}
