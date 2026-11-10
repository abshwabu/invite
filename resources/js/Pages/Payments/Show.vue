<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    payment: {
        type: Object,
        required: true,
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    instructions: {
        type: Object,
        default: () => ({ en: '', am: '' }),
    },
});

const isResubmitting = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});
const copiedField = ref(null);

const fileInput = ref(null);
const receiptFile = ref(null);
const receiptPreview = ref(null);

const form = ref({
    reference_number: props.payment.reference_number || '',
    method_id: props.payment.method?.id || (props.paymentMethods[0]?.id || ''),
    amount_declared: props.payment.amount_declared || props.payment.amount_expected || '',
    paid_from: props.payment.paid_from || '',
    paid_at_declared: props.payment.paid_at_declared ? props.payment.paid_at_declared.substring(0, 16) : '',
    user_note: props.payment.user_note || '',
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

const copyToClipboard = async (text, fieldKey) => {
    if (!text) return;
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldKey;
        setTimeout(() => {
            if (copiedField.value === fieldKey) copiedField.value = null;
        }, 2000);
    } catch (e) {
        copiedField.value = fieldKey;
        setTimeout(() => {
            if (copiedField.value === fieldKey) copiedField.value = null;
        }, 2000);
    }
};

const handleFileSelect = (e) => {
    errorMessage.value = '';
    const file = e.target.files?.[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
        errorMessage.value = 'Please select a valid image (JPEG, PNG, or WebP).';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        errorMessage.value = 'File size must not exceed 5MB.';
        return;
    }

    receiptFile.value = file;
    const reader = new FileReader();
    reader.onload = (ev) => {
        receiptPreview.value = ev.target?.result;
    };
    reader.readAsDataURL(file);
};

const submitProof = async () => {
    errorMessage.value = '';
    fieldErrors.value = {};

    const hasRef = form.value.reference_number && form.value.reference_number.trim().length > 0;
    const hasReceipt = receiptFile.value !== null || props.payment.has_receipt;

    if (!hasRef && !hasReceipt) {
        errorMessage.value = 'Please upload a receipt screenshot OR enter the transaction reference number.';
        return;
    }

    submitting.value = true;

    try {
        const formData = new FormData();
        if (form.value.reference_number) {
            formData.append('reference_number', form.value.reference_number.trim());
        }
        if (receiptFile.value) {
            formData.append('receipt', receiptFile.value);
        }
        if (form.value.method_id) {
            formData.append('method_id', form.value.method_id);
        }
        if (form.value.amount_declared) {
            formData.append('amount_declared', form.value.amount_declared);
        }
        if (form.value.paid_from) {
            formData.append('paid_from', form.value.paid_from);
        }
        if (form.value.paid_at_declared) {
            formData.append('paid_at_declared', form.value.paid_at_declared);
        }
        if (form.value.user_note) {
            formData.append('user_note', form.value.user_note);
        }

        await axios.post(route('payments.submit', props.payment.id), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json',
            },
        });

        submitting.value = false;
        isResubmitting.value = false;
        router.reload();
    } catch (err) {
        submitting.value = false;
        if (err.response?.status === 422) {
            fieldErrors.value = err.response.data?.errors || {};
            errorMessage.value = err.response.data?.message || 'Validation error.';
        } else {
            errorMessage.value = err.response?.data?.message || 'Error submitting payment proof.';
        }
    }
};

const formatDate = (isoString) => {
    if (!isoString) return '—';
    return new Date(isoString).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`${$page.props.locale === 'am' ? 'የክፍያ ዝርዝር' : 'Payment Details'} - ${payment.payment_code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('payments.index')"
                        class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition"
                        title="Back to Payments"
                    >
                        ←
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-mono font-bold leading-tight text-gray-900 dark:text-white">
                                {{ payment.payment_code }}
                            </h2>
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border"
                                :class="getStatusBadge(payment.status).class"
                            >
                                {{ $page.props.locale === 'am' ? getStatusBadge(payment.status).label_am : getStatusBadge(payment.status).label_en }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $page.props.locale === 'am' ? payment.tier?.name_am : payment.tier?.name_en }}
                            • {{ Number(payment.amount_expected).toLocaleString() }} ETB
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a
                        v-if="payment.status === 'approved'"
                        :href="route('payments.invoice', payment.id)"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <span>📄</span>
                        <span>{{ $page.props.locale === 'am' ? 'ደረሰኝ / ኢንቮይስ አውርድ' : 'Download Invoice' }}</span>
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Status Alerts -->
                <div
                    v-if="payment.status === 'submitted'"
                    class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-xs sm:text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-200 flex items-start gap-3 shadow-sm"
                >
                    <span class="text-xl">⏳</span>
                    <div>
                        <h4 class="font-bold">
                            {{ $page.props.locale === 'am' ? 'ክፍያዎ በግምገማ ላይ ነው' : 'Payment Under Review' }}
                        </h4>
                        <p class="mt-0.5 text-xs">
                            {{ $page.props.locale === 'am'
                                ? 'ክፍያዎን በ24 ሰዓት ውስጥ አረጋግጠን ካርዶችዎን እናስጀምራለን። ለአሁን ማዘጋጀትዎን መቀጠል ይችላሉ።'
                                : "We'll verify your payment. You can send your cards within 24 hours after we verify. For now, keep editing."
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="payment.status === 'needs_info'"
                    class="rounded-xl border border-orange-300 bg-orange-50 p-4 text-xs sm:text-sm text-orange-950 dark:border-orange-900 dark:bg-orange-950/60 dark:text-orange-200 flex items-start gap-3 shadow-sm"
                >
                    <span class="text-xl">⚠️</span>
                    <div class="space-y-1">
                        <h4 class="font-bold">
                            {{ $page.props.locale === 'am' ? 'ተጨማሪ ማብራሪያ ያስፈልጋል' : 'Additional Information Requested' }}
                        </h4>
                        <p class="text-xs">
                            {{ $page.props.locale === 'am'
                                ? 'አስተዳዳሪው ለክፍያዎ ተጨማሪ መረጃ ጠይቋል። እባክዎ ከታች የተገለጸውን ማስታወሻ አይተው እንደገና ያስገቡ።'
                                : 'The super admin requested more details to verify your payment. Please review the note and resubmit below.'
                            }}
                        </p>
                        <div v-if="payment.admin_note" class="mt-2 p-2.5 rounded-lg bg-orange-100/70 dark:bg-orange-900/40 font-medium text-xs">
                            <strong>{{ $page.props.locale === 'am' ? 'የአስተዳዳሪው ማስታወሻ፡' : 'Admin Note:' }}</strong> {{ payment.admin_note }}
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="payment.status === 'rejected'"
                    class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-xs sm:text-sm text-rose-950 dark:border-rose-900 dark:bg-rose-950/60 dark:text-rose-200 flex items-start gap-3 shadow-sm"
                >
                    <span class="text-xl">❌</span>
                    <div class="space-y-1">
                        <h4 class="font-bold">
                            {{ $page.props.locale === 'am' ? 'ክፍያው ተቀባይነት አላገኘም' : 'Payment Verification Rejected' }}
                        </h4>
                        <p class="text-xs">
                            {{ $page.props.locale === 'am'
                                ? 'የቀረበው ማረጋገጫ ተቀባይነት አላገኘም። ትክክለኛውን የባንክ/ቴሌብር ደረሰኝ ወይም ማጣቀሻ ቁጥር እንደገና ማስገባት ይችላሉ።'
                                : 'This payment submission was rejected. You may correct your reference or upload the valid receipt below.'
                            }}
                        </p>
                        <div v-if="payment.rejection_reason" class="mt-2 p-2.5 rounded-lg bg-rose-100/70 dark:bg-rose-900/40 font-medium text-xs">
                            <strong>{{ $page.props.locale === 'am' ? 'የውድቅ የተደረገበት ምክንያት፡' : 'Rejection Reason:' }}</strong> {{ payment.rejection_reason }}
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="payment.status === 'approved'"
                    class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-xs sm:text-sm text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/60 dark:text-emerald-200 flex items-start gap-3 shadow-sm"
                >
                    <span class="text-xl">✅</span>
                    <div>
                        <h4 class="font-bold">
                            {{ $page.props.locale === 'am' ? 'ክፍያዎ ተረጋግጧል!' : 'Payment Verified & Approved!' }}
                        </h4>
                        <p class="mt-0.5 text-xs">
                            {{ $page.props.locale === 'am'
                                ? 'የተገዛው ዕቅድ እና የካርድ ኮታዎ ገቢ ተደርጓል። አሁን ግብዣዎችን መላክ ይችላሉ።'
                                : 'Your payment has been verified and your invitation cards quota has been credited to your account.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Two-Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column: Details & Proof Submission -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Payment Overview Card -->
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4 border-b border-gray-150 pb-2 dark:border-gray-700">
                                {{ $page.props.locale === 'am' ? 'የክፍያ መረጃ' : 'Payment Summary' }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                <div>
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የተገዛው ዕቅድ' : 'Purchased Tier' }}:</span>
                                    <p class="font-bold text-gray-900 dark:text-white text-sm mt-0.5">
                                        {{ $page.props.locale === 'am' ? payment.tier?.name_am : payment.tier?.name_en }}
                                    </p>
                                    <p class="text-[11px] text-gray-500">
                                        {{ Number(payment.tier?.card_quota).toLocaleString() }} cards included
                                    </p>
                                </div>

                                <div>
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የሚጠበቀው መጠን' : 'Amount Expected' }}:</span>
                                    <p class="font-bold text-gray-900 dark:text-white text-sm mt-0.5">
                                        {{ Number(payment.amount_expected).toLocaleString() }} ETB
                                    </p>
                                    <p v-if="payment.amount_verified" class="text-[11px] text-emerald-600 font-semibold">
                                        Verified: {{ Number(payment.amount_verified).toLocaleString() }} ETB
                                    </p>
                                </div>

                                <div>
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የግብይት ማጣቀሻ (Reference)' : 'Reference Number' }}:</span>
                                    <p class="font-mono font-bold text-gray-900 dark:text-white mt-0.5">
                                        {{ payment.reference_number || '—' }}
                                    </p>
                                </div>

                                <div>
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የተከፈለበት መንገድ' : 'Payment Method' }}:</span>
                                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">
                                        {{ payment.method?.bank_name || payment.method?.label || payment.method?.type || '—' }}
                                    </p>
                                </div>

                                <div v-if="payment.paid_from">
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የከፋዩ ስም/ስልክ' : 'Paid From' }}:</span>
                                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">
                                        {{ payment.paid_from }}
                                    </p>
                                </div>

                                <div v-if="payment.paid_at_declared">
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የተከፈለበት ቀን' : 'Paid Date (Declared)' }}:</span>
                                    <p class="font-semibold text-gray-900 dark:text-white mt-0.5">
                                        {{ formatDate(payment.paid_at_declared) }}
                                    </p>
                                </div>

                                <div v-if="payment.user_note" class="sm:col-span-2">
                                    <span class="text-gray-400">{{ $page.props.locale === 'am' ? 'የተላከ ማስታወሻ' : 'User Note' }}:</span>
                                    <p class="text-gray-800 dark:text-gray-200 mt-0.5 bg-gray-50 dark:bg-gray-750 p-2 rounded-lg">
                                        {{ payment.user_note }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded Receipt Preview (if available) -->
                        <div v-if="payment.has_receipt && payment.receipt_url" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                            <div class="flex items-center justify-between mb-3 border-b border-gray-150 pb-2 dark:border-gray-700">
                                <h3 class="text-sm font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $page.props.locale === 'am' ? 'የተያያዘ የደረሰኝ ፎቶ' : 'Submitted Receipt Image' }}
                                </h3>
                                <a
                                    :href="payment.receipt_url"
                                    target="_blank"
                                    class="text-xs font-semibold text-amber-600 hover:underline dark:text-amber-400"
                                >
                                    {{ $page.props.locale === 'am' ? 'ሙሉ ምስሉን ክፈት ↗' : 'Open Full Image ↗' }}
                                </a>
                            </div>

                            <div class="relative overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex justify-center p-3">
                                <img
                                    :src="payment.receipt_url"
                                    alt="Payment Receipt"
                                    class="max-h-96 w-auto object-contain rounded-lg shadow-sm"
                                />
                            </div>
                            <p class="text-[11px] text-gray-400 mt-2 text-center">
                                🔒 {{ $page.props.locale === 'am' ? 'ደረሰኝዎ በደህንነት የተጠበቀ ሲሆን ለእርስዎ እና ለአስተዳዳሪ ብቻ ይታያል።' : 'Stored securely in private storage and accessible only via short-lived signed links.' }}
                            </p>
                        </div>

                        <!-- Proof Submission / Resubmission Card -->
                        <div
                            v-if="['awaiting_proof', 'needs_info', 'rejected'].includes(payment.status)"
                            class="rounded-2xl border-2 border-amber-300 dark:border-amber-700 bg-white dark:bg-gray-800 p-6 shadow-sm"
                        >
                            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">
                                {{ payment.status === 'awaiting_proof'
                                    ? ($page.props.locale === 'am' ? 'የክፍያ ማረጋገጫ ያስገቡ' : 'Submit Payment Proof')
                                    : ($page.props.locale === 'am' ? 'የተስተካከለ ማረጋገጫ እንደገና ያስገቡ' : 'Resubmit Corrected Payment Proof')
                                }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                                {{ $page.props.locale === 'am'
                                    ? 'የደረሰኝ ፎቶ ይስቀሉ ወይም የግብይት ማጣቀሻ ቁጥር (Reference) ያስገቡ። ቢያንስ አንዱ ግዴታ ነው።'
                                    : 'Upload a clear screenshot of your receipt OR enter the transaction reference number (or both).'
                                }}
                            </p>

                            <div v-if="errorMessage" class="mb-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 dark:bg-rose-950/60 dark:border-rose-900 dark:text-rose-200">
                                {{ errorMessage }}
                            </div>

                            <form @submit.prevent="submitProof" class="space-y-4">
                                <!-- Receipt File -->
                                <div>
                                    <InputLabel :value="$page.props.locale === 'am' ? 'የደረሰኝ ፎቶ (ምስል)' : 'Receipt Screenshot / Image'" />
                                    <div class="mt-1 flex items-center gap-3">
                                        <input
                                            ref="fileInput"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100 dark:file:bg-amber-950/40 dark:file:text-amber-300 cursor-pointer"
                                            @change="handleFileSelect"
                                        />
                                    </div>
                                    <div v-if="receiptPreview" class="mt-2">
                                        <img :src="receiptPreview" alt="Receipt Preview" class="h-28 w-auto rounded-lg border border-gray-200 dark:border-gray-700 object-cover" />
                                    </div>
                                    <InputError :message="fieldErrors.receipt?.[0]" class="mt-1" />
                                </div>

                                <!-- Reference Number -->
                                <div>
                                    <InputLabel for="show-ref" :value="$page.props.locale === 'am' ? 'የግብይት ማጣቀሻ ቁጥር (Txn Reference Number)' : 'Transaction Reference Number'" />
                                    <TextInput
                                        id="show-ref"
                                        v-model="form.reference_number"
                                        type="text"
                                        class="mt-1 block w-full font-mono text-xs uppercase"
                                        placeholder="e.g. FT24098... or Telebirr Txn ID"
                                    />
                                    <InputError :message="fieldErrors.reference_number?.[0]" class="mt-1" />
                                </div>

                                <!-- Method -->
                                <div v-if="paymentMethods.length > 0">
                                    <InputLabel :value="$page.props.locale === 'am' ? 'የተከፈለበት መንገድ' : 'Payment Method'" />
                                    <select
                                        v-model="form.method_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                    >
                                        <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                                            {{ m.bank_name || m.label || m.type }} ({{ m.account_number || m.phone_number }})
                                        </option>
                                    </select>
                                </div>

                                <!-- User Note -->
                                <div>
                                    <InputLabel :value="$page.props.locale === 'am' ? 'ማስታወሻ (አማራጭ)' : 'Note / Sender details (Optional)'" />
                                    <textarea
                                        v-model="form.user_note"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        placeholder="Any notes or remarks..."
                                    ></textarea>
                                </div>

                                <div class="pt-2">
                                    <PrimaryButton
                                        type="submit"
                                        :disabled="submitting"
                                        class="w-full sm:w-auto bg-amber-600 hover:bg-amber-500 font-bold py-2 px-6"
                                    >
                                        <span v-if="submitting" class="animate-spin mr-1">⏳</span>
                                        <span>{{ submitting ? ($page.props.locale === 'am' ? 'በመላክ ላይ...' : 'Submitting...') : ($page.props.locale === 'am' ? 'ማረጋገጫ አስገባ' : 'Submit for verification') }}</span>
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar (Timeline & Payment Instructions) -->
                    <div class="space-y-6">
                        <!-- Payment Code Card -->
                        <div class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50/50 dark:border-amber-700 dark:bg-amber-950/20 p-5 shadow-sm">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                {{ $page.props.locale === 'am' ? 'የክፍያ መለያ ኮድ' : 'Payment Code' }}
                            </span>
                            <div class="flex items-center justify-between mt-1">
                                <span class="font-mono text-xl font-black text-gray-900 dark:text-white">
                                    {{ payment.payment_code }}
                                </span>
                                <button
                                    type="button"
                                    @click="copyToClipboard(payment.payment_code, 'code-side')"
                                    class="text-xs font-bold text-amber-700 dark:text-amber-300 hover:underline"
                                >
                                    {{ copiedField === 'code-side' ? ($page.props.locale === 'am' ? '✓ ተቀድቷል' : '✓ Copied') : ($page.props.locale === 'am' ? 'ቅዳ' : 'Copy') }}
                                </button>
                            </div>
                            <p class="text-[11px] text-amber-900/80 dark:text-amber-200/80 mt-2">
                                {{ $page.props.locale === 'am'
                                    ? 'ክፍያውን ሲፈጽሙ በምክንያት/ማስታወሻ (Remark) ቦታ ይህንን ኮድ ያስገቡ።'
                                    : 'Please write this payment code in the transfer remark / note so we can match your payment.'
                                }}
                            </p>
                        </div>

                        <!-- Active Receiving Accounts -->
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-3">
                                {{ $page.props.locale === 'am' ? 'የድርጅታችን የሂሳብ ቁጥሮች' : 'Our Receiving Accounts' }}
                            </h4>

                            <div class="space-y-3">
                                <div
                                    v-for="m in paymentMethods"
                                    :key="m.id"
                                    class="rounded-xl border border-gray-150 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-750/70 p-3 text-xs"
                                >
                                    <div class="flex items-start justify-between gap-1">
                                        <div>
                                            <div class="font-bold text-gray-900 dark:text-white">
                                                {{ m.bank_name || m.label || m.type }}
                                            </div>
                                            <div class="text-[11px] text-gray-500">
                                                {{ m.account_name }}
                                            </div>
                                            <div class="font-mono font-bold text-gray-900 dark:text-white mt-0.5">
                                                {{ m.account_number || m.phone_number }}
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            @click="copyToClipboard(m.account_number || m.phone_number, `acc-${m.id}`)"
                                            class="shrink-0 text-[11px] font-semibold text-amber-600 dark:text-amber-400 hover:underline"
                                        >
                                            {{ copiedField === `acc-${m.id}` ? '✓ Copied' : 'Copy' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline / Status Events -->
                        <div v-if="payment.events && payment.events.length > 0" class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-3">
                                {{ $page.props.locale === 'am' ? 'የክፍያ ሂደት ታሪክ' : 'Payment Timeline' }}
                            </h4>

                            <ol class="relative border-l border-gray-200 dark:border-gray-700 ml-2 space-y-4">
                                <li v-for="evt in payment.events" :key="evt.id" class="ml-4">
                                    <div class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full border border-white bg-amber-500 dark:border-gray-800"></div>
                                    <div class="text-xs font-bold text-gray-900 dark:text-white capitalize">
                                        {{ evt.to_status }}
                                    </div>
                                    <time class="block text-[11px] font-normal leading-none text-gray-400 mt-0.5">
                                        {{ formatDate(evt.created_at) }}
                                    </time>
                                    <p v-if="evt.note" class="mt-1 text-xs text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-750 p-2 rounded-lg">
                                        {{ evt.note }}
                                    </p>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
