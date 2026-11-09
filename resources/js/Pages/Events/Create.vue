<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { toEthiopian } from '@/Utils/ethiopianCalendar';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    canCreate: {
        type: Boolean,
        default: true,
    },
    maxEvents: {
        type: Number,
        default: null,
    },
    activeEventsCount: {
        type: Number,
        default: 0,
    },
    canQrCheckin: {
        type: Boolean,
        default: false,
    },
    timezones: {
        type: Array,
        default: () => ['Africa/Addis_Ababa'],
    },
});

const currentStep = ref(1);

const form = useForm({
    category_id: props.categories[0]?.id || null,
    title: '',
    description: '',
    host_names: '',
    starts_at: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
    ends_at: new Date(Date.now() + 7 * 86400000 + 6 * 3600000).toISOString().slice(0, 16),
    timezone: 'Africa/Addis_Ababa',
    venue_name: '',
    venue_address: '',
    map_url: '',
    is_invite_only: props.canQrCheckin,
    rsvp_enabled: true,
    plus_ones_allowed: false,
    template_id: null,
});

const selectedCategory = computed(() => {
    return props.categories.find(c => c.id === form.category_id) || props.categories[0] || null;
});

const isFuneral = computed(() => {
    return selectedCategory.value?.slug === 'funeral';
});

// React to category changes (Funeral category auto-defaults)
watch(selectedCategory, (cat) => {
    if (cat?.slug === 'funeral') {
        form.rsvp_enabled = false;
        form.plus_ones_allowed = false;
        form.is_invite_only = false;
        if (!form.title) {
            form.title = 'የመጨረሻ የስንብት እና የመታሰቢያ ዝግጅት';
        }
    }
}, { immediate: true });

// Live Ethiopian Calendar conversion
const ethiopianStart = computed(() => {
    return toEthiopian(form.starts_at);
});

// Templates for chosen category
const categoryTemplates = computed(() => {
    return selectedCategory.value?.templates || [];
});

const submit = () => {
    form.post(route('events.store'));
};
</script>

<template>
    <Head title="Create New Event" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        Create New Event
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Follow the 3-step wizard to setup your event and design cards
                    </p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    Cancel
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Limit Exceeded Warning -->
                <div
                    v-if="!canCreate"
                    class="mb-6 rounded-2xl bg-amber-500/10 border border-amber-500/30 p-6 text-center text-amber-900 dark:text-amber-200"
                >
                    <div class="text-3xl mb-2">🔒</div>
                    <h3 class="text-lg font-bold">Event Limit Reached</h3>
                    <p class="text-sm mt-1 text-gray-600 dark:text-gray-300">
                        Your current plan allows up to {{ maxEvents }} active event(s). You currently have {{ activeEventsCount }} active events.
                    </p>
                    <div class="mt-4">
                        <Link
                            :href="route('plan.index')"
                            class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-xl text-xs transition shadow-sm"
                        >
                            Upgrade Plan to Create More Events →
                        </Link>
                    </div>
                </div>

                <div v-else class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <!-- Progress Stepper Header -->
                    <div class="border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50 px-6 py-4">
                        <div class="flex items-center justify-between max-w-md mx-auto">
                            <!-- Step 1 -->
                            <button
                                type="button"
                                @click="currentStep = 1"
                                class="flex items-center space-x-2 text-xs font-semibold"
                                :class="currentStep === 1 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 hover:text-gray-600'"
                            >
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs"
                                    :class="currentStep === 1 ? 'bg-amber-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                >
                                    1
                                </span>
                                <span>Details</span>
                            </button>

                            <div class="h-0.5 w-12 bg-gray-200 dark:bg-gray-700"></div>

                            <!-- Step 2 -->
                            <button
                                type="button"
                                @click="currentStep = 2"
                                class="flex items-center space-x-2 text-xs font-semibold"
                                :class="currentStep === 2 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 hover:text-gray-600'"
                            >
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs"
                                    :class="currentStep === 2 ? 'bg-amber-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                >
                                    2
                                </span>
                                <span>Settings</span>
                            </button>

                            <div class="h-0.5 w-12 bg-gray-200 dark:bg-gray-700"></div>

                            <!-- Step 3 -->
                            <button
                                type="button"
                                @click="currentStep = 3"
                                class="flex items-center space-x-2 text-xs font-semibold"
                                :class="currentStep === 3 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 hover:text-gray-600'"
                            >
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs"
                                    :class="currentStep === 3 ? 'bg-amber-600 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'"
                                >
                                    3
                                </span>
                                <span>Template & Design</span>
                            </button>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="p-6 sm:p-8">
                        <!-- STEP 1: EVENT DETAILS -->
                        <div v-show="currentStep === 1" class="space-y-6">
                            <!-- Category Picker -->
                            <div>
                                <InputLabel value="Event Category" />
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mt-2">
                                    <button
                                        v-for="cat in categories"
                                        :key="cat.id"
                                        type="button"
                                        @click="form.category_id = cat.id"
                                        :class="form.category_id === cat.id ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 ring-2 ring-amber-500/20' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300'"
                                        class="p-3 rounded-xl border text-left text-xs font-semibold transition"
                                    >
                                        <div class="truncate">{{ cat.name_en }}</div>
                                        <div class="text-[10px] text-gray-400 truncate mt-0.5">{{ cat.name_am }}</div>
                                    </button>
                                </div>
                                <InputError :message="form.errors.category_id" class="mt-1" />
                            </div>

                            <!-- Funeral notice -->
                            <div v-if="isFuneral" class="p-4 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-xs text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 flex items-start space-x-2.5">
                                <span class="text-base">🕊️</span>
                                <div>
                                    <strong class="font-bold">Funeral & Memorial Mode:</strong>
                                    <p class="mt-0.5">RSVP and plus-ones are disabled by default for respect and solemnity. Wording will reflect memorial honoring.</p>
                                </div>
                            </div>

                            <!-- Title -->
                            <div>
                                <InputLabel for="title" value="Event Title *" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="e.g. Samuel & Sara's Wedding"
                                    required
                                />
                                <InputError :message="form.errors.title" class="mt-1" />
                            </div>

                            <!-- Host Names -->
                            <div>
                                <InputLabel for="host_names" :value="isFuneral ? 'Family of the Deceased / Hosts' : 'Host Names / Couple'" />
                                <TextInput
                                    id="host_names"
                                    v-model="form.host_names"
                                    type="text"
                                    class="mt-1 block w-full"
                                    :placeholder="isFuneral ? 'The Demissie Family' : 'Abebe & Chaltu'"
                                />
                                <InputError :message="form.errors.host_names" class="mt-1" />
                            </div>

                            <!-- Date / Time with Ethiopian Display -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="starts_at" value="Date & Time (Gregorian) *" />
                                    <TextInput
                                        id="starts_at"
                                        v-model="form.starts_at"
                                        type="datetime-local"
                                        class="mt-1 block w-full font-mono text-sm"
                                        required
                                    />
                                    <!-- Ethiopian Date Preview -->
                                    <div v-if="ethiopianStart" class="mt-2 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 flex items-center space-x-1.5 font-medium">
                                        <span>🇪🇹</span>
                                        <span>የኢትዮጵያ ዘመን አቆጣጠር፡ <strong>{{ ethiopianStart.formattedAm }}</strong> ({{ ethiopianStart.formattedEn }})</span>
                                    </div>
                                    <InputError :message="form.errors.starts_at" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="timezone" value="Timezone" />
                                    <select
                                        id="timezone"
                                        v-model="form.timezone"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                                    >
                                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                                    </select>
                                    <InputError :message="form.errors.timezone" class="mt-1" />
                                </div>
                            </div>

                            <!-- Venue Information -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="venue_name" value="Venue Name" />
                                    <TextInput
                                        id="venue_name"
                                        v-model="form.venue_name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="e.g. Sheraton Addis, Lalibela Grand Ballroom"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="venue_address" value="Venue Address" />
                                    <TextInput
                                        id="venue_address"
                                        v-model="form.venue_address"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="e.g. Taitu St, Kirkos, Addis Ababa"
                                    />
                                </div>
                            </div>

                            <!-- Google Maps URL -->
                            <div>
                                <InputLabel for="map_url" value="Google Maps Link" />
                                <TextInput
                                    id="map_url"
                                    v-model="form.map_url"
                                    type="url"
                                    class="mt-1 block w-full"
                                    placeholder="https://maps.google.com/?q=..."
                                />
                                <InputError :message="form.errors.map_url" class="mt-1" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="description" value="Description / Welcome Message (Optional)" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                                    placeholder="A brief message or dress code info for your guests..."
                                ></textarea>
                                <InputError :message="form.errors.description" class="mt-1" />
                            </div>

                            <div class="flex justify-end pt-4">
                                <PrimaryButton type="button" @click="currentStep = 2" class="px-6 py-2.5">
                                    Next: Event Settings →
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- STEP 2: EVENT SETTINGS -->
                        <div v-show="currentStep === 2" class="space-y-6">
                            <!-- QR Check-in & Invite-Only -->
                            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="font-bold text-sm text-gray-900 dark:text-white">QR Code Check-in (Invite-Only)</span>
                                            <span v-if="!canQrCheckin" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-amber-500/20 text-amber-700 dark:text-amber-300">
                                                Paid Tier Feature
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xl">
                                            Generates single-use secure QR codes on each card. Door scanners can verify guest entry and prevent duplicate admissions.
                                        </p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        v-model="form.is_invite_only"
                                        :disabled="!canQrCheckin"
                                        class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500 disabled:opacity-40"
                                    />
                                </div>
                                <div v-if="!canQrCheckin" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                    QR check-in is available on Bronze, Silver, and Gold tiers. <Link :href="route('plan.index')" class="underline font-semibold">Upgrade now</Link>
                                </div>
                            </div>

                            <!-- RSVP Feature -->
                            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-bold text-sm text-gray-900 dark:text-white">RSVP Responses</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xl">
                                            Allows guests to accept or decline attendance with optional notes and party size confirmation.
                                        </p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        v-model="form.rsvp_enabled"
                                        class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500"
                                    />
                                </div>
                            </div>

                            <!-- Plus-ones -->
                            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-bold text-sm text-gray-900 dark:text-white">Allow Plus-Ones (+1)</span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xl">
                                            Permits invited guests to bring additional family members or companions within their allocated party size.
                                        </p>
                                    </div>
                                    <input
                                        type="checkbox"
                                        v-model="form.plus_ones_allowed"
                                        class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-between pt-4">
                                <SecondaryButton type="button" @click="currentStep = 1">
                                    ← Back to Details
                                </SecondaryButton>
                                <PrimaryButton type="button" @click="currentStep = 3" class="px-6 py-2.5">
                                    Next: Select Design Template →
                                </PrimaryButton>
                            </div>
                        </div>

                        <!-- STEP 3: TEMPLATE & CARD DESIGN -->
                        <div v-show="currentStep === 3" class="space-y-6">
                            <div>
                                <h3 class="font-bold text-sm text-gray-900 dark:text-white mb-1">
                                    Choose Starting Template for {{ selectedCategory?.name_en }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                    You will be taken directly into the Konva card editor to customize fonts, colors, and layout right after creation.
                                </p>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-h-96 overflow-y-auto p-1">
                                    <!-- Blank Canvas Option -->
                                    <div
                                        @click="form.template_id = null"
                                        :class="form.template_id === null ? 'border-amber-500 ring-2 ring-amber-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-amber-400'"
                                        class="cursor-pointer rounded-2xl border p-3 bg-gray-50 dark:bg-gray-800 flex flex-col items-center justify-center aspect-[3/4] text-center transition"
                                    >
                                        <span class="text-2xl mb-1">✨</span>
                                        <div class="text-xs font-bold text-gray-900 dark:text-white">Blank Canvas</div>
                                        <div class="text-[10px] text-gray-500">Design from scratch</div>
                                    </div>

                                    <!-- Category Templates -->
                                    <div
                                        v-for="tmpl in categoryTemplates"
                                        :key="tmpl.id"
                                        @click="form.template_id = tmpl.id"
                                        :class="form.template_id === tmpl.id ? 'border-amber-500 ring-2 ring-amber-500/30' : 'border-gray-200 dark:border-gray-700 hover:border-amber-400'"
                                        class="cursor-pointer rounded-2xl border overflow-hidden bg-gray-900 flex flex-col justify-between aspect-[3/4] transition relative group"
                                    >
                                        <img
                                            v-if="tmpl.preview_url"
                                            :src="tmpl.preview_url"
                                            :alt="tmpl.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="flex-1 flex items-center justify-center p-3 text-center text-xs text-gray-300">
                                            {{ tmpl.name }}
                                        </div>
                                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 to-transparent p-2 text-center">
                                            <span class="text-[11px] font-semibold text-white truncate block">{{ tmpl.name }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between pt-4 border-t border-gray-100 dark:border-gray-800">
                                <SecondaryButton type="button" @click="currentStep = 2">
                                    ← Back to Settings
                                </SecondaryButton>
                                <PrimaryButton :disabled="form.processing" class="px-8 py-3 text-sm font-bold bg-amber-600 hover:bg-amber-500">
                                    {{ form.processing ? 'Creating Event...' : 'Create Event & Open Editor →' }}
                                </PrimaryButton>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
