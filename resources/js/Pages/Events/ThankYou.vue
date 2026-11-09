<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    templates: {
        type: Array,
        default: () => [],
    },
    hasDesign: {
        type: Boolean,
        default: false,
    },
    defaultAudience: {
        type: String,
        default: 'all',
    },
    audienceExplanation: {
        type: String,
        default: '',
    },
    audienceCounts: {
        type: Object,
        default: () => ({ checked_in: 0, rsvp_yes: 0, all: 0 }),
    },
    consumesQuota: {
        type: Boolean,
        default: false,
    },
    availableQuota: {
        type: Number,
        default: 0,
    },
    defaultScheduleTime: {
        type: String,
        default: '',
    },
    isScheduledDefault: {
        type: Boolean,
        default: false,
    },
    defaults: {
        type: Object,
        default: () => ({ message_en: '', message_am: '', subject: '' }),
    },
    guests: {
        type: Array,
        default: () => [],
    },
});

// Form State
const selectedLang = ref('en');
const timingMode = ref(props.isScheduledDefault ? 'scheduled' : 'now');
const scheduleHours = ref(12);

const form = useForm({
    type: 'thank_you',
    channel: 'sms',
    audience_type: props.defaultAudience,
    guest_ids: [],
    subject: props.defaults.subject || 'Thank you for joining us!',
    body: props.defaults.message_en,
    link_mode: 'thank_you',
    schedule_hours_after_end: 12,
    scheduled_at: null,
    thankyou_note: props.event.thankyou_note || '',
    thankyou_photo_url: props.event.thankyou_photo_url || '',
});

const selectLanguageTemplate = (lang) => {
    selectedLang.value = lang;
    if (lang === 'am') {
        form.body = props.defaults.message_am;
    } else {
        form.body = props.defaults.message_en;
    }
};

const insertMergeField = (field) => {
    form.body += ` ${field}`;
};

// Selected Audience computation
const currentRecipientCount = computed(() => {
    if (form.audience_type === 'manual') {
        return form.guest_ids.length;
    }
    return props.audienceCounts[form.audience_type] || 0;
});

// Calculate scheduled date/time string
const calculatedScheduleDisplay = computed(() => {
    const base = new Date(props.event.ends_at || props.event.starts_at);
    base.setHours(base.getHours() + Number(scheduleHours.value));
    return base.toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
});

const submitCampaign = () => {
    if (timingMode.value === 'scheduled') {
        form.schedule_hours_after_end = Number(scheduleHours.value);
    } else {
        form.schedule_hours_after_end = null;
        form.scheduled_at = null;
    }

    form.post(route('events.campaigns.store', { event: props.event.id }));
};
</script>

<template>
    <Head :title="`Thank-You Cards • ${event.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center space-x-2">
                        <Link :href="route('dashboard')" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            ← Dashboard
                        </Link>
                        <span class="text-gray-300">/</span>
                        <Link :href="route('events.guests.index', { event: event.id })" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                            {{ event.title }}
                        </Link>
                        <span class="text-gray-300">/</span>
                        <span class="text-xs font-semibold text-purple-600 dark:text-purple-400">Thank-You Cards</span>
                    </div>
                    <h2 class="text-xl font-bold leading-tight text-gray-900 dark:text-gray-100 mt-1">
                        {{ event.title }} • Send Thank-You Cards
                    </h2>
                </div>

                <div class="flex items-center space-x-3">
                    <Link
                        :href="route('events.thank-you-design', { event: event.id })"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1.5 shadow-sm"
                    >
                        <span>🎨</span>
                        <span>Edit Thank-You Design</span>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Quota & Pricing Banner -->
            <div
                v-if="!consumesQuota"
                class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between"
            >
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">✨</span>
                    <div>
                        <h4 class="text-sm font-bold text-emerald-800 dark:text-emerald-200">
                            Included In Your Package (0 Cards Quota)
                        </h4>
                        <p class="text-xs text-emerald-700 dark:text-emerald-300">
                            Thank-you cards are fully included in your subscription and do not consume your card balance.
                        </p>
                    </div>
                </div>
                <span class="text-xs font-bold bg-emerald-200 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300 px-3 py-1 rounded-full">
                    Free with Tier
                </span>
            </div>

            <div
                v-else
                class="p-4 rounded-2xl bg-purple-500/10 border border-purple-500/30 flex items-center justify-between"
            >
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">💳</span>
                    <div>
                        <h4 class="text-sm font-bold text-purple-800 dark:text-purple-200">
                            Quota Consumption Notice
                        </h4>
                        <p class="text-xs text-purple-700 dark:text-purple-300">
                            Your current plan consumes cards for thank-you sends. Available balance: {{ availableQuota }} cards.
                        </p>
                    </div>
                </div>
                <Link
                    :href="route('plan.index')"
                    class="text-xs font-bold px-3 py-1 bg-purple-600 hover:bg-purple-700 text-white rounded-xl"
                >
                    View Plan
                </Link>
            </div>

            <form @submit.prevent="submitCampaign" class="space-y-6">
                <!-- STEP 1: Card Design & Visual Check -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">1. Thank-You Card Design</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Customize the card visual sent to guests.</p>
                        </div>
                        <Link
                            :href="route('events.thank-you-design', { event: event.id })"
                            class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-800 dark:text-gray-200 text-xs font-semibold rounded-xl"
                        >
                            {{ hasDesign ? 'Edit in Canvas Editor ↗' : 'Choose / Create Design ↗' }}
                        </Link>
                    </div>

                    <div v-if="!hasDesign" class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-800 dark:text-amber-200 flex items-center justify-between">
                        <span>No thank-you design customized yet. Default template will be used, or click "Edit in Canvas Editor" to design one.</span>
                    </div>
                    <div v-else class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl text-xs text-gray-600 dark:text-gray-400 flex items-center space-x-2">
                        <span class="text-emerald-500 font-bold">✓</span>
                        <span>Custom thank-you card design is ready and will render with each guest's name on <code>/t/{token}</code>.</span>
                    </div>
                </div>

                <!-- STEP 2: Audience Selection -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">2. Target Audience</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Select who will receive the thank-you note.</p>
                    </div>

                    <!-- Audience explanation badge -->
                    <div class="p-3 bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 rounded-xl text-xs text-purple-700 dark:text-purple-300">
                        <strong>Automatic Recommendation:</strong> {{ audienceExplanation }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label
                            class="p-4 rounded-xl border-2 cursor-pointer transition flex flex-col justify-between"
                            :class="form.audience_type === 'checked_in' ? 'border-purple-600 bg-purple-50/30 dark:bg-purple-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center space-x-2">
                                <input type="radio" v-model="form.audience_type" value="checked_in" class="text-purple-600 focus:ring-purple-500" />
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100">Checked-In Attendees</span>
                            </div>
                            <div class="text-xl font-extrabold text-purple-600 dark:text-purple-400 mt-2">
                                {{ audienceCounts.checked_in }}
                                <span class="text-[11px] font-normal text-gray-500">guests</span>
                            </div>
                        </label>

                        <label
                            class="p-4 rounded-xl border-2 cursor-pointer transition flex flex-col justify-between"
                            :class="form.audience_type === 'rsvp_yes' ? 'border-purple-600 bg-purple-50/30 dark:bg-purple-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center space-x-2">
                                <input type="radio" v-model="form.audience_type" value="rsvp_yes" class="text-purple-600 focus:ring-purple-500" />
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100">RSVP'd "Yes"</span>
                            </div>
                            <div class="text-xl font-extrabold text-purple-600 dark:text-purple-400 mt-2">
                                {{ audienceCounts.rsvp_yes }}
                                <span class="text-[11px] font-normal text-gray-500">guests</span>
                            </div>
                        </label>

                        <label
                            class="p-4 rounded-xl border-2 cursor-pointer transition flex flex-col justify-between"
                            :class="form.audience_type === 'all' ? 'border-purple-600 bg-purple-50/30 dark:bg-purple-950/20' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300'"
                        >
                            <div class="flex items-center space-x-2">
                                <input type="radio" v-model="form.audience_type" value="all" class="text-purple-600 focus:ring-purple-500" />
                                <span class="text-xs font-bold text-gray-900 dark:text-gray-100">All Guests</span>
                            </div>
                            <div class="text-xl font-extrabold text-purple-600 dark:text-purple-400 mt-2">
                                {{ audienceCounts.all }}
                                <span class="text-[11px] font-normal text-gray-500">guests</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- STEP 3: Delivery Channel & Message Content -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">3. Channel & Message</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Compose the SMS/Email delivered with the link to their card.</p>
                        </div>

                        <!-- Language Template Selector -->
                        <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-700 p-1 rounded-xl text-xs">
                            <button
                                type="button"
                                @click="selectLanguageTemplate('en')"
                                class="px-2.5 py-1 rounded-lg font-semibold transition"
                                :class="selectedLang === 'en' ? 'bg-white dark:bg-gray-800 text-purple-600 shadow-sm' : 'text-gray-500'"
                            >
                                English
                            </button>
                            <button
                                type="button"
                                @click="selectLanguageTemplate('am')"
                                class="px-2.5 py-1 rounded-lg font-semibold transition"
                                :class="selectedLang === 'am' ? 'bg-white dark:bg-gray-800 text-purple-600 shadow-sm' : 'text-gray-500'"
                            >
                                አማርኛ
                            </button>
                        </div>
                    </div>

                    <!-- Channel selector -->
                    <div>
                        <InputLabel value="Delivery Channel" />
                        <div class="grid grid-cols-3 gap-3 mt-1.5">
                            <label
                                class="p-3 border rounded-xl flex items-center justify-center space-x-2 cursor-pointer transition text-xs font-semibold"
                                :class="form.channel === 'sms' ? 'border-purple-600 bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300' : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input type="radio" v-model="form.channel" value="sms" class="text-purple-600" />
                                <span>📱 SMS Only</span>
                            </label>

                            <label
                                class="p-3 border rounded-xl flex items-center justify-center space-x-2 cursor-pointer transition text-xs font-semibold"
                                :class="form.channel === 'email' ? 'border-purple-600 bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300' : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input type="radio" v-model="form.channel" value="email" class="text-purple-600" />
                                <span>✉️ Email Only</span>
                            </label>

                            <label
                                class="p-3 border rounded-xl flex items-center justify-center space-x-2 cursor-pointer transition text-xs font-semibold"
                                :class="form.channel === 'both' ? 'border-purple-600 bg-purple-50 dark:bg-purple-950 text-purple-700 dark:text-purple-300' : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input type="radio" v-model="form.channel" value="both" class="text-purple-600" />
                                <span>📱+✉️ SMS & Email</span>
                            </label>
                        </div>
                    </div>

                    <!-- Email Subject (if email involved) -->
                    <div v-if="form.channel !== 'sms'">
                        <InputLabel for="subject" value="Email Subject" />
                        <TextInput
                            id="subject"
                            v-model="form.subject"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            required
                        />
                        <InputError :message="form.errors.subject" class="mt-1" />
                    </div>

                    <!-- Message Body -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <InputLabel for="body" value="Message Body" />
                            <div class="text-[11px] text-gray-400">
                                Link placeholder: <code>{link}</code>
                            </div>
                        </div>

                        <!-- Merge field buttons -->
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button
                                v-for="tag in ['{guest_name}', '{event_title}', '{date}', '{time}', '{venue}', '{host_names}', '{link}']"
                                :key="tag"
                                type="button"
                                @click="insertMergeField(tag)"
                                class="px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-purple-100 dark:hover:bg-purple-900 text-[11px] font-mono text-purple-700 dark:text-purple-300 transition"
                            >
                                + {{ tag }}
                            </button>
                        </div>

                        <textarea
                            id="body"
                            v-model="form.body"
                            rows="4"
                            class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-xs focus:ring-purple-500 focus:border-purple-500"
                            required
                        ></textarea>
                        <InputError :message="form.errors.body" class="mt-1" />
                    </div>
                </div>

                <!-- STEP 4: Personal Host Note & Photo Link (Shown on /t/{token}) -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">4. Webpage Extras (Optional)</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Displayed on the guest's personalized thank-you page under their card.</p>
                    </div>

                    <div>
                        <InputLabel for="thankyou_note" value="Personal Letter / Note From Host" />
                        <textarea
                            id="thankyou_note"
                            v-model="form.thankyou_note"
                            rows="3"
                            class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-xs focus:ring-purple-500 mt-1"
                            placeholder="Add a heartfelt reflection, thank-you note, or memories to display on their card..."
                        ></textarea>
                    </div>

                    <div>
                        <InputLabel for="thankyou_photo_url" value="Event Photo Gallery / Album Link" />
                        <TextInput
                            id="thankyou_photo_url"
                            v-model="form.thankyou_photo_url"
                            type="url"
                            placeholder="https://photos.app.goo.gl/... or iCloud album link"
                            class="mt-1 block w-full text-xs"
                        />
                        <p class="text-[11px] text-gray-400 mt-0.5">Guests will see a "View Event Photos" button to browse the event photos.</p>
                    </div>
                </div>

                <!-- STEP 5: Delivery Timing & Scheduling -->
                <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm space-y-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">5. Timing & Dispatch</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Choose whether to deliver right now or schedule automatically after the event.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label
                            class="p-4 rounded-xl border-2 cursor-pointer transition flex items-start space-x-3"
                            :class="timingMode === 'now' ? 'border-purple-600 bg-purple-50/30 dark:bg-purple-950/20' : 'border-gray-200 dark:border-gray-700'"
                        >
                            <input type="radio" v-model="timingMode" value="now" class="mt-0.5 text-purple-600" />
                            <div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100">Send Immediately</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">Deliver thank-you messages now to all selected recipients.</div>
                            </div>
                        </label>

                        <label
                            class="p-4 rounded-xl border-2 cursor-pointer transition flex items-start space-x-3"
                            :class="timingMode === 'scheduled' ? 'border-purple-600 bg-purple-50/30 dark:bg-purple-950/20' : 'border-gray-200 dark:border-gray-700'"
                        >
                            <input type="radio" v-model="timingMode" value="scheduled" class="mt-0.5 text-purple-600" />
                            <div>
                                <div class="text-xs font-bold text-gray-900 dark:text-gray-100">Schedule Automatically After Event</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">Delivered {{ scheduleHours }}h after the event ends.</div>
                            </div>
                        </label>
                    </div>

                    <div v-if="timingMode === 'scheduled'" class="p-4 bg-gray-50 dark:bg-gray-900 rounded-xl space-y-3">
                        <div class="flex items-center space-x-3">
                            <InputLabel for="hours" value="Hours after event ends:" class="whitespace-nowrap text-xs" />
                            <select
                                id="hours"
                                v-model="scheduleHours"
                                class="rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 text-xs py-1"
                            >
                                <option :value="2">2 hours after event</option>
                                <option :value="6">6 hours after event</option>
                                <option :value="12">12 hours after event (Default)</option>
                                <option :value="24">24 hours after event (Next morning)</option>
                                <option :value="48">48 hours after event (2 days)</option>
                            </select>
                        </div>
                        <div class="text-xs text-purple-600 dark:text-purple-400 font-semibold">
                            Scheduled dispatch: {{ calculatedScheduleDisplay }} ({{ event.timezone }})
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between pt-2">
                    <Link
                        :href="route('events.guests.index', { event: event.id })"
                        class="text-xs text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing || currentRecipientCount === 0"
                        class="px-6 py-3 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl shadow-md transition"
                    >
                        <span v-if="form.processing">Processing...</span>
                        <span v-else-if="timingMode === 'scheduled'">Schedule Thank-You ({{ currentRecipientCount }} Guests)</span>
                        <span v-else>Send Thank-You Now ({{ currentRecipientCount }} Guests)</span>
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
