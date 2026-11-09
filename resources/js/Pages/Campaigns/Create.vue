<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    tier: {
        type: Object,
        required: true,
    },
    availableCards: {
        type: Number,
        required: true,
    },
    defaultTemplates: {
        type: Object,
        required: true,
    },
    testSends: {
        type: Object,
        default: () => ({ used: 0, remaining: 3, max: 3 }),
    },
    currentUser: {
        type: Object,
        default: () => ({ phone: '', email: '' }),
    },
});

const channel = ref('sms');
const templateLang = ref('en');
const subject = ref(`${props.event.title} - Invitation from ${props.event.host_names || 'Host'}`);
const body = ref(props.defaultTemplates.en || '');
const linkMode = ref('guest_invitation');
const showConfirmModal = ref(false);

// Test Send State
const testChannel = ref('sms');
const testPhone = ref(props.currentUser.phone || '');
const testEmail = ref(props.currentUser.email || '');
const isSendingTest = ref(false);
const testFeedback = ref('');
const testError = ref('');

// Switch default template language
watch(templateLang, (newLang) => {
    if (newLang === 'en') {
        body.value = props.defaultTemplates.en;
    } else if (newLang === 'am') {
        body.value = props.defaultTemplates.am;
    }
});

// Live GSM-7 vs Unicode segmentation calculation
const GSM_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
const GSM_EXTENDED = "^{}\\[~]|€";

const segmentStats = computed(() => {
    const text = body.value || '';
    const length = Array.from(text).length;

    if (length === 0) {
        return {
            encoding: 'gsm7',
            charCount: 0,
            segmentCount: 1,
            remaining: 160,
            limit: 160,
        };
    }

    // Check if Unicode
    let isGsm = true;
    for (const char of text) {
        if (!GSM_BASIC.includes(char) && !GSM_EXTENDED.includes(char)) {
            isGsm = false;
            break;
        }
    }

    if (!isGsm) {
        const singleLimit = 70;
        const multiLimit = 67;
        const segmentCount = length <= singleLimit ? 1 : Math.ceil(length / multiLimit);
        const limit = segmentCount === 1 ? singleLimit : segmentCount * multiLimit;
        return {
            encoding: 'unicode',
            charCount: length,
            segmentCount: Math.max(1, segmentCount),
            remaining: limit - length,
            limit: segmentCount === 1 ? singleLimit : multiLimit,
        };
    }

    // GSM-7 effective length
    let effective = 0;
    for (const char of text) {
        effective += GSM_EXTENDED.includes(char) ? 2 : 1;
    }

    const singleLimit = 160;
    const multiLimit = 153;
    const segmentCount = effective <= singleLimit ? 1 : Math.ceil(effective / multiLimit);
    const limit = segmentCount === 1 ? singleLimit : segmentCount * multiLimit;

    return {
        encoding: 'gsm7',
        charCount: effective,
        segmentCount: Math.max(1, segmentCount),
        remaining: limit - effective,
        limit: segmentCount === 1 ? singleLimit : multiLimit,
    };
});

// Calculate recipient count and card cost
const recipientCount = computed(() => {
    if (channel.value === 'sms') return props.stats.sms_eligible_count;
    if (channel.value === 'email') return props.stats.email_eligible_count;
    return props.stats.both_eligible_count;
});

const calculatedCost = computed(() => {
    const smsWeight = Math.max(1, props.tier.sms_weight || 1);
    const emailWeight = Math.max(1, props.tier.email_weight || 1);

    if (channel.value === 'sms') {
        return props.stats.sms_eligible_count * smsWeight;
    }
    if (channel.value === 'email') {
        return props.stats.email_eligible_count * emailWeight;
    }
    // Channel is 'both'
    const smsDeliveries = props.stats.sms_eligible_count;
    const emailDeliveries = props.stats.email_eligible_count;
    return (smsDeliveries * smsWeight) + (emailDeliveries * emailWeight);
});

const hasSufficientQuota = computed(() => {
    return props.availableCards >= calculatedCost.value;
});

// Rendered live preview
const previewMessage = computed(() => {
    let t = body.value || '';
    const replacements = {
        '{guest_name}': 'Abebe Kebede',
        '{{guest_name}}': 'Abebe Kebede',
        '{event_title}': props.event.title,
        '{{event_title}}': props.event.title,
        '{date}': props.event.starts_at ? new Date(props.event.starts_at).toLocaleDateString() : '2026-12-25',
        '{{date}}': props.event.starts_at ? new Date(props.event.starts_at).toLocaleDateString() : '2026-12-25',
        '{time}': props.event.starts_at ? new Date(props.event.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '6:00 PM',
        '{{time}}': props.event.starts_at ? new Date(props.event.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '6:00 PM',
        '{venue}': props.event.venue_name || 'Hilton Addis Ballroom',
        '{{venue}}': props.event.venue_name || 'Hilton Addis Ballroom',
        '{host_names}': props.event.host_names || 'The Hosts',
        '{{host_names}}': props.event.host_names || 'The Hosts',
        '{link}': window.location.origin + '/s/a1b2c3d4',
        '{{link}}': window.location.origin + '/s/a1b2c3d4',
    };

    for (const [key, val] of Object.entries(replacements)) {
        t = t.split(key).join(val);
    }
    return t;
});

// Insert placeholder at cursor
const messageInputRef = ref(null);
const insertPlaceholder = (ph) => {
    body.value = (body.value || '') + ph;
};

// Form submission
const form = useForm({
    channel: 'sms',
    body: '',
    subject: '',
    link_mode: 'guest_invitation',
});

const submitCampaign = () => {
    form.channel = channel.value;
    form.body = body.value;
    form.subject = subject.value;
    form.link_mode = linkMode.value;

    form.post(route('events.campaigns.store', { event: props.event.id }), {
        onSuccess: () => {
            showConfirmModal.value = false;
        },
        onError: () => {
            showConfirmModal.value = false;
        },
    });
};

// Test Send execution
const testForm = useForm({
    channel: 'sms',
    phone: '',
    email: '',
    body: '',
    subject: '',
});

const handleTestSend = () => {
    isSendingTest.value = true;
    testFeedback.value = '';
    testError.value = '';

    testForm.channel = testChannel.value;
    testForm.phone = testPhone.value;
    testForm.email = testEmail.value;
    testForm.body = body.value;
    testForm.subject = subject.value;

    testForm.post(route('events.campaigns.test-send', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isSendingTest.value = false;
            testFeedback.value = `Test ${testChannel.value.toUpperCase()} dispatched!`;
        },
        onError: (errs) => {
            isSendingTest.value = false;
            testError.value = errs.test_send || 'Failed to dispatch test message.';
        },
    });
};
</script>

<template>
    <Head :title="`Send Invitations - ${event.title}`" />

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
                        Send Invitations & Messages
                    </h2>
                </div>

                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('events.deliveries', { event: event.id })"
                        class="px-4 py-2 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1.5"
                    >
                        <span>📋 View Delivery Logs</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Top Audience & Eligibility Summary -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-gray-400 font-medium">Total Guests</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stats.total_guests }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-blue-500 font-medium">SMS Eligible (Phone)</div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ stats.sms_eligible_count }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-purple-500 font-medium">Email Eligible</div>
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ stats.email_eligible_count }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-emerald-500 font-medium">Both Channels</div>
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ stats.both_complete_count }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-red-500 font-medium">Opted Out (Skipped)</div>
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ stats.opted_out_count }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left 2 Cols: Channel, Templates, Editor -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Channel Selector -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">
                                1. Select Delivery Channel
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <label
                                    :class="[
                                        'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition',
                                        channel === 'sms'
                                            ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-900/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <input type="radio" value="sms" v-model="channel" class="sr-only" />
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">📱 SMS Only</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-semibold">
                                            {{ stats.sms_eligible_count }} guests
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Cost: {{ tier.sms_weight }} card per recipient
                                    </p>
                                </label>

                                <label
                                    :class="[
                                        'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition',
                                        channel === 'email'
                                            ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-900/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <input type="radio" value="email" v-model="channel" class="sr-only" />
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">✉ Email Only</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 font-semibold">
                                            {{ stats.email_eligible_count }} guests
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Cost: {{ tier.email_weight }} card per recipient
                                    </p>
                                </label>

                                <label
                                    :class="[
                                        'relative flex flex-col p-4 rounded-xl border-2 cursor-pointer transition',
                                        channel === 'both'
                                            ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-900/20'
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'
                                    ]"
                                >
                                    <input type="radio" value="both" v-model="channel" class="sr-only" />
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-gray-900 dark:text-white">🔄 SMS & Email</span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 font-semibold">
                                            {{ stats.both_eligible_count }} guests
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Send to both channels when available
                                    </p>
                                </label>
                            </div>
                        </div>

                        <!-- Message Template & Content -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-5">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    2. Compose Invitation Message
                                </h3>

                                <!-- Language / Template Preset Switcher -->
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs text-gray-400">Template:</span>
                                    <button
                                        type="button"
                                        @click="templateLang = 'en'"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-semibold rounded-lg transition',
                                            templateLang === 'en'
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        English
                                    </button>
                                    <button
                                        type="button"
                                        @click="templateLang = 'am'"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-semibold rounded-lg transition',
                                            templateLang === 'am'
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        አማርኛ (Amharic)
                                    </button>
                                </div>
                            </div>

                            <!-- Subject line (if Email or Both) -->
                            <div v-if="channel === 'email' || channel === 'both'">
                                <InputLabel for="subject" value="Email Subject Line" />
                                <TextInput
                                    id="subject"
                                    type="text"
                                    v-model="subject"
                                    class="mt-1 block w-full"
                                    placeholder="e.g. Wedding Invitation from Abebe & Almaz"
                                    required
                                />
                                <InputError :message="form.errors.subject" class="mt-1" />
                            </div>

                            <!-- Merge Fields Badges -->
                            <div>
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">
                                    Insert Personalized Merge Fields:
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{guest_name}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {guest_name}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{event_title}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {event_title}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{date}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {date}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{time}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {time}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{venue}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {venue}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{host_names}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition"
                                    >
                                        + {host_names}
                                    </button>
                                    <button
                                        type="button"
                                        @click="insertPlaceholder('{link}')"
                                        class="px-2.5 py-1 rounded-md text-xs font-mono bg-indigo-50 dark:bg-indigo-900/40 hover:bg-indigo-100 text-indigo-700 dark:text-indigo-300 font-bold transition"
                                    >
                                        + {link}
                                    </button>
                                </div>
                            </div>

                            <!-- Body Textarea -->
                            <div>
                                <InputLabel for="body" value="Message Body" />
                                <textarea
                                    id="body"
                                    ref="messageInputRef"
                                    rows="5"
                                    v-model="body"
                                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-sans"
                                    placeholder="Enter your invitation message..."
                                    required
                                ></textarea>
                                <InputError :message="form.errors.body" class="mt-1" />
                            </div>

                            <!-- SMS Segment Counter -->
                            <div class="flex flex-wrap items-center justify-between text-xs p-3 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-100 dark:border-gray-800 gap-2">
                                <div class="flex items-center space-x-2">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider',
                                            segmentStats.encoding === 'unicode'
                                                ? 'bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300'
                                                : 'bg-emerald-100 dark:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300'
                                        ]"
                                    >
                                        {{ segmentStats.encoding === 'unicode' ? 'Unicode (Amharic / UCS-2)' : 'Standard GSM-7' }}
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400">
                                        {{ segmentStats.charCount }} characters
                                    </span>
                                </div>

                                <div class="flex items-center space-x-3 text-gray-700 dark:text-gray-300">
                                    <span>
                                        <strong>{{ segmentStats.segmentCount }}</strong> {{ segmentStats.segmentCount === 1 ? 'SMS segment' : 'SMS segments' }}
                                    </span>
                                    <span class="text-gray-400">•</span>
                                    <span>
                                        {{ segmentStats.remaining }} chars left in segment
                                    </span>
                                </div>
                            </div>

                            <!-- Live Sample Preview -->
                            <div>
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
                                    Live Guest Sample Preview:
                                </div>
                                <div class="p-3.5 rounded-xl bg-gray-100 dark:bg-gray-900/80 border border-gray-200 dark:border-gray-700 text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap font-sans">
                                    {{ previewMessage }}
                                </div>
                            </div>
                        </div>

                        <!-- Test Send Panel -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center space-x-1.5">
                                    <span>🧪 Free Test Send to Host</span>
                                </h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                                    {{ testSends.remaining }} of {{ testSends.max }} free sends remaining today
                                </span>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Send a free test preview message to your own phone or email before dispatching to your entire guest list. This does not consume your card quota.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <select
                                        v-model="testChannel"
                                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                    >
                                        <option value="sms">SMS</option>
                                        <option value="email">Email</option>
                                    </select>
                                </div>

                                <div class="sm:col-span-2">
                                    <input
                                        v-if="testChannel === 'sms'"
                                        type="tel"
                                        v-model="testPhone"
                                        placeholder="+251911223344"
                                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                    />
                                    <input
                                        v-else
                                        type="email"
                                        v-model="testEmail"
                                        placeholder="you@example.com"
                                        class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <div class="text-xs">
                                    <span v-if="testFeedback" class="text-emerald-600 font-semibold">{{ testFeedback }}</span>
                                    <span v-if="testError" class="text-red-500 font-semibold">{{ testError }}</span>
                                </div>

                                <button
                                    type="button"
                                    @click="handleTestSend"
                                    :disabled="isSendingTest || testSends.remaining <= 0"
                                    class="px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-900 text-white dark:bg-gray-700 dark:hover:bg-gray-600 text-xs font-semibold disabled:opacity-50 transition"
                                >
                                    {{ isSendingTest ? 'Sending...' : 'Send Free Test' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Quota, Cost & Launch Review -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-5 sticky top-6">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                3. Review & Launch
                            </h3>

                            <div class="space-y-3.5 text-xs">
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Current Plan:</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ tier.name }}</span>
                                </div>

                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Selected Channel:</span>
                                    <span class="font-bold uppercase text-indigo-600 dark:text-indigo-400">{{ channel }}</span>
                                </div>

                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Eligible Recipients:</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ recipientCount }} guests</span>
                                </div>

                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Rate / Weight:</span>
                                    <span class="text-gray-700 dark:text-gray-300">
                                        {{ channel === 'sms' ? tier.sms_weight : (channel === 'email' ? tier.email_weight : `${tier.sms_weight} (SMS) + ${tier.email_weight} (Email)`) }} card(s)
                                    </span>
                                </div>

                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Available Quota:</span>
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400 text-sm">{{ availableCards }} cards</span>
                                </div>

                                <div class="flex items-center justify-between py-3 border-b-2 border-indigo-500/20 text-sm">
                                    <span class="font-bold text-gray-900 dark:text-white">Campaign Cost:</span>
                                    <span class="font-extrabold text-indigo-600 dark:text-indigo-400 text-base">
                                        {{ calculatedCost }} cards
                                    </span>
                                </div>
                            </div>

                            <!-- Insufficient Quota Warning -->
                            <div v-if="!hasSufficientQuota" class="p-4 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-xs text-red-700 dark:text-red-300 space-y-3">
                                <div class="font-bold flex items-center space-x-1.5">
                                    <span>⚠️ Insufficient Quota</span>
                                </div>
                                <p>
                                    You need <strong>{{ calculatedCost }} cards</strong> for this send, but you only have <strong>{{ availableCards }} cards</strong> available in your subscription.
                                </p>
                                <Link
                                    :href="route('plan.index')"
                                    class="inline-block w-full text-center px-3.5 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold transition"
                                >
                                    Upgrade Plan for More Cards →
                                </Link>
                            </div>

                            <!-- Launch Button -->
                            <div class="pt-2">
                                <PrimaryButton
                                    type="button"
                                    @click="showConfirmModal = true"
                                    :disabled="!hasSufficientQuota || recipientCount === 0 || form.processing"
                                    class="w-full justify-center py-3 text-sm font-bold shadow-lg shadow-indigo-600/20 disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Dispatching...' : `Send to ${recipientCount} Guests` }}
                                </PrimaryButton>
                            </div>

                            <p class="text-[11px] text-gray-400 text-center">
                                Quota is atomically reserved. Any permanently failed messages will be automatically refunded to your quota.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Send Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    Confirm Campaign Dispatch
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    You are about to dispatch this campaign to <strong>{{ recipientCount }} guests</strong>.
                </p>

                <div class="p-3.5 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-xs text-indigo-800 dark:text-indigo-200 space-y-1">
                    <div class="flex justify-between">
                        <span>Total Quota Required:</span>
                        <strong class="font-bold">{{ calculatedCost }} cards</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Remaining Quota After:</span>
                        <strong class="font-bold">{{ availableCards - calculatedCost }} cards</strong>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-3">
                    <SecondaryButton @click="showConfirmModal = false" :disabled="form.processing">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="submitCampaign" :disabled="form.processing">
                        {{ form.processing ? 'Dispatching...' : 'Yes, Send Now' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
