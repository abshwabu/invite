<script setup>
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    payment: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'keepEditing']);

const handleKeepEditing = () => {
    emit('keepEditing');
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div class="p-6 sm:p-8 text-center">
            <!-- Icon -->
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 mb-4 ring-8 ring-emerald-50 dark:ring-emerald-900/20">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 1118 0z" />
                </svg>
            </div>

            <!-- Title -->
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                {{ $page.props.locale === 'am' ? 'የክፍያ መረጃዎን ተቀብለናል' : 'We received your payment details' }}
            </h3>

            <!-- Notice / Body Text -->
            <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p class="font-medium">
                    {{ $page.props.locale === 'am'
                        ? 'ክፍያዎን እናረጋግጣለን። ካረጋገጥን በኋላ በ24 ሰዓት ውስጥ ካርዶችዎን መላክ ይችላሉ። ለአሁን ማዘጋጀትዎን ይቀጥሉ።'
                        : "We'll verify your payment. You can send your cards within 24 hours after we verify. For now, keep editing."
                    }}
                </p>
                <p v-if="payment?.payment_code" class="text-xs text-gray-400 dark:text-gray-500 pt-1">
                    {{ $page.props.locale === 'am' ? 'የክፍያ መለያ ኮድ፡' : 'Payment Code:' }}
                    <span class="font-mono font-bold text-gray-700 dark:text-gray-300">{{ payment.payment_code }}</span>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                <PrimaryButton
                    type="button"
                    class="w-full sm:w-auto justify-center bg-amber-600 hover:bg-amber-500 font-bold py-2.5 px-6"
                    @click="handleKeepEditing"
                >
                    {{ $page.props.locale === 'am' ? 'ማዘጋጀቱን ይቀጥሉ' : 'Keep editing' }}
                </PrimaryButton>

                <Link
                    v-if="payment?.id"
                    :href="route('payments.show', payment.id)"
                    class="w-full sm:w-auto inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 dark:text-gray-300 shadow-sm transition hover:bg-gray-50 dark:hover:bg-gray-700"
                    @click="$emit('close')"
                >
                    {{ $page.props.locale === 'am' ? 'የክፍያ ሁኔታ ይመልከቱ' : 'View payment status' }}
                </Link>
            </div>
        </div>
    </Modal>
</template>
