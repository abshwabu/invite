<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';
import axios from 'axios';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    totals: {
        type: Object,
        required: true,
    },
    recentArrivals: {
        type: Array,
        default: () => [],
    },
    charts: {
        type: Object,
        required: true,
    },
    reminders: {
        type: Array,
        default: () => [],
    },
    entitlements: {
        type: Object,
        default: () => ({}),
    },
});

// Live Check-in State
const isPolling = ref(true);
const checkedInCount = ref(props.totals.checked_in);
const totalGuests = ref(props.totals.guests);
const checkinPercentage = ref(props.totals.checkin_percentage);
const expectedHeadcount = ref(props.totals.expected_headcount);
const arrivalsList = ref([...props.recentArrivals]);
const lastPolledAt = ref(null);
let pollTimer = null;

// Chart canvas refs
const sendsChartCanvas = ref(null);
const rsvpChartCanvas = ref(null);
const clicksChartCanvas = ref(null);

let sendsChartInstance = null;
let rsvpChartInstance = null;
let clicksChartInstance = null;

const initCharts = () => {
    // 1. Sends Timeline Chart
    if (sendsChartCanvas.value) {
        if (sendsChartInstance) sendsChartInstance.destroy();
        sendsChartInstance = new Chart(sendsChartCanvas.value, {
            type: 'bar',
            data: {
                labels: props.charts.sendsTimeline.labels,
                datasets: [
                    {
                        label: 'SMS',
                        data: props.charts.sendsTimeline.sms,
                        backgroundColor: '#3B82F6',
                        borderRadius: 4,
                    },
                    {
                        label: 'Email',
                        data: props.charts.sendsTimeline.email,
                        backgroundColor: '#10B981',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    // 2. RSVP Distribution Donut Chart
    if (rsvpChartCanvas.value) {
        if (rsvpChartInstance) rsvpChartInstance.destroy();
        rsvpChartInstance = new Chart(rsvpChartCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.charts.rsvpBreakdown.labels,
                datasets: [
                    {
                        data: props.charts.rsvpBreakdown.data,
                        backgroundColor: props.charts.rsvpBreakdown.colors,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });
    }

    // 3. Link Clicks Chart
    if (clicksChartCanvas.value && props.charts.linkClicks?.length > 0) {
        if (clicksChartInstance) clicksChartInstance.destroy();
        clicksChartInstance = new Chart(clicksChartCanvas.value, {
            type: 'bar',
            data: {
                labels: props.charts.linkClicks.map(l => l.code),
                datasets: [
                    {
                        label: 'Clicks',
                        data: props.charts.linkClicks.map(l => l.clicks),
                        backgroundColor: '#8B5CF6',
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                },
                plugins: {
                    legend: { display: false },
                },
            },
        });
    }
};

const pollLiveCheckins = async () => {
    if (!isPolling.value) return;
    try {
        const response = await axios.get(route('events.live-checkins', props.event.id));
        if (response.data) {
            checkedInCount.value = response.data.checked_in_count;
            totalGuests.value = response.data.total_guests;
            checkinPercentage.value = response.data.percentage;
            expectedHeadcount.value = response.data.expected_headcount;
            arrivalsList.value = response.data.recent_arrivals;
            lastPolledAt.value = new Date().toLocaleTimeString();
        }
    } catch (e) {
        // silent fail on poll
    }
};

onMounted(() => {
    initCharts();
    if (props.event.is_invite_only) {
        pollTimer = setInterval(pollLiveCheckins, 5000);
    }
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (sendsChartInstance) sendsChartInstance.destroy();
    if (rsvpChartInstance) rsvpChartInstance.destroy();
    if (clicksChartInstance) clicksChartInstance.destroy();
});

const togglePolling = () => {
    isPolling.value = !isPolling.value;
    if (isPolling.value) {
        pollLiveCheckins();
    }
};

const closeEvent = () => {
    if (confirm('Are you sure you want to close this event? Scanner admission and RSVP changes will be stopped.')) {
        router.post(route('events.close', props.event.id));
    }
};

const reopenEvent = () => {
    if (confirm('Reopen this event? Scanner admission and RSVPs will be re-enabled.')) {
        router.post(route('events.reopen', props.event.id));
    }
};

const showExportMenu = ref(false);
</script>

<template>
    <Head :title="`${event.title} • Overview`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="event.category"
                            class="text-xs px-2.5 py-0.5 rounded-full font-medium"
                            :style="{ backgroundColor: (event.category.color || '#F59E0B') + '20', color: event.category.color || '#D97706' }"
                        >
                            {{ event.category.icon }} {{ event.category.name_en }}
                        </span>
                        <span
                            class="text-xs px-2.5 py-0.5 rounded-full font-semibold uppercase tracking-wider"
                            :class="{
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': event.status === 'published',
                                'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-300': event.status === 'draft',
                                'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': event.status === 'closed',
                                'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': event.status === 'suspended',
                            }"
                        >
                            {{ event.status }}
                        </span>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ event.title }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        <span>📅 {{ new Date(event.starts_at).toLocaleDateString() }}</span>
                        <span v-if="event.ethiopian_date" class="ms-1 font-medium text-amber-700 dark:text-amber-400">({{ event.ethiopian_date.formatted }})</span>
                        <span v-if="event.venue_name" class="ms-3">📍 {{ event.venue_name }}</span>
                    </p>
                </div>

                <!-- Action Toolbar -->
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('events.design', event.id)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition flex items-center gap-1.5"
                    >
                        <span>🎨</span> {{ $t('Card Editor') }}
                    </Link>

                    <Link
                        :href="route('events.guests.index', event.id)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                    >
                        👥 {{ $t('Guests') }}
                    </Link>

                    <Link
                        :href="route('events.campaigns.index', event.id)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                    >
                        ✉️ {{ $t('Messages') }}
                    </Link>

                    <Link
                        :href="route('events.reminders.index', event.id)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                    >
                        ⏰ {{ $t('Reminders') }}
                    </Link>

                    <Link
                        v-if="event.is_invite_only"
                        :href="route('events.scanners.index', event.id)"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                    >
                        📷 {{ $t('Scanners') }}
                    </Link>

                    <!-- Export Dropdown -->
                    <div class="relative">
                        <button
                            @click="showExportMenu = !showExportMenu"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition flex items-center gap-1"
                        >
                            <span>📥</span> {{ $t('Export') }} <span>▼</span>
                        </button>
                        <div
                            v-if="showExportMenu"
                            @click="showExportMenu = false"
                            class="absolute right-0 mt-1 w-52 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-1.5 z-20"
                        >
                            <a
                                :href="route('events.export.csv', event.id)"
                                class="block px-4 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium"
                            >
                                📊 CSV (with RSVP & Delivery)
                            </a>
                            <a
                                :href="route('events.export.attendance', event.id)"
                                target="_blank"
                                class="block px-4 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium"
                            >
                                🖨️ Printable Attendance Sheet
                            </a>
                            <a
                                :href="route('events.export.door-list', event.id)"
                                target="_blank"
                                class="block px-4 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium"
                            >
                                📋 Day-of Door List (Paper)
                            </a>
                        </div>
                    </div>

                    <!-- Close / Reopen Event -->
                    <button
                        v-if="event.status !== 'closed'"
                        @click="closeEvent"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-900 transition"
                    >
                        🔒 {{ $t('Close Event') }}
                    </button>
                    <button
                        v-else
                        @click="reopenEvent"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 transition"
                    >
                        🔓 {{ $t('Reopen Event') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Closed Event Banner -->
            <div
                v-if="event.status === 'closed'"
                class="p-4 rounded-2xl bg-amber-50 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-900/60 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🔒</span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-900 dark:text-amber-200">This event is closed</h4>
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            Ticket scanning is locked and RSVP submissions are closed. You can still send Thank-You notes and export final records.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('events.thank-you', event.id)"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-700 text-white"
                >
                    💌 Send Thank-You Cards
                </Link>
            </div>

            <!-- Top Row: Metric KPI Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $t('Total Guests') }}</span>
                    <p class="text-2xl font-black text-gray-900 dark:text-white mt-1">{{ totals.guests }}</p>
                    <span class="text-[11px] text-gray-400">Registered</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $t('Deliveries') }}</span>
                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">{{ totals.sent }}</p>
                    <span class="text-[11px] text-gray-400">{{ totals.delivered }} delivered • {{ totals.failed }} failed</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ $t('Opened Cards') }}</span>
                    <p class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ totals.opened }}</p>
                    <span class="text-[11px] text-gray-400">Viewed personal link</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">{{ $t('RSVP Yes') }}</span>
                    <p class="text-2xl font-black text-emerald-600 mt-1">{{ totals.rsvp_yes }}</p>
                    <span class="text-[11px] text-gray-400">{{ totals.rsvp_pending }} pending response</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-amber-600 uppercase tracking-wider">{{ $t('Expected Heads') }}</span>
                    <p class="text-2xl font-black text-amber-600 mt-1">{{ expectedHeadcount }}</p>
                    <span class="text-[11px] text-gray-400">Party size total</span>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">{{ $t('Checked In') }}</span>
                    <p class="text-2xl font-black text-indigo-600 mt-1">{{ checkedInCount }}</p>
                    <span class="text-[11px] text-gray-400">{{ checkinPercentage }}% of guests</span>
                </div>
            </div>

            <!-- Middle Row: Live Door Check-in Monitor (for invite-only) -->
            <div
                v-if="event.is_invite_only"
                class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                🎟️ {{ $t('Live Admission & Door Check-in') }}
                            </h3>
                            <button
                                @click="togglePolling"
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold transition"
                                :class="isPolling ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                            >
                                <span v-if="isPolling" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span v-else class="w-2 h-2 rounded-full bg-gray-400"></span>
                                {{ isPolling ? 'Live (5s)' : 'Paused' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Real-time door arrival counter powered by active scanner check-ins.
                            <span v-if="lastPolledAt" class="ms-1">Last updated: {{ lastPolledAt }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Link
                            :href="route('events.scanners.index', event.id)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                        >
                            Manage Door Staff & Scanners
                        </Link>
                    </div>
                </div>

                <!-- Live Progress Bar -->
                <div class="my-6">
                    <div class="flex items-center justify-between text-sm font-semibold mb-2">
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ checkedInCount }} of {{ totalGuests }} checked in
                        </span>
                        <span class="text-indigo-600 dark:text-indigo-400 font-bold text-lg">
                            {{ checkinPercentage }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                        <div
                            class="bg-gradient-to-r from-indigo-500 to-purple-600 h-4 rounded-full transition-all duration-500"
                            :style="{ width: `${checkinPercentage}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Recent Arrivals Feed -->
                <div>
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">
                        Recently Arrived Guests (Live Stream)
                    </h4>
                    <div v-if="arrivalsList.length === 0" class="p-6 text-center text-xs text-gray-400 bg-gray-50 dark:bg-gray-900/50 rounded-2xl">
                        No check-ins yet. Arriving guests scanned by door staff will appear here immediately.
                    </div>
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                        <div
                            v-for="arrival in arrivalsList"
                            :key="arrival.id"
                            class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40 flex items-center justify-between"
                        >
                            <div class="truncate">
                                <p class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ arrival.name }}</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Party of {{ arrival.party_size }} • via {{ arrival.scanner_name || 'Staff' }}
                                </p>
                            </div>
                            <span class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold shrink-0 ms-2">
                                {{ arrival.checked_in_time }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row (Chart.js) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Delivery Sells Over Time -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Delivery Sends Over Time</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">SMS and Email dispatch timeline</p>
                            </div>
                            <Link
                                :href="route('events.deliveries', event.id)"
                                class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-medium"
                            >
                                View all deliveries →
                            </Link>
                        </div>
                        <div class="h-64 relative">
                            <canvas ref="sendsChartCanvas"></canvas>
                        </div>
                    </div>
                </div>

                <!-- RSVP Distribution Donut -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">RSVP Breakdown</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Responses from guests</p>
                            </div>
                        </div>
                        <div class="h-64 relative flex items-center justify-center">
                            <canvas ref="rsvpChartCanvas"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Link Clicks & Scheduled Reminders -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Link Clicks -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Custom Campaign Link Clicks</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Shortened link engagement analytics ({{ totals.total_link_clicks }} total clicks)</p>
                        </div>
                    </div>

                    <div v-if="charts.linkClicks.length === 0" class="p-8 text-center text-xs text-gray-400">
                        No shortened link clicks recorded yet. When you send messages with links, click tracking will display here.
                    </div>
                    <div v-else>
                        <div class="h-44 relative mb-4">
                            <canvas ref="clicksChartCanvas"></canvas>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="link in charts.linkClicks"
                                :key="link.code"
                                class="p-2.5 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center justify-between text-xs"
                            >
                                <div class="truncate max-w-[280px]">
                                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">/s/{{ link.code }}</span>
                                    <p class="text-gray-500 truncate text-[11px]">{{ link.target_url }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 font-bold dark:bg-indigo-950 dark:text-indigo-300">
                                    {{ link.clicks }} clicks
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configured Reminders -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Automated Reminders</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Scheduled reminders for this event</p>
                            </div>
                            <Link
                                :href="route('events.reminders.index', event.id)"
                                class="px-3 py-1 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white"
                            >
                                + Schedule
                            </Link>
                        </div>

                        <div v-if="reminders.length === 0" class="p-8 text-center text-xs text-gray-400">
                            No automated reminders scheduled for this event yet.
                        </div>
                        <div v-else class="space-y-2.5">
                            <div
                                v-for="rem in reminders"
                                :key="rem.id"
                                class="p-3 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/30 flex items-center justify-between"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-gray-900 dark:text-white capitalize">
                                            {{ rem.preset.replace(/_/g, ' ') }}
                                        </span>
                                        <span
                                            class="text-[10px] px-2 py-0.5 rounded-full font-semibold uppercase"
                                            :class="{
                                                'bg-amber-100 text-amber-800': rem.status === 'pending',
                                                'bg-emerald-100 text-emerald-800': rem.status === 'sent',
                                                'bg-rose-100 text-rose-800': rem.status === 'failed',
                                                'bg-gray-100 text-gray-600': rem.status === 'cancelled',
                                            }"
                                        >
                                            {{ rem.status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ new Date(rem.scheduled_at).toLocaleString() }} • Audience: {{ rem.target_audience }}
                                    </p>
                                </div>

                                <span class="text-xs font-mono font-bold uppercase text-gray-500">
                                    {{ rem.channel }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 text-right">
                        <Link
                            :href="route('events.reminders.index', event.id)"
                            class="text-xs text-amber-600 dark:text-amber-400 font-semibold hover:underline"
                        >
                            Configure reminder presets →
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
