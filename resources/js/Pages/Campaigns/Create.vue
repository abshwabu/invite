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
    isCustom: {
        type: Boolean,
        default: false,
    },
    audienceCounts: {
        type: Object,
        default: () => ({}),
    },
    guests: {
        type: Array,
        default: () => [],
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
        default: () => ({ phone: '', email: '', is_new_account: false }),
    },
});

const channel = ref('sms');
const audienceType = ref('all');
const selectedGuestIds = ref([]);
const manualGuestSearch = ref('');
const templateLang = ref('en');
const subject = ref(props.isCustom ? `Update regarding ${props.event.title}` : `${props.event.title} - Invitation from ${props.event.host_names || 'Host'}`);
const body = ref(props.defaultTemplates.en || '');
const linkMode = ref(props.isCustom ? 'event_page' : 'guest_invitation');
const customUrl = ref('');
const sendTiming = ref('now'); // 'now' or 'schedule'
const scheduledAt = ref('');
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

// Filter guests based on audience criteria
const resolvedAudience = computed(() => {
    let list = props.guests || [];

    switch (audienceType.value) {
        case 'rsvp_pending':
            list = list.filter((g) => g.rsvp_status === 'pending');
            break;
        case 'rsvp_yes':
            list = list.filter((g) => g.rsvp_status === 'yes');
            break;
        case 'rsvp_no':
            list = list.filter((g) => g.rsvp_status === 'no');
            break;
        case 'rsvp_maybe':
            list = list.filter((g) => g.rsvp_status === 'maybe');
            break;
        case 'checked_in':
            list = list.filter((g) => g.checked_in_at !== null && g.checked_in_at !== undefined);
            break;
        case 'not_checked_in':
            list = list.filter((g) => !g.checked_in_at);
            break;
        case 'not_viewed':
            list = list.filter((g) => !g.viewed_at);
            break;
        case 'manual':
            list = list.filter((g) => selectedGuestIds.value.includes(g.id));
            break;
        case 'all':
        default:
            break;
    }

    const totalAudience = list.length;
    const nonOptedOut = list.filter((g) => !g.opted_out);
    const optedOutCount = totalAudience - nonOptedOut.length;

    const eligible = nonOptedOut.filter((g) => {
        const hasPhone = Boolean(g.phone && String(g.phone).trim());
        const hasEmail = Boolean(g.email && String(g.email).trim());

        if (channel.value === 'sms') return hasPhone;
        if (channel.value === 'email') return hasEmail;
        return hasPhone || hasEmail;
    });

    const missingContactCount = nonOptedOut.length - eligible.length;

    let smsCount = 0;
    let emailCount = 0;

    for (const g of eligible) {
        const hasPhone = Boolean(g.phone && String(g.phone).trim());
        const hasEmail = Boolean(g.email && String(g.email).trim());

        if (channel.value === 'sms') {
            smsCount++;
        } else if (channel.value === 'email') {
            emailCount++;
        } else {
            if (hasPhone) smsCount++;
            if (hasEmail) emailCount++;
        }
    }

    return {
        totalAudience,
        optedOutCount,
        missingContactCount,
        eligibleCount: eligible.length,
        smsCount,
        emailCount,
    };
});

// Card calculation
const calculatedCost = computed(() => {
    const smsWeight = Math.max(1, props.tier.sms_weight || 1);
    const emailWeight = Math.max(1, props.tier.email_weight || 1);

    return (resolvedAudience.value.smsCount * smsWeight) + (resolvedAudience.value.emailCount * emailWeight);
});

const hasSufficientQuota = computed(() => {
    return props.availableCards >= calculatedCost.value;
});

// Filtered guests for manual checkbox list
const filteredManualGuests = computed(() => {
    const q = manualGuestSearch.value.toLowerCase().trim();
    if (!q) return props.guests;
    return props.guests.filter((g) => {
        return (g.name && g.name.toLowerCase().includes(q)) ||
               (g.phone && g.phone.toLowerCase().includes(q)) ||
               (g.email && g.email.toLowerCase().includes(q));
    });
});

const toggleSelectAllManual = () => {
    if (selectedGuestIds.value.length === props.guests.length) {
        selectedGuestIds.value = [];
    } else {
        selectedGuestIds.value = props.guests.map((g) => g.id);
    }
};

// Rendered live preview
const previewMessage = computed(() => {
    let t = body.value || '';
    let linkTarget = window.location.origin + '/s/a1b2c3d4';

    if (linkMode.value === 'none') {
        linkTarget = '';
    } else if (linkMode.value === 'event_page') {
        linkTarget = window.location.origin + '/s/evt_8x9y';
    } else if (linkMode.value === 'custom_url') {
        linkTarget = window.location.origin + '/s/cst_link';
    }

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
        '{link}': linkTarget,
        '{{link}}': linkTarget,
    };

    for (const [key, val] of Object.entries(replacements)) {
        t = t.split(key).join(val);
    }

    if (linkTarget && !body.value.includes('{link}') && !body.value.includes('{{link}}')) {
        t += '\n\n' + linkTarget;
    }

    return t;
});

// Insert placeholder
const insertPlaceholder = (ph) => {
    body.value = (body.value || '') + ph;
};

// Form submission
const form = useForm({
    type: props.isCustom ? 'custom' : 'invitation',
    channel: 'sms',
    audience_type: 'all',
    guest_ids: [],
    body: '',
    subject: '',
    link_mode: 'guest_invitation',
    custom_url: '',
    scheduled_at: null,
});

const submitCampaign = () => {
    form.type = props.isCustom ? 'custom' : 'invitation';
    form.channel = channel.value;
    form.audience_type = audienceType.value;
    form.guest_ids = audienceType.value === 'manual' ? selectedGuestIds.value : [];
    form.body = body.value;
    form.subject = subject.value;
    form.link_mode = linkMode.value;
    form.custom_url = customUrl.value;
    form.scheduled_at = sendTiming.value === 'schedule' ? scheduledAt.value : null;

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
    <Head :title="`${isCustom ? 'Send Custom Message' : 'Send Invitations'} - ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('events.campaigns.index', { event: event.id })" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ← Campaigns
                        </Link>
                        <span class="text-gray-300">/</span>
                        <Link :href="route('events.guests.index', { event: event.id })" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            Guests
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ event.title }}</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 mt-1 flex items-center space-x-2">
                        <span>{{ isCustom ? '✉️ Send Custom Message' : '📨 Send Invitations' }}</span>
                        <span v-if="isCustom" class="text-xs px-2.5 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 font-semibold">
                            Custom Type
                        </span>
                    </h2>
                </div>

                <div class="flex items-center space-x-2">
                    <Link
                        v-if="!isCustom && tier.can_custom_messages"
                        :href="route('events.messages.create', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl border border-purple-500/50 text-purple-600 dark:text-purple-400 hover:bg-purple-500/10 text-xs font-semibold transition"
                    >
                        Switch to Custom Message
                    </Link>

                    <Link
                        :href="route('events.deliveries', { event: event.id })"
                        class="px-3.5 py-1.5 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-semibold transition flex items-center space-x-1"
                    >
                        <span>📋 Delivery Logs</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Top Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-gray-400 font-medium">Selected Audience</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ resolvedAudience.totalAudience }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-emerald-500 font-medium">Eligible Recipients</div>
                        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ resolvedAudience.eligibleCount }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-blue-500 font-medium">SMS Dispatches</div>
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ resolvedAudience.smsCount }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-purple-500 font-medium">Email Dispatches</div>
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ resolvedAudience.emailCount }}</div>
                    </div>
                    <div class="p-4 rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm">
                        <div class="text-xs text-red-500 font-medium">Excluded (Opt-Out/No-Contact)</div>
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">
                            {{ resolvedAudience.optedOutCount + resolvedAudience.missingContactCount }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left 2 Cols: Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Step 1: Channel -->
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
                                            {{ resolvedAudience.smsCount }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        {{ tier.sms_weight }} card(s) per SMS
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
                                            {{ resolvedAudience.emailCount }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        {{ tier.email_weight }} card(s) per Email
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
                                            Both
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Send to both channels when contact exists
                                    </p>
                                </label>
                            </div>
                        </div>

                        <!-- Step 2: Audience Filter -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    2. Target Audience Filter
                                </h3>
                                <span class="text-xs font-semibold text-gray-500">
                                    {{ resolvedAudience.eligibleCount }} guests will receive message
                                </span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                                <button
                                    type="button"
                                    @click="audienceType = 'all'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'all'
                                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>👥 All Guests</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.all || 0 }} total</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'rsvp_yes'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'rsvp_yes'
                                            ? 'border-emerald-600 bg-emerald-50/50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>✅ RSVP: Attending</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.rsvp_yes || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'rsvp_pending'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'rsvp_pending'
                                            ? 'border-amber-600 bg-amber-50/50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>⏳ RSVP: Pending</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.rsvp_pending || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'rsvp_maybe'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'rsvp_maybe'
                                            ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>🤔 RSVP: Maybe</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.rsvp_maybe || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'rsvp_no'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'rsvp_no'
                                            ? 'border-red-600 bg-red-50/50 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>❌ RSVP: Declined</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.rsvp_no || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'checked_in'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'checked_in'
                                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>🎟 Checked In (Attended)</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.checked_in || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'not_checked_in'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'not_checked_in'
                                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>🚪 Not Checked In</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.not_checked_in || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'not_viewed'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'not_viewed'
                                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>👁 Not Yet Viewed</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ audienceCounts.not_viewed || 0 }} guests</div>
                                </button>

                                <button
                                    type="button"
                                    @click="audienceType = 'manual'"
                                    :class="[
                                        'p-3 text-left rounded-xl border transition text-xs font-semibold',
                                        audienceType === 'manual'
                                            ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                            : 'border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-300'
                                    ]"
                                >
                                    <div>✍️ Manual Selection</div>
                                    <div class="text-[11px] font-normal text-gray-500 mt-0.5">{{ selectedGuestIds.length }} selected</div>
                                </button>
                            </div>

                            <!-- Manual Guest Selector Drawer/Table -->
                            <div v-if="audienceType === 'manual'" class="mt-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-bold text-gray-700 dark:text-gray-200">
                                        Choose Specific Guests:
                                    </div>
                                    <button
                                        type="button"
                                        @click="toggleSelectAllManual"
                                        class="text-xs text-indigo-600 font-semibold hover:underline"
                                    >
                                        {{ selectedGuestIds.length === guests.length ? 'Deselect All' : 'Select All' }}
                                    </button>
                                </div>

                                <input
                                    type="text"
                                    v-model="manualGuestSearch"
                                    placeholder="Search guests by name, phone or email..."
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                />

                                <div class="max-h-48 overflow-y-auto divide-y divide-gray-200 dark:divide-gray-800 border rounded-lg bg-white dark:bg-gray-800">
                                    <label
                                        v-for="guest in filteredManualGuests"
                                        :key="guest.id"
                                        class="flex items-center space-x-3 p-2.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="guest.id"
                                            v-model="selectedGuestIds"
                                            class="rounded text-indigo-600"
                                        />
                                        <div class="flex-1">
                                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ guest.name }}</span>
                                            <span class="text-gray-400 text-[11px] ml-2 font-mono">
                                                {{ guest.phone || guest.email || 'No contact' }}
                                            </span>
                                            <span v-if="guest.opted_out" class="ml-2 px-1.5 py-0.2 rounded bg-red-100 text-red-700 text-[10px]">
                                                Opted out
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Exclusions Notice -->
                            <div v-if="resolvedAudience.optedOutCount > 0 || resolvedAudience.missingContactCount > 0" class="text-xs p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800 flex items-center justify-between">
                                <span>
                                    ⚠️ Exclusions: <strong>{{ resolvedAudience.optedOutCount }}</strong> opted-out guests and <strong>{{ resolvedAudience.missingContactCount }}</strong> guests lacking {{ channel.toUpperCase() }} contact info were automatically excluded.
                                </span>
                            </div>
                        </div>

                        <!-- Step 3: Link Mode -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                3. Link Options ({link} field)
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex flex-col',
                                        linkMode === 'guest_invitation' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700'
                                    ]"
                                >
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" value="guest_invitation" v-model="linkMode" class="text-indigo-600" />
                                        <span>Personal Invitation Card</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 font-normal mt-1 ml-5">
                                        Unique personalized link to /i/{token} for each guest
                                    </span>
                                </label>

                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex flex-col',
                                        linkMode === 'event_page' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700'
                                    ]"
                                >
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" value="event_page" v-model="linkMode" class="text-indigo-600" />
                                        <span>Public Event Page</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 font-normal mt-1 ml-5">
                                        Short link to the public event info page /e/{public_slug}
                                    </span>
                                </label>

                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex flex-col',
                                        linkMode === 'custom_url' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700',
                                        !tier.can_custom_links ? 'opacity-60 cursor-not-allowed' : ''
                                    ]"
                                >
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" value="custom_url" v-model="linkMode" :disabled="!tier.can_custom_links" class="text-indigo-600" />
                                        <span>Custom External URL</span>
                                        <span v-if="!tier.can_custom_links" class="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.2 rounded">Tier Gated</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 font-normal mt-1 ml-5">
                                        Redirects through our tracked short link with click counter
                                    </span>
                                </label>

                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex flex-col',
                                        linkMode === 'none' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700'
                                    ]"
                                >
                                    <div class="flex items-center space-x-2">
                                        <input type="radio" value="none" v-model="linkMode" class="text-indigo-600" />
                                        <span>No Link</span>
                                    </div>
                                    <span class="text-[11px] text-gray-500 font-normal mt-1 ml-5">
                                        Pure text message without any links
                                    </span>
                                </label>
                            </div>

                            <!-- Custom URL Input -->
                            <div v-if="linkMode === 'custom_url'" class="pt-2">
                                <InputLabel for="custom_url" value="Enter Custom HTTPS Destination URL" />
                                <TextInput
                                    id="custom_url"
                                    type="url"
                                    v-model="customUrl"
                                    placeholder="https://example.com/gallery or https://t.me/event_group"
                                    class="mt-1 block w-full text-xs font-mono"
                                    required
                                />
                                <div class="text-[11px] text-gray-500 mt-1">
                                    Must use HTTPS. Third-party shorteners (e.g. bit.ly) and IP addresses are prohibited.
                                    <span v-if="currentUser.is_new_account" class="text-amber-600 font-semibold block mt-0.5">
                                        🛡️ Note: Because your account is newer than 3 days, messages with custom URLs will be held for brief admin review.
                                    </span>
                                </div>
                                <InputError :message="form.errors.custom_url" class="mt-1" />
                            </div>
                        </div>

                        <!-- Step 4: Compose Message -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                    4. Message Content
                                </h3>

                                <div class="flex items-center space-x-2">
                                    <span class="text-xs text-gray-400">Language:</span>
                                    <button
                                        type="button"
                                        @click="templateLang = 'en'"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-semibold rounded-lg transition',
                                            templateLang === 'en' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        English
                                    </button>
                                    <button
                                        type="button"
                                        @click="templateLang = 'am'"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-semibold rounded-lg transition',
                                            templateLang === 'am' ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                                        ]"
                                    >
                                        አማርኛ
                                    </button>
                                </div>
                            </div>

                            <div v-if="channel === 'email' || channel === 'both'">
                                <InputLabel for="subject" value="Email Subject Line" />
                                <TextInput
                                    id="subject"
                                    type="text"
                                    v-model="subject"
                                    class="mt-1 block w-full text-xs"
                                    required
                                />
                                <InputError :message="form.errors.subject" class="mt-1" />
                            </div>

                            <!-- Merge Buttons -->
                            <div>
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">
                                    Insert Merge Placeholders:
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" @click="insertPlaceholder('{guest_name}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {guest_name}
                                    </button>
                                    <button type="button" @click="insertPlaceholder('{event_title}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {event_title}
                                    </button>
                                    <button type="button" @click="insertPlaceholder('{date}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {date}
                                    </button>
                                    <button type="button" @click="insertPlaceholder('{time}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {time}
                                    </button>
                                    <button type="button" @click="insertPlaceholder('{venue}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {venue}
                                    </button>
                                    <button type="button" @click="insertPlaceholder('{host_names}')" class="px-2.5 py-1 rounded text-xs font-mono bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-700 dark:text-gray-200">
                                        + {host_names}
                                    </button>
                                    <button v-if="linkMode !== 'none'" type="button" @click="insertPlaceholder('{link}')" class="px-2.5 py-1 rounded text-xs font-mono bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold">
                                        + {link}
                                    </button>
                                </div>
                            </div>

                            <!-- Body Textarea -->
                            <div>
                                <textarea
                                    id="body"
                                    rows="4"
                                    v-model="body"
                                    class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-xs font-sans"
                                    placeholder="Write your message..."
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
                                                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/60 dark:text-purple-300'
                                                : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/60 dark:text-emerald-300'
                                        ]"
                                    >
                                        {{ segmentStats.encoding === 'unicode' ? 'Unicode (Amharic / UCS-2)' : 'Standard GSM-7' }}
                                    </span>
                                    <span class="text-gray-500">
                                        {{ segmentStats.charCount }} chars
                                    </span>
                                </div>

                                <div class="flex items-center space-x-3 text-gray-700 dark:text-gray-300">
                                    <span>
                                        <strong>{{ segmentStats.segmentCount }}</strong> {{ segmentStats.segmentCount === 1 ? 'SMS segment' : 'segments' }}
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

                        <!-- Step 5: Schedule or Send Now -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                5. Send Timing
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex items-center space-x-2',
                                        sendTiming === 'now' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700'
                                    ]"
                                >
                                    <input type="radio" value="now" v-model="sendTiming" class="text-indigo-600" />
                                    <span>⚡ Send Immediately</span>
                                </label>

                                <label
                                    :class="[
                                        'p-3 rounded-xl border cursor-pointer text-xs transition flex items-center space-x-2',
                                        sendTiming === 'schedule' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-900/30 font-bold' : 'border-gray-200 dark:border-gray-700'
                                    ]"
                                >
                                    <input type="radio" value="schedule" v-model="sendTiming" class="text-indigo-600" />
                                    <span>⏰ Schedule for Later</span>
                                </label>
                            </div>

                            <div v-if="sendTiming === 'schedule'" class="pt-2">
                                <InputLabel for="scheduled_at" value="Select Date & Time (Event Timezone)" />
                                <TextInput
                                    id="scheduled_at"
                                    type="datetime-local"
                                    v-model="scheduledAt"
                                    class="mt-1 block w-full text-xs"
                                    required
                                />
                                <p class="text-[11px] text-gray-500 mt-1">
                                    Quota is reserved when the send actually starts. You can cancel scheduled campaigns anytime prior to execution.
                                </p>
                            </div>
                        </div>

                        <!-- Test Send Panel -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center space-x-1.5">
                                    <span>🧪 Free Test Send to Host</span>
                                </h3>
                                <span class="text-xs text-gray-500">
                                    {{ testSends.remaining }} of {{ testSends.max }} free sends left today
                                </span>
                            </div>

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
                                    class="px-3.5 py-1.5 rounded-xl bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold disabled:opacity-50 transition"
                                >
                                    {{ isSendingTest ? 'Sending...' : 'Send Free Test' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Review & Launch -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4 sticky top-6">
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                6. Review & Launch
                            </h3>

                            <div class="space-y-3 text-xs">
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Plan:</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ tier.name }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Channel:</span>
                                    <span class="font-bold uppercase text-indigo-600">{{ channel }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Target Audience:</span>
                                    <span class="font-bold capitalize text-gray-800 dark:text-gray-200">{{ audienceType.replace('_', ' ') }}</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Eligible Recipients:</span>
                                    <span class="font-bold text-gray-900 dark:text-white">{{ resolvedAudience.eligibleCount }} guests</span>
                                </div>
                                <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-500">Available Quota:</span>
                                    <span class="font-bold text-emerald-600 text-sm">{{ availableCards }} cards</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b-2 border-indigo-500/20 text-sm">
                                    <span class="font-bold text-gray-900 dark:text-white">Campaign Cost:</span>
                                    <span class="font-extrabold text-indigo-600 text-base">{{ calculatedCost }} cards</span>
                                </div>
                            </div>

                            <!-- Insufficient Quota Warning -->
                            <div v-if="!hasSufficientQuota" class="p-3.5 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-xs text-red-700 dark:text-red-300 space-y-2">
                                <div class="font-bold">⚠️ Insufficient Quota</div>
                                <p>You need {{ calculatedCost }} cards, but have only {{ availableCards }} available.</p>
                                <Link :href="route('plan.index')" class="block text-center py-1.5 rounded bg-red-600 hover:bg-red-700 text-white font-semibold">
                                    Upgrade Plan →
                                </Link>
                            </div>

                            <!-- Launch Button -->
                            <PrimaryButton
                                type="button"
                                @click="showConfirmModal = true"
                                :disabled="resolvedAudience.eligibleCount === 0 || form.processing || (!hasSufficientQuota && sendTiming === 'now')"
                                class="w-full justify-center py-3 text-sm font-bold shadow-lg shadow-indigo-600/20 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Processing...' : (sendTiming === 'schedule' ? `Schedule for ${resolvedAudience.eligibleCount} Guests` : `Send to ${resolvedAudience.eligibleCount} Guests`) }}
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Modal -->
        <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-700 space-y-4">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ sendTiming === 'schedule' ? 'Confirm Campaign Schedule' : 'Confirm Immediate Dispatch' }}
                </h3>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ sendTiming === 'schedule'
                        ? `You are scheduling this campaign to run on ${scheduledAt} for ${resolvedAudience.eligibleCount} guests.`
                        : `You are about to dispatch this campaign to ${resolvedAudience.eligibleCount} guests.`
                    }}
                </p>

                <div class="p-3 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-xs text-indigo-800 dark:text-indigo-200 space-y-1">
                    <div class="flex justify-between">
                        <span>Total Quota Cost:</span>
                        <strong>{{ calculatedCost }} cards</strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Quota Balance After:</span>
                        <strong>{{ availableCards - calculatedCost }} cards</strong>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-3">
                    <SecondaryButton @click="showConfirmModal = false" :disabled="form.processing">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton @click="submitCampaign" :disabled="form.processing">
                        {{ form.processing ? 'Dispatching...' : 'Yes, Confirm' }}
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
