<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLink from '@/components/AppLink.vue';
import AppSpinner from '@/components/AppSpinner.vue';
import { toFa } from '@/lib/ui';
import adminRoutes from '@/routes/admin';
import adminOrders from '@/routes/admin/orders';
import adminProducts from '@/routes/admin/products';
import adminUsers from '@/routes/admin/users';
import mainRoutes from '@/routes/main';
import panelRoutes from '@/routes/panel';
import type { SharedProps } from '@/types/likeshow';

const props = withDefaults(
    defineProps<{ kind?: 'landing' | 'main' | 'panel' | 'admin' }>(),
    { kind: 'main' },
);

const page = usePage();
const shared = computed(() => page.props as unknown as SharedProps);
const user = computed(() => shared.value.auth.user);
const urls = computed(() => shared.value.urls);

// Logout posts to the current section's own logout route, so the request
// always stays same-origin (main → main.logout, panel → panel.logout,
// admin → admin.logout).
const logoutUrl = computed(() => {
    if (props.kind === 'admin') {
        return adminRoutes.logout.url();
    }

    if (props.kind === 'panel') {
        return panelRoutes.logout.url();
    }

    return mainRoutes.logout.url();
});

const loggingOut = ref(false);

const logout = (): void => {
    loggingOut.value = true;

    router.post(
        logoutUrl.value,
        {},
        {
            onFinish: () => {
                loggingOut.value = false;
            },
        },
    );
};

/**
 * One consistent loading state for every action the layout fires (currently
 * only logout): the button shows the shared spinner while the XHR is pending.
 */
const isBusy = computed((): boolean => loggingOut.value);

const hidden = ref<Record<string, boolean>>({});
watch(
    () => shared.value.flash,
    () => {
        hidden.value = {};
    },
);

const flashItems = computed(() => {
    const flash = shared.value.flash;

    return (['success', 'error', 'info'] as const)
        .map((key) => ({ key, message: flash[key] }))
        .filter((item) => item.message && !hidden.value[item.key]);
});

const dismiss = (key: string): void => {
    hidden.value = { ...hidden.value, [key]: true };
};

const year = computed(() => toFa(new Date().getFullYear()));

// Landing/main pages use a narrower centered container so the content never
// feels full-bleed; panel/admin keep a wider shell for data tables.
const containerClass = computed(() =>
    props.kind === 'panel' || props.kind === 'admin'
        ? 'max-w-6xl'
        : 'max-w-5xl',
);

const landingNav = [
    { href: '#services', label: 'سرویس‌ها' },
    { href: '#benefits', label: 'مزایا' },
    { href: '#how', label: 'مراحل سفارش' },
    { href: '#faq', label: 'سوالات متداول' },
];

// The admin header menu. Detail pages (create/show/edit) live under their
// section's index URL, so highlighting by path prefix covers them too.
const adminNav = computed(() => [
    { href: adminUsers.index.url(), label: 'کاربران' },
    { href: adminProducts.index.url(), label: 'محصولات' },
    { href: adminOrders.index.url(), label: 'سفارش‌ها' },
]);

/**
 * Path-prefix match against the current Inertia URL; parsed with a dummy
 * base so it stays SSR-safe (no window access).
 */
const isActiveAdminNav = (href: string): boolean => {
    const current = new URL(page.url, 'http://localhost');
    const target = new URL(href, 'http://localhost');

    return (
        current.pathname === target.pathname ||
        current.pathname.startsWith(`${target.pathname}/`)
    );
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-950">
        <header
            class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/80 backdrop-blur"
        >
            <div
                :class="containerClass"
                class="mx-auto flex h-16 items-center gap-6 px-4"
            >
                <AppLink
                    :href="urls.main.home"
                    class="flex items-center gap-2 text-lg font-bold text-white"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-fuchsia-500 to-indigo-500 text-sm"
                    >
                        ل
                    </span>
                    لایک شو
                    <span
                        v-if="kind === 'admin'"
                        class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-300 ring-1 ring-amber-500/30 ring-inset"
                    >
                        مدیریت
                    </span>
                </AppLink>

                <nav
                    v-if="kind === 'landing'"
                    class="hidden items-center gap-6 text-sm text-slate-300 lg:flex"
                >
                    <a
                        v-for="item in landingNav"
                        :key="item.href"
                        :href="item.href"
                        class="transition hover:text-white"
                    >
                        {{ item.label }}
                    </a>
                </nav>

                <nav
                    v-if="kind === 'admin'"
                    class="hidden items-center gap-1 text-sm md:flex"
                >
                    <AppLink
                        v-for="item in adminNav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-lg px-3 py-1.5 transition"
                        :class="
                            isActiveAdminNav(item.href)
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:text-white'
                        "
                    >
                        {{ item.label }}
                    </AppLink>
                </nav>

                <nav class="ms-auto flex items-center gap-3 text-sm">
                    <template v-if="user">
                        <AppLink
                            v-if="kind !== 'panel'"
                            :href="urls.panel.orders"
                            class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 transition hover:border-indigo-500/50 hover:text-white"
                        >
                            {{
                                kind === 'admin' ? 'پنل کاربری' : 'سفارش‌های من'
                            }}
                        </AppLink>
                        <AppLink
                            v-else-if="user.is_admin"
                            :href="urls.admin.home"
                            class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 transition hover:border-amber-500/40 hover:text-amber-300"
                        >
                            پنل مدیریت
                        </AppLink>

                        <span
                            class="hidden items-center gap-2 text-slate-300 sm:flex"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500/40 to-fuchsia-500/40 text-xs font-bold text-white"
                            >
                                {{ user.name.charAt(0) }}
                            </span>
                            <span v-text="user.name" />
                        </span>

                        <button
                            type="button"
                            :disabled="isBusy"
                            class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-1.5 text-slate-300 transition hover:border-rose-500/40 hover:text-rose-400 disabled:opacity-50"
                            @click="logout"
                        >
                            <AppSpinner v-if="isBusy" class="size-4" />
                            {{ isBusy ? 'در حال خروج…' : 'خروج' }}
                        </button>
                    </template>

                    <template v-else>
                        <AppLink
                            :href="urls.panel.login"
                            class="rounded-lg border border-white/10 px-3 py-1.5 text-slate-200 transition hover:border-indigo-500/50 hover:text-white"
                        >
                            ورود
                        </AppLink>
                        <AppLink
                            :href="urls.panel.register"
                            class="rounded-lg bg-indigo-500 px-3 py-1.5 font-medium text-white transition hover:bg-indigo-400"
                        >
                            ثبت‌نام
                        </AppLink>
                    </template>
                </nav>
            </div>

            <nav
                v-if="kind === 'admin'"
                class="border-t border-white/10 md:hidden"
            >
                <div
                    :class="containerClass"
                    class="flex gap-1 overflow-x-auto px-4 py-2 text-sm"
                >
                    <AppLink
                        v-for="item in adminNav"
                        :key="item.href"
                        :href="item.href"
                        class="shrink-0 rounded-lg px-3 py-1.5 transition"
                        :class="
                            isActiveAdminNav(item.href)
                                ? 'bg-white/10 font-medium text-white'
                                : 'text-slate-300 hover:text-white'
                        "
                    >
                        {{ item.label }}
                    </AppLink>
                </div>
            </nav>
        </header>

        <main :class="containerClass" class="mx-auto w-full flex-1 px-4 py-8">
            <div
                v-for="item in flashItems"
                :key="item.key"
                class="mb-6 flex items-start justify-between gap-4 rounded-xl border px-4 py-3 text-sm"
                :class="{
                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-300':
                        item.key === 'success',
                    'border-rose-500/30 bg-rose-500/10 text-rose-300':
                        item.key === 'error',
                    'border-sky-500/30 bg-sky-500/10 text-sky-300':
                        item.key === 'info',
                }"
            >
                <span v-text="item.message" />
                <button
                    type="button"
                    class="shrink-0 opacity-70 hover:opacity-100"
                    @click="dismiss(item.key)"
                >
                    ✕
                </button>
            </div>

            <slot />
        </main>

        <footer class="border-t border-white/10">
            <div :class="containerClass" class="mx-auto px-4 py-12">
                <div class="grid gap-10 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <div
                            class="flex items-center gap-2 text-lg font-bold text-white"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-fuchsia-500 to-indigo-500 text-sm"
                            >
                                ل
                            </span>
                            لایک شو
                        </div>
                        <p
                            class="mt-4 max-w-sm text-sm leading-7 text-slate-400"
                        >
                            مرجع سفارش فالوور و لایک اینستاگرام با تحویل سریع،
                            پرداخت امن بانکی و پشتیبانی واقعی — بدون نیاز به رمز
                            پیج.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-white">
                            دسترسی سریع
                        </h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-slate-400">
                            <li>
                                <AppLink
                                    :href="urls.main.home"
                                    class="transition hover:text-white"
                                >
                                    خانه
                                </AppLink>
                            </li>
                            <li>
                                <AppLink
                                    :href="urls.panel.orders"
                                    class="transition hover:text-white"
                                >
                                    سفارش‌های من
                                </AppLink>
                            </li>
                            <li v-if="user?.is_admin">
                                <AppLink
                                    :href="urls.admin.home"
                                    class="transition hover:text-white"
                                >
                                    پنل مدیریت
                                </AppLink>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-white">
                            حساب کاربری و پشتیبانی
                        </h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-slate-400">
                            <li>
                                <AppLink
                                    :href="urls.panel.login"
                                    class="transition hover:text-white"
                                >
                                    ورود
                                </AppLink>
                            </li>
                            <li>
                                <AppLink
                                    :href="urls.panel.register"
                                    class="transition hover:text-white"
                                >
                                    ثبت‌نام
                                </AppLink>
                            </li>
                            <li>
                                <a
                                    href="mailto:support@likeshow.ir"
                                    dir="ltr"
                                    class="transition hover:text-white"
                                >
                                    support@likeshow.ir
                                </a>
                            </li>
                            <li>پاسخگویی: همه‌روزه ۹ تا ۲۴</li>
                        </ul>
                    </div>
                </div>

                <div
                    class="mt-10 border-t border-white/5 pt-6 text-center text-xs text-slate-500"
                >
                    © {{ year }} لایک شو — تمامی حقوق محفوظ است.
                </div>
            </div>
        </footer>
    </div>
</template>
