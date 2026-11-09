<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { toEthiopian } from '@/Utils/ethiopianCalendar';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    canQrCheckin: {
        type: Boolean,
        default: false,
    },
    ethiopianDate: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    category_id: props.event.category_id,
    title: props.event.title,
    description: props.event.description || '',
    host_names: props.event.host_names || '',
    starts_at: props.event.starts_at ? new Date(props.event.starts_at).toISOString().slice(0, 16) : '',
    ends_at: props.event.ends_at ? new Date(props.event.ends_at).toISOString().slice(0, 16) : '',
    timezone: props.event.timezone || 'Africa/Addis_Ababa',
    venue_name: props.event.venue_name || '',
    venue_address: props.event.venue_address || '',
    map_url: props.event.map_url || '',
    is_invite_only: Boolean(props.event.is_invite_only),
    rsvp_enabled: Boolean(props.event.rsvp_enabled),
    plus_ones_allowed: Boolean(props.event.plus_ones_allowed),
    status: props.event.status || 'draft',
});

const liveEthiopianStart = computed(() => {
    return toEthiopian(form.starts_at);
});

const submit = () => {
    form.patch(route('events.update', { event: props.event.id }));
};
</script>

<template>
    <Head :title="`Edit Event: ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        Edit Event: {{ event.title }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Update venue, date, time, and RSVP options
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('events.design', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-lg border border-amber-500/50 text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 text-xs font-semibold"
                    >
                        Card Editor →
                    </Link>
                    <Link
                        :href="route('events.guests.index', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-lg bg-zinc-800 text-zinc-200 text-xs font-semibold hover:bg-zinc-700"
                    >
                        Guest List →
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm p-6 sm:p-8">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Category & Status -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="category_id" value="Event Category" />
                                <select
                                    id="category_id"
                                    v-model="form.category_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                                >
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                        {{ cat.name_en }} ({{ cat.name_am }})
                                    </option>
                                </select>
                            </div>

                            <div>
                                <InputLabel for="status" value="Event Status" />
                                <select
                                    id="status"
                                    v-model="form.status"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
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
                                required
                            />
                            <InputError :message="form.errors.title" class="mt-1" />
                        </div>

                        <!-- Hosts -->
                        <div>
                            <InputLabel for="host_names" value="Host Names / Couple" />
                            <TextInput
                                id="host_names"
                                v-model="form.host_names"
                                type="text"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <!-- Date & Time -->
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
                                <div v-if="liveEthiopianStart" class="mt-2 p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 dark:text-amber-300 flex items-center space-x-1.5 font-medium">
                                    <span>🇪🇹</span>
                                    <span>የኢትዮጵያ ዘመን አቆጣጠር፡ <strong>{{ liveEthiopianStart.formattedAm }}</strong></span>
                                </div>
                                <InputError :message="form.errors.starts_at" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="timezone" value="Timezone" />
                                <TextInput
                                    id="timezone"
                                    v-model="form.timezone"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                            </div>
                        </div>

                        <!-- Venue Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="venue_name" value="Venue Name" />
                                <TextInput
                                    id="venue_name"
                                    v-model="form.venue_name"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel for="venue_address" value="Venue Address" />
                                <TextInput
                                    id="venue_address"
                                    v-model="form.venue_address"
                                    type="text"
                                    class="mt-1 block w-full"
                                />
                            </div>
                        </div>

                        <!-- Map URL -->
                        <div>
                            <InputLabel for="map_url" value="Google Maps Link" />
                            <TextInput
                                id="map_url"
                                v-model="form.map_url"
                                type="url"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <!-- Description -->
                        <div>
                            <InputLabel for="description" value="Description" />
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-amber-500 focus:ring-amber-500 text-sm"
                            ></textarea>
                        </div>

                        <!-- Settings Toggles -->
                        <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-sm text-gray-900 dark:text-white">QR Code Check-in (Invite-Only)</span>
                                    <p class="text-xs text-gray-500">Requires single-use QR admission token</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="form.is_invite_only"
                                    :disabled="!canQrCheckin"
                                    class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500"
                                />
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-sm text-gray-900 dark:text-white">RSVP Responses</span>
                                    <p class="text-xs text-gray-500">Enable guests to reply online</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="form.rsvp_enabled"
                                    class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500"
                                />
                            </div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-bold text-sm text-gray-900 dark:text-white">Allow Plus-Ones (+1)</span>
                                    <p class="text-xs text-gray-500">Allow guests to bring extra guests</p>
                                </div>
                                <input
                                    type="checkbox"
                                    v-model="form.plus_ones_allowed"
                                    class="w-5 h-5 rounded text-amber-600 focus:ring-amber-500"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-100 dark:border-gray-800">
                            <Link :href="route('dashboard')" class="text-xs text-gray-500 hover:text-gray-700">
                                ← Back to Dashboard
                            </Link>

                            <PrimaryButton :disabled="form.processing" class="px-6 py-2.5">
                                Save Changes
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
