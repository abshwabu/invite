<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    scanners: {
        type: Array,
        default: () => [],
    },
    recentLogs: {
        type: Array,
        default: () => [],
    },
    conflictLogs: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    entitlements: {
        type: Object,
        default: () => ({}),
    },
});

// Modals
const showCreateModal = ref(false);
const showManualModal = ref(false);
const manualActionType = ref('checkin'); // 'checkin' | 'undo'
const targetGuest = ref(null);
const manualReason = ref('');
const manualReasonError = ref('');
const copiedToken = ref(null);

// Create scanner form
const createForm = useForm({
    name: '',
    pin: '',
});

const submitCreateScanner = () => {
    createForm.post(route('events.scanners.store', { event: props.event.id }), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const revokeScanner = (scanner) => {
    if (confirm(`Revoke scanner "${scanner.name}"? They will be immediately blocked from scanning.`)) {
        router.post(route('events.scanners.revoke', { event: props.event.id, scanner: scanner.id }));
    }
};

const copyUrl = (url, token) => {
    navigator.clipboard?.writeText(url);
    copiedToken.value = token;
    setTimeout(() => {
        if (copiedToken.value === token) copiedToken.value = null;
    }, 2500);
};

// Guest search for manual override
const guestSearchQuery = ref('');
const isSearchingGuests = ref(false);
const searchResults = ref([]);

const searchGuests = async () => {
    if (!guestSearchQuery.value || guestSearchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    isSearchingGuests.value = true;
    try {
        const res = await axios.get(route('api.scan.guests'), {
            params: {
                query: guestSearchQuery.value,
                scanner_token: props.scanners[0]?.token || '',
            },
        });
        searchResults.value = res.data.guests || [];
    } catch (e) {
        // Fallback search
        searchResults.value = [];
    } finally {
        isSearchingGuests.value = false;
    }
};

const openManualModal = (guest, action) => {
    targetGuest.value = guest;
    manualActionType.value = action;
    manualReason.value = '';
    manualReasonError.value = '';
    showManualModal.value = true;
};

const submitManualAction = () => {
    if (!manualReason.value.trim()) {
        manualReasonError.value = 'Please provide an audit reason.';
        return;
    }

    const routeName = manualActionType.value === 'checkin'
        ? 'events.guests.manual-checkin'
        : 'events.guests.undo-checkin';

    router.post(route(routeName, { event: props.event.id, guest: targetGuest.value.id }), {
        reason: manualReason.value.trim(),
    }, {
        onSuccess: () => {
            showManualModal.value = false;
            targetGuest.value = null;
            searchGuests();
        },
    });
};

const checkinPercentage = computed(() => {
    if (!props.stats.total_guests || props.stats.total_guests === 0) return 0;
    return Math.round((props.stats.checked_in / props.stats.total_guests) * 100);
});
</script>

<template>
    <Head :title="`QR Scanners - ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('dashboard')" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ← Dashboard
                        </Link>
                        <span class="text-gray-300">/</span>
                        <Link :href="route('events.guests.index', { event: event.id })" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            {{ event.title }}
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-semibold text-purple-600 dark:text-purple-400">QR Check-in & Scanners</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ event.title }} • Scanners & Door Check-In
                    </h2>
                </div>

                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('events.guests.index', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition"
                    >
                        👥 Guest List
                    </Link>

                    <button
                        v-if="entitlements.qr_checkin && !entitlements.limit_reached"
                        type="button"
                        @click="showCreateModal = true"
                        class="px-4 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold shadow-sm transition flex items-center space-x-1.5"
                    >
                        <span>＋ Add Door Scanner</span>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Entitlement / Gating Banner -->
            <div v-if="!entitlements.qr_checkin" class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">🔒</span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-800 dark:text-amber-200">QR Check-in is Locked</h4>
                        <p class="text-xs text-amber-700 dark:text-amber-300">Your current plan does not include single-use QR check-in scanners. Upgrade to Bronze or higher to generate door scanner links.</p>
                    </div>
                </div>
                <Link
                    :href="route('plan.index')"
                    class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl shadow-sm whitespace-nowrap"
                >
                    Upgrade Plan
                </Link>
            </div>

            <!-- Limit Reached Banner -->
            <div v-else-if="entitlements.limit_reached" class="p-4 bg-purple-500/10 border border-purple-500/30 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">ℹ️</span>
                    <div>
                        <h4 class="text-sm font-bold text-purple-900 dark:text-purple-200">Scanner Limit Reached</h4>
                        <p class="text-xs text-purple-700 dark:text-purple-300">You are using {{ stats.active_scanners }} of {{ entitlements.max_scanners }} scanner(s) allowed on your plan. Upgrade to add more doors.</p>
                    </div>
                </div>
                <Link
                    :href="route('plan.index')"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded-xl shadow-sm whitespace-nowrap"
                >
                    Upgrade Plan
                </Link>
            </div>

            <!-- Offline Conflict Alert -->
            <div v-if="conflictLogs.length > 0" class="p-4 bg-yellow-500/10 border border-yellow-500/40 rounded-2xl">
                <div class="flex items-start space-x-3">
                    <span class="text-xl">⚠️</span>
                    <div>
                        <h4 class="text-sm font-bold text-yellow-800 dark:text-yellow-200">Offline Scan Conflict Detected</h4>
                        <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-0.5">
                            One or more guests were scanned while devices were offline. Per policy, the system preserved the earliest check-in timestamp and flagged the conflict for your review.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Total Guests</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ stats.total_guests }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">{{ event.is_invite_only ? 'Invite-only QR pass' : 'Open RSVP' }}</div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Checked-In Guests</div>
                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.checked_in }}</div>
                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-0.5">{{ checkinPercentage }}% arrived</div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Active Scanners</div>
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                        {{ stats.active_scanners }}
                        <span class="text-xs font-normal text-gray-400">/ {{ entitlements.max_scanners ?? '∞' }}</span>
                    </div>
                    <div class="text-[11px] text-gray-400 mt-0.5">Doors & entrance gates</div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                    <div class="text-xs text-gray-500 dark:text-gray-400">Event Status</div>
                    <div class="text-lg font-bold text-gray-800 dark:text-gray-200 mt-1 capitalize">{{ event.status }}</div>
                    <div class="text-[11px]" :class="['closed', 'suspended', 'cancelled'].includes(event.status) ? 'text-red-500 font-semibold' : 'text-emerald-500 font-semibold'">
                        {{ ['closed', 'suspended', 'cancelled'].includes(event.status) ? 'Scanning disabled' : 'Scanning active' }}
                    </div>
                </div>
            </div>

            <!-- Scanners List Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Door Scanners</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Scanners open their unique link on their mobile device, enter their 4-6 digit PIN, and start scanning.</p>
                    </div>
                    <button
                        v-if="entitlements.qr_checkin && !entitlements.limit_reached"
                        type="button"
                        @click="showCreateModal = true"
                        class="px-3 py-1.5 bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-300 hover:bg-purple-100 text-xs font-semibold rounded-xl transition"
                    >
                        ＋ Create Scanner
                    </button>
                </div>

                <div v-if="scanners.length === 0" class="p-8 text-center text-gray-400 text-xs">
                    No door scanners created yet. Click "+ Add Door Scanner" above to give door staff access.
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                    <div v-for="sc in scanners" :key="sc.id" class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-gray-50/50 dark:hover:bg-gray-750 transition">
                        <div class="space-y-1">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ sc.name }}</span>
                                <span v-if="sc.is_revoked" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                                    Revoked
                                </span>
                                <span v-else-if="sc.is_locked" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                    Locked (PIN attempts exceeded)
                                </span>
                                <span v-else class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    Active
                                </span>
                            </div>
                            <div class="flex items-center space-x-2 text-xs text-gray-400">
                                <span>Created {{ new Date(sc.created_at).toLocaleDateString() }}</span>
                                <span>•</span>
                                <span>{{ sc.last_used_at ? `Last used ${new Date(sc.last_used_at).toLocaleTimeString()}` : 'Not used yet' }}</span>
                                <span v-if="sc.failed_attempts > 0" class="text-amber-600 font-semibold">• {{ sc.failed_attempts }} failed PIN attempt(s)</span>
                            </div>
                            <div class="flex items-center space-x-2 pt-1">
                                <code class="text-[11px] bg-gray-100 dark:bg-gray-900 px-2.5 py-1 rounded-lg text-gray-700 dark:text-gray-300 font-mono select-all truncate max-w-sm">
                                    {{ sc.url }}
                                </code>
                                <button
                                    type="button"
                                    @click="copyUrl(sc.url, sc.token)"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-300 transition"
                                >
                                    {{ copiedToken === sc.token ? '✓ Copied' : 'Copy Link' }}
                                </button>
                                <a
                                    :href="sc.url"
                                    target="_blank"
                                    class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-purple-50 dark:bg-purple-950 hover:bg-purple-100 text-purple-600 dark:text-purple-300 transition"
                                >
                                    Open App ↗
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button
                                v-if="!sc.is_revoked"
                                type="button"
                                @click="revokeScanner(sc)"
                                class="px-3 py-1.5 rounded-xl border border-red-200 dark:border-red-900 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950 text-xs font-semibold transition"
                            >
                                Revoke Access
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manual Check-in Override Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Host Manual Check-In Override</h3>
                    <p class="text-xs text-gray-400 mt-0.5">If a guest lost their phone or cannot scan, search below to manually check them in or undo a check-in. Every manual action requires an audit reason.</p>
                </div>

                <div class="flex items-center space-x-3">
                    <input
                        type="text"
                        v-model="guestSearchQuery"
                        @input="searchGuests"
                        placeholder="Search guest by name, phone, or email..."
                        class="flex-1 rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-xs focus:ring-purple-500"
                    />
                    <button
                        type="button"
                        @click="searchGuests"
                        class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-xs font-semibold rounded-xl text-gray-700 dark:text-gray-200"
                    >
                        Search
                    </button>
                </div>

                <div v-if="searchResults.length > 0" class="border border-gray-100 dark:border-gray-700 rounded-xl overflow-hidden divide-y divide-gray-100 dark:divide-gray-700">
                    <div v-for="g in searchResults" :key="g.id" class="p-3 flex items-center justify-between hover:bg-gray-50/50 dark:hover:bg-gray-750 transition text-xs">
                        <div>
                            <div class="font-bold text-gray-900 dark:text-gray-100">{{ g.name }}</div>
                            <div class="text-[11px] text-gray-400">
                                <span>Party size: {{ g.party_size }}</span>
                                <span v-if="g.phone">• {{ g.phone }}</span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span v-if="g.is_checked_in" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                Checked In ({{ new Date(g.checked_in_at).toLocaleTimeString() }})
                            </span>
                            <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                Not Checked In
                            </span>

                            <button
                                v-if="!g.is_checked_in"
                                type="button"
                                @click="openManualModal(g, 'checkin')"
                                class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-semibold text-[11px]"
                            >
                                Check In
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="openManualModal(g, 'undo')"
                                class="px-3 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-semibold text-[11px]"
                            >
                                Undo Check In
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Check-In Logs -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">Live Check-In Activity Log</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Real-time audit log of scans, door admissions, already-used attempts, and manual overrides.</p>
                    </div>
                </div>

                <div v-if="recentLogs.length === 0" class="p-6 text-center text-xs text-gray-400">
                    No scans recorded yet.
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-700 max-h-96 overflow-y-auto">
                    <div v-for="log in recentLogs" :key="log.id" class="p-3.5 flex items-center justify-between text-xs hover:bg-gray-50/50 dark:hover:bg-gray-750 transition">
                        <div class="space-y-0.5">
                            <div class="flex items-center space-x-2">
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ log.guest_name }}</span>
                                <span
                                    class="px-2 py-0.5 text-[10px] font-bold rounded-full capitalize"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': log.result === 'success',
                                        'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300': log.result === 'already_used',
                                        'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300': log.result === 'wrong_event' || log.result === 'invalid',
                                        'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300': log.result === 'manual_override' || log.result === 'undo_override',
                                        'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300': log.result === 'conflict_earliest_kept',
                                    }"
                                >
                                    {{ log.result.replace('_', ' ') }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-400">
                                <span>via {{ log.scanner_name }}</span>
                                <span>•</span>
                                <span>{{ new Date(log.scanned_at).toLocaleTimeString() }}</span>
                            </div>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] font-mono text-gray-400 truncate block max-w-xs">{{ log.raw_payload ? log.raw_payload.slice(0, 35) + '...' : '' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Scanner Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Create Door Scanner</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Assign a scanner name (e.g. "Main Entrance Door A") and a 4-6 digit numeric PIN. Scanners use this PIN to log in on their mobile phones.
                </p>

                <form @submit.prevent="submitCreateScanner" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="sc_name" value="Scanner Name / Gate Label" />
                        <TextInput
                            id="sc_name"
                            v-model="createForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="e.g. Door 1, VIP Entrance"
                            required
                        />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="sc_pin" value="Numeric Security PIN (4-6 digits)" />
                        <TextInput
                            id="sc_pin"
                            v-model="createForm.pin"
                            type="password"
                            inputmode="numeric"
                            pattern="[0-9]{4,6}"
                            maxlength="6"
                            class="mt-1 block w-full font-mono text-center tracking-widest text-lg"
                            placeholder="1234"
                            required
                        />
                        <InputError :message="createForm.errors.pin" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-3">
                        <SecondaryButton @click="showCreateModal = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="createForm.processing">Create Scanner</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Manual Override Reason Modal -->
        <Modal :show="showManualModal" @close="showManualModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    {{ manualActionType === 'checkin' ? 'Manual Check-In Override' : 'Undo Check-In Override' }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Guest: <span class="font-bold text-gray-800 dark:text-gray-200">{{ targetGuest?.name }}</span>
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <InputLabel for="reason" value="Audit Reason (Required)" />
                        <textarea
                            id="reason"
                            v-model="manualReason"
                            rows="3"
                            class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-xs focus:ring-purple-500 mt-1"
                            placeholder="e.g. Guest forgot phone, paper pass verified by host"
                            required
                        ></textarea>
                        <div v-if="manualReasonError" class="text-red-500 text-xs mt-1">{{ manualReasonError }}</div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-2">
                        <SecondaryButton @click="showManualModal = false">Cancel</SecondaryButton>
                        <PrimaryButton
                            v-if="manualActionType === 'checkin'"
                            @click="submitManualAction"
                            class="bg-emerald-600 hover:bg-emerald-700"
                        >
                            Confirm Check In
                        </PrimaryButton>
                        <DangerButton
                            v-else
                            @click="submitManualAction"
                        >
                            Confirm Undo
                        </DangerButton>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
