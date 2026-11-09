<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    coHostEvents: {
        type: Array,
        default: () => [],
    },
    canCreateEvent: {
        type: Boolean,
        default: true,
    },
    canThankYou: {
        type: Boolean,
        default: false,
    },
    maxEvents: {
        type: Number,
        default: null,
    },
    activeEventsCount: {
        type: Number,
        default: 0,
    },
});

const eventToDelete = ref(null);
const showDeleteModal = ref(false);

const confirmDelete = (evt) => {
    eventToDelete.value = evt;
    showDeleteModal.value = true;
};

const deleteEvent = () => {
    if (!eventToDelete.value) return;
    router.delete(route('events.destroy', { event: eventToDelete.value.id }), {
        onSuccess: () => {
            showDeleteModal.value = false;
            eventToDelete.value = null;
        },
    });
};

const duplicateEvent = (evt) => {
    router.post(route('events.duplicate', { event: evt.id }));
};
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                        {{ $t('My Events') }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Manage invitations, guest lists, and door check-in
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('templates.index')"
                        class="text-xs font-semibold text-gray-700 dark:text-gray-300 hover:text-amber-600 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 transition"
                    >
                        {{ $t('Template Gallery') }}
                    </Link>

                    <Link
                        v-if="canCreateEvent"
                        :href="route('events.create')"
                        class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 transition flex items-center space-x-1"
                    >
                        <span>+</span>
                        <span>Create Event</span>
                    </Link>

                    <Link
                        v-else
                        :href="route('plan.index')"
                        class="rounded-xl bg-gray-200 dark:bg-gray-800 px-3.5 py-2 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline transition"
                        title="Event limit reached. Upgrade to create more."
                    >
                        🔒 Limit Reached ({{ activeEventsCount }}/{{ maxEvents }})
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-10">
                <!-- User Owned Events -->
                <div>
                    <div v-if="events && events.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="evt in events"
                            :key="evt.id"
                            class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between"
                        >
                            <div>
                                <!-- Category & Status -->
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        {{ evt.category?.name_en || 'Event' }}
                                    </span>
                                    <span
                                        class="text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': evt.status === 'published',
                                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300': evt.status === 'draft',
                                            'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300': evt.status === 'cancelled',
                                            'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300': evt.status === 'completed',
                                        }"
                                    >
                                        {{ evt.status }}
                                    </span>
                                </div>

                                <h3 class="font-bold text-lg text-gray-900 dark:text-white line-clamp-1">
                                    {{ evt.title }}
                                </h3>

                                <!-- Dates -->
                                <div class="mt-2 space-y-0.5">
                                    <p class="text-xs text-gray-600 dark:text-gray-300 flex items-center space-x-1.5">
                                        <span>🗓</span>
                                        <span>{{ new Date(evt.starts_at).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) }}</span>
                                    </p>
                                    <p v-if="evt.ethiopian_date" class="text-[11px] text-amber-700 dark:text-amber-400 font-medium pl-5">
                                        🇪🇹 {{ evt.ethiopian_date.formattedAm }}
                                    </p>
                                </div>

                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 line-clamp-1 flex items-center space-x-1.5">
                                    <span>📍</span>
                                    <span>{{ evt.venue_name || 'Venue to be announced' }}</span>
                                </p>

                                <!-- Guest Metrics Pill -->
                                <div class="mt-4 p-3 rounded-2xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800/80 text-xs flex items-center justify-between">
                                    <div class="text-center flex-1 border-r border-gray-200 dark:border-gray-700">
                                        <div class="font-bold text-gray-900 dark:text-white">{{ evt.guests_count || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">Guests</div>
                                    </div>
                                    <div class="text-center flex-1 border-r border-gray-200 dark:border-gray-700">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ evt.guests_attending_count || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">Confirmed</div>
                                    </div>
                                    <div class="text-center flex-1">
                                        <div class="font-bold text-amber-600 dark:text-amber-400">{{ evt.guests_checked_in_count || 0 }}</div>
                                        <div class="text-[10px] text-gray-500">Checked In</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="pt-5 mt-5 border-t border-gray-100 dark:border-gray-800 flex flex-col space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <Link
                                        :href="route('events.guests.index', { event: evt.id })"
                                        class="py-2 px-3 text-center text-xs font-semibold rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 dark:hover:bg-amber-900/60 transition"
                                    >
                                        👥 Guest List
                                    </Link>
                                    <Link
                                        :href="route('events.design', { event: evt.id })"
                                        class="py-2 px-3 text-center text-xs font-semibold rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-800 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition"
                                    >
                                        🎨 Card Editor
                                    </Link>
                                </div>

                                <div class="flex items-center justify-between text-xs pt-1">
                                    <Link
                                        :href="route('events.edit', { event: evt.id })"
                                        class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                    >
                                        Edit Details
                                    </Link>
                                    <button
                                        type="button"
                                        @click="duplicateEvent(evt)"
                                        class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                        title="Clone event as draft"
                                    >
                                        Duplicate
                                    </button>
                                    <button
                                        type="button"
                                        @click="confirmDelete(evt)"
                                        class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 font-medium"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-800 p-12 text-center bg-white dark:bg-gray-900"
                    >
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-950/50 text-3xl mb-4">
                            💌
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                            No events yet
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                            Get started by creating your first event invitation. Design the card and import your guest list!
                        </p>
                        <div class="mt-6">
                            <Link
                                :href="route('events.create')"
                                class="inline-flex items-center px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs shadow-sm transition"
                            >
                                + Create New Event
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Co-Hosted Events Section -->
                <div v-if="coHostEvents && coHostEvents.length > 0">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center space-x-2">
                        <span>🤝 Co-Hosted Events</span>
                        <span class="text-xs font-normal text-gray-500">({{ coHostEvents.length }})</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div
                            v-for="evt in coHostEvents"
                            :key="evt.id"
                            class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 p-6 shadow-sm flex flex-col justify-between"
                        >
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                        Role: {{ evt.my_role }}
                                    </span>
                                    <span class="text-xs text-gray-400">By {{ evt.user?.name }}</span>
                                </div>

                                <h4 class="font-bold text-base text-gray-900 dark:text-white line-clamp-1">
                                    {{ evt.title }}
                                </h4>
                                <p class="text-xs text-gray-500 mt-1">
                                    🗓 {{ new Date(evt.starts_at).toLocaleDateString() }}
                                </p>
                            </div>

                            <div class="pt-4 mt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs">
                                <Link
                                    :href="route('events.guests.index', { event: evt.id })"
                                    class="text-amber-600 hover:underline font-semibold"
                                >
                                    Guest List ({{ evt.guests_count }}) →
                                </Link>

                                <Link
                                    v-if="evt.my_role === 'editor'"
                                    :href="route('events.design', { event: evt.id })"
                                    class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                >
                                    Edit Design
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Delete Event</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    Are you sure you want to delete "<strong>{{ eventToDelete?.title }}</strong>"? This will remove all invitation cards, guest lists, and RSVP records. This action cannot be undone.
                </p>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="showDeleteModal = false">Cancel</SecondaryButton>
                    <button
                        type="button"
                        @click="deleteEvent"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold transition"
                    >
                        Yes, Delete Event
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
