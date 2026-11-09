<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    guests: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    limits: {
        type: Object,
        default: () => ({}),
    },
    features: {
        type: Object,
        default: () => ({}),
    },
    members: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

// Modals
const showAddModal = ref(false);
const showBulkPasteModal = ref(false);
const showImportModal = ref(false);
const showEditModal = ref(false);
const showCoHostModal = ref(false);
const editingGuest = ref(null);

// Search & Filter state
const search = ref(props.filters.search || '');
const rsvpStatus = ref(props.filters.rsvp_status || '');
const checkedIn = ref(props.filters.checked_in !== undefined ? props.filters.checked_in : '');
const channelFilter = ref(props.filters.channel || '');

// Selection state
const selectedIds = ref([]);
const selectAll = ref(false);

const toggleSelectAll = () => {
    if (selectAll.value) {
        selectedIds.value = props.guests.map(g => g.id);
    } else {
        selectedIds.value = [];
    }
};

const applyFilters = () => {
    router.get(
        route('events.guests.index', { event: props.event.id }),
        {
            search: search.value || undefined,
            rsvp_status: rsvpStatus.value || undefined,
            checked_in: checkedIn.value !== '' ? checkedIn.value : undefined,
            channel: channelFilter.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

// Form: Add Single Guest
const addForm = useForm({
    name: '',
    phone: '',
    email: '',
    preferred_channel: props.features.sms_channel ? 'sms' : 'email',
    max_party_size: 1,
});

const submitAddGuest = () => {
    addForm.post(route('events.guests.store', { event: props.event.id }), {
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

// Form: Bulk Paste
const pasteForm = useForm({
    text: '',
    on_duplicate: 'skip',
});

const submitBulkPaste = () => {
    pasteForm.post(route('events.guests.bulk-paste', { event: props.event.id }), {
        onSuccess: () => {
            showBulkPasteModal.value = false;
            pasteForm.reset();
        },
    });
};

// Form: CSV Import
const importForm = useForm({
    file: null,
    on_duplicate: 'skip',
});

const submitCsvImport = () => {
    importForm.post(route('events.guests.import', { event: props.event.id }), {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
};

// Form: Edit Guest
const editForm = useForm({
    name: '',
    phone: '',
    email: '',
    preferred_channel: 'sms',
    max_party_size: 1,
    rsvp_status: 'pending',
});

const openEditModal = (guest) => {
    editingGuest.value = guest;
    editForm.name = guest.name;
    editForm.phone = guest.phone || '';
    editForm.email = guest.email || '';
    editForm.preferred_channel = guest.preferred_channel;
    editForm.max_party_size = guest.max_party_size;
    editForm.rsvp_status = guest.rsvp_status;
    showEditModal.value = true;
};

const submitEditGuest = () => {
    if (!editingGuest.value) return;
    editForm.patch(route('events.guests.update', { event: props.event.id, guest: editingGuest.value.id }), {
        onSuccess: () => {
            showEditModal.value = false;
            editingGuest.value = null;
        },
    });
};

// Delete Single Guest
const deleteGuest = (guest) => {
    if (confirm(`Remove ${guest.name} from this event?`)) {
        router.delete(route('events.guests.destroy', { event: props.event.id, guest: guest.id }));
    }
};

// Bulk Actions
const bulkDelete = () => {
    if (selectedIds.value.length === 0) return;
    if (confirm(`Delete ${selectedIds.value.length} selected guests?`)) {
        router.post(route('events.guests.bulk-delete', { event: props.event.id }), {
            ids: selectedIds.value,
        }, {
            onSuccess: () => {
                selectedIds.value = [];
                selectAll.value = false;
            },
        });
    }
};

const bulkSetChannel = (ch) => {
    if (selectedIds.value.length === 0) return;
    router.post(route('events.guests.bulk-channel', { event: props.event.id }), {
        channel: ch,
        ids: selectedIds.value,
    }, {
        onSuccess: () => {
            selectedIds.value = [];
            selectAll.value = false;
        },
    });
};

// Co-hosts Management
const coHostForm = useForm({
    email: '',
    role: 'editor',
});

const submitInviteCoHost = () => {
    coHostForm.post(route('events.members.store', { event: props.event.id }), {
        onSuccess: () => {
            coHostForm.reset();
        },
    });
};

const removeCoHost = (user) => {
    if (confirm(`Remove co-host ${user.name}?`)) {
        router.delete(route('events.members.destroy', { event: props.event.id, user: user.id }));
    }
};

// Copy Token
const copyToken = (tok) => {
    navigator.clipboard?.writeText(tok);
    alert('Token copied to clipboard: ' + tok);
};
</script>

<template>
    <Head :title="`Guest List - ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('dashboard')" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ← Dashboard
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">{{ event.category?.name_en }}</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ event.title }} • Guest List
                    </h2>
                </div>

                <!-- Right Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('events.design', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl border border-amber-500/50 text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 text-xs font-semibold transition"
                    >
                        🎨 Card Editor
                    </Link>

                    <button
                        type="button"
                        @click="showCoHostModal = true"
                        class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>🤝 Co-Hosts</span>
                        <span class="text-[10px] bg-gray-300 dark:bg-gray-700 px-1.5 py-0.2 rounded-full">{{ members.length }}</span>
                    </button>

                    <a
                        :href="route('events.guests.export', { event: event.id })"
                        class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1"
                        download
                    >
                        <span>⬇ Export CSV</span>
                    </a>

                    <button
                        type="button"
                        @click="showBulkPasteModal = true"
                        class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition"
                    >
                        📋 Bulk Paste
                    </button>

                    <button
                        type="button"
                        @click="showImportModal = true"
                        class="px-3 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>📁 CSV Import</span>
                        <span v-if="!features.csv_import" class="text-[10px] text-amber-500">🔒</span>
                    </button>

                    <button
                        type="button"
                        @click="showAddModal = true"
                        class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold transition shadow-sm"
                    >
                        + Add Guest
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- KPI Stat Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total Guests</div>
                        <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                            {{ stats.total || 0 }}
                            <span v-if="limits.max_guests_per_event" class="text-xs font-normal text-gray-400">
                                / {{ limits.max_guests_per_event }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Attending (Yes)</div>
                        <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
                            {{ stats.attending || 0 }}
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-rose-600 dark:text-rose-400 font-medium">Declined (No)</div>
                        <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                            {{ stats.declined || 0 }}
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-amber-600 dark:text-amber-400 font-medium">Tentative (Maybe)</div>
                        <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                            {{ stats.maybe || 0 }}
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending Reply</div>
                        <div class="text-xl font-bold text-gray-700 dark:text-gray-300 mt-1">
                            {{ stats.pending || 0 }}
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4">
                        <div class="text-xs text-blue-600 dark:text-blue-400 font-medium">Checked In</div>
                        <div class="text-xl font-bold text-blue-600 dark:text-blue-400 mt-1">
                            {{ stats.checked_in || 0 }}
                        </div>
                    </div>
                </div>

                <!-- Search and Filters Bar -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4 flex flex-col md:flex-row items-center justify-between gap-3 text-xs">
                    <div class="w-full md:w-72">
                        <input
                            type="text"
                            v-model="search"
                            @keyup.enter="applyFilters"
                            placeholder="Search name, phone, email..."
                            class="w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-xs focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto">
                        <!-- RSVP Filter -->
                        <select
                            v-model="rsvpStatus"
                            @change="applyFilters"
                            class="rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-xs"
                        >
                            <option value="">All RSVP Status</option>
                            <option value="yes">Attending (Yes)</option>
                            <option value="no">Declined (No)</option>
                            <option value="maybe">Maybe</option>
                            <option value="pending">Pending</option>
                        </select>

                        <!-- Check-in Filter -->
                        <select
                            v-model="checkedIn"
                            @change="applyFilters"
                            class="rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-xs"
                        >
                            <option value="">All Check-in</option>
                            <option value="true">Checked In</option>
                            <option value="false">Not Checked In</option>
                        </select>

                        <!-- Channel Filter -->
                        <select
                            v-model="channelFilter"
                            @change="applyFilters"
                            class="rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 text-xs"
                        >
                            <option value="">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                            <option value="both">Both</option>
                        </select>

                        <button
                            type="button"
                            @click="applyFilters"
                            class="px-3 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 font-semibold text-gray-700 dark:text-gray-200"
                        >
                            Apply
                        </button>
                    </div>
                </div>

                <!-- Bulk Selection Action Bar -->
                <div
                    v-if="selectedIds.length > 0"
                    class="bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-2xl p-3 px-4 flex items-center justify-between text-xs text-amber-900 dark:text-amber-200"
                >
                    <span class="font-semibold">{{ selectedIds.length }} guest(s) selected</span>
                    <div class="flex items-center space-x-2">
                        <button
                            type="button"
                            @click="bulkSetChannel('sms')"
                            class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 font-medium"
                        >
                            Set Channel: SMS
                        </button>
                        <button
                            type="button"
                            @click="bulkSetChannel('email')"
                            class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-800 border border-amber-300 dark:border-amber-700 font-medium"
                        >
                            Set Channel: Email
                        </button>
                        <button
                            type="button"
                            @click="bulkDelete"
                            class="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-medium"
                        >
                            Delete Selected
                        </button>
                    </div>
                </div>

                <!-- Guest Table -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50/75 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="p-4 w-10">
                                        <input
                                            type="checkbox"
                                            v-model="selectAll"
                                            @change="toggleSelectAll"
                                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                        />
                                    </th>
                                    <th class="py-4 px-3">Guest Name</th>
                                    <th class="py-4 px-3">Contact</th>
                                    <th class="py-4 px-3">Channel</th>
                                    <th class="py-4 px-3 text-center">Party</th>
                                    <th class="py-4 px-3">RSVP Status</th>
                                    <th class="py-4 px-3">Check-in</th>
                                    <th class="py-4 px-3">Token</th>
                                    <th class="py-4 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr
                                    v-for="guest in guests"
                                    :key="guest.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40 transition"
                                >
                                    <td class="p-4">
                                        <input
                                            type="checkbox"
                                            :value="guest.id"
                                            v-model="selectedIds"
                                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                        />
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-gray-900 dark:text-white">
                                        {{ guest.name }}
                                    </td>
                                    <td class="py-3 px-3 text-gray-600 dark:text-gray-300">
                                        <div v-if="guest.phone" class="font-mono text-[11px]">{{ guest.phone }}</div>
                                        <div v-if="guest.email" class="text-[11px] text-gray-400">{{ guest.email }}</div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                            {{ guest.preferred_channel }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center font-semibold text-gray-700 dark:text-gray-300">
                                        {{ guest.max_party_size }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <span
                                            class="text-[11px] font-semibold px-2 py-0.5 rounded-full inline-block"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': guest.rsvp_status === 'yes',
                                                'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-300': guest.rsvp_status === 'no',
                                                'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300': guest.rsvp_status === 'maybe',
                                                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300': guest.rsvp_status === 'pending',
                                            }"
                                        >
                                            {{ guest.rsvp_status === 'yes' ? 'Attending' : guest.rsvp_status === 'no' ? 'Declined' : guest.rsvp_status === 'maybe' ? 'Maybe' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span v-if="guest.checked_in_at" class="text-blue-600 dark:text-blue-400 font-semibold text-[11px] flex items-center space-x-1">
                                            <span>✓</span>
                                            <span>{{ new Date(guest.checked_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
                                        </span>
                                        <span v-else class="text-gray-400 text-[11px]">Not yet</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <button
                                            type="button"
                                            @click="copyToken(guest.token)"
                                            class="font-mono text-[10px] text-gray-400 hover:text-amber-600 max-w-[80px] truncate block"
                                            title="Click to copy 32-char token"
                                        >
                                            {{ guest.token.slice(0, 8) }}...
                                        </button>
                                    </td>
                                    <td class="py-3 px-4 text-right space-x-2">
                                        <button
                                            type="button"
                                            @click="openEditModal(guest)"
                                            class="text-amber-600 hover:underline font-semibold"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteGuest(guest)"
                                            class="text-rose-500 hover:text-rose-700"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="guests.length === 0">
                                    <td colspan="9" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                        No guests found matching your criteria. Click "+ Add Guest" or "Bulk Paste" to invite attendees!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Guest Modal -->
        <Modal :show="showAddModal" @close="showAddModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Add Guest</h3>
                <p class="text-xs text-gray-500 mt-1">Provide contact info to send an invitation</p>

                <form @submit.prevent="submitAddGuest" class="mt-4 space-y-4 text-xs">
                    <div>
                        <InputLabel value="Guest Name *" />
                        <TextInput v-model="addForm.name" class="w-full mt-1" placeholder="e.g. Almaz Bekele or አበበ በቀለ" required />
                        <InputError :message="addForm.errors.name" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Phone Number (Ethiopian)" />
                            <TextInput v-model="addForm.phone" class="w-full mt-1" placeholder="0911223344" />
                            <InputError :message="addForm.errors.phone" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Email Address" />
                            <TextInput v-model="addForm.email" type="email" class="w-full mt-1" placeholder="almaz@example.com" />
                            <InputError :message="addForm.errors.email" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Preferred Channel" />
                            <select v-model="addForm.preferred_channel" class="w-full mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs">
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                                <option value="both">Both (SMS + Email)</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Party Size / Seats" />
                            <TextInput v-model.number="addForm.max_party_size" type="number" min="1" max="20" class="w-full mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-2 pt-2">
                        <SecondaryButton @click="showAddModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="addForm.processing">Add Guest</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Bulk Paste Modal -->
        <Modal :show="showBulkPasteModal" @close="showBulkPasteModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Bulk Paste Guests</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Paste one guest per line in the format: <code>Name, phone/email, party_size</code>
                </p>

                <form @submit.prevent="submitBulkPaste" class="mt-4 space-y-4 text-xs">
                    <div>
                        <textarea
                            v-model="pasteForm.text"
                            rows="7"
                            class="w-full font-mono text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800"
                            placeholder="Almaz Bekele, 0911223344, 2&#10;Chaltu Kebede, chaltu@example.com, 1&#10;አበበ በቀለ, +251912345678, 1"
                            required
                        ></textarea>
                        <InputError :message="pasteForm.errors.text" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="If a guest already exists in this event:" />
                        <div class="flex items-center space-x-4 mt-1.5">
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" v-model="pasteForm.on_duplicate" value="skip" class="text-amber-600" />
                                <span>Skip duplicate</span>
                            </label>
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" v-model="pasteForm.on_duplicate" value="merge" class="text-amber-600" />
                                <span>Update existing record</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-2 pt-2">
                        <SecondaryButton @click="showBulkPasteModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="pasteForm.processing">Import Pasted Guests</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- CSV Import Modal -->
        <Modal :show="showImportModal" @close="showImportModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Import CSV / Excel File</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Upload your guest list file with headers (Name, Phone, Email, Party Size).
                </p>

                <!-- Plan Gate Notice if not entitled -->
                <div v-if="!features.csv_import" class="mt-4 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-900 dark:text-amber-200">
                    <strong class="font-bold">Feature Locked:</strong> CSV/Excel import is available on Bronze, Silver, and Gold tiers.
                    <div class="mt-2">
                        <Link :href="route('plan.index')" class="text-amber-600 underline font-semibold">
                            Upgrade your plan →
                        </Link>
                    </div>
                </div>

                <form v-else @submit.prevent="submitCsvImport" class="mt-4 space-y-4 text-xs">
                    <div>
                        <input
                            type="file"
                            accept=".csv, text/csv, application/vnd.ms-excel, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            @change="importForm.file = $event.target.files[0]"
                            class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100"
                            required
                        />
                        <InputError :message="importForm.errors.file" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="If a guest already exists:" />
                        <div class="flex items-center space-x-4 mt-1.5">
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" v-model="importForm.on_duplicate" value="skip" class="text-amber-600" />
                                <span>Skip duplicate</span>
                            </label>
                            <label class="flex items-center space-x-1.5 cursor-pointer">
                                <input type="radio" v-model="importForm.on_duplicate" value="merge" class="text-amber-600" />
                                <span>Merge / Update</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-2 pt-2">
                        <SecondaryButton @click="showImportModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="importForm.processing">Upload and Import</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Guest Modal -->
        <Modal :show="showEditModal" @close="showEditModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Edit Guest</h3>

                <form @submit.prevent="submitEditGuest" class="mt-4 space-y-4 text-xs">
                    <div>
                        <InputLabel value="Guest Name" />
                        <TextInput v-model="editForm.name" class="w-full mt-1" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Phone Number" />
                            <TextInput v-model="editForm.phone" class="w-full mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Email Address" />
                            <TextInput v-model="editForm.email" type="email" class="w-full mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <InputLabel value="Channel" />
                            <select v-model="editForm.preferred_channel" class="w-full mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs">
                                <option value="sms">SMS</option>
                                <option value="email">Email</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Party Size" />
                            <TextInput v-model.number="editForm.max_party_size" type="number" min="1" max="20" class="w-full mt-1" />
                        </div>
                        <div>
                            <InputLabel value="RSVP Status" />
                            <select v-model="editForm.rsvp_status" class="w-full mt-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs">
                                <option value="pending">Pending</option>
                                <option value="yes">Attending</option>
                                <option value="no">Declined</option>
                                <option value="maybe">Maybe</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-2 pt-2">
                        <SecondaryButton @click="showEditModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="editForm.processing">Save Changes</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Co-Hosts Modal -->
        <Modal :show="showCoHostModal" @close="showCoHostModal = false">
            <div class="p-6 bg-white dark:bg-gray-900 text-gray-900 dark:text-white rounded-3xl">
                <h3 class="text-lg font-bold">Event Co-Hosts</h3>
                <p class="text-xs text-gray-500 mt-1">
                    Invite collaborators to edit the card design, manage guests, or view responses.
                </p>

                <!-- Plan Gate -->
                <div v-if="!features.co_hosts" class="mt-4 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-900 dark:text-amber-200">
                    <strong class="font-bold">Feature Locked:</strong> Co-hosts are available on Bronze, Silver, and Gold tiers.
                    <div class="mt-2">
                        <Link :href="route('plan.index')" class="text-amber-600 underline font-semibold">
                            Upgrade your plan →
                        </Link>
                    </div>
                </div>

                <div v-else class="mt-4 space-y-4">
                    <form @submit.prevent="submitInviteCoHost" class="flex items-center gap-2 text-xs">
                        <input
                            type="email"
                            v-model="coHostForm.email"
                            placeholder="Collaborator's email address..."
                            class="flex-1 rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs"
                            required
                        />
                        <select
                            v-model="coHostForm.role"
                            class="rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs"
                        >
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                        <PrimaryButton :disabled="coHostForm.processing" class="py-2 text-xs">
                            Invite
                        </PrimaryButton>
                    </form>
                    <InputError :message="coHostForm.errors.email" />

                    <!-- Existing Members List -->
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 pt-2">
                        <div
                            v-for="member in members"
                            :key="member.id"
                            class="py-2.5 flex items-center justify-between text-xs"
                        >
                            <div>
                                <div class="font-semibold">{{ member.user?.name }}</div>
                                <div class="text-gray-400 text-[11px]">{{ member.user?.email }} • <span class="uppercase text-amber-600 font-bold">{{ member.role }}</span></div>
                            </div>
                            <button
                                type="button"
                                @click="removeCoHost(member.user)"
                                class="text-rose-500 hover:text-rose-700 text-xs"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end pt-2">
                    <SecondaryButton @click="showCoHostModal = false">Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
