<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    templates: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ kind: 'invitation', category: 'all', search: '' }),
    },
    canUpload: {
        type: Boolean,
        default: false,
    },
});

const currentKind = ref(props.filters.kind || 'invitation');
const selectedCategory = ref(props.filters.category || 'all');
const searchQuery = ref(props.filters.search || '');

const showUpgradeModal = ref(false);
const showUploadModal = ref(false);

const uploadForm = useForm({
    image: null,
    name: '',
    category_id: '',
});

const previewImageSrc = ref(null);

const handleFileSelect = (e) => {
    const file = e.target.files[0];
    if (file) {
        uploadForm.image = file;
        const reader = new FileReader();
        reader.onload = (event) => {
            previewImageSrc.value = event.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const handleUploadSubmit = () => {
    uploadForm.post(route('templates.upload'), {
        preserveScroll: true,
        onSuccess: () => {
            showUploadModal.value = false;
            uploadForm.reset();
            previewImageSrc.value = null;
        },
    });
};

const handleUploadClick = () => {
    if (!props.canUpload) {
        showUpgradeModal.value = true;
    } else {
        showUploadModal.value = true;
    }
};

const filterGallery = () => {
    router.get(
        route('templates.index'),
        {
            kind: currentKind.value,
            category: selectedCategory.value,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

const switchKind = (kind) => {
    currentKind.value = kind;
    filterGallery();
};

const switchCategory = (slug) => {
    selectedCategory.value = slug;
    filterGallery();
};

const onSearchInput = () => {
    filterGallery();
};
</script>

<template>
    <Head :title="$t('Template Gallery')" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100 flex flex-col">
        <!-- Top Navigation -->
        <header class="border-b border-gray-200 dark:border-gray-800 bg-white/90 dark:bg-gray-900/90 backdrop-blur sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2">
                    <ApplicationLogo class="h-8 w-8 text-amber-600 fill-current" />
                    <span class="font-bold text-xl tracking-tight">Invite</span>
                </Link>

                <div class="flex items-center gap-4">
                    <LanguageSwitcher />

                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('plan.index')"
                            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800"
                        >
                            <span>✉️</span>
                            <span>{{ $page.props.auth?.quota?.available ?? 0 }} {{ $t('cards') }}</span>
                        </Link>
                        <Link
                            :href="route('dashboard')"
                            class="text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-200 hover:text-amber-600"
                        >
                            {{ $t('Dashboard') }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-amber-600"
                        >
                            {{ $t('Log In') }}
                        </Link>
                        <Link
                            :href="route('register')"
                            class="rounded-xl bg-amber-600 px-3.5 py-1.5 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-amber-500 transition"
                        >
                            {{ $t('Sign Up') }}
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- Main Page Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
            <!-- Header Title & Action Buttons -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b border-gray-200 dark:border-gray-800">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl text-gray-900 dark:text-white">
                        {{ $t('Template Gallery') }}
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-2xl">
                        {{ $t('Choose an invitation or thank-you card design from our rich collection of authentic Ethiopian & modern layouts.') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        @click="handleUploadClick"
                        class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-gray-800 border border-amber-500/50 hover:border-amber-500 px-4 py-2 text-xs font-semibold text-amber-700 dark:text-amber-400 shadow-sm transition"
                    >
                        <span>📤</span>
                        <span>{{ $t('Upload Your Own') }}</span>
                    </button>

                    <Link
                        :href="route('editor.show')"
                        class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-500 px-4 py-2 text-xs font-semibold text-white shadow-sm transition"
                    >
                        <span>✨</span>
                        <span>{{ $t('Create from Scratch') }}</span>
                    </Link>
                </div>
            </div>

            <!-- Kind Toggle & Search -->
            <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Kind Toggle (Invitation vs Thank-You) -->
                <div class="inline-flex p-1 rounded-2xl bg-gray-200 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 shadow-inner">
                    <button
                        type="button"
                        @click="switchKind('invitation')"
                        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2"
                        :class="currentKind === 'invitation' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                    >
                        <span>✉️</span>
                        <span>{{ $t('Invitation Cards') }}</span>
                    </button>
                    <button
                        type="button"
                        @click="switchKind('thank_you')"
                        class="px-5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2"
                        :class="currentKind === 'thank_you' ? 'bg-amber-600 text-white shadow-sm' : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white'"
                    >
                        <span>🙏</span>
                        <span>{{ $t('Thank-You Cards') }}</span>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:w-72">
                    <input
                        type="text"
                        v-model="searchQuery"
                        @input="onSearchInput"
                        :placeholder="$t('Search templates...')"
                        class="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="mt-4 flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                <button
                    type="button"
                    @click="switchCategory('all')"
                    :class="[
                        'px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition',
                        selectedCategory === 'all'
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-sm'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-gray-400'
                    ]"
                >
                    {{ $t('All Categories') }}
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    type="button"
                    @click="switchCategory(cat.slug)"
                    :class="[
                        'px-4 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition',
                        selectedCategory === cat.slug
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 shadow-sm'
                            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-gray-400'
                    ]"
                >
                    {{ $page.props.locale === 'am' ? cat.name_am : cat.name_en }}
                </button>
            </div>

            <!-- Templates Count & Status -->
            <div class="mt-6 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $t('Showing :count templates', { count: templates.length }) }}</span>
                <span v-if="currentKind === 'thank_you'" class="font-medium text-amber-600 dark:text-amber-400">
                    {{ $t('Gratitude and appreciation designs') }}
                </span>
            </div>

            <!-- Templates Grid -->
            <div v-if="templates.length === 0" class="mt-12 text-center py-16 bg-white dark:bg-gray-800/50 rounded-3xl border border-gray-200 dark:border-gray-800">
                <div class="text-4xl mb-3">🎨</div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ $t('No templates found') }}</h3>
                <p class="text-xs text-gray-500 mt-1 max-w-sm mx-auto">
                    {{ $t('Try selecting another category or searching with different keywords.') }}
                </p>
                <button
                    type="button"
                    @click="switchCategory('all'); searchQuery = ''; filterGallery()"
                    class="mt-4 px-4 py-2 bg-amber-600 text-white rounded-xl text-xs font-semibold"
                >
                    {{ $t('Reset Filters') }}
                </button>
            </div>

            <div v-else class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <div
                    v-for="tmpl in templates"
                    :key="tmpl.id"
                    class="group relative rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col"
                >
                    <!-- Card Thumbnail Image -->
                    <div class="aspect-[1080/1528] w-full bg-gray-100 dark:bg-gray-900 overflow-hidden relative">
                        <img
                            :src="tmpl.preview_path"
                            :alt="tmpl.name"
                            loading="lazy"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                        />

                        <!-- Overlay on Hover -->
                        <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex flex-col items-center justify-center gap-3 p-4">
                            <Link
                                :href="route('editor.show', { template: tmpl.id })"
                                class="w-full py-2.5 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-lg text-center transition"
                            >
                                {{ $t('Customize Template') }} &rarr;
                            </Link>
                        </div>

                        <!-- Top Badges -->
                        <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-black/60 backdrop-blur text-white">
                                {{ tmpl.category ? ($page.props.locale === 'am' ? tmpl.category.name_am : tmpl.category.name_en) : 'Template' }}
                            </span>
                            <span v-if="!tmpl.is_system" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-500 text-slate-950">
                                {{ $t('Custom') }}
                            </span>
                        </div>
                    </div>

                    <!-- Card Info Footer -->
                    <div class="p-3.5 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <div class="truncate mr-2">
                            <h4 class="font-semibold text-xs text-gray-900 dark:text-gray-100 truncate" :title="tmpl.name">
                                {{ tmpl.name }}
                            </h4>
                            <p class="text-[10px] text-gray-400 capitalize">
                                {{ tmpl.kind === 'thank_you' ? $t('Thank-You Card') : $t('Invitation Card') }}
                            </p>
                        </div>

                        <Link
                            :href="route('editor.show', { template: tmpl.id })"
                            class="shrink-0 p-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-amber-600 hover:text-white transition"
                            :title="$t('Use this template')"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </main>

        <!-- Upgrade Required Modal -->
        <Modal :show="showUpgradeModal" @close="showUpgradeModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-3 text-amber-500 mb-4">
                    <span class="text-3xl">👑</span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $t('Upgrade to Upload Custom Designs') }}
                    </h3>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed mb-6">
                    {{ $t('Uploading custom background images and card artwork is an exclusive feature of our Bronze, Silver, Gold, and Platinum tiers.') }}
                </p>

                <div class="flex items-center justify-end gap-3">
                    <SecondaryButton @click="showUpgradeModal = false">
                        {{ $t('Maybe Later') }}
                    </SecondaryButton>
                    <Link
                        :href="route('plan.index')"
                        class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs px-4 py-2 transition"
                    >
                        {{ $t('View Tier Plans') }} &rarr;
                    </Link>
                </div>
            </div>
        </Modal>

        <!-- Upload Custom Template Modal -->
        <Modal :show="showUploadModal" @close="showUploadModal = false" max-width="lg">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">
                    {{ $t('Upload Your Own Template Design') }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">
                    {{ $t('Upload your high-resolution card artwork (PNG, JPG, WebP up to 8 MB). We will optimize and prepare it for canvas text layers.') }}
                </p>

                <form @submit.prevent="handleUploadSubmit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('Template Name') }}
                        </label>
                        <input
                            type="text"
                            v-model="uploadForm.name"
                            placeholder="e.g. My Wedding Custom Card"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('Event Category') }}
                        </label>
                        <select
                            v-model="uploadForm.category_id"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                        >
                            <option value="">{{ $t('Select a category...') }}</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ $page.props.locale === 'am' ? cat.name_am : cat.name_en }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('Card Artwork Image') }} (Max 8 MB)
                        </label>
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            @change="handleFileSelect"
                            class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 dark:file:bg-amber-950/60 dark:file:text-amber-300"
                        />
                        <InputError :message="uploadForm.errors.image" class="mt-1" />
                    </div>

                    <!-- Image Preview -->
                    <div v-if="previewImageSrc" class="mt-4 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 text-center">
                        <span class="text-[10px] text-gray-400 block mb-2">{{ $t('Image Preview') }}</span>
                        <img :src="previewImageSrc" class="max-h-56 mx-auto rounded-lg shadow-sm" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <SecondaryButton @click="showUploadModal = false">
                            {{ $t('Cancel') }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="uploadForm.processing || !uploadForm.image">
                            {{ uploadForm.processing ? $t('Uploading...') : $t('Upload & Open in Editor') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>
