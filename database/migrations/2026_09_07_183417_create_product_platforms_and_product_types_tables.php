<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dynamic product taxonomies: platforms and product types become
     * manageable database rows (instead of the hard-coded enums) and the
     * products table references them through foreign keys.
     */
    public function up(): void
    {
        Schema::create('product_platforms', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name', 64);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the previously hard-coded enum values so existing products,
        // forms and tests keep working without a manual seeding step.
        DB::table('product_platforms')->insert([
            ['slug' => 'instagram', 'name' => 'اینستاگرام', 'is_active' => true, 'sort_order' => 1],
        ]);

        DB::table('product_types')->insert([
            ['slug' => 'followers', 'name' => 'فالوور', 'is_active' => true, 'sort_order' => 1],
            ['slug' => 'likes', 'name' => 'لایک', 'is_active' => true, 'sort_order' => 2],
        ]);

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_platform_id')->nullable()->constrained('product_platforms')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
        });

        // Backfill existing products from their enum snapshots.
        DB::statement('UPDATE products SET product_platform_id = (SELECT id FROM product_platforms WHERE slug = products.platform)');
        DB::statement('UPDATE products SET product_type_id = (SELECT id FROM product_types WHERE slug = products.type)');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_platform_type_unique');
            $table->unique(['product_platform_id', 'product_type_id'], 'products_platform_type_unique');
            $table->dropColumn(['type', 'platform']);
        });

        // Orders keep the same taxonomy snapshot through foreign keys.
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('product_platform_id')->nullable()->after('product_id')->constrained('product_platforms')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->after('product_platform_id')->constrained('product_types')->nullOnDelete();
        });

        DB::statement('UPDATE orders SET product_platform_id = (SELECT id FROM product_platforms WHERE slug = orders.product_platform)');
        DB::statement('UPDATE orders SET product_type_id = (SELECT id FROM product_types WHERE slug = orders.product_type)');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['product_type', 'product_platform']);
        });
    }

    public function down(): void
    {
        // Restore the enum snapshot columns on orders first (they only
        // reference the taxonomy tables through these statements).
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'product_type')) {
                $table->string('product_type', 32)->nullable()->after('product_id')->comment('ProductType enum');
            }

            if (! Schema::hasColumn('orders', 'product_platform')) {
                $table->string('product_platform', 32)->nullable()->after('product_type')->comment('Platform enum');
            }
        });

        if (Schema::hasColumn('orders', 'product_type_id')) {
            DB::statement('UPDATE orders SET product_type = (SELECT slug FROM product_types WHERE id = orders.product_type_id) WHERE product_type IS NULL');
            DB::statement('UPDATE orders SET product_platform = (SELECT slug FROM product_platforms WHERE id = orders.product_platform_id) WHERE product_platform IS NULL');

            foreach (['product_type_id', 'product_platform_id'] as $column) {
                $this->quietly(fn () => Schema::table('orders', fn (Blueprint $table) => $table->dropForeign([$column])));

                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        // Restore the enum snapshot columns on products. The platform FK may
        // be backed by the combined (product_platform_id, product_type_id)
        // index, so drop the FKs before touching that unique index.
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'platform')) {
                $table->string('platform', 32)->nullable()->comment('Platform enum');
            }

            if (! Schema::hasColumn('products', 'type')) {
                $table->string('type', 32)->nullable()->comment('ProductType enum');
            }
        });

        if (Schema::hasColumn('products', 'product_platform_id')) {
            DB::statement('UPDATE products SET platform = (SELECT slug FROM product_platforms WHERE id = products.product_platform_id) WHERE platform IS NULL');
            DB::statement('UPDATE products SET type = (SELECT slug FROM product_types WHERE id = products.product_type_id) WHERE type IS NULL');

            foreach (['product_type_id', 'product_platform_id'] as $column) {
                $this->quietly(fn () => Schema::table('products', fn (Blueprint $table) => $table->dropForeign([$column])));
            }

            $this->quietly(fn () => Schema::table('products', fn (Blueprint $table) => $table->dropUnique('products_platform_type_unique')));
            $this->quietly(fn () => Schema::table('products', fn (Blueprint $table) => $table->unique(['platform', 'type'], 'products_platform_type_unique')));

            Schema::table('products', function (Blueprint $table): void {
                foreach (['product_type_id', 'product_platform_id'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('product_types');
        Schema::dropIfExists('product_platforms');
    }

    /**
     * Run a schema change tolerating objects a previously interrupted
     * rollback already dropped (MySQL lacks DROP ... IF EXISTS for keys).
     */
    private function quietly(callable $change): void
    {
        try {
            $change();
        } catch (QueryException $e) {
            $driverCode = (int) ($e->errorInfo[1] ?? 0);

            // 1091 can't drop missing key/column, 1060 duplicate column,
            // 1061 duplicate key name, 1553 index needed by constraint.
            if (! in_array($driverCode, [1060, 1061, 1091, 1553], true)) {
                throw $e;
            }
        }
    }
};
