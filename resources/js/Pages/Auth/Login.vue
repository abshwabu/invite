<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import TurnstileWidget from '@/Components/TurnstileWidget.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const authMode = ref('phone'); // 'phone' | 'email'
const otpStep = ref('request'); // 'request' | 'verify'
const localDraft = ref(null);

onMounted(() => {
    try {
        const stored = localStorage.getItem('invite_guest_draft');
        if (stored) {
            localDraft.value = JSON.parse(stored);
        }
    } catch (e) {
        // ignore JSON parse error
    }
});

// Email Form
const emailForm = useForm({
    email: '',
    password: '',
    remember: false,
    draft: null,
});

const submitEmail = () => {
    emailForm.draft = localDraft.value;
    emailForm.post(route('login'), {
        onSuccess: () => {
            if (localDraft.value) {
                localStorage.removeItem('invite_guest_draft');
            }
        },
        onFinish: () => emailForm.reset('password'),
    });
};

// Phone OTP Form
const otpForm = useForm({
    phone: '',
    code: '',
    draft: null,
});

const otpStatus = ref('');
const otpError = ref('');
const isSendingOtp = ref(false);
const turnstileToken = ref('');

const requestOtp = async () => {
    otpError.value = '';
    otpStatus.value = '';
    isSendingOtp.value = true;

    try {
        const response = await axios.post(route('login.otp.request'), {
            phone: otpForm.phone,
            'cf-turnstile-response': turnstileToken.value,
        });
        otpForm.phone = response.data.phone;
        otpStatus.value = response.data.message;
        otpStep.value = 'verify';
    } catch (error) {
        if (error.response?.data?.errors?.phone) {
            otpError.value = error.response.data.errors.phone[0];
        } else if (error.response?.data?.errors?.['cf-turnstile-response']) {
            otpError.value = error.response.data.errors['cf-turnstile-response'][0];
        } else if (error.response?.data?.message) {
            otpError.value = error.response.data.message;
        } else {
            otpError.value = 'Failed to send OTP code. Please check your phone number.';
        }
    } finally {
        isSendingOtp.value = false;
    }
};

const verifyOtp = () => {
    otpForm.draft = localDraft.value;
    otpForm.post(route('login.otp.verify'), {
        onSuccess: () => {
            if (localDraft.value) {
                localStorage.removeItem('invite_guest_draft');
            }
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Log in')" />

        <div v-if="localDraft" class="mb-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 p-3 text-xs text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800">
            <strong>{{ $t('Guest Mode') }}:</strong> Your card draft will be automatically saved to your account upon login!
        </div>

        <div v-if="status || otpStatus" class="mb-4 text-sm font-medium text-emerald-600 dark:text-emerald-400">
            {{ status || otpStatus }}
        </div>

        <!-- Auth Tabs -->
        <div class="mb-6 flex rounded-lg border border-gray-200 p-1 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
            <button
                type="button"
                @click="authMode = 'phone'"
                :class="[
                    'flex-1 py-1.5 text-xs font-semibold rounded-md transition-colors',
                    authMode === 'phone'
                        ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-sm'
                        : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                ]"
            >
                {{ $t('Sign in with Phone (OTP)') }}
            </button>
            <button
                type="button"
                @click="authMode = 'email'"
                :class="[
                    'flex-1 py-1.5 text-xs font-semibold rounded-md transition-colors',
                    authMode === 'email'
                        ? 'bg-white dark:bg-gray-700 text-amber-600 dark:text-amber-400 shadow-sm'
                        : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                ]"
            >
                {{ $t('Sign in with Email') }}
            </button>
        </div>

        <!-- Phone (OTP) Mode -->
        <div v-if="authMode === 'phone'">
            <!-- Step 1: Request OTP -->
            <form v-if="otpStep === 'request'" @submit.prevent="requestOtp">
                <div>
                    <InputLabel for="otp_phone" :value="$t('Phone Number')" />
                    <TextInput
                        id="otp_phone"
                        type="tel"
                        class="mt-1 block w-full"
                        v-model="otpForm.phone"
                        placeholder="0911223344 or 0711223344"
                        required
                        autofocus
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Ethiopian numbers (+251 9... or +251 7...)
                    </p>
                    <InputError class="mt-2" :message="otpError || otpForm.errors.phone" />

                    <TurnstileWidget v-model="turnstileToken" class="mt-4" />
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <Link
                        :href="route('register')"
                        class="text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100"
                    >
                        {{ $t('Register') }}
                    </Link>

                    <PrimaryButton :disabled="isSendingOtp" :class="{ 'opacity-50': isSendingOtp }">
                        {{ isSendingOtp ? 'Sending...' : $t('Send Verification Code') }}
                    </PrimaryButton>
                </div>
            </form>

            <!-- Step 2: Verify OTP -->
            <form v-else @submit.prevent="verifyOtp">
                <div class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                    Enter the 6-digit code sent to <strong class="text-amber-600">{{ otpForm.phone }}</strong>
                </div>

                <div>
                    <InputLabel for="otp_code" :value="$t('Verification Code')" />
                    <TextInput
                        id="otp_code"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        class="mt-1 block w-full tracking-widest text-center text-xl font-mono font-bold"
                        v-model="otpForm.code"
                        placeholder="••••••"
                        required
                        autofocus
                    />
                    <InputError class="mt-2" :message="otpForm.errors.code" />
                </div>

                <div class="mt-6 flex items-center justify-between">
                    <button
                        type="button"
                        @click="otpStep = 'request'"
                        class="text-xs text-gray-500 underline hover:text-gray-800 dark:hover:text-gray-200"
                    >
                        {{ $t('Change Phone Number') }}
                    </button>

                    <div class="flex gap-2">
                        <SecondaryButton type="button" @click="requestOtp" :disabled="isSendingOtp">
                            {{ $t('Resend Code') }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="otpForm.processing" :class="{ 'opacity-50': otpForm.processing }">
                            {{ $t('Verify & Sign In') }}
                        </PrimaryButton>
                    </div>
                </div>
            </form>
        </div>

        <!-- Email Mode -->
        <form v-else @submit.prevent="submitEmail">
            <div>
                <InputLabel for="email" :value="$t('Email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="emailForm.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="emailForm.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="$t('Password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="emailForm.password"
                    required
                    autocomplete="current-password"
                />

                <InputError class="mt-2" :message="emailForm.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="emailForm.remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $t('Remember me') }}
                    </span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-white"
                >
                    {{ $t('Forgot your password?') }}
                </Link>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('register')"
                        class="text-xs text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100"
                    >
                        {{ $t('Register') }}
                    </Link>

                    <PrimaryButton
                        :class="{ 'opacity-25': emailForm.processing }"
                        :disabled="emailForm.processing"
                    >
                        {{ $t('Log in') }}
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
