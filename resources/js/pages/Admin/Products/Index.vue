<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/components/AppLayout.vue';
import AppLink from '@/components/AppLink.vue';
import AppSpinner from '@/components/AppSpinner.vue';
import { toFa } from '@/lib/ui';
import adminProducts from '@/routes/admin/products';
import type { Product } from '@/types/likeshow';

defineProps<{ products: Product[] }>();

const pending = ref<number | null>(null);

const toggle = (product: Product): void => {
    pending.value = product.id;

    router.patch(
        adminProducts.toggle.url(product),
        {},
        {
            onFinish: () => {
                pending.value = null;
            },
        },
    );
};

const destroy = (product: Product): void => {
    if (!window.confirm(`محصول «${product.title}» حذف شود؟`)) {
        return;
    }

    pending.value = product.id;

    router.delete(adminProducts.destroy.url(product), {
        onFinish: () => {
            pending.value = null;
        },
    });
};
</script>

<template>
    <AppLayout kind="admin">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-white">محصولات</h1>
            <AppLink
                :href="adminProducts.create.url()"
                class="rounded-xl bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400"
            >
                محصول جدید
            </AppLink>
        </div>

        <div
            class="mt-6 overflow-x-auto rounded-2xl border border-white/10 bg-slate-900/60"
        >
            <table class="w-full text-right text-sm">
                <thead class="text-xs text-slate-400">
                    <tr class="border-b border-white/10">
                        <th class="px-4 py-3 font-medium">عنوان</th>
                        <th class="px-4 py-3 font-medium">نوع</th>
                        <th class="px-4 py-3 font-medium">قیمت پایه</th>
                        <th class="px-4 py-3 font-medium">محدوده</th>
                        <th class="px-4 py-3 font-medium">پله‌ها</th>
                        <th class="px-4 py-3 font-medium">وضعیت</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="product in products" :key="product.id">
                        <td class="px-4 py-3 text-slate-200">
                            {{ product.title }}
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ product.platform_name }} ·
                            {{ product.type_name }}
                        </td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ toFa(product.base_price) }} تومان
                        </td>
                        <td class="px-4 py-3 text-slate-400">
                            {{ toFa(product.min_quantity) }} تا
                            {{ toFa(product.max_quantity) }}
                        </td>
                        <td class="px-4 py-3 text-slate-400">
                            {{ toFa(product.prices?.length ?? 0) }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="{
                                    'text-emerald-400': product.is_active,
                                    'text-rose-400': !product.is_active,
                                }"
                            >
                                {{ product.is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3 text-xs">
                                <button
                                    type="button"
                                    :disabled="pending === product.id"
                                    class="inline-flex items-center gap-1.5 text-amber-400 transition hover:text-amber-300 disabled:opacity-50"
                                    @click="toggle(product)"
                                >
                                    <AppSpinner
                                        v-if="pending === product.id"
                                        class="size-3"
                                    />
                                    {{ product.is_active ? 'غیرفعال' : 'فعال' }}
                                </button>
                                <AppLink
                                    :href="adminProducts.edit.url(product)"
                                    class="text-indigo-400 hover:text-indigo-300"
                                >
                                    ویرایش
                                </AppLink>
                                <button
                                    type="button"
                                    :disabled="pending === product.id"
                                    class="inline-flex items-center gap-1.5 text-rose-400 transition hover:text-rose-300 disabled:opacity-50"
                                    @click="destroy(product)"
                                >
                                    <AppSpinner
                                        v-if="pending === product.id"
                                        class="size-3"
                                    />
                                    حذف
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
