<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    payments: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
});

const getStatusBadge = (status) => {
    switch (status) {
        case 'approved':
            return {
                label_en: 'Approved',
                label_am: 'የተረጋገጠ',
                class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800',
            };
        case 'submitted':
            return {
                label_en: 'Under review',
                label_am: 'በግምገማ ላይ',
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-800',
            };
        case 'needs_info':
            return {
                label_en: 'Needs info',
                label_am: 'ተጨማሪ መረጃ ያስፈልጋል',
                class: 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border-orange-300 dark:border-orange-800',
            };
        case 'awaiting_proof':
            return {
                label_en: 'Not submitted',
                label_am: 'ማረጋገጫ አልቀረበም',
                class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-700',
            };
        case 'rejected':
            return {
                label_en: 'Rejected',
                label_am: 'ተቀባይነት አላገኘም',
                class: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-800',
            };
        case 'refunded':
            return {
                label_en: 'Refunded',
                label_am: 'የተመለሰ',
                class: 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-300 dark:border-purple-800',
            };
        case 'abandoned':
            return {
                label_en: 'Abandoned',
                label_am: 'የተተወ',
                class: 'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-500 border-gray-200 dark:border-gray-700',
            };
        default:
            return {
                label_en: status,
                label_am: status,
                class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            };
    }
};

const formatDate = (isoString) => {
    if (!isoString) return '—';
    return new Date(isoString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="$t('My Payments')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $page.props.locale === 'am' ? 'የእኔ ክፍያዎች' : 'My Payments & Receipts' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $page.props.locale === 'am'
                            ? 'የባንክና ቴሌብር ክፍያዎችን ታሪክ፣ ደረሰኞችን እና ማረጋገጫዎችን ይከታተሉ።'
                            : 'Track bank and Telebirr transfer proofs, verification statuses, and download tax receipts.'
                        }}
                    </p>
                </div>
                <div>
                    <Link
                        :href="route('plan.index')"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-500 transition"
                    >
                        <span>+</span>
                        <span>{{ $page.props.locale === 'am' ? 'ዕቅድ አሻሽል / ግዛ' : 'Buy / Upgrade Tier' }}</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div
                    v-if="payments.length === 0"
                    class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 p-12 text-center"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600 dark:bg-amber-950/40 text-2xl mb-4">
                        💳
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        {{ $page.props.locale === 'am' ? 'እስካሁን ምንም ክፍያ አልተመዘገበም' : 'No payments found' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1">
                        {{ $page.props.locale === 'am'
                            ? 'የግብዣ ካርዶችን ቁጥር ለመጨመር እና ተጨማሪ አገልግሎቶችን ለመጠቀም ዕቅድዎን አሁን ያሻሽሉ።'
                            : 'When you purchase tier packages via bank transfer or Telebirr, your payments and review status will appear here.'
                        }}
                    </p>
                    <div class="mt-6">
                        <Link
                            :href="route('plan.index')"
                            class="inline-flex items-center rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-500"
                        >
                            {{ $page.props.locale === 'am' ? 'ዕቅዶችን ይመልከቱ' : 'View Available Plans' }}
                        </Link>
                    </div>
                </div>

                <!-- Payments Table -->
                <div v-else class="overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-750 text-gray-500 uppercase tracking-wider text-[11px] border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'የክፍያ ኮድ' : 'Payment Code' }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'ዕቅድ' : 'Tier' }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'መጠን' : 'Amount' }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'ማጣቀሻ / ደረሰኝ' : 'Reference / Proof' }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'ሁኔታ' : 'Status' }}</th>
                                    <th class="px-6 py-3 font-semibold">{{ $page.props.locale === 'am' ? 'የቀረበበት ቀን' : 'Date' }}</th>
                                    <th class="px-6 py-3 font-semibold text-right">{{ $page.props.locale === 'am' ? 'ድርጊት' : 'Actions' }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                <tr v-for="p in payments" :key="p.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-750/50 transition">
                                    <td class="px-6 py-4 font-mono font-bold text-gray-900 dark:text-white">
                                        <Link :href="route('payments.show', p.id)" class="hover:text-amber-600 underline">
                                            {{ p.payment_code }}
                                        </Link>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                        {{ $page.props.locale === 'am' ? p.tier?.name_am : p.tier?.name_en }}
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-900 dark:text-white">
                                        {{ Number(p.amount_verified || p.amount_expected).toLocaleString() }} ETB
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="space-y-0.5">
                                            <div v-if="p.reference_number" class="font-mono text-[11px] text-gray-700 dark:text-gray-300 font-semibold truncate max-w-[140px]">
                                                {{ p.reference_number }}
                                            </div>
                                            <div v-if="p.has_receipt" class="text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1 font-medium">
                                                <span>📸</span>
                                                <span>{{ $page.props.locale === 'am' ? 'ደረሰኝ ተያይዟል' : 'Receipt uploaded' }}</span>
                                            </div>
                                            <div v-if="!p.reference_number && !p.has_receipt" class="text-gray-400 italic text-[11px]">
                                                {{ $page.props.locale === 'am' ? 'አልቀረበም' : 'Pending proof' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                                            :class="getStatusBadge(p.status).class"
                                        >
                                            {{ $page.props.locale === 'am' ? getStatusBadge(p.status).label_am : getStatusBadge(p.status).label_en }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                        {{ formatDate(p.submitted_at || p.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <a
                                                v-if="p.status === 'approved'"
                                                :href="route('payments.invoice', p.id)"
                                                target="_blank"
                                                class="rounded-lg border border-gray-300 dark:border-gray-600 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                                :title="$page.props.locale === 'am' ? 'ደረሰኝ አውርድ' : 'Download Invoice'"
                                            >
                                                📄 {{ $page.props.locale === 'am' ? 'ደረሰኝ' : 'Invoice' }}
                                            </a>
                                            <Link
                                                v-if="['awaiting_proof', 'needs_info', 'rejected'].includes(p.status)"
                                                :href="route('payments.show', p.id)"
                                                class="rounded-lg bg-amber-600 px-2.5 py-1 text-xs font-bold text-white hover:bg-amber-500 transition shadow-sm"
                                            >
                                                {{ p.status === 'awaiting_proof' ? ($page.props.locale === 'am' ? 'ማረጋገጫ አስገባ' : 'Submit Proof') : ($page.props.locale === 'am' ? 'እንደገና አስገባ' : 'Resubmit') }}
                                            </Link>
                                            <Link
                                                :href="route('payments.show', p.id)"
                                                class="rounded-lg border border-gray-200 dark:border-gray-700 px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                {{ $page.props.locale === 'am' ? 'ዝርዝር' : 'Details' }} →
                                            </Link>
                                        </div>
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
