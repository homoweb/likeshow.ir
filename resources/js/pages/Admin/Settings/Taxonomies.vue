<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/components/AppLayout.vue';
import AppSpinner from '@/components/AppSpinner.vue';
import adminSettingsPlatforms from '@/routes/admin/settings/platforms';
import adminSettingsTypes from '@/routes/admin/settings/types';
import { toFa } from '@/lib/ui';
import type { TaxonomyTerm } from '@/types/likeshow';

defineProps<{
    platforms: TaxonomyTerm[];
    types: TaxonomyTerm[];
}>();

// "platform" | "type" | null — which row is currently in edit mode.
const editing = ref<string | null>(null);
const pendingToggle = ref<string | null>(null);

const platformForm = useForm({ slug: '', name: '', sort_order: 0 });
const typeForm = useForm({ slug: '', name: '', sort_order: 0 });
const editForm = useForm({ slug: '', name: '', sort_order: 0 });

const resetForms = (): void => {
    platformForm.reset();
    typeForm.reset();
    editForm.reset();
    editing.value = null;
};

const startEdit = (kind: 'platform' | 'type', term: TaxonomyTerm): void => {
    editing.value = `${kind}:${term.id}`;
    editForm.slug = term.slug;
    editForm.name = term.name;
    editForm.sort_order = term.sort_order;
};

const isEditing = (kind: 'platform' | 'type', term: TaxonomyTerm): boolean =>
    editing.value === `${kind}:${term.id}`;

const storePlatform = (): void => {
    platformForm.post(adminSettingsPlatforms.store.url(), {
        onSuccess: () => {
            platformForm.reset();
        },
    });
};

const storeType = (): void => {
    typeForm.post(adminSettingsTypes.store.url(), {
        onSuccess: () => {
            typeForm.reset();
        },
    });
};

const update = (
    kind: 'platform' | 'type',
    term: TaxonomyTerm,
): void => {
    const submit = kind === 'platform'
        ? adminSettingsPlatforms.update.url(term)
        : adminSettingsTypes.update.url(term);

    editForm.put(submit, {
        onSuccess: () => {
            resetForms();
        },
    });
};

const toggle = (kind: 'platform' | 'type', term: TaxonomyTerm): void => {
    pendingToggle.value = `${kind}:${term.id}`;

    const url = kind === 'platform'
        ? adminSettingsPlatforms.toggle.url(term)
        : adminSettingsTypes.toggle.url(term);

    router.patch(url, {}, {
        onFinish: () => {
            pendingToggle.value = null;
        },
    });
};

const isPending = (kind: 'platform' | 'type', term: TaxonomyTerm): boolean =>
    pendingToggle.value === `${kind}:${term.id}`;
</script>

<template>
    <AppLayout kind="admin">
        <h1 class="text-2xl font-bold text-white">تنظیمات محصول</h1>
        <p class="mt-1 text-sm text-slate-400">
            پلتفرم‌ها و انواع سرویس به‌صورت پویا مدیریت می‌شوند؛ مقادیر
            غیرفعال از فروشگاه و فرم محصول جدید حذف می‌شوند.
        </p>

        <div class="mt-6 grid gap-6 xl:grid-cols-2">
            <!-- Platforms -->
            <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5">
                <h2 class="text-lg font-bold text-white">پلتفرم‌ها</h2>

                <form
                    class="mt-4 flex flex-wrap items-end gap-3"
                    @submit.prevent="storePlatform"
                >
                    <div>
                        <label
                            for="platform_slug"
                            class="mb-1 block text-xs text-slate-400"
                        >شناسه لاتین</label>
                        <input
                            id="platform_slug"
                            v-model="platformForm.slug"
                            dir="ltr"
                            class="w-32 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                            placeholder="telegram"
                        >
                    </div>
                    <div>
                        <label
                            for="platform_name"
                            class="mb-1 block text-xs text-slate-400"
                        >نام نمایشی</label>
                        <input
                            id="platform_name"
                            v-model="platformForm.name"
                            class="w-36 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                            placeholder="تلگرام"
                        >
                    </div>
                    <button
                        type="submit"
                        :disabled="platformForm.processing"
                        class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400 disabled:opacity-50"
                    >
                        افزودن
                    </button>
                </form>
                <p
                    v-if="platformForm.errors.slug || platformForm.errors.name"
                    class="mt-2 text-sm text-rose-400"
                >
                    {{ platformForm.errors.slug || platformForm.errors.name }}
                </p>

                <ul class="mt-5 divide-y divide-white/5">
                    <li
                        v-for="term in platforms"
                        :key="term.id"
                        class="py-3"
                    >
                        <template v-if="isEditing('platform', term)">
                            <div class="flex flex-wrap items-end gap-3">
                                <input
                                    v-model="editForm.slug"
                                    dir="ltr"
                                    class="w-32 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                                >
                                <input
                                    v-model="editForm.name"
                                    class="w-36 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                                >
                                <button
                                    type="button"
                                    :disabled="editForm.processing"
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-emerald-500 disabled:opacity-50"
                                    @click="update('platform', term)"
                                >
                                    ذخیره
                                </button>
                                <button
                                    type="button"
                                    class="text-xs text-slate-400 transition hover:text-white"
                                    @click="resetForms"
                                >
                                    انصراف
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <span class="font-medium text-slate-100">{{ term.name }}</span>
                                    <span
                                        dir="ltr"
                                        class="ms-2 text-xs text-slate-500"
                                    >{{ term.slug }}</span>
                                    <span class="ms-2 text-xs text-slate-500">
                                        {{ toFa(term.products_count) }} محصول
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    <span
                                        :class="{
                                            'text-emerald-400': term.is_active,
                                            'text-rose-400': !term.is_active,
                                        }"
                                    >
                                        {{ term.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <button
                                        type="button"
                                        :disabled="isPending('platform', term)"
                                        class="inline-flex items-center gap-1.5 text-amber-400 transition hover:text-amber-300 disabled:opacity-50"
                                        @click="toggle('platform', term)"
                                    >
                                        <AppSpinner
                                            v-if="isPending('platform', term)"
                                            class="size-3"
                                        />
                                        {{ term.is_active ? 'غیرفعال' : 'فعال' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-indigo-400 transition hover:text-indigo-300"
                                        @click="startEdit('platform', term)"
                                    >
                                        ویرایش
                                    </button>
                                </div>
                            </div>
                        </template>
                    </li>
                </ul>
            </section>

            <!-- Service types -->
            <section class="rounded-2xl border border-white/10 bg-slate-900/60 p-5">
                <h2 class="text-lg font-bold text-white">انواع سرویس</h2>

                <form
                    class="mt-4 flex flex-wrap items-end gap-3"
                    @submit.prevent="storeType"
                >
                    <div>
                        <label
                            for="type_slug"
                            class="mb-1 block text-xs text-slate-400"
                        >شناسه لاتین</label>
                        <input
                            id="type_slug"
                            v-model="typeForm.slug"
                            dir="ltr"
                            class="w-32 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                            placeholder="views"
                        >
                    </div>
                    <div>
                        <label
                            for="type_name"
                            class="mb-1 block text-xs text-slate-400"
                        >نام نمایشی</label>
                        <input
                            id="type_name"
                            v-model="typeForm.name"
                            class="w-36 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                            placeholder="بازدید"
                        >
                    </div>
                    <button
                        type="submit"
                        :disabled="typeForm.processing"
                        class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-400 disabled:opacity-50"
                    >
                        افزودن
                    </button>
                </form>
                <p
                    v-if="typeForm.errors.slug || typeForm.errors.name"
                    class="mt-2 text-sm text-rose-400"
                >
                    {{ typeForm.errors.slug || typeForm.errors.name }}
                </p>

                <ul class="mt-5 divide-y divide-white/5">
                    <li
                        v-for="term in types"
                        :key="term.id"
                        class="py-3"
                    >
                        <template v-if="isEditing('type', term)">
                            <div class="flex flex-wrap items-end gap-3">
                                <input
                                    v-model="editForm.slug"
                                    dir="ltr"
                                    class="w-32 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                                >
                                <input
                                    v-model="editForm.name"
                                    class="w-36 rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-slate-100 outline-none focus:border-indigo-500/60"
                                >
                                <button
                                    type="button"
                                    :disabled="editForm.processing"
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-emerald-500 disabled:opacity-50"
                                    @click="update('type', term)"
                                >
                                    ذخیره
                                </button>
                                <button
                                    type="button"
                                    class="text-xs text-slate-400 transition hover:text-white"
                                    @click="resetForms"
                                >
                                    انصراف
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <span class="font-medium text-slate-100">{{ term.name }}</span>
                                    <span
                                        dir="ltr"
                                        class="ms-2 text-xs text-slate-500"
                                    >{{ term.slug }}</span>
                                    <span class="ms-2 text-xs text-slate-500">
                                        {{ toFa(term.products_count) }} محصول
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs">
                                    <span
                                        :class="{
                                            'text-emerald-400': term.is_active,
                                            'text-rose-400': !term.is_active,
                                        }"
                                    >
                                        {{ term.is_active ? 'فعال' : 'غیرفعال' }}
                                    </span>
                                    <button
                                        type="button"
                                        :disabled="isPending('type', term)"
                                        class="inline-flex items-center gap-1.5 text-amber-400 transition hover:text-amber-300 disabled:opacity-50"
                                        @click="toggle('type', term)"
                                    >
                                        <AppSpinner
                                            v-if="isPending('type', term)"
                                            class="size-3"
                                        />
                                        {{ term.is_active ? 'غیرفعال' : 'فعال' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="text-indigo-400 transition hover:text-indigo-300"
                                        @click="startEdit('type', term)"
                                    >
                                        ویرایش
                                    </button>
                                </div>
                            </div>
                        </template>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
