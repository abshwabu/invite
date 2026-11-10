<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    payment: {
        type: Object,
        required: true,
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'back', 'submitted']);

const fileInput = ref(null);
const receiptFile = ref(null);
const receiptPreview = ref(null);
const isDragging = ref(false);

const form = ref({
    reference_number: '',
    method_id: '',
    amount_declared: '',
    paid_from: '',
    paid_at_declared: '',
    user_note: '',
});

const showExtraFields = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const fieldErrors = ref({});

// Reset or populate when payment changes or modal opens
watch(
    () => props.payment,
    (newPayment) => {
        if (newPayment) {
            form.value.reference_number = newPayment.reference_number || '';
            form.value.method_id = newPayment.method?.id || newPayment.method_id || (props.paymentMethods[0]?.id || '');
            form.value.amount_declared = newPayment.amount_expected || '';
            form.value.paid_from = newPayment.paid_from || '';
            form.value.user_note = newPayment.user_note || '';
        }
    },
    { immediate: true }
);

const handleFileSelect = (e) => {
    const file = e.target.files?.[0];
    processFile(file);
};

const handleDrop = (e) => {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    processFile(file);
};

const processFile = (file) => {
    errorMessage.value = '';
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
        errorMessage.value = 'Please select a valid image file (JPEG, PNG, or WebP).';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        errorMessage.value = 'Image size must not exceed 5MB.';
        return;
    }

    receiptFile.value = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        receiptPreview.value = e.target?.result;
    };
    reader.readAsDataURL(file);
};

const removeFile = () => {
    receiptFile.value = null;
    receiptPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = async () => {
    errorMessage.value = '';
    fieldErrors.value = {};

    const hasRef = form.value.reference_number && form.value.reference_number.trim().length > 0;
    const hasReceipt = receiptFile.value !== null;

    if (!hasRef && !hasReceipt) {
        errorMessage.value = 'Please upload a receipt screenshot OR enter the transaction reference number (or both).';
        return;
    }

    submitting.value = true;

    try {
        const formData = new FormData();
        if (hasRef) {
            formData.append('reference_number', form.value.reference_number.trim());
        }
        if (hasReceipt) {
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

        const response = await axios.post(route('payments.submit', props.payment.id), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json',
            },
        });

        submitting.value = false;
        emit('submitted', response.data?.payment || props.payment);
    } catch (err) {
        submitting.value = false;
        if (err.response?.status === 422) {
            fieldErrors.value = err.response.data?.errors || {};
            errorMessage.value = err.response.data?.message || 'Validation error. Please verify your submitted proof.';
        } else {
            errorMessage.value = err.response?.data?.message || 'An error occurred while submitting payment proof.';
        }
    }
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="$emit('close')">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-150 pb-4 dark:border-gray-700">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $page.props.locale === 'am' ? 'የክፍያ ማረጋገጫ ያስገቡ' : 'Submit Payment Proof' }}
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ $page.props.locale === 'am' ? 'የክፍያ ኮድ፡' : 'Payment Code:' }}
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ payment.payment_code }}</span>
                        • {{ payment.amount_expected ? Number(payment.amount_expected).toLocaleString() : '' }} ETB
                    </p>
                </div>
                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    @click="$emit('close')"
                >
                    ✕
                </button>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="mt-4 rounded-lg bg-rose-50 border border-rose-200 p-3 text-xs text-rose-800 dark:bg-rose-950/60 dark:border-rose-900 dark:text-rose-200">
                {{ errorMessage }}
            </div>

            <form @submit.prevent="submit" class="mt-4 space-y-4">
                <!-- Dropzone for Receipt Image -->
                <div>
                    <InputLabel :value="$page.props.locale === 'am' ? 'የደረሰኝ ፎቶ ወይም ስክሪንሾት' : 'Receipt Image or Screenshot'" />
                    <div
                        v-if="!receiptPreview"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        @click="$refs.fileInput.click()"
                        class="mt-1 flex flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-6 text-center cursor-pointer transition"
                        :class="isDragging ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/20' : 'border-gray-300 dark:border-gray-600 hover:border-amber-500 dark:hover:border-amber-400 bg-gray-50/50 dark:bg-gray-800/50'"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                        <div class="h-10 w-10 text-gray-400 mb-2">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            {{ $page.props.locale === 'am' ? 'ፎቶ ለመጫን እዚህ ይጫኑ ወይም ይጎትቱ' : 'Click to upload receipt or drag & drop' }}
                        </p>
                        <p class="text-[11px] text-gray-400 mt-1">PNG, JPG, or WebP up to 5MB</p>
                    </div>

                    <!-- Receipt Preview -->
                    <div v-else class="mt-1 relative rounded-xl border border-gray-200 dark:border-gray-700 p-2 flex items-center justify-between bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <img :src="receiptPreview" alt="Receipt preview" class="h-14 w-14 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm" />
                            <div class="text-xs truncate">
                                <p class="font-medium text-gray-800 dark:text-gray-200 truncate">{{ receiptFile?.name }}</p>
                                <p class="text-gray-400 text-[11px]">{{ (receiptFile?.size / 1024).toFixed(1) }} KB</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="removeFile"
                            class="px-2.5 py-1 text-xs text-rose-600 hover:text-rose-800 dark:text-rose-400 font-semibold"
                        >
                            {{ $page.props.locale === 'am' ? 'አስወግድ' : 'Remove' }}
                        </button>
                    </div>
                    <InputError :message="fieldErrors.receipt?.[0]" class="mt-1" />
                </div>

                <!-- Divider / OR -->
                <div class="relative flex items-center justify-center my-3">
                    <div class="border-t border-gray-200 dark:border-gray-700 w-full"></div>
                    <span class="bg-white dark:bg-gray-800 px-3 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                        {{ $page.props.locale === 'am' ? 'ወይም' : 'OR' }}
                    </span>
                    <div class="border-t border-gray-200 dark:border-gray-700 w-full"></div>
                </div>

                <!-- Reference Number Input -->
                <div>
                    <InputLabel for="ref-num" :value="$page.props.locale === 'am' ? 'የግብይት ማጣቀሻ ቁጥር (Transaction Reference)' : 'Transaction Reference / Txn ID'" />
                    <TextInput
                        id="ref-num"
                        v-model="form.reference_number"
                        type="text"
                        class="mt-1 block w-full font-mono text-sm tracking-wide"
                        placeholder="e.g. FT24098... or Telebirr Txn ID"
                    />
                    <p class="text-[11px] text-gray-400 mt-1">
                        {{ $page.props.locale === 'am' ? 'በባንክ ወይም ቴሌብር ደረሰኝ/ኤስኤምኤስ ላይ የሚገኘው የግብይት ቁጥር' : 'From your bank transfer SMS or mobile banking receipt' }}
                    </p>
                    <InputError :message="fieldErrors.reference_number?.[0]" class="mt-1" />
                </div>

                <!-- Collapsible Optional Details -->
                <div class="pt-2">
                    <button
                        type="button"
                        @click="showExtraFields = !showExtraFields"
                        class="text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1"
                    >
                        <span>{{ showExtraFields ? '−' : '+' }}</span>
                        <span>{{ $page.props.locale === 'am' ? 'ተጨማሪ መረጃዎች (አማራጭ)' : 'Optional payment details' }}</span>
                    </button>

                    <div v-show="showExtraFields" class="mt-3 space-y-3 p-3 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-150 dark:border-gray-700">
                        <!-- Method Used -->
                        <div v-if="paymentMethods.length > 0">
                            <InputLabel :value="$page.props.locale === 'am' ? 'የተከፈለበት መንገድ' : 'Payment Method Used'" />
                            <select
                                v-model="form.method_id"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option value="">{{ $page.props.locale === 'am' ? 'ዘዴ ይምረጡ' : 'Select Method' }}</option>
                                <option v-for="m in paymentMethods" :key="m.id" :value="m.id">
                                    {{ m.label || m.bank_name || m.type }}
                                </option>
                            </select>
                        </div>

                        <!-- Amount Declared -->
                        <div>
                            <InputLabel :value="$page.props.locale === 'am' ? 'የተከፈለው መጠን (ETB)' : 'Amount Paid (ETB)'" />
                            <TextInput
                                v-model="form.amount_declared"
                                type="number"
                                step="0.01"
                                class="mt-1 block w-full text-xs"
                                placeholder="ETB"
                            />
                        </div>

                        <!-- Paid From -->
                        <div>
                            <InputLabel :value="$page.props.locale === 'am' ? 'የከፋዩ ስም ወይም ስልክ' : 'Paid From (Account Name / Phone)'" />
                            <TextInput
                                v-model="form.paid_from"
                                type="text"
                                class="mt-1 block w-full text-xs"
                                placeholder="Abebe Kebede or 0911..."
                            />
                        </div>

                        <!-- Date Paid -->
                        <div>
                            <InputLabel :value="$page.props.locale === 'am' ? 'የተከፈለበት ቀንና ሰዓት' : 'Date & Time Paid'" />
                            <TextInput
                                v-model="form.paid_at_declared"
                                type="datetime-local"
                                class="mt-1 block w-full text-xs"
                            />
                        </div>

                        <!-- User Note -->
                        <div>
                            <InputLabel :value="$page.props.locale === 'am' ? 'ተጨማሪ ማስታወሻ' : 'Notes / Remarks'" />
                            <textarea
                                v-model="form.user_note"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                placeholder="Any additional details..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-between pt-4 border-t border-gray-150 dark:border-gray-700">
                    <button
                        type="button"
                        class="text-xs font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white"
                        @click="$emit('back')"
                    >
                        ← {{ $page.props.locale === 'am' ? 'ተመለስ' : 'Back' }}
                    </button>

                    <div class="flex items-center gap-2">
                        <SecondaryButton type="button" @click="$emit('close')">
                            {{ $page.props.locale === 'am' ? 'ዝጋ' : 'Cancel' }}
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="submitting"
                            class="bg-amber-600 hover:bg-amber-500 font-bold"
                        >
                            <span v-if="submitting" class="inline-block animate-spin mr-1.5">⏳</span>
                            <span>{{ submitting ? ($page.props.locale === 'am' ? 'በመላክ ላይ...' : 'Submitting...') : ($page.props.locale === 'am' ? 'ለማረጋገጫ አስገባ' : 'Submit for verification') }}</span>
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>
    </Modal>
</template>
