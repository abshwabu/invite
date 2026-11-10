<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PaymentVerificationModal from '@/Components/Payments/PaymentVerificationModal.vue';
import PaymentConfirmationModal from '@/Components/Payments/PaymentConfirmationModal.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    tier: {
        type: Object,
        default: null,
    },
    initialPayment: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'completed']);

// Steps: 'pay' (step 1), 'verify' (step 2), 'confirm' (step 3)
const currentStep = ref('pay');
const loading = ref(false);
const error = ref('');
const paymentData = ref(null);
const methods = ref([]);
const instructions = ref({ en: '', am: '' });
const copiedField = ref(null);

watch(
    () => props.show,
    async (isOpen) => {
        if (isOpen) {
            currentStep.value = 'pay';
            error.value = '';
            if (props.initialPayment) {
                paymentData.value = props.initialPayment;
                fetchInitiateDetails();
            } else if (props.tier) {
                await initiatePayment(props.tier.id);
            }
        }
    }
);

watch(
    () => props.tier,
    async (newTier) => {
        if (props.show && newTier && (!paymentData.value || paymentData.value.tier?.id !== newTier.id)) {
            await initiatePayment(newTier.id);
        }
    }
);

const initiatePayment = async (tierId) => {
    loading.value = true;
    error.value = '';
    try {
        const response = await axios.post(route('payments.initiate'), { tier_id: tierId });
        paymentData.value = response.data.payment;
        methods.value = response.data.methods || [];
        instructions.value = response.data.instructions || { en: '', am: '' };
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to initiate payment. Please try again.';
    } finally {
        loading.value = false;
    }
};

const fetchInitiateDetails = async () => {
    // If opened with existing payment, load active methods if empty
    if (methods.value.length === 0 && paymentData.value?.tier?.id) {
        try {
            const response = await axios.post(route('payments.initiate'), { tier_id: paymentData.value.tier.id });
            methods.value = response.data.methods || [];
            instructions.value = response.data.instructions || { en: '', am: '' };
        } catch (e) {
            // silent fail
        }
    }
};

const copyToClipboard = async (text, fieldKey) => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldKey;
        setTimeout(() => {
            if (copiedField.value === fieldKey) {
                copiedField.value = null;
            }
        }, 2000);
    } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copiedField.value = fieldKey;
        setTimeout(() => {
            if (copiedField.value === fieldKey) {
                copiedField.value = null;
            }
        }, 2000);
    }
};

const goToVerify = () => {
    currentStep.value = 'verify';
};

const handleProofSubmitted = (updatedPayment) => {
    if (updatedPayment) {
        paymentData.value = { ...paymentData.value, ...updatedPayment };
    }
    currentStep.value = 'confirm';
    emit('completed', paymentData.value);
};

const handleKeepEditing = () => {
    emit('close');
};

const close = () => {
    emit('close');
};
</script>

<template>
    <div>
        <!-- STEP 1: Pay Modal -->
        <Modal :show="show && currentStep === 'pay'" max-width="lg" @close="close">
            <div class="p-6">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-150 pb-4 dark:border-gray-700">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $page.props.locale === 'am' ? `ለ${paymentData?.tier?.name_am || tier?.name_am || tier?.name_en || 'ዕቅድ'} ይክፈሉ` : `Pay for ${paymentData?.tier?.name_en || tier?.name_en || 'Tier'}` }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            <span v-if="paymentData?.tier || tier">
                                {{ Number(paymentData?.tier?.card_quota || tier?.card_quota).toLocaleString() }}
                                {{ $page.props.locale === 'am' ? 'የግብዣ ካርዶች' : 'invitation cards' }}
                                •
                                {{ (paymentData?.tier?.validity_days || tier?.validity_days)
                                    ? `${paymentData?.tier?.validity_days || tier?.validity_days} ${$page.props.locale === 'am' ? 'ቀናት' : 'days validity'}`
                                    : ($page.props.locale === 'am' ? 'ያልተገደበ ጊዜ' : 'Never expires')
                                }}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        @click="close"
                    >
                        ✕
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="py-12 text-center space-y-3">
                    <div class="inline-block animate-spin text-3xl">⏳</div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $page.props.locale === 'am' ? 'የክፍያ መረጃ በማዘጋጀት ላይ...' : 'Preparing payment details...' }}
                    </p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="py-6 text-center space-y-4">
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-xs text-rose-800 dark:bg-rose-950/60 dark:border-rose-900 dark:text-rose-200">
                        {{ error }}
                    </div>
                    <PrimaryButton @click="initiatePayment(tier?.id)">
                        {{ $page.props.locale === 'am' ? 'እንደገና ሞክር' : 'Retry' }}
                    </PrimaryButton>
                </div>

                <!-- Main Payment Details -->
                <div v-else-if="paymentData" class="mt-4 space-y-5">
                    <!-- Amount Highlight -->
                    <div class="flex items-baseline justify-between rounded-xl bg-amber-50/70 p-4 border border-amber-200/80 dark:bg-amber-950/30 dark:border-amber-900/60">
                        <div>
                            <span class="text-xs uppercase tracking-wider font-semibold text-amber-800 dark:text-amber-300">
                                {{ $page.props.locale === 'am' ? 'የሚከፈል መጠን' : 'Amount to pay' }}
                            </span>
                            <div class="text-2xl font-black text-amber-900 dark:text-amber-200 mt-0.5">
                                {{ Number(paymentData.amount).toLocaleString() }} <span class="text-sm font-semibold">ETB</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-200/80 text-amber-900 dark:bg-amber-900/80 dark:text-amber-200">
                                {{ $page.props.locale === 'am' ? 'በእጅ የሚፈጸም ክፍያ' : 'Manual Transfer' }}
                            </span>
                        </div>
                    </div>

                    <!-- Unique Payment Code Box -->
                    <div class="rounded-xl border-2 border-dashed border-amber-300 bg-white p-4 shadow-sm dark:border-amber-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $page.props.locale === 'am' ? 'የክፍያ ኮድዎ (Payment Code)' : 'Your Payment Code' }}
                                </span>
                                <div class="mt-0.5 font-mono text-xl sm:text-2xl font-black tracking-wider text-gray-900 dark:text-white">
                                    {{ paymentData.payment_code }}
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="copyToClipboard(paymentData.payment_code, 'code')"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-900 transition hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200 shadow-sm"
                            >
                                <span v-if="copiedField === 'code'" class="text-emerald-600 font-bold">✓ {{ $page.props.locale === 'am' ? 'ተቀድቷል!' : 'Copied!' }}</span>
                                <span v-else class="flex items-center gap-1">📋 {{ $page.props.locale === 'am' ? 'ኮዱን ቅዳ' : 'Copy Code' }}</span>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-amber-800 dark:text-amber-300 font-medium">
                            ⚠️ {{ $page.props.locale === 'am'
                                ? 'ክፍያውን በባንክ ወይም በቴሌብር ሲፈጽሙ በማስታወሻ (Reason / Remark) ቦታ ላይ ይህንን ኮድ ያስገቡ።'
                                : 'Please write this payment code in the transfer remark / note so we can match your payment.'
                            }}
                        </p>
                    </div>

                    <!-- Payment Methods (Accounts) -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-2">
                            {{ $page.props.locale === 'am' ? 'የክፍያ መንገዶች (የሂሳብ ቁጥሮች)' : 'Our Payment Accounts' }}
                        </h4>

                        <div class="space-y-2.5">
                            <div
                                v-for="m in methods"
                                :key="m.id"
                                class="rounded-xl border border-gray-200 bg-gray-50/70 p-3.5 dark:border-gray-700 dark:bg-gray-800/80 transition hover:border-gray-300"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                                {{ m.bank_name || m.label || m.type }}
                                            </span>
                                            <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ m.type }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-600 dark:text-gray-300">
                                            {{ $page.props.locale === 'am' ? 'የሂሳብ ስም፡' : 'Account Name:' }}
                                            <span class="font-medium text-gray-900 dark:text-white">{{ m.account_name }}</span>
                                        </p>
                                        <div class="flex items-center gap-2 font-mono text-sm font-bold text-gray-900 dark:text-white pt-0.5">
                                            <span>{{ m.account_number || m.phone_number }}</span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="copyToClipboard(m.account_number || m.phone_number, `acc-${m.id}`)"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                                    >
                                        <span v-if="copiedField === `acc-${m.id}`" class="text-emerald-600 font-bold">✓ {{ $page.props.locale === 'am' ? 'ተቀድቷል' : 'Copied' }}</span>
                                        <span v-else>📋 {{ $page.props.locale === 'am' ? 'ቁጥሩን ቅዳ' : 'Copy' }}</span>
                                    </button>
                                </div>

                                <div v-if="m.instructions" class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 pt-1.5">
                                    {{ m.instructions }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reassuring Instructions -->
                    <div class="rounded-lg bg-gray-100 p-3 text-xs text-gray-600 dark:bg-gray-800/60 dark:text-gray-400">
                        {{ $page.props.locale === 'am' ? instructions.am : instructions.en }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-between pt-3 border-t border-gray-150 dark:border-gray-700">
                        <SecondaryButton type="button" @click="close">
                            {{ $page.props.locale === 'am' ? 'ዝጋ' : 'Close' }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="button"
                            class="bg-amber-600 hover:bg-amber-500 font-bold py-2.5 px-6 shadow-md"
                            @click="goToVerify"
                        >
                            {{ $page.props.locale === 'am' ? 'ከፍያለሁ፣ ያረጋግጡ' : "I've paid, verify" }} →
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- STEP 2: Verification Modal -->
        <PaymentVerificationModal
            v-if="paymentData"
            :show="show && currentStep === 'verify'"
            :payment="paymentData"
            :payment-methods="methods"
            @close="close"
            @back="currentStep = 'pay'"
            @submitted="handleProofSubmitted"
        />

        <!-- STEP 3: Confirmation Modal -->
        <PaymentConfirmationModal
            v-if="paymentData"
            :show="show && currentStep === 'confirm'"
            :payment="paymentData"
            @close="close"
            @keep-editing="handleKeepEditing"
        />
    </div>
</template>
