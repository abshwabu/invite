<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    deliveries: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, sent: 0, delivered: 0, failed: 0, queued: 0 }),
    },
    campaigns: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const statusFilter = ref(props.filters.status || '');
const channelFilter = ref(props.filters.channel || '');
const campaignFilter = ref(props.filters.campaign_id || '');
const searchQuery = ref(props.filters.search || '');

const applyFilters = () => {
    router.get(
        route('events.deliveries', { event: props.event.id }),
        {
            status: statusFilter.value || undefined,
            channel: channelFilter.value || undefined,
            campaign_id: campaignFilter.value || undefined,
            search: searchQuery.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resendSingle = (delivery) => {
    if (confirm(`Resend ${delivery.channel.toUpperCase()} message to ${delivery.guest?.name || 'guest'}?`)) {
        router.post(route('events.deliveries.resend', { event: props.event.id, delivery: delivery.id }));
    }
};

const resendAllFailed = (campaignId) => {
    if (!campaignId && props.campaigns.length > 0) {
        campaignId = props.campaigns[0].id;
    }
    if (!campaignId) return;

    if (confirm('Resend all failed deliveries in this campaign? This will reserve required cards and re-queue messages.')) {
        router.post(route('events.campaigns.resend-failed', { event: props.event.id, campaign: campaignId }));
    }
};

const resendRsvpPending = (campaignId) => {
    if (!campaignId && props.campaigns.length > 0) {
        campaignId = props.campaigns[0].id;
    }
    if (!campaignId) return;

    if (confirm('Resend invitations to guests who have not RSVP’d yet?')) {
        router.post(route('events.campaigns.resend-pending', { event: props.event.id, campaign: campaignId }));
    }
};
</script>

<template>
    <Head :title="`Delivery Logs - ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('events.guests.index', { event: event.id })" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ← Guest List
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ event.title }}</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 mt-1">
                        Delivery Logs & Queue Status
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="stats.failed > 0 && campaigns.length > 0"
                        type="button"
                        @click="resendAllFailed(campaignFilter || campaigns[0]?.id)"
                        class="px-3.5 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>🔄 Resend All Failed ({{ stats.failed }})</span>
                    </button>

                    <button
                        v-if="campaigns.length > 0"
                        type="button"
                        @click="resendRsvpPending(campaignFilter || campaigns[0]?.id)"
                        class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>⏰ Remind RSVP Pending</span>
                    </button>

                    <Link
                        :href="route('events.campaigns.create', { event: event.id })"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition flex items-center space-x-1.5"
                    >
                        <span>📨 Send New Campaign</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Summary KPI Cards -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-gray-400 font-medium">Total Messages</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-indigo-500 font-medium">Sent</div>
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ stats.sent }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-emerald-500 font-medium">Delivered</div>
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.delivered }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-amber-500 font-medium">Queued / Sending</div>
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ stats.queued }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-red-500 font-medium">Failed / Bounced</div>
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.failed }}</div>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <!-- Campaign Filter -->
                        <select
                            v-model="campaignFilter"
                            @change="applyFilters"
                            class="text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Campaigns</option>
                            <option v-for="c in campaigns" :key="c.id" :value="c.id">
                                Campaign #{{ c.id }} ({{ c.channel.toUpperCase() }}) - {{ new Date(c.created_at).toLocaleDateString() }}
                            </option>
                        </select>

                        <!-- Status Filter -->
                        <select
                            v-model="statusFilter"
                            @change="applyFilters"
                            class="text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Statuses</option>
                            <option value="queued">Queued</option>
                            <option value="sending">Sending</option>
                            <option value="sent">Sent</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="bounced">Bounced</option>
                        </select>

                        <!-- Channel Filter -->
                        <select
                            v-model="channelFilter"
                            @change="applyFilters"
                            class="text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Channels</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="w-full md:w-72">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="applyFilters"
                            placeholder="Search recipient or name..."
                            class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        />
                    </div>
                </div>

                <!-- Deliveries Table -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                    <th class="py-3.5 px-4">Guest</th>
                                    <th class="py-3.5 px-4">Channel</th>
                                    <th class="py-3.5 px-4">Recipient</th>
                                    <th class="py-3.5 px-4">Status</th>
                                    <th class="py-3.5 px-4">Quota</th>
                                    <th class="py-3.5 px-4">Sent At / Details</th>
                                    <th class="py-3.5 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-xs text-gray-800 dark:text-gray-200">
                                <tr v-if="deliveries.data.length === 0">
                                    <td colspan="7" class="py-12 text-center text-gray-400">
                                        No deliveries found matching the selected filters.
                                    </td>
                                </tr>
                                <tr v-for="del in deliveries.data" :key="del.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="py-3 px-4 font-semibold text-gray-900 dark:text-white">
                                        {{ del.guest?.name || 'Unknown Guest' }}
                                    </td>

                                    <td class="py-3 px-4">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                                del.channel === 'sms'
                                                    ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
                                                    : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'
                                            ]"
                                        >
                                            {{ del.channel.toUpperCase() }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 font-mono text-[11px] text-gray-600 dark:text-gray-300">
                                        {{ del.channel === 'sms' ? (del.guest?.phone || '—') : (del.guest?.email || '—') }}
                                    </td>

                                    <td class="py-3 px-4">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold capitalize',
                                                del.status === 'delivered' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' : '',
                                                del.status === 'sent' ? 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' : '',
                                                del.status === 'queued' || del.status === 'sending' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : '',
                                                del.status === 'failed' || del.status === 'bounced' ? 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300' : '',
                                            ]"
                                        >
                                            {{ del.status }}
                                        </span>
                                    </td>

                                    <td class="py-3 px-4 font-medium">
                                        <span :class="del.quota_charged > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-400 line-through'">
                                            {{ del.quota_charged }} card(s)
                                        </span>
                                    </td>

                                    <td class="py-3 px-4">
                                        <div v-if="del.sent_at" class="text-gray-600 dark:text-gray-300">
                                            {{ new Date(del.sent_at).toLocaleString() }}
                                        </div>
                                        <div v-if="del.error" class="text-red-500 font-semibold text-[11px] mt-0.5 max-w-xs truncate" :title="del.error">
                                            ⚠️ {{ del.error }}
                                        </div>
                                    </td>

                                    <td class="py-3 px-4 text-right">
                                        <button
                                            type="button"
                                            @click="resendSingle(del)"
                                            class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-[11px] font-semibold transition"
                                        >
                                            Resend
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="deliveries.links && deliveries.links.length > 3" class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs">
                        <div class="text-gray-500">
                            Showing {{ deliveries.from || 0 }} to {{ deliveries.to || 0 }} of {{ deliveries.total }} deliveries
                        </div>
                        <div class="flex space-x-1">
                            <Component
                                :is="link.url ? Link : 'span'"
                                v-for="(link, i) in deliveries.links"
                                :key="i"
                                :href="link.url || '#'"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-1 rounded-lg transition text-xs',
                                    link.active ? 'bg-indigo-600 text-white font-bold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
                                    !link.url ? 'opacity-40 cursor-not-allowed' : ''
                                ]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
