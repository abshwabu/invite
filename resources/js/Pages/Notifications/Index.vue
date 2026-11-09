<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
    currentFilter: {
        type: String,
        default: 'all',
    },
});

const markAsRead = (id) => {
    router.post(route('notifications.read', id), {}, { preserveScroll: true });
};

const markAllRead = () => {
    router.post(route('notifications.mark-all-read'), {}, { preserveScroll: true });
};

const deleteNotification = (id) => {
    router.delete(route('notifications.destroy', id), { preserveScroll: true });
};

const getTypeIcon = (type) => {
    return matchType(type).icon;
};

const matchType = (type) => {
    switch (type) {
        case 'rsvp_received':
            return { icon: '💌', color: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' };
        case 'delivery_failure':
            return { icon: '⚠️', color: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' };
        case 'low_quota':
        case 'quota_exhausted':
            return { icon: '💳', color: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' };
        case 'campaign_pending_review':
            return { icon: '🛡️', color: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' };
        case 'subscription_expiring':
            return { icon: '⏳', color: 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300' };
        case 'reminder_failed':
            return { icon: '⏰', color: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' };
        default:
            return { icon: '🔔', color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300' };
    }
};
</script>

<template>
    <Head title="Notifications • Host Alerts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        🔔 {{ $t('Host Notifications') }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Alerts for guest RSVPs, deliveries, quota limits, and campaign reviews.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="unreadCount > 0"
                        @click="markAllRead"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition"
                    >
                        ✓ {{ $t('Mark All Read') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <!-- Filter Tabs -->
            <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-2">
                <Link
                    :href="route('notifications.index', { filter: 'all' })"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                    :class="currentFilter === 'all' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                >
                    All Notifications
                </Link>
                <Link
                    :href="route('notifications.index', { filter: 'unread' })"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition flex items-center gap-1.5"
                    :class="currentFilter === 'unread' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                >
                    <span>Unread</span>
                    <span v-if="unreadCount > 0" class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-bold">
                        {{ unreadCount }}
                    </span>
                </Link>
            </div>

            <!-- Notifications List -->
            <div v-if="notifications.data.length === 0" class="p-16 text-center text-xs text-gray-400 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700">
                <span class="text-3xl block mb-2">🎉</span>
                No notifications to display.
            </div>

            <div v-else class="space-y-2.5">
                <div
                    v-for="item in notifications.data"
                    :key="item.id"
                    class="p-4 rounded-2xl border transition flex items-start justify-between gap-4"
                    :class="item.is_read
                        ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'
                        : 'bg-amber-50/60 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/60 shadow-sm'"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="w-9 h-9 rounded-xl flex items-center justify-center text-base shrink-0"
                            :class="matchType(item.type).color"
                        >
                            {{ matchType(item.type).icon }}
                        </span>

                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white">
                                    {{ item.title }}
                                </h4>
                                <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-amber-500"></span>
                            </div>

                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                {{ item.message }}
                            </p>

                            <div class="flex items-center gap-3 mt-2 text-[11px] text-gray-400">
                                <span>{{ item.created_at }}</span>
                                <span v-if="item.action_url">
                                    •
                                    <a :href="item.action_url" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">
                                        View Details →
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            v-if="!item.is_read"
                            @click="markAsRead(item.id)"
                            class="p-1 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            title="Mark as read"
                        >
                            ✓
                        </button>
                        <button
                            @click="deleteNotification(item.id)"
                            class="p-1 text-xs text-gray-400 hover:text-rose-500"
                            title="Delete"
                        >
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
