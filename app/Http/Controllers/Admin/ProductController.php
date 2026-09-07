<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductPlatform;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * All products (including inactive ones).
     */
    public function index(): Response
    {
        $products = Product::query()
            ->orderBy('sort_order')
            ->with(['prices', 'platform', 'type'])
            ->get();

        return Inertia::render('Admin/Products/Index', [
            'products' => ProductResource::collection($products)->resolve(),
        ]);
    }

    /**
     * The product creation form.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', [
            'platforms' => $this->platformOptions(),
            'types' => $this->typeOptions(),
        ]);
    }

    /**
     * Store a new product with its price tiers.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $product = Product::query()->create(collect($validated)->except('prices')->all());

        $this->syncTiers($product, $validated['prices'] ?? []);

        return redirect()->route('admin.products.index')
            ->with('success', 'محصول جدید ایجاد شد.');
    }

    /**
     * The product edit form.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => (new ProductResource($product->load(['prices', 'platform', 'type'])))->resolve(),
            'platforms' => $this->platformOptions(),
            'types' => $this->typeOptions(),
        ]);
    }

    /**
     * Update a product and its price tiers.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product);

        $product->update(collect($validated)->except('prices')->all());

        $this->syncTiers($product, $validated['prices'] ?? []);

        return redirect()->route('admin.products.index')
            ->with('success', 'اطلاعات محصول به‌روزرسانی شد.');
    }

    /**
     * Toggle product visibility.
     */
    public function toggle(Product $product): RedirectResponse
    {
        $product->forceFill(['is_active' => ! $product->is_active])->save();

        return back()->with('success', 'وضعیت محصول تغییر کرد.');
    }

    /**
     * Delete a product. Orders keep their immutable snapshot.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->orders()->exists()) {
            $product->forceFill(['is_active' => false])->save();

            return back()->with('info', 'محصول دارای سفارش است؛ به‌جای حذف، غیرفعال شد.');
        }

        $product->delete();

        return back()->with('success', 'محصول حذف شد.');
    }

    /**
     * Shared product validation (uniqueness of platform+type on update too).
     *
     * @return array<string, mixed>
     */
    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'platform' => ['required', 'string', 'max:32', Rule::exists('product_platforms', 'slug')],
            'type' => ['required', 'string', 'max:32', Rule::exists('product_types', 'slug')],
            'min_quantity' => ['required', 'integer', 'min:1'],
            'max_quantity' => ['required', 'integer', 'gte:min_quantity'],
            'step_quantity' => ['required', 'integer', 'min:1'],
            'base_price' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.*.min_quantity' => ['required_with:prices', 'integer', 'min:1'],
            'prices.*.max_quantity' => ['required_with:prices', 'integer', 'gte:prices.*.min_quantity'],
            'prices.*.price' => ['required_with:prices', 'integer', 'min:1'],
        ], [
            'title.required' => 'عنوان محصول الزامی است.',
            'base_price.required' => 'قیمت پایه الزامی است.',
            'platform.required' => 'انتخاب پلتفرم الزامی است.',
            'platform.exists' => 'پلتفرم انتخابی معتبر نیست.',
            'type.required' => 'انتخاب نوع سرویس الزامی است.',
            'type.exists' => 'نوع سرویس انتخابی معتبر نیست.',
        ]);

        $platform = ProductPlatform::query()->where('slug', $validated['platform'])->firstOrFail();
        $type = ProductType::query()->where('slug', $validated['type'])->firstOrFail();

        $taken = Product::query()
            ->where('product_platform_id', $platform->getKey())
            ->where('product_type_id', $type->getKey())
            ->when($product !== null, fn ($query) => $query->whereKeyNot($product->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'platform' => 'برای این پلتفرم و نوع محصول قبلاً محصولی ثبت شده است.',
            ]);
        }

        $validated['product_platform_id'] = $platform->getKey();
        $validated['product_type_id'] = $type->getKey();

        unset($validated['platform'], $validated['type']);

        return $validated;
    }

    /**
     * Replace the product's price tiers.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     */
    private function syncTiers(Product $product, array $tiers): void
    {
        $product->prices()->delete();

        if ($tiers === []) {
            return;
        }

        $product->prices()->createMany($tiers);
    }

    /**
     * Active platform options for the product form dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function platformOptions(): array
    {
        return ProductPlatform::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductPlatform $platform): array => [
                'value' => $platform->slug,
                'label' => $platform->name,
            ])
            ->all();
    }

    /**
     * Active service type options for the product form dropdowns.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return ProductType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductType $type): array => [
                'value' => $type->slug,
                'label' => $type->name,
            ])
            ->all();
    }
}
