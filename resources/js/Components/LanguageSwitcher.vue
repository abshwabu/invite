<script setup>
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { locale } = useI18n();

const currentLocale = computed(() => page.props.locale || locale.value || 'en');

const switchLocale = (newLocale) => {
    if (newLocale === currentLocale.value) return;

    locale.value = newLocale;

    router.post(route('locale.update'), {
        locale: newLocale,
    }, {
        preserveScroll: true,
        preserveState: false,
    });
};
</script>

<template>
    <div class="inline-flex items-center rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-0.5 text-xs font-medium shadow-sm">
        <button
            type="button"
            @click="switchLocale('en')"
            :class="[
                'px-2 py-1 rounded transition-colors',
                currentLocale === 'en'
                    ? 'bg-amber-500 text-white font-semibold'
                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            ]"
        >
            EN
        </button>
        <button
            type="button"
            @click="switchLocale('am')"
            :class="[
                'px-2 py-1 rounded transition-colors',
                currentLocale === 'am'
                    ? 'bg-amber-500 text-white font-semibold'
                    : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'
            ]"
        >
            አማ
        </button>
    </div>
</template>
