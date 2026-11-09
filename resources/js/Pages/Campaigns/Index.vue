<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    campaigns: {
        type: Object,
        required: true,
    },
    canCustomMessages: {
        type: Boolean,
        default: false,
    },
});

const cancelCampaign = (campaign) => {
    if (confirm(`Cancel scheduled campaign #${campaign.id}?`)) {
        router.post(route('events.campaigns.cancel', { event: props.event.id, campaign: campaign.id }));
    }
};
</script>

<template>
    <Head :title="`Campaign History - ${event.title}`" />

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
                        Campaign History & Scheduled Messages
                    </h2>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="canCustomMessages"
                        :href="route('events.messages.create', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl border border-purple-500/50 text-purple-600 dark:text-purple-400 hover:bg-purple-500/10 text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>✉️ Custom Message</span>
                    </Link>

                    <Link
                        :href="route('events.campaigns.create', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition flex items-center space-x-1"
                    >
                        <span>📨 Send Invitations</span>
                    </Link>

                    <Link
                        :href="route('events.deliveries', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>📋 Deliveries Log</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Campaigns Table -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                    <th class="py-3.5 px-4">Campaign</th>
                                    <th class="py-3.5 px-4">Type</th>
                                    <th class="py-3.5 px-4">Audience</th>
                                    <th class="py-3.5 px-4">Status</th>
                                    <th class="py-3.5 px-4">Deliveries</th>
                                    <th class="py-3.5 px-4">Link Clicks</th>
                                    <th class="py-3.5 px-4">Cost</th>
                                    <th class="py-3.5 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-xs text-gray-800 dark:text-gray-200">
                                <tr v-if="campaigns.data.length === 0">
                                    <td colspan="8" class="py-12 text-center text-gray-400">
                                        No campaigns created for this event yet.
                                    </td>
                                </tr>
                                <tr v-for="c in campaigns.data" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">
                                            {{ c.subject || (c.type === 'custom' ? 'Custom Broadcast' : 'Invitation Send') }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 max-w-xs truncate mt-0.5">
                                            {{ c.body }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            {{ new Date(c.created_at).toLocaleString() }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider',
                                                c.type === 'custom' ? 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
                                            ]"
                                        >
                                            {{ c.type }}
                                        </span>
                                        <div class="text-[10px] text-gray-400 uppercase mt-0.5 font-mono">
                                            {{ c.channel }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4 capitalize font-medium">
                                        {{ (c.audience?.type || 'all').replace('_', ' ') }}
                                        <span v-if="c.audience_count" class="text-[11px] text-gray-400 block font-normal">
                                            {{ c.audience_count }} recipients
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span
                                            :class="[
                                                'px-2 py-0.5 rounded-full text-[10px] font-bold capitalize',
                                                c.status === 'sent' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : '',
                                                c.status === 'sending' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : '',
                                                c.status === 'scheduled' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : '',
                                                c.status === 'pending_review' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300' : '',
                                                c.status === 'cancelled' ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' : '',
                                                c.status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' : '',
                                            ]"
                                        >
                                            {{ c.status.replace('_', ' ') }}
                                        </span>
                                        <div v-if="c.scheduled_at" class="text-[10px] text-gray-400 mt-1">
                                            Scheduled: {{ new Date(c.scheduled_at).toLocaleString() }}
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <div class="text-[11px] space-y-0.5">
                                            <div><span class="text-gray-400">Sent:</span> <strong>{{ c.sent_count || 0 }}</strong></div>
                                            <div><span class="text-gray-400">Delivered:</span> <strong class="text-emerald-600">{{ c.delivered_count || 0 }}</strong></div>
                                            <div v-if="c.failed_count > 0"><span class="text-gray-400">Failed:</span> <strong class="text-red-600">{{ c.failed_count }}</strong></div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span class="font-bold text-gray-900 dark:text-white">
                                            {{ c.link_clicks || 0 }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 ml-1">clicks</span>
                                    </td>

                                    <td class="py-3.5 px-4 font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ c.estimated_cards }} cards
                                    </td>

                                    <td class="py-3.5 px-4 text-right space-x-2">
                                        <button
                                            v-if="c.status === 'scheduled'"
                                            type="button"
                                            @click="cancelCampaign(c)"
                                            class="px-2 py-1 rounded bg-red-50 hover:bg-red-100 text-red-600 text-[11px] font-semibold transition"
                                        >
                                            Cancel
                                        </button>

                                        <Link
                                            :href="route('events.deliveries', { event: event.id, campaign_id: c.id })"
                                            class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-[11px] font-semibold transition inline-block"
                                        >
                                            View Deliveries
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="campaigns.links && campaigns.links.length > 3" class="p-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs">
                        <div class="text-gray-500">
                            Showing {{ campaigns.from || 0 }} to {{ campaigns.to || 0 }} of {{ campaigns.total }} campaigns
                        </div>
                        <div class="flex space-x-1">
                            <Component
                                :is="link.url ? Link : 'span'"
                                v-for="(link, i) in campaigns.links"
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
