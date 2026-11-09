<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import axios from 'axios';

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    isGuest: {
        type: Boolean,
        default: true,
    },
});

const defaultTheme = {
    theme: props.template?.design_json?.theme || 'gold',
    primaryColor: props.template?.design_json?.primaryColor || '#D4AF37',
    backgroundColor: props.template?.design_json?.backgroundColor || '#18181B',
    textColor: props.template?.design_json?.textColor || '#FAFAFA',
    fontFamily: props.template?.design_json?.fontFamily || 'Noto Sans Ethiopic',
    heading: props.template?.design_json?.heading || 'የክብር ግብዣ',
    subheading: props.template?.design_json?.subheading || 'Cordially Invites You',
};

const draft = ref({
    template_id: props.template?.id || null,
    category_id: props.template?.category_id || (props.categories[0]?.id ?? null),
    title: props.template ? `${props.template.name} Invitation` : 'My Invitation Card',
    host_names: props.template?.design_json?.hosts || 'Abebe & Chaltu',
    venue_name: props.template?.design_json?.venue || 'Sheraton Addis',
    venue_address: 'Taitu Street, Addis Ababa',
    starts_at: '2027-01-10T14:00',
    design_json: { ...defaultTheme },
});

const isSavedLocally = ref(false);
const showGuestModal = ref(false);
const guestModalAction = ref('');

// Load draft from localStorage on mount if exists
onMounted(() => {
    try {
        const stored = localStorage.getItem('invite_guest_draft');
        if (stored) {
            const parsed = JSON.parse(stored);
            // If template wasn't explicitly selected in URL, load full stored draft
            if (!props.template && parsed.design_json) {
                draft.value = { ...draft.value, ...parsed };
            }
        }
    } catch (e) {
        // ignore JSON parse error
    }
    saveToLocalStorage();
});

const saveToLocalStorage = () => {
    try {
        localStorage.setItem('invite_guest_draft', JSON.stringify(draft.value));
        isSavedLocally.value = true;
        setTimeout(() => {
            isSavedLocally.value = false;
        }, 2000);
    } catch (e) {
        // storage quota exceeded or disabled
    }
};

// Auto-save on changes
watch(draft, () => {
    saveToLocalStorage();
}, { deep: true });

// Guest action handler
const handleGuestAction = (actionName) => {
    saveToLocalStorage();

    if (props.isGuest) {
        guestModalAction.value = actionName;
        showGuestModal.value = true;
        return;
    }

    if (actionName === 'save') {
        saveEventToServer();
    } else {
        router.visit(route('dashboard'));
    }
};

const isSaving = ref(false);
const saveError = ref('');

const saveEventToServer = async () => {
    isSaving.value = true;
    saveError.value = '';

    try {
        await axios.post(route('events.import-draft'), {
            draft: draft.value,
        });
        localStorage.removeItem('invite_guest_draft');
        router.visit(route('dashboard'));
    } catch (error) {
        saveError.value = error.response?.data?.message || 'Failed to save event.';
    } finally {
        isSaving.value = false;
    }
};

const redirectToAuth = (targetRoute) => {
    saveToLocalStorage();
    router.visit(route(targetRoute, { redirect: '/dashboard' }));
};
</script>

<template>
    <Head :title="draft.title || $t('Editor')" />

    <div class="min-h-screen bg-gray-100 dark:bg-gray-950 flex flex-col text-gray-900 dark:text-gray-100">
        <!-- Top Toolbar -->
        <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between shadow-sm sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <Link :href="route('templates.index')" class="text-xs text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 flex items-center gap-1 font-medium">
                    ← {{ $t('Gallery') }}
                </Link>
                <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-600 bg-amber-50 dark:bg-amber-950/50 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                        {{ isGuest ? $t('Guest Mode') : 'Editor' }}
                    </span>
                    <h1 class="text-sm font-semibold truncate max-w-xs sm:max-w-md">
                        {{ draft.title }}
                    </h1>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span v-if="isSavedLocally" class="hidden sm:inline-flex text-xs text-emerald-600 dark:text-emerald-400 items-center gap-1">
                    ✓ {{ $t('Draft Saved') }}
                </span>

                <LanguageSwitcher />

                <PrimaryButton
                    @click="handleGuestAction('save')"
                    :disabled="isSaving"
                    class="!py-1.5 !px-3 text-xs"
                >
                    {{ isGuest ? $t('Save to Account') : $t('Save') }}
                </PrimaryButton>
            </div>
        </header>

        <!-- Guest Alert Banner -->
        <div v-if="isGuest" class="bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-b border-amber-200 dark:border-amber-900/50 px-4 py-2 text-xs text-amber-900 dark:text-amber-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-bold">✨ {{ $t('Guest Mode') }}:</span>
                <span>You can design freely! Cards and guest lists require a free account.</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="redirectToAuth('login')"
                    class="font-semibold underline hover:text-amber-700 dark:hover:text-amber-100"
                >
                    {{ $t('Log in') }}
                </button>
                <button
                    type="button"
                    @click="redirectToAuth('register')"
                    class="bg-amber-600 hover:bg-amber-500 text-white rounded px-2.5 py-1 font-semibold shadow-xs"
                >
                    {{ $t('Register') }}
                </button>
            </div>
        </div>

        <!-- Editor Body -->
        <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
            <!-- Left Controls Sidebar -->
            <div class="w-full lg:w-96 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 p-6 overflow-y-auto space-y-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900 dark:text-white">
                        {{ $t('Customize Invitation') }}
                    </h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Edit details and real-time preview updates instantly.
                    </p>
                </div>

                <!-- Event Details Form -->
                <div class="space-y-4 text-xs">
                    <div>
                        <InputLabel for="title" :value="$t('Title')" />
                        <TextInput
                            id="title"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            v-model="draft.title"
                        />
                    </div>

                    <div>
                        <InputLabel for="heading" value="Card Headline" />
                        <TextInput
                            id="heading"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            v-model="draft.design_json.heading"
                        />
                    </div>

                    <div>
                        <InputLabel for="subheading" value="Subheading" />
                        <TextInput
                            id="subheading"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            v-model="draft.design_json.subheading"
                        />
                    </div>

                    <div>
                        <InputLabel for="host_names" :value="$t('Host Names')" />
                        <TextInput
                            id="host_names"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            v-model="draft.host_names"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="venue_name" :value="$t('Venue Name')" />
                            <TextInput
                                id="venue_name"
                                type="text"
                                class="mt-1 block w-full text-xs"
                                v-model="draft.venue_name"
                            />
                        </div>
                        <div>
                            <InputLabel for="starts_at" :value="$t('Date & Time')" />
                            <TextInput
                                id="starts_at"
                                type="datetime-local"
                                class="mt-1 block w-full text-xs"
                                v-model="draft.starts_at"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="venue_address" :value="$t('Venue Address')" />
                        <TextInput
                            id="venue_address"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            v-model="draft.venue_address"
                        />
                    </div>

                    <!-- Theme Colors -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                            {{ $t('Theme Color') }}
                        </label>
                        <div class="flex items-center gap-3">
                            <div>
                                <span class="text-[10px] text-gray-500 block mb-1">Background</span>
                                <input
                                    type="color"
                                    v-model="draft.design_json.backgroundColor"
                                    class="h-8 w-12 rounded cursor-pointer border-0 bg-transparent"
                                />
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-500 block mb-1">Accent Gold</span>
                                <input
                                    type="color"
                                    v-model="draft.design_json.primaryColor"
                                    class="h-8 w-12 rounded cursor-pointer border-0 bg-transparent"
                                />
                            </div>
                            <div>
                                <span class="text-[10px] text-gray-500 block mb-1">Text</span>
                                <input
                                    type="color"
                                    v-model="draft.design_json.textColor"
                                    class="h-8 w-12 rounded cursor-pointer border-0 bg-transparent"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guest-blocked Action Buttons -->
                <div class="pt-6 border-t border-gray-100 dark:border-gray-800 space-y-2">
                    <button
                        type="button"
                        @click="handleGuestAction('upload_image')"
                        class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-700 text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        📷 {{ $t('Upload Image') }}
                        <span v-if="isGuest" class="text-[10px] bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-amber-600 font-bold">PRO</span>
                    </button>

                    <button
                        type="button"
                        @click="handleGuestAction('add_guests')"
                        class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg border border-gray-300 dark:border-gray-700 text-xs font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                    >
                        👥 {{ $t('Add Guests') }}
                        <span v-if="isGuest" class="text-[10px] bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-amber-600 font-bold">AUTH</span>
                    </button>

                    <button
                        type="button"
                        @click="handleGuestAction('send_cards')"
                        class="w-full flex items-center justify-center gap-2 py-2 px-3 rounded-lg bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold shadow-sm transition-colors"
                    >
                        🚀 {{ $t('Send Invitations') }} (SMS/Email)
                    </button>
                </div>
            </div>

            <!-- Right Interactive Canvas / Card Preview -->
            <div class="flex-1 p-6 md:p-12 flex flex-col items-center justify-center bg-gray-100 dark:bg-gray-950 overflow-y-auto">
                <div class="text-xs text-gray-500 mb-3 flex items-center gap-2">
                    <span>{{ $t('Card Preview') }} (1080 × 1920 Mobile Ratio)</span>
                </div>

                <!-- The Invitation Card Frame -->
                <div
                    class="w-full max-w-sm aspect-[9/16] rounded-3xl shadow-2xl p-8 flex flex-col justify-between text-center relative overflow-hidden transition-all duration-300 border border-white/10"
                    :style="{
                        backgroundColor: draft.design_json.backgroundColor,
                        color: draft.design_json.textColor
                    }"
                >
                    <!-- Decorative Ethiopian Corner Motif -->
                    <div class="absolute top-4 left-4 right-4 flex justify-between items-center opacity-30 text-[10px] tracking-widest uppercase">
                        <span>INVITE • ETHIOPIA</span>
                        <span>QR VERIFIED</span>
                    </div>

                    <!-- Header Section -->
                    <div class="mt-8 space-y-2">
                        <div
                            class="text-xs font-semibold tracking-widest uppercase"
                            :style="{ color: draft.design_json.primaryColor }"
                        >
                            {{ draft.design_json.subheading }}
                        </div>
                        <h2 class="text-3xl font-serif font-black tracking-wide leading-tight">
                            {{ draft.design_json.heading }}
                        </h2>
                    </div>

                    <!-- Center Host & Event Info -->
                    <div class="my-auto py-6 space-y-4">
                        <div class="w-12 h-0.5 mx-auto rounded-full" :style="{ backgroundColor: draft.design_json.primaryColor }"></div>
                        <p class="text-sm font-light uppercase tracking-wider opacity-90">
                            Honor of your presence is requested with
                        </p>
                        <h3 class="text-2xl font-bold font-serif" :style="{ color: draft.design_json.primaryColor }">
                            {{ draft.host_names }}
                        </h3>
                        <div class="w-12 h-0.5 mx-auto rounded-full" :style="{ backgroundColor: draft.design_json.primaryColor }"></div>
                    </div>

                    <!-- Bottom Event Date, Venue, and QR Mock -->
                    <div class="space-y-4 pb-2">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider">
                                📍 {{ draft.venue_name }}
                            </p>
                            <p class="text-[11px] opacity-75">
                                {{ draft.venue_address }}
                            </p>
                            <p class="text-xs font-mono opacity-90 pt-1">
                                🗓 {{ draft.starts_at }}
                            </p>
                        </div>

                        <!-- Single-use QR Preview Box -->
                        <div class="p-3 bg-white/10 backdrop-blur rounded-xl border border-white/15 flex items-center justify-between text-left">
                            <div class="text-[10px] leading-tight opacity-90">
                                <strong>Individual Guest Pass</strong><br />
                                Scan at entrance for check-in
                            </div>
                            <div class="w-10 h-10 bg-white rounded-lg p-1 flex items-center justify-center shadow-xs">
                                <div class="w-full h-full bg-black rounded-xs flex items-center justify-center text-[7px] text-white font-mono">
                                    QR
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guest Modal Prompt -->
        <Modal :show="showGuestModal" @close="showGuestModal = false">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/40 text-2xl mb-4">
                    ✨
                </div>

                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ $t('Guest Mode') }}
                </h3>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ $t('Please create an account or log in to save your invitation, add guests, and send cards.') }}
                </p>

                <p class="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    ✓ {{ $t('Your draft has been saved locally!') }} It will be automatically saved to your new account.
                </p>

                <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                    <PrimaryButton @click="redirectToAuth('register')" class="justify-center">
                        {{ $t('Create Free Account') }}
                    </PrimaryButton>

                    <SecondaryButton @click="redirectToAuth('login')" class="justify-center">
                        {{ $t('Log in') }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </div>
</template>
