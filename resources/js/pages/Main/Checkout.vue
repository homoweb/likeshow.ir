<script setup lang="ts">
import { usePage, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/components/AppLayout.vue';
import AppSpinner from '@/components/AppSpinner.vue';
import {
    totalPriceFor,
    toFa,
    unitPriceFor,
} from '@/lib/ui';
import mainCheckout from '@/routes/main/checkout';
import type { Product, SharedProps } from '@/types/likeshow';

const props = defineProps<{ product: Product }>();

const page = usePage();
const user = computed(() => (page.props as unknown as SharedProps).auth.user);

const minQuantity = computed(() =>
    Math.max(1000, Number(props.product.min_quantity)),
);
const maxQuantity = computed(() =>
    Math.min(1000000, Number(props.product.max_quantity)),
);
const stepQuantity = computed(
    () => Number(props.product.step_quantity) || 1000,
);

const form = useForm({
    quantity: minQuantity.value,
    target_username: '',
});

const snap = (value: number): number => {
    const bounded = Math.min(
        Math.max(value, minQuantity.value),
        maxQuantity.value,
    );

    return (
        minQuantity.value +
        Math.round((bounded - minQuantity.value) / stepQuantity.value) *
            stepQuantity.value
    );
};

const changeBy = (delta: number): void => {
    form.quantity = snap(form.quantity + delta);
};

const quickChips = computed(() =>
    [1000, 5000, 10000, 50000]
        .map((value) => snap(value))
        .filter((value, index, list) => list.indexOf(value) === index)
        .slice(0, 4),
);

const unitPrice = computed(() => unitPriceFor(props.product, form.quantity));
const totalPrice = computed(() => totalPriceFor(props.product, form.quantity));

const submit = (): void => {
    form.post(mainCheckout.store.url(props.product));
};
</script>

<template>
    <AppLayout kind="main">
        <div class="mx-auto max-w-2xl">
            <h1 class="text-2xl font-bold text-white">
                ثبت سفارش {{ product.title }}
            </h1>
            <p class="mt-1 text-sm text-slate-400">
                {{ product.platform_name ?? product.platform }} ·
                {{ product.type_name ?? product.type }}
            </p>

            <form
                class="mt-8 space-y-6 rounded-2xl border border-white/10 bg-slate-900/60 p-6"
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="target_username"
                        class="mb-2 block text-sm font-medium text-slate-200"
                    >
                        آیدی پیج هدف
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-lg text-slate-500">@</span>
                        <input
                            id="target_username"
                            v-model="form.target_username"
                            dir="ltr"
                            type="text"
                            required
                            class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-left text-slate-100 transition outline-none focus:border-indigo-500/60"
                            placeholder="instagram"
                        />
                    </div>
                    <p
                        v-if="form.errors.target_username"
                        class="mt-2 text-sm text-rose-400"
                    >
                        {{ form.errors.target_username }}
                    </p>
                </div>

                <div>
                    <label
                        for="quantity"
                        class="mb-2 block text-sm font-medium text-slate-200"
                    >
                        تعداد ({{ product.type_name ?? product.type }})
                    </label>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="size-10 shrink-0 rounded-xl border border-white/10 text-lg text-slate-200 transition hover:border-indigo-500/50"
                            @click="changeBy(-stepQuantity)"
                        >
                            −
                        </button>
                        <input
                            id="quantity"
                            v-model.number="form.quantity"
                            dir="ltr"
                            type="number"
                            class="w-full rounded-xl border border-white/10 bg-slate-950 px-4 py-2.5 text-center text-lg text-slate-100 transition outline-none focus:border-indigo-500/60"
                            :min="minQuantity"
                            :max="maxQuantity"
                            :step="stepQuantity"
                            @change="
                                form.quantity = snap(Number(form.quantity))
                            "
                        />
                        <button
                            type="button"
                            class="size-10 shrink-0 rounded-xl border border-white/10 text-lg text-slate-200 transition hover:border-indigo-500/50"
                            @click="changeBy(stepQuantity)"
                        >
                            +
                        </button>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="chip in quickChips"
                            :key="chip"
                            type="button"
                            class="rounded-lg border border-white/10 px-3 py-1.5 text-xs text-slate-300 transition hover:border-indigo-500/50 hover:text-white"
                            @click="form.quantity = chip"
                        >
                            {{ toFa(chip) }}
                        </button>
                    </div>

                    <p
                        v-if="form.errors.quantity"
                        class="mt-2 text-sm text-rose-400"
                    >
                        {{ form.errors.quantity }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-white/10 bg-slate-950 p-4 text-sm"
                >
                    <div
                        class="flex items-center justify-between text-slate-400"
                    >
                        <span>قیمت هر ۱۰۰۰</span>
                        <span>{{ toFa(unitPrice) }} تومان</span>
                    </div>
                    <div
                        class="mt-2 flex items-center justify-between text-base font-bold text-white"
                    >
                        <span>مبلغ قابل پرداخت</span>
                        <span class="text-emerald-400">
                            {{ toFa(totalPrice) }} تومان
                        </span>
                    </div>
                </div>

                <p
                    v-if="!user"
                    class="rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-300"
                >
                    برای تکمیل خرید به حساب کاربری نیاز دارید؛ پس از ثبت سفارش
                    به صفحه ورود هدایت می‌شوید.
                </p>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-500 px-4 py-3 font-medium text-white transition hover:bg-indigo-400 disabled:opacity-50"
                >
                    <AppSpinner v-if="form.processing" class="size-4" />
                    ادامه و پرداخت
                </button>
            </form>
        </div>
    </AppLayout>
</template>
