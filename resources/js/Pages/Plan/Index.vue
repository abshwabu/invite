<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    currentTier: Object,
    activeSubscriptions: Array,
    quota: Object,
    usageHistory: Array,
    allTiers: Array,
    features: Object,
});

const upgradeForm = useForm({
    tier_id: null,
});

const requestUpgrade = (tierId) => {
    upgradeForm.tier_id = tierId;
    upgradeForm.post(route('plan.upgrade-request'), {
        preserveScroll: true,
    });
};

const formatDate = (isoString) => {
    if (!isoString) return 'Never expires';
    return new Date(isoString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (isoString) => {
    if (!isoString) return '-';
    return new Date(isoString).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const progressColor = computed(() => {
    if (props.quota.is_exhausted) return 'bg-rose-500';
    if (props.quota.is_low) return 'bg-amber-500';
    return 'bg-emerald-500';
});
</script>

<template>
    <Head :title="$t('My Plan & Quotas')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $t('My Plan & Card Quota') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('Monitor your invitation cards balance, usage history, and upgrade features.') }}
                    </p>
                </div>
                <div v-if="currentTier" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                    <span>{{ $t('Active Tier') }}:</span>
                    <span>{{ $page.props.locale === 'am' ? currentTier.name_am : currentTier.name_en }}</span>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Quota Alert Banners -->
                <div v-if="quota.is_exhausted" class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-rose-800 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">⚠️</span>
                            <div>
                                <h4 class="font-bold text-sm">{{ $t('You have exhausted your invitation card quota.') }}</h4>
                                <p class="text-xs text-rose-700 dark:text-rose-300 mt-0.5">{{ $t('Upgrade your plan now to send more invitation and reminder cards.') }}</p>
                            </div>
                        </div>
                        <a href="#compare-tiers" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 transition">
                            {{ $t('Upgrade Plan') }}
                        </a>
                    </div>
                </div>

                <div v-else-if="quota.is_low" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">⚡</span>
                            <div>
                                <h4 class="font-bold text-sm">{{ $t('Card quota running low (under 10% remaining).') }}</h4>
                                <p class="text-xs text-amber-800 dark:text-amber-300 mt-0.5">
                                    {{ $t('Only :count cards remaining in your active balance.', { count: quota.available }) }}
                                </p>
                            </div>
                        </div>
                        <a href="#compare-tiers" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 transition">
                            {{ $t('Get More Cards') }}
                        </a>
                    </div>
                </div>

                <!-- Current Balance & Progress Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Balance Summary -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 md:col-span-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs uppercase tracking-wider font-semibold text-gray-500 dark:text-gray-400">{{ $t('Card Quota Balance') }}</span>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-4xl font-extrabold text-gray-900 dark:text-white">{{ quota.available }}</span>
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('cards available') }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $t('Total Allocated') }}</span>
                                <p class="font-semibold text-gray-700 dark:text-gray-200">{{ quota.total }} cards</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $t('Used') }}: {{ quota.used }}</p>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="mt-6">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span>{{ $t('Usage') }}: {{ quota.percentage_used }}%</span>
                                <span>{{ quota.used }} / {{ quota.total }} {{ $t('cards used') }}</span>
                            </div>
                            <div class="h-3 w-full bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="progressColor"
                                    :style="{ width: `${Math.min(100, quota.percentage_used)}%` }"
                                ></div>
                            </div>
                        </div>

                        <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $t('Earliest Expiration') }}: <strong class="text-gray-700 dark:text-gray-300">{{ formatDate(quota.earliest_expiry) }}</strong></span>
                            <span>{{ $t('1 Card = 1 Sent Message (SMS or Email)') }}</span>
                        </div>
                    </div>

                    <!-- Tier Quick Badge & Perks -->
                    <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/20 border border-amber-200/60 dark:border-amber-800/60 rounded-2xl p-6 flex flex-col justify-between">
                        <div>
                            <span class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">{{ $t('Current Package') }}</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                                {{ currentTier ? ($page.props.locale === 'am' ? currentTier.name_am : currentTier.name_en) : 'Free' }}
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ currentTier ? ($page.props.locale === 'am' ? currentTier.description_am : currentTier.description_en) : '' }}
                            </p>

                            <div class="mt-4 space-y-2">
                                <div class="flex items-center text-xs text-gray-700 dark:text-gray-300 gap-2">
                                    <span :class="features?.qr_checkin ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                                        {{ features?.qr_checkin ? '✓' : '✗' }}
                                    </span>
                                    <span>{{ $t('QR Code Guest Check-in') }}</span>
                                </div>
                                <div class="flex items-center text-xs text-gray-700 dark:text-gray-300 gap-2">
                                    <span :class="features?.thank_you ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                                        {{ features?.thank_you ? '✓' : '✗' }}
                                    </span>
                                    <span>{{ $t('Post-Event Thank You Cards') }}</span>
                                </div>
                                <div class="flex items-center text-xs text-gray-700 dark:text-gray-300 gap-2">
                                    <span :class="features?.custom_messages ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400'">
                                        {{ features?.custom_messages ? '✓' : '✗' }}
                                    </span>
                                    <span>{{ $t('Custom Messages & Links') }}</span>
                                </div>
                            </div>
                        </div>

                        <a href="#compare-tiers" class="mt-6 block text-center rounded-xl bg-amber-600 text-white text-xs font-semibold py-2.5 px-4 hover:bg-amber-500 shadow-sm transition">
                            {{ $t('Upgrade or Renew') }}
                        </a>
                    </div>
                </div>

                <!-- Subscriptions Table (if multiple exist) -->
                <div v-if="activeSubscriptions && activeSubscriptions.length > 0" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900 dark:text-white text-sm">{{ $t('Active Subscriptions') }}</h3>
                        <span class="text-xs text-gray-400">{{ activeSubscriptions.length }} {{ $t('active grants') }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-750 text-gray-500 uppercase tracking-wider text-[11px] border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Tier') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Source') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Cards Used / Total') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Started') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Expires') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="sub in activeSubscriptions" :key="sub.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-750/50">
                                    <td class="px-6 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $page.props.locale === 'am' ? sub.tier?.name_am : sub.tier?.name_en }}
                                    </td>
                                    <td class="px-6 py-3 capitalize">{{ sub.source }}</td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <span>{{ sub.cards_used }} / {{ sub.cards_total }}</span>
                                            <span class="text-gray-400">({{ sub.cards_total - sub.cards_used }} left)</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">{{ formatDate(sub.starts_at) }}</td>
                                    <td class="px-6 py-3">{{ formatDate(sub.expires_at) }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            {{ sub.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tier Comparison Grid -->
                <div id="compare-tiers" class="pt-6">
                    <div class="text-center max-w-2xl mx-auto mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Available Plans & Upgrades') }}</h3>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                            {{ $t('Choose the ideal package for your upcoming weddings, memorials, birthdays, or corporate celebrations.') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <div
                            v-for="tier in allTiers"
                            :key="tier.id"
                            class="bg-white dark:bg-gray-800 rounded-2xl border transition-all duration-200 flex flex-col justify-between p-5 relative"
                            :class="currentTier?.id === tier.id ? 'border-amber-500 shadow-md ring-2 ring-amber-500/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 shadow-sm'"
                        >
                            <div v-if="currentTier?.id === tier.id" class="absolute -top-3 left-1/2 -translate-x-1/2 px-2.5 py-0.5 bg-amber-600 text-white text-[10px] uppercase font-bold rounded-full">
                                {{ $t('Current Plan') }}
                            </div>

                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-white text-base">
                                    {{ $page.props.locale === 'am' ? tier.name_am : tier.name_en }}
                                </h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 min-h-[32px]">
                                    {{ $page.props.locale === 'am' ? tier.description_am : tier.description_en }}
                                </p>

                                <div class="my-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                                    <div class="text-2xl font-black text-gray-900 dark:text-white">
                                        {{ tier.price_amount > 0 ? `${Number(tier.price_amount).toLocaleString()} ETB` : $t('Free') }}
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ tier.validity_days ? `${tier.validity_days} days validity` : 'Never expires' }}
                                    </div>
                                </div>

                                <ul class="space-y-2 text-xs text-gray-600 dark:text-gray-300 mb-6">
                                    <li class="flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                                        <span class="text-amber-500">✉️</span>
                                        <span>{{ Number(tier.card_quota).toLocaleString() }} {{ $t('cards included') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span>📅</span>
                                        <span>{{ tier.max_events ? `${tier.max_events} events max` : $t('Unlimited events') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span>👥</span>
                                        <span>{{ tier.max_guests_per_event }} {{ $t('guests per event') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span>📱</span>
                                        <span>{{ tier.max_scanners_per_event }} {{ $t('scanner doors') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span :class="tier.features?.qr_checkin ? 'text-emerald-500 font-bold' : 'text-gray-300'">
                                            {{ tier.features?.qr_checkin ? '✓' : '—' }}
                                        </span>
                                        <span>{{ $t('QR Check-in') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span :class="tier.features?.thank_you ? 'text-emerald-500 font-bold' : 'text-gray-300'">
                                            {{ tier.features?.thank_you ? '✓' : '—' }}
                                        </span>
                                        <span>{{ $t('Thank-you cards') }}</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span :class="tier.features?.remove_branding ? 'text-emerald-500 font-bold' : 'text-gray-300'">
                                            {{ tier.features?.remove_branding ? '✓' : '—' }}
                                        </span>
                                        <span>{{ $t('No branding watermark') }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <button
                                    v-if="currentTier?.id === tier.id"
                                    type="button"
                                    disabled
                                    class="w-full py-2 px-3 text-xs font-semibold rounded-xl bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-400 cursor-default"
                                >
                                    {{ $t('Current Plan') }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    @click="requestUpgrade(tier.id)"
                                    :disabled="upgradeForm.processing"
                                    class="w-full py-2 px-3 text-xs font-semibold rounded-xl bg-amber-600 hover:bg-amber-500 text-white shadow-sm transition disabled:opacity-50"
                                >
                                    {{ upgradeForm.processing && upgradeForm.tier_id === tier.id ? $t('Requesting...') : $t('Select & Upgrade') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usage History List -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900 dark:text-white text-sm">{{ $t('Recent Card Usage Ledger') }}</h3>
                        <span class="text-xs text-gray-400">{{ usageHistory.length }} {{ $t('records') }}</span>
                    </div>

                    <div v-if="usageHistory.length === 0" class="p-8 text-center text-xs text-gray-400">
                        {{ $t('No card usage recorded yet. When you send invitations or reminders, records will appear here.') }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-750 text-gray-500 uppercase tracking-wider text-[11px] border-b border-gray-100 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Date & Time') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Kind') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Channel') }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $t('Event / Recipient') }}</th>
                                    <th class="px-6 py-3 font-semibold text-right">{{ $t('Quantity') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="ledger in usageHistory" :key="ledger.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-750/50">
                                    <td class="px-6 py-3">{{ formatDateTime(ledger.created_at) }}</td>
                                    <td class="px-6 py-3 capitalize">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold uppercase"
                                            :class="ledger.quantity < 0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                        >
                                            {{ ledger.kind }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 uppercase text-[11px] font-mono">{{ ledger.channel }}</td>
                                    <td class="px-6 py-3">
                                        <span v-if="ledger.event">{{ ledger.event.title }}</span>
                                        <span v-if="ledger.guest" class="text-gray-400 ml-1">({{ ledger.guest.name }})</span>
                                        <span v-if="!ledger.event && !ledger.guest" class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-6 py-3 text-right font-bold" :class="ledger.quantity < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white'">
                                        {{ ledger.quantity > 0 ? `-${ledger.quantity}` : `+${Math.abs(ledger.quantity)}` }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
