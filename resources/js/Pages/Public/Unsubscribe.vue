<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    guestName: {
        type: String,
        default: 'Guest',
    },
    eventTitle: {
        type: String,
        default: 'Event',
    },
    token: {
        type: String,
        required: true,
    },
});

const isResubscribed = ref(false);
const isSubmitting = ref(false);

const handleResubscribe = () => {
    isSubmitting.value = true;
    router.post(route('public.resubscribe', { token: props.token }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isResubscribed.value = true;
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Unsubscribed" />

    <div class="min-h-screen bg-sand text-ink flex items-center justify-center p-4 sm:p-6 font-sans">
        <div class="w-full max-w-md bg-paper border border-mist rounded-sm p-6 sm:p-8 shadow-sm text-center">
            <!-- Icon -->
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-mist/50 flex items-center justify-center text-ink/70">
                <svg v-if="!isResubscribed" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <svg v-else class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <!-- Heading & Body -->
            <div v-if="!isResubscribed" class="space-y-3">
                <h1 class="font-serif text-2xl font-bold text-ink">
                    Unsubscribed
                </h1>
                <p class="text-sm text-ink/80 leading-relaxed">
                    Hello <span class="font-semibold text-ink">{{ guestName }}</span>, you have been opted out from reminders for
                    <span class="font-semibold text-ink">"{{ eventTitle }}"</span>.
                </p>
                <p class="text-xs text-ink/60">
                    You will no longer receive SMS or email reminder notifications for this event. Your invitation access remains intact.
                </p>
                <p class="text-xs text-ink/60 amharic">
                    ከዚህ ዝግጅት ጋር የተያያዙ ማስታወሻዎች ወደፊት ወደ እርስዎ አይላኩም።
                </p>

                <!-- Actions -->
                <div class="pt-6 space-y-3">
                    <Link
                        :href="route('public.invitation', { token })"
                        class="block w-full py-2.5 px-4 rounded-sm bg-ink text-white text-sm font-medium hover:bg-ink/90 transition-colors shadow-xs"
                    >
                        View Invitation Card
                    </Link>

                    <button
                        type="button"
                        @click="handleResubscribe"
                        :disabled="isSubmitting"
                        class="text-xs text-ink/70 hover:text-ink underline transition-colors"
                    >
                        Opted out by mistake? Click here to re-subscribe
                    </button>
                </div>
            </div>

            <!-- Resubscribed State -->
            <div v-else class="space-y-3">
                <h1 class="font-serif text-2xl font-bold text-green-700">
                    Subscribed
                </h1>
                <p class="text-sm text-ink/80 leading-relaxed">
                    You have successfully re-subscribed to reminders for
                    <span class="font-semibold text-ink">"{{ eventTitle }}"</span>.
                </p>
                <p class="text-xs text-ink/60 amharic">
                    በድጋሚ ተመዝግበዋል። ማስታወሻዎች ይደርስዎታል።
                </p>

                <div class="pt-6">
                    <Link
                        :href="route('public.invitation', { token })"
                        class="inline-block py-2.5 px-6 rounded-sm bg-ink text-white text-sm font-medium hover:bg-ink/90 transition-colors shadow-xs"
                    >
                        Back to Invitation
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
