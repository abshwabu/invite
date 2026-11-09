<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    reminders: {
        type: Array,
        default: () => [],
    },
    canReminders: {
        type: Boolean,
        default: true,
    },
    availableQuota: {
        type: Number,
        default: 0,
    },
    audienceCounts: {
        type: Object,
        default: () => ({ all: 0, rsvp_pending: 0, rsvp_yes: 0 }),
    },
    presetTimes: {
        type: Object,
        default: () => ({}),
    },
});

const form = useForm({
    preset: '1_day_before',
    channel: 'sms',
    target_audience: 'rsvp_pending',
    custom_scheduled_at: '',
    subject: `Reminder: ${props.event.title}`,
    message_body: `Reminder for {guest_name}: {event_title} is coming up on {date} at {time}. More details: {link}`,
});

const selectedAudienceCount = computed(() => {
    return props.audienceCounts[form.target_audience] || 0;
});

const estimatedCost = computed(() => {
    const weight = form.channel === 'both' ? 2 : 1;
    return selectedAudienceCount.value * weight;
});

const hasEnoughQuota = computed(() => {
    return props.availableQuota >= estimatedCost.value;
});

const submit = () => {
    form.post(route('events.reminders.store', props.event.id), {
        onSuccess: () => {
            form.reset('custom_scheduled_at');
        },
    });
};

const cancelReminder = (reminderId) => {
    if (confirm('Cancel this scheduled reminder?')) {
        router.delete(route('events.reminders.destroy', [props.event.id, reminderId]));
    }
};
</script>

<template>
    <Head :title="`${event.title} • Automated Reminders`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <Link :href="route('events.show', event.id)" class="text-xs text-amber-600 dark:text-amber-400 hover:underline">
                            ← {{ event.title }}
                        </Link>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        ⏰ {{ $t('Automated Event Reminders') }}
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200">
                        Balance: {{ availableQuota }} cards
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <!-- Feature Gate Warning for Free Tier -->
            <div
                v-if="!canReminders"
                class="p-6 rounded-3xl bg-amber-50 border border-amber-200 dark:bg-amber-950/40 dark:border-amber-900/60"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-bold text-amber-900 dark:text-amber-200">
                            ⭐ Automated Reminders are a Paid Tier Feature
                        </h3>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 max-w-xl">
                            Automated reminders dispatch customized SMS and email notices on a precise schedule before your event. Upgrade to Bronze or higher to unlock automated reminders.
                        </p>
                    </div>
                    <Link
                        :href="route('plan.index')"
                        class="px-4 py-2 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shrink-0 shadow-sm"
                    >
                        Upgrade Plan →
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left: Schedule New Reminder Form -->
                <div class="lg:col-span-5 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">
                        Schedule a Reminder
                    </h3>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Timing Preset -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Dispatch Timing
                            </label>
                            <select
                                v-model="form.preset"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                                :disabled="!canReminders"
                            >
                                <option value="3_days_before">3 Days Before Event (9:00 AM)</option>
                                <option value="1_day_before">1 Day Before Event (9:00 AM)</option>
                                <option value="morning_of">Morning of Event (8:00 AM)</option>
                                <option value="custom">Custom Date & Time</option>
                            </select>
                            <p v-if="presetTimes[form.preset]" class="text-[11px] text-gray-400 mt-1">
                                Scheduled for: {{ new Date(presetTimes[form.preset]).toLocaleString() }}
                            </p>
                        </div>

                        <!-- Custom Date Picker -->
                        <div v-if="form.preset === 'custom'">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Custom Scheduled Date & Time
                            </label>
                            <input
                                type="datetime-local"
                                v-model="form.custom_scheduled_at"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                                required
                            />
                            <p v-if="form.errors.custom_scheduled_at" class="text-xs text-rose-500 mt-1">
                                {{ form.errors.custom_scheduled_at }}
                            </p>
                        </div>

                        <!-- Target Audience -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Target Recipients
                            </label>
                            <select
                                v-model="form.target_audience"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                                :disabled="!canReminders"
                            >
                                <option value="rsvp_pending">RSVP Pending only ({{ audienceCounts.rsvp_pending }} guests)</option>
                                <option value="rsvp_yes">RSVP Yes / Attending ({{ audienceCounts.rsvp_yes }} guests)</option>
                                <option value="all">All non-opted-out guests ({{ audienceCounts.all }} guests)</option>
                            </select>
                            <p class="text-[11px] text-gray-400 mt-1">
                                Guests who opted out via their invitation link are automatically skipped.
                            </p>
                        </div>

                        <!-- Channel -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Delivery Channel
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <label
                                    class="p-2.5 rounded-xl border text-center cursor-pointer text-xs font-semibold transition"
                                    :class="form.channel === 'sms' ? 'border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="sms" v-model="form.channel" class="sr-only" />
                                    📱 SMS
                                </label>
                                <label
                                    class="p-2.5 rounded-xl border text-center cursor-pointer text-xs font-semibold transition"
                                    :class="form.channel === 'email' ? 'border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="email" v-model="form.channel" class="sr-only" />
                                    📧 Email
                                </label>
                                <label
                                    class="p-2.5 rounded-xl border text-center cursor-pointer text-xs font-semibold transition"
                                    :class="form.channel === 'both' ? 'border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'border-gray-200 dark:border-gray-700'"
                                >
                                    <input type="radio" value="both" v-model="form.channel" class="sr-only" />
                                    🔄 Both
                                </label>
                            </div>
                        </div>

                        <!-- Email Subject -->
                        <div v-if="form.channel === 'email' || form.channel === 'both'">
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Email Subject
                            </label>
                            <input
                                type="text"
                                v-model="form.subject"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                            />
                        </div>

                        <!-- Message Body -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Message Text
                            </label>
                            <textarea
                                v-model="form.message_body"
                                rows="3"
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                                placeholder="Reminder for {guest_name}..."
                            ></textarea>
                            <p class="text-[11px] text-gray-400 mt-1">
                                Supported placeholders: {guest_name}, {event_title}, {date}, {time}, {venue}, {link}
                            </p>
                        </div>

                        <!-- Cost Estimation & Warning -->
                        <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Recipients:</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ selectedAudienceCount }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Estimated Cost:</span>
                                <span class="font-bold text-amber-600 dark:text-amber-400">{{ estimatedCost }} cards</span>
                            </div>
                            <div v-if="!hasEnoughQuota" class="pt-2 text-xs text-rose-600 font-semibold flex items-center gap-1">
                                <span>⚠️</span>
                                <span>You have {{ availableQuota }} cards available. More quota will be required before this reminder can be sent.</span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="form.processing || !canReminders"
                            class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-white bg-amber-500 hover:bg-amber-600 disabled:opacity-50 transition shadow-sm"
                        >
                            {{ form.processing ? 'Scheduling...' : 'Confirm Reminder Schedule' }}
                        </button>
                    </form>
                </div>

                <!-- Right: Configured Reminders Table -->
                <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-4">
                        Configured Reminders ({{ reminders.length }})
                    </h3>

                    <div v-if="reminders.length === 0" class="p-12 text-center text-xs text-gray-400">
                        No reminders scheduled yet. Configure presets on the left to notify your guests automatically.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="rem in reminders"
                            :key="rem.id"
                            class="p-4 rounded-2xl border border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white capitalize">
                                        {{ rem.preset.replace(/_/g, ' ') }}
                                    </h4>
                                    <span
                                        class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider"
                                        :class="{
                                            'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300': rem.status === 'pending',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300': rem.status === 'sent',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300': rem.status === 'failed',
                                            'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300': rem.status === 'cancelled',
                                        }"
                                    >
                                        {{ rem.status }}
                                    </span>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-gray-200 dark:bg-gray-700 uppercase font-semibold">
                                        {{ rem.channel }}
                                    </span>
                                </div>

                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    📅 Scheduled for: <strong>{{ new Date(rem.scheduled_at).toLocaleString() }}</strong>
                                </p>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    Audience: <span class="capitalize">{{ rem.target_audience.replace(/_/g, ' ') }}</span>
                                    <span v-if="rem.campaign" class="ms-2">
                                        • Campaign: #{{ rem.campaign.id }} ({{ rem.campaign.audience_count }} recipients)
                                    </span>
                                </p>
                                <p v-if="rem.error" class="text-xs text-rose-600 dark:text-rose-400 mt-1">
                                    ⚠️ {{ rem.error }}
                                </p>
                            </div>

                            <div v-if="rem.status === 'pending'" class="shrink-0">
                                <button
                                    @click="cancelReminder(rem.id)"
                                    class="px-3 py-1.5 text-xs font-semibold rounded-lg text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 border border-rose-200 dark:border-rose-900 transition"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
