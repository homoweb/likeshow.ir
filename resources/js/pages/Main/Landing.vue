<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/components/AppLayout.vue';
import AppLink from '@/components/AppLink.vue';
import {
    formatPrice,
    toFa,
    totalPriceFor,
    unitPriceFor,
} from '@/lib/ui';
import mainCheckout from '@/routes/main/checkout';
import panelOrders from '@/routes/panel/orders';
import panel from '@/routes/panel';
import type { Product, SharedProps } from '@/types/likeshow';

const props = defineProps<{ products: Product[] }>();

const page = usePage();
const shared = computed(() => page.props as unknown as SharedProps);
const user = computed(() => shared.value.auth.user);

const selectedProductId = ref<number | null>(props.products[0]?.id ?? null);
const selectedProduct = computed(
    () =>
        props.products.find(
            (product) => product.id === selectedProductId.value,
        ) ?? null,
);

const minQuantity = computed(() =>
    Math.max(1000, Number(selectedProduct.value?.min_quantity ?? 1000)),
);
const maxQuantity = computed(() =>
    Math.min(1000000, Number(selectedProduct.value?.max_quantity ?? 1000000)),
);
const stepQuantity = computed(
    () => Number(selectedProduct.value?.step_quantity) || 1000,
);

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

const quantity = ref(snap(minQuantity.value));

watch(selectedProductId, () => {
    quantity.value = snap(quantity.value);
});

const changeQuantity = (delta: number): void => {
    quantity.value = snap(quantity.value + delta);
};

const quantityOptions = computed(() =>
    [1000, 5000, 10000, 50000]
        .map((value) => snap(value))
        .filter((value, index, list) => list.indexOf(value) === index)
        .slice(0, 4),
);

const unitPrice = computed(() =>
    selectedProduct.value === null
        ? 0
        : unitPriceFor(selectedProduct.value, quantity.value),
);

const totalPrice = computed(() =>
    selectedProduct.value === null
        ? 0
        : totalPriceFor(selectedProduct.value, quantity.value),
);

const checkoutHref = computed(() =>
    selectedProduct.value === null
        ? '#services'
        : mainCheckout.show.url(selectedProduct.value.id, {
              query: { quantity: quantity.value },
          }),
);

const stats = [
    { value: '+۱۲٬۰۰۰', label: 'سفارش موفق' },
    { value: '+۸٬۵۰۰', label: 'کاربر فعال' },
    { value: '۹۸٪', label: 'رضایت مشتری' },
    { value: '< ۵ دقیقه', label: 'میانگین شروع سفارش' },
];

const benefits = [
    {
        icon: 'M13 2 3 14h7l-1 8 10-12h-7l1-8Z',
        title: 'تحویل سریع',
        text: 'سفارش‌ها بلافاصله پس از پرداخت به‌صورت خودکار شروع می‌شوند.',
    },
    {
        icon: 'M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8-6.1-3.4-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8L12 2Z',
        title: 'کیفیت واقعی',
        text: 'فالوور و لایک با کیفیت بالا و پروفایل فعال ارسال می‌شود.',
    },
    {
        icon: 'M12 1 3 5v6c0 5.6 3.8 10.7 9 12 5.2-1.3 9-6.4 9-12V5l-9-4Z',
        title: 'پرداخت امن',
        text: 'پرداخت از طریق درگاه امن بانکی با تایید شاپرک انجام می‌شود.',
    },
    {
        icon: 'M12 2a5 5 0 0 1 5 5v3h1a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5Zm0 2a3 3 0 0 0-3 3v3h6V7a3 3 0 0 0-3-3Z',
        title: 'حریم خصوصی',
        text: 'برای شروع سفارش به رمز یا ورود به پیج‌ات هیچ نیازی نداریم.',
    },
    {
        icon: 'M4.9 3C3.9 3 3 3.9 3 4.9 3 13.8 10.2 21 19.1 21c1 0 1.9-.9 1.9-1.9v-3.2c0-.9-.6-1.6-1.4-1.8l-3.5-.9c-.7-.2-1.4.1-1.8.7l-1 1.4a14.5 14.5 0 0 1-6.6-6.6l1.4-1c.6-.4.9-1.1.7-1.8l-.9-3.5C7.7 3.6 7 3 6.1 3H4.9Z',
        title: 'پشتیبانی انسانی',
        text: 'تیم پشتیبانی همه‌روزه پاسخگوی سوالات و مشکلات شماست.',
    },
    {
        icon: 'M4.755 10.059a7.5 7.5 0 0 1 12.548-3.364l1.903 1.903h-3.183a.75.75 0 0 1 0-1.5h4.992a.75.75 0 0 0 .75-.75V4.356a.75.75 0 0 0-1.5 0v3.18l-1.9-1.9A9 9 0 0 0 3.306 9.67a.75.75 0 1 0 1.45.388Zm15.408 3.352a.75.75 0 0 0-.919.53 7.5 7.5 0 0 1-12.548 3.364l-1.902-1.903h3.183a.75.75 0 0 1 0 1.5H2.984a.75.75 0 0 0-.75.75v4.992a.75.75 0 0 0 1.5 0v-3.18l1.9 1.9a9 9 0 0 0 15.059-4.035.75.75 0 0 0-.53-.918Z',
        title: 'شفافیت کامل',
        text: 'وضعیت لحظه‌ای سفارش را در پنل کاربری می‌بینی؛ بدون ابهام.',
    },
];

const steps = [
    {
        title: 'انتخاب سرویس',
        text: 'سرویس و تعداد موردنظرت را انتخاب کن.',
    },
    {
        title: 'آیدی و پرداخت',
        text: 'آیدی پیج هدف را وارد کن و پرداخت امن را انجام بده.',
    },
    {
        title: 'شروع خودکار',
        text: 'سفارش بلافاصله پس از تایید پرداخت اجرا می‌شود.',
    },
    {
        title: 'پیگیری در پنل',
        text: 'پیشرفت لحظه‌ای سفارش را در پنل کاربری ببین.',
    },
];

const faq = [
    {
        q: 'برای شروع سفارش به رمز پیج نیاز دارم؟',
        a: 'خیر. فقط کافی است آیدی (یوزرنیم) پیج هدف را وارد کنید؛ به رمز و ورود به حساب هیچ نیازی نیست.',
    },
    {
        q: 'سفارش چه زمانی شروع می‌شود؟',
        a: 'بلافاصله پس از تایید پرداخت، سفارش به‌صورت خودکار وارد صف اجرا می‌شود و معمولاً در کمتر از چند دقیقه شروع می‌شود.',
    },
    {
        q: 'اگر سفارش ناقص ماند چه اتفاقی می‌افتد؟',
        a: 'در صورت ناقص ماندن سفارش، اختلاف آن به‌صورت سفارش جبرانی یا اعتبار در پنل کاربری شما برگردانده می‌شود.',
    },
    {
        q: 'درگاه پرداخت امن است؟',
        a: 'بله، پرداخت از طریق درگاه رسمی بانکی با تایید شاپرک انجام می‌شود و اطلاعات کارت شما نزد ما ذخیره نمی‌شود.',
    },
    {
        q: 'حداقل و حداکثر تعداد سفارش چقدر است؟',
        a: 'بسته به سرویس متفاوت است؛ محدوده مجاز هر سرویس در کارت همان سرویس نمایش داده شده است.',
    },
];
</script>

<template>
    <AppLayout kind="landing">
        <!-- Hero -->
        <section
            class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-bl from-indigo-950/80 via-slate-950 to-slate-950 px-6 py-16 sm:px-12 sm:py-20"
        >
            <div
                class="animate-glow pointer-events-none absolute -top-32 -left-24 size-96 rounded-full bg-indigo-600/25 blur-3xl"
            />
            <div
                class="animate-glow pointer-events-none absolute -right-20 -bottom-40 size-96 rounded-full bg-fuchsia-600/20 blur-3xl"
            />

            <div class="relative grid items-center gap-12 lg:grid-cols-2">
                <div>
                    <span
                        class="animate-fade-up inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-4 py-1.5 text-xs font-medium text-indigo-300"
                    >
                        ⚡ تحویل فوری · شروع از ۱٬۰۰۰ عدد
                    </span>
                    <h1
                        class="animate-fade-up mt-6 text-4xl leading-[1.3] font-bold text-white sm:text-5xl sm:leading-[1.3]"
                    >
                        رشد واقعی پیج اینستاگرام‌ات را
                        <span
                            class="bg-gradient-to-l from-fuchsia-400 via-indigo-300 to-sky-400 bg-clip-text text-transparent"
                        >
                            همین امروز
                        </span>
                        شروع کن
                    </h1>
                    <p
                        class="animate-fade-up mt-5 max-w-xl leading-8 text-slate-300"
                    >
                        فالوور و لایک اینستاگرام با قیمت بدون واسطه، پرداخت امن
                        بانکی و شروع خودکار سفارش — بدون نیاز به رمز پیج؛ فقط
                        آیدی هدف را وارد کن.
                    </p>

                    <div
                        class="animate-fade-up mt-8 flex flex-wrap items-center gap-3"
                    >
                        <a
                            href="#services"
                            class="rounded-xl bg-gradient-to-l from-indigo-500 to-fuchsia-500 px-6 py-3 font-medium text-white shadow-lg shadow-indigo-950/50 transition hover:opacity-90"
                        >
                            مشاهده سرویس‌ها
                        </a>
                        <AppLink
                            v-if="user"
                            :href="panelOrders.index.url()"
                            class="rounded-xl border border-white/15 px-6 py-3 text-slate-200 transition hover:border-indigo-500/50 hover:text-white"
                        >
                            سفارش‌های من
                        </AppLink>
                        <AppLink
                            v-else
                            :href="panel.register.url()"
                            class="rounded-xl border border-white/15 px-6 py-3 text-slate-200 transition hover:border-indigo-500/50 hover:text-white"
                        >
                            ثبت‌نام رایگان
                        </AppLink>
                    </div>
                    <ul
                        class="animate-fade-up mt-8 flex flex-wrap gap-x-6 gap-y-2 text-xs text-slate-400"
                    >
                        <li>✓ بدون نیاز به رمز پیج</li>
                        <li>✓ پرداخت امن شاپرک</li>
                        <li>✓ پشتیبانی واقعی انسانی</li>
                    </ul>
                </div>

                <!-- Product mock -->
                <div class="relative mx-auto hidden w-full max-w-sm sm:block">
                    <div
                        class="animate-float rounded-2xl border border-white/10 bg-slate-900/80 p-5 shadow-2xl shadow-indigo-950/40 backdrop-blur"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-white">
                                فالوور اینستاگرام
                            </span>
                            <span
                                class="rounded-full bg-sky-500/10 px-2.5 py-0.5 text-xs text-sky-300 ring-1 ring-sky-500/30 ring-inset"
                            >
                                در حال انجام
                            </span>
                        </div>
                        <div
                            class="mt-4 flex items-center justify-between text-xs text-slate-400"
                        >
                            <span>۵٬۰۰۰ عدد</span>
                            <span dir="ltr">@page</span>
                        </div>
                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-slate-800"
                        >
                            <div
                                class="h-full w-2/3 rounded-full bg-gradient-to-l from-indigo-500 to-fuchsia-500"
                            />
                        </div>
                        <div class="mt-2 text-left text-xs text-slate-500">
                            ۶۸٪
                        </div>
                    </div>
                    <div
                        class="animate-float-delayed -mt-10 ml-10 rounded-2xl border border-emerald-500/20 bg-slate-900/80 p-4 shadow-2xl shadow-emerald-950/30 backdrop-blur"
                    >
                        <div
                            class="flex items-center gap-3 text-sm text-emerald-300"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-emerald-500/15"
                            >
                                ✓
                            </span>
                            پرداخت موفق · ۱۹۵٬۰۰۰ تومان
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats -->
        <section class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-2xl border border-white/10 bg-slate-900/60 px-6 py-5 text-center"
            >
                <div class="text-2xl font-bold text-white">
                    {{ stat.value }}
                </div>
                <div class="mt-1 text-xs text-slate-400">
                    {{ stat.label }}
                </div>
            </div>
        </section>

        <!-- Services -->
        <section id="services" class="scroll-mt-24">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-white">سرویس‌ها</h2>
                <p class="mt-3 text-slate-400">
                    سرویس موردنظرت را انتخاب کن و در چند دقیقه سفارش را نهایی
                    کن.
                </p>
            </div>

            <div
                v-if="products.length === 0"
                class="mt-10 rounded-2xl border border-white/10 bg-slate-900/60 px-6 py-12 text-center text-slate-400"
            >
                سرویس‌های جدید به‌زودی اضافه می‌شوند.
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <article
                    v-for="product in products"
                    :key="product.id"
                    class="group relative overflow-hidden rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:border-indigo-500/40 hover:bg-slate-900"
                >
                    <div
                        class="pointer-events-none absolute -top-16 -left-16 size-40 rounded-full bg-indigo-600/10 blur-2xl transition group-hover:bg-indigo-600/20"
                    />
                    <div class="relative">
                        <span
                            class="rounded-full bg-indigo-500/10 px-3 py-1 text-xs font-medium text-indigo-300 ring-1 ring-indigo-500/30 ring-inset"
                        >
                            {{ product.type_name ?? product.type }}
                            {{ product.platform_name ?? product.platform }}
                        </span>
                        <h3 class="mt-4 text-lg font-bold text-white">
                            {{ product.title }}
                        </h3>
                        <p
                            v-if="product.description"
                            class="mt-2 line-clamp-2 text-sm leading-6 text-slate-400"
                        >
                            {{ product.description }}
                        </p>
                        <dl class="mt-5 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-400">قیمت هر ۱٬۰۰۰</dt>
                                <dd class="font-medium text-slate-100">
                                    {{ formatPrice(product.base_price) }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-400">محدوده تعداد</dt>
                                <dd class="text-slate-300">
                                    {{ toFa(product.min_quantity) }} تا
                                    {{ toFa(product.max_quantity) }}
                                </dd>
                            </div>
                        </dl>
                        <AppLink
                            :href="mainCheckout.show.url(product.id)"
                            class="mt-6 block rounded-xl bg-indigo-500 px-4 py-2.5 text-center text-sm font-medium text-white transition hover:bg-indigo-400"
                        >
                            ثبت سفارش
                        </AppLink>
                    </div>
                </article>
            </div>

            <!-- Quick order -->
            <div
                v-if="products.length > 0"
                class="mt-10 rounded-2xl border border-indigo-500/25 bg-gradient-to-l from-indigo-950/60 to-slate-900/60 p-6 sm:p-8"
            >
                <h3 class="text-lg font-bold text-white">سفارش سریع</h3>
                <p class="mt-1 text-sm text-slate-400">
                    سرویس و تعداد را همین‌جا انتخاب کن؛ در مرحله بعد فقط آیدی
                    پیج را وارد می‌کنی.
                </p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        v-for="product in products"
                        :key="product.id"
                        type="button"
                        class="rounded-xl border px-4 py-2 text-sm transition"
                        :class="
                            product.id === selectedProductId
                                ? 'border-indigo-500 bg-indigo-500/15 text-white'
                                : 'border-white/10 text-slate-300 hover:border-indigo-500/40 hover:text-white'
                        "
                        @click="selectedProductId = product.id"
                    >
                        {{ product.title }}
                    </button>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="size-9 rounded-lg border border-white/10 text-lg text-slate-200 transition hover:border-indigo-500/50"
                            @click="changeQuantity(-stepQuantity)"
                        >
                            −
                        </button>
                        <span
                            class="min-w-24 rounded-lg border border-white/10 bg-slate-950 px-4 py-2 text-center text-lg font-bold text-white"
                        >
                            {{ toFa(quantity) }}
                        </span>
                        <button
                            type="button"
                            class="size-9 rounded-lg border border-white/10 text-lg text-slate-200 transition hover:border-indigo-500/50"
                            @click="changeQuantity(stepQuantity)"
                        >
                            +
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in quantityOptions"
                            :key="option"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-xs transition"
                            :class="
                                option === quantity
                                    ? 'border-indigo-500/60 text-indigo-300'
                                    : 'border-white/10 text-slate-400 hover:text-white'
                            "
                            @click="quantity = option"
                        >
                            {{ toFa(option) }}
                        </button>
                    </div>
                </div>

                <div
                    class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-5 text-sm"
                >
                    <div class="space-y-1 text-slate-400">
                        <p>
                            قیمت هر ۱٬۰۰۰:
                            <span class="text-slate-200">
                                {{ formatPrice(unitPrice) }}
                            </span>
                        </p>
                        <p>
                            مبلغ قابل پرداخت:
                            <span class="text-base font-bold text-emerald-400">
                                {{ formatPrice(totalPrice) }}
                            </span>
                        </p>
                    </div>
                    <AppLink
                        :href="checkoutHref"
                        class="rounded-xl bg-gradient-to-l from-indigo-500 to-fuchsia-500 px-6 py-3 font-medium text-white transition hover:opacity-90"
                    >
                        ادامه و ثبت سفارش
                    </AppLink>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="benefit in benefits"
                :key="benefit.title"
                class="rounded-2xl border border-white/10 bg-slate-900/60 p-6 transition hover:border-indigo-500/40"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-bl from-indigo-500/20 to-fuchsia-500/20 text-indigo-300 ring-1 ring-indigo-500/30 ring-inset"
                >
                    <svg viewBox="0 0 24 24" fill="currentColor" class="size-5">
                        <path :d="benefit.icon" />
                    </svg>
                </span>
                <h3 class="mt-4 font-bold text-white">{{ benefit.title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-400">
                    {{ benefit.text }}
                </p>
            </div>
        </section>

        <!-- How it works -->
        <section class="mt-20">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-white">چطور کار می‌کند؟</h2>
                <p class="mt-3 text-slate-400">
                    فقط چهار قدم تا رشد پیج اینستاگرامت فاصله داری.
                </p>
            </div>
            <ol class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="relative rounded-2xl border border-white/10 bg-slate-900/60 p-6"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-full bg-gradient-to-bl from-indigo-500 to-fuchsia-500 text-sm font-bold text-white"
                    >
                        {{ toFa(index + 1) }}
                    </span>
                    <h3 class="mt-4 font-bold text-white">{{ step.title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        {{ step.text }}
                    </p>
                </li>
            </ol>
        </section>

        <!-- FAQ -->
        <section class="mt-20">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-white">سوالات پرتکرار</h2>
                <p class="mt-3 text-slate-400">
                    پاسخت را پیدا نکردی؟ تیم پشتیبانی در دسترس است.
                </p>
            </div>
            <div class="mx-auto mt-10 max-w-3xl space-y-3">
                <details
                    v-for="item in faq"
                    :key="item.q"
                    class="group rounded-2xl border border-white/10 bg-slate-900/60 px-6 py-4 transition open:border-indigo-500/40"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium text-slate-100 [&::-webkit-details-marker]:hidden"
                    >
                        {{ item.q }}
                        <span
                            class="shrink-0 text-lg text-indigo-400 transition group-open:rotate-45"
                        >
                            +
                        </span>
                    </summary>
                    <p class="mt-3 text-sm leading-7 text-slate-400">
                        {{ item.a }}
                    </p>
                </details>
            </div>
        </section>

        <!-- Final CTA -->
        <section
            class="relative mt-20 overflow-hidden rounded-3xl border border-indigo-500/25 bg-gradient-to-bl from-indigo-950/80 via-slate-950 to-slate-950 px-6 py-14 text-center sm:px-12"
        >
            <div
                class="animate-glow pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-fuchsia-600/20 blur-3xl"
            />
            <div class="relative">
                <h2 class="text-3xl font-bold text-white sm:text-4xl">
                    همین حالا اولین سفارشت را ثبت کن
                </h2>
                <p class="mx-auto mt-4 max-w-xl leading-8 text-slate-300">
                    بدون نیاز به رمز پیج، فقط آیدی هدف را وارد کن و رشد را به ما
                    بسپار؛ شروع سفارش کمتر از ۵ دقیقه طول می‌کشد.
                </p>
                <a
                    href="#services"
                    class="mt-8 inline-block rounded-xl bg-gradient-to-l from-indigo-500 to-fuchsia-500 px-8 py-3.5 font-medium text-white shadow-lg shadow-indigo-950/50 transition hover:opacity-90"
                >
                    شروع سفارش
                </a>
            </div>
        </section>
    </AppLayout>
</template>
