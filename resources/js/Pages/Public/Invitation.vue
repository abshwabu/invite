<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import CardRenderer from '@/Components/CardRenderer.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    guest: {
        type: Object,
        required: true,
    },
    placeholderData: {
        type: Object,
        default: () => ({}),
    },
    qrSvg: {
        type: String,
        default: null,
    },
    ethiopianDate: {
        type: Object,
        default: null,
    },
    hasRemoveBranding: {
        type: Boolean,
        default: false,
    },
    isPast: {
        type: Boolean,
        default: false,
    },
    isFuneral: {
        type: Boolean,
        default: false,
    },
    currentLocale: {
        type: String,
        default: 'en',
    },
    ogImageUrl: {
        type: String,
        default: '',
    },
});

const activeLang = ref(props.currentLocale || 'en');

const rsvpForm = useForm({
    rsvp_status: props.guest.rsvp_status && props.guest.rsvp_status !== 'pending' ? props.guest.rsvp_status : 'yes',
    rsvp_party_size: props.guest.rsvp_party_size || 1,
    rsvp_note: props.guest.rsvp_note || '',
});

const isOptedOut = ref(props.guest.opted_out);
const cardRendererRef = ref(null);

const submitRsvp = () => {
    rsvpForm.post(route('public.invitation.rsvp', { token: props.guest.token }), {
        preserveScroll: true,
    });
};

const handleOptOut = () => {
    if (confirm(activeLang.value === 'am' ? 'ከዚህ በኋላ ማሳሰቢያ እንዳይደርስዎት ማረጋገጥ ይፈልጋሉ?' : 'Are you sure you want to stop receiving reminders for this event?')) {
        router.post(route('public.invitation.opt-out', { token: props.guest.token }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                isOptedOut.value = true;
            },
        });
    }
};

const downloadCard = () => {
    if (!cardRendererRef.value) return;
    const dataUrl = cardRendererRef.value.exportDataUrl(2);
    if (!dataUrl) return;

    const link = document.createElement('a');
    link.download = `${props.event.title}-${props.guest.name}.png`;
    link.href = dataUrl;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const toggleLanguage = (lang) => {
    activeLang.value = lang;
    router.get(route('public.invitation.show', { token: props.guest.token }), {
        lang: lang,
    }, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="min-h-screen bg-[#0E0E12] text-zinc-100 flex flex-col items-center justify-start p-4 sm:p-6 font-sans">
        <!-- Open Graph and Security Headers in Head -->
        <Head>
            <title>{{ event.title }} - Invitation for {{ guest.name }}</title>
            <meta name="description" :content="`Special invitation for ${guest.name} to ${event.title}`" />
            <meta property="og:title" :content="`${event.title} - Invitation for ${guest.name}`" />
            <meta property="og:description" :content="`You are cordially invited by ${event.host_names || 'the hosts'} to ${event.title}`" />
            <meta property="og:image" :content="ogImageUrl" />
            <meta property="og:type" content="website" />
            <meta name="robots" content="noindex, nofollow" />
        </Head>

        <!-- Top Header & Language Toggle -->
        <header class="w-full max-w-lg flex items-center justify-between py-3 mb-4 border-b border-zinc-800/80">
            <div class="flex items-center space-x-2">
                <span class="text-xs uppercase tracking-widest font-bold text-[#D4AF37]">
                    {{ isFuneral ? (activeLang === 'am' ? 'የመታሰቢያ ዝግጅት' : 'MEMORIAL') : (activeLang === 'am' ? 'የክብር ግብዣ' : 'INVITATION') }}
                </span>
            </div>

            <!-- Language Switcher -->
            <div class="flex items-center space-x-1 bg-zinc-900 p-0.5 rounded-lg border border-zinc-800 text-xs">
                <button
                    type="button"
                    @click="toggleLanguage('en')"
                    :class="activeLang === 'en' ? 'bg-[#D4AF37] text-black font-bold' : 'text-zinc-400 hover:text-white'"
                    class="px-2.5 py-1 rounded-md transition"
                >
                    EN
                </button>
                <button
                    type="button"
                    @click="toggleLanguage('am')"
                    :class="activeLang === 'am' ? 'bg-[#D4AF37] text-black font-bold' : 'text-zinc-400 hover:text-white'"
                    class="px-2.5 py-1 rounded-md transition"
                >
                    አማ
                </button>
            </div>
        </header>

        <main class="w-full max-w-lg space-y-6">
            <!-- Personalized Greeting Banner -->
            <div class="text-center py-2">
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-zinc-100">
                    <span v-if="activeLang === 'am'">
                        ውድ <span class="text-[#D4AF37]">{{ guest.name }}</span>
                    </span>
                    <span v-else>
                        Cordial Invitation for <span class="text-[#D4AF37]">{{ guest.name }}</span>
                    </span>
                </h1>
                <p class="text-xs text-zinc-400 mt-1">
                    {{ isFuneral
                        ? (activeLang === 'am' ? 'በዚህ የመታሰቢያ እና የስንብት ዝግጅት አብረውን እንዲሆኑ ተጋብዘዋል' : 'You are invited to join us in memory and honor.')
                        : (activeLang === 'am' ? 'በዚህ ታላቅ ዝግጅት አብረውን እንዲያከብሩ በክብር ተጋብዘዋል' : 'You are cordially invited to celebrate this joyous occasion.')
                    }}
                </p>
            </div>

            <!-- The Rendered Card (Using Reusable CardRenderer) -->
            <div class="w-full flex flex-col items-center">
                <div class="w-full max-w-md rounded-2xl overflow-hidden shadow-2xl border border-zinc-800/80 bg-zinc-950 flex justify-center">
                    <CardRenderer
                        ref="cardRendererRef"
                        :design="event.design_json"
                        :data="placeholderData"
                        :showWatermark="!hasRemoveBranding"
                        :previewMode="true"
                    />
                </div>

                <button
                    type="button"
                    @click="downloadCard"
                    class="mt-3 text-xs text-zinc-400 hover:text-[#D4AF37] flex items-center space-x-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>{{ activeLang === 'am' ? 'የካርዱን ፎቶ አውርድ (PNG)' : 'Save Card as Image' }}</span>
                </button>
            </div>

            <!-- Event Details Card -->
            <div class="bg-zinc-900/90 rounded-3xl border border-zinc-800 p-6 space-y-4 text-xs">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[#D4AF37] pb-2 border-b border-zinc-800">
                    {{ activeLang === 'am' ? 'የዝግጅቱ መረጃ' : 'Event Details' }}
                </h2>

                <!-- Title & Host -->
                <div>
                    <div class="text-base font-bold text-white">{{ event.title }}</div>
                    <div v-if="event.host_names" class="text-zinc-400 mt-0.5">
                        {{ activeLang === 'am' ? 'አዘጋጆች፡' : 'Hosted by:' }} <span class="text-zinc-200">{{ event.host_names }}</span>
                    </div>
                </div>

                <!-- Date & Time (Gregorian & Ethiopian) -->
                <div class="space-y-1.5 bg-zinc-950/60 p-3.5 rounded-2xl border border-zinc-800/60">
                    <div class="flex items-start space-x-2 text-zinc-200">
                        <span class="text-base">🗓</span>
                        <div>
                            <div class="font-semibold text-white">
                                {{ new Date(event.starts_at).toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                            </div>
                            <div class="text-zinc-400 text-[11px]">
                                {{ new Date(event.starts_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} ({{ event.timezone }})
                            </div>
                        </div>
                    </div>

                    <!-- Ethiopian Calendar Display -->
                    <div v-if="ethiopianDate" class="flex items-center space-x-2 text-[#D4AF37] font-medium pt-1.5 border-t border-zinc-800/50">
                        <span>🇪🇹</span>
                        <span>የኢትዮጵያ ዘመን አቆጣጠር፡ <strong>{{ ethiopianDate.formattedAm }}</strong></span>
                    </div>
                </div>

                <!-- Venue Info & Map Action -->
                <div class="space-y-2 bg-zinc-950/60 p-3.5 rounded-2xl border border-zinc-800/60">
                    <div class="flex items-start space-x-2 text-zinc-200">
                        <span class="text-base">📍</span>
                        <div class="flex-1">
                            <div class="font-semibold text-white">{{ event.venue_name || 'Venue' }}</div>
                            <div v-if="event.venue_address" class="text-zinc-400 text-[11px]">{{ event.venue_address }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <a
                            v-if="event.map_url"
                            :href="event.map_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex-1 text-center py-2 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-semibold transition"
                        >
                            🗺 {{ activeLang === 'am' ? 'በካርታ ክፈት' : 'Open in Maps' }}
                        </a>

                        <a
                            :href="route('public.invitation.ics', { token: guest.token })"
                            class="flex-1 text-center py-2 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-semibold transition"
                            download
                        >
                            📅 {{ activeLang === 'am' ? 'ወደ ካላንደር መዝግብ' : 'Add to Calendar' }}
                        </a>
                    </div>
                </div>

                <!-- Description / Message -->
                <p v-if="event.description" class="text-zinc-300 leading-relaxed italic bg-zinc-950/30 p-3 rounded-xl">
                    "{{ event.description }}"
                </p>
            </div>

            <!-- RSVP Section (if rsvp_enabled) -->
            <div
                v-if="event.rsvp_enabled"
                class="bg-zinc-900/90 rounded-3xl border border-zinc-800 p-6 space-y-4 text-xs"
            >
                <div class="flex items-center justify-between pb-2 border-b border-zinc-800">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#D4AF37]">
                        {{ activeLang === 'am' ? 'የመገኘት ምላሽ (RSVP)' : 'RSVP Response' }}
                    </h2>
                    <span v-if="guest.rsvp_status && guest.rsvp_status !== 'pending'" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-emerald-950 text-emerald-300 border border-emerald-800">
                        {{ activeLang === 'am' ? 'ምላሽ ተመዝግቧል' : 'Response Recorded' }}
                    </span>
                </div>

                <!-- If Event is in the past -->
                <div v-if="isPast" class="p-4 rounded-2xl bg-zinc-950 text-center text-zinc-400">
                    {{ activeLang === 'am' ? 'ዝግጅቱ የተካሄደ በመሆኑ ምላሽ መስጠት ተዘግቷል።' : 'RSVP is now closed as this event has already taken place.' }}
                </div>

                <!-- Active RSVP Form -->
                <form v-else @submit.prevent="submitRsvp" class="space-y-4">
                    <!-- Choice Buttons -->
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            @click="rsvpForm.rsvp_status = 'yes'"
                            :class="rsvpForm.rsvp_status === 'yes' ? 'bg-emerald-600 text-white font-bold ring-2 ring-emerald-400/50' : 'bg-zinc-950 text-zinc-300 hover:bg-zinc-800 border border-zinc-800'"
                            class="py-3 px-2 rounded-2xl text-center transition flex flex-col items-center justify-center space-y-1"
                        >
                            <span class="text-lg">✓</span>
                            <span>{{ activeLang === 'am' ? 'እገኛለሁ' : 'Attending' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="rsvpForm.rsvp_status = 'maybe'"
                            :class="rsvpForm.rsvp_status === 'maybe' ? 'bg-amber-600 text-white font-bold ring-2 ring-amber-400/50' : 'bg-zinc-950 text-zinc-300 hover:bg-zinc-800 border border-zinc-800'"
                            class="py-3 px-2 rounded-2xl text-center transition flex flex-col items-center justify-center space-y-1"
                        >
                            <span class="text-lg">?</span>
                            <span>{{ activeLang === 'am' ? 'እሞክራለሁ' : 'Maybe' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="rsvpForm.rsvp_status = 'no'"
                            :class="rsvpForm.rsvp_status === 'no' ? 'bg-rose-600 text-white font-bold ring-2 ring-rose-400/50' : 'bg-zinc-950 text-zinc-300 hover:bg-zinc-800 border border-zinc-800'"
                            class="py-3 px-2 rounded-2xl text-center transition flex flex-col items-center justify-center space-y-1"
                        >
                            <span class="text-lg">✕</span>
                            <span>{{ activeLang === 'am' ? 'አልችልም' : 'Decline' }}</span>
                        </button>
                    </div>

                    <!-- Party Size selection if attending and max_party_size > 1 -->
                    <div v-if="rsvpForm.rsvp_status === 'yes' && guest.max_party_size > 1" class="space-y-1">
                        <label class="block text-zinc-400 font-semibold">
                            {{ activeLang === 'am' ? 'የሚገኙ ሰዎች ብዛት (እስከ ' + guest.max_party_size + ')' : 'Number of Guests Attending (up to ' + guest.max_party_size + ')' }}
                        </label>
                        <select
                            v-model.number="rsvpForm.rsvp_party_size"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-zinc-100 text-xs focus:border-[#D4AF37]"
                        >
                            <option v-for="n in guest.max_party_size" :key="n" :value="n">
                                {{ n }} {{ n === 1 ? (activeLang === 'am' ? 'ሰው' : 'Person') : (activeLang === 'am' ? 'ሰዎች' : 'People') }}
                            </option>
                        </select>
                    </div>

                    <!-- Optional Note -->
                    <div class="space-y-1">
                        <label class="block text-zinc-400 font-semibold">
                            {{ activeLang === 'am' ? 'መልእክት ወይም አስተያየት (አማራጭ)' : 'Note for the Hosts (Optional)' }}
                        </label>
                        <textarea
                            v-model="rsvpForm.rsvp_note"
                            rows="2"
                            class="w-full bg-zinc-950 border border-zinc-800 rounded-xl p-2.5 text-zinc-100 text-xs focus:border-[#D4AF37]"
                            :placeholder="activeLang === 'am' ? 'የእንኳን ደስ አላችሁ ወይም ሌላ መልእክት ያስቀምጡ...' : 'Send your blessings or a brief note to the hosts...'"
                        ></textarea>
                    </div>

                    <PrimaryButton :disabled="rsvpForm.processing" class="w-full py-3 text-center justify-center font-bold text-sm bg-gradient-to-r from-[#D4AF37] to-[#F59E0B] text-black">
                        {{ activeLang === 'am' ? 'ምላሼን መዝግብ' : 'Confirm RSVP' }}
                    </PrimaryButton>

                    <p class="text-[11px] text-zinc-500 text-center">
                        {{ activeLang === 'am' ? 'ዝግጅቱ እስኪጀመር ድረስ በማንኛውም ጊዜ መልስዎን መቀየር ይችላሉ።' : 'You can update your response at any time before the event begins.' }}
                    </p>
                </form>
            </div>

            <!-- Single-Use QR Pass (if is_invite_only) -->
            <div
                v-if="event.is_invite_only && qrSvg"
                class="bg-zinc-900/90 rounded-3xl border border-zinc-800 p-6 flex flex-col items-center text-center space-y-4"
            >
                <div class="flex items-center space-x-1.5 text-xs uppercase font-bold tracking-wider text-[#D4AF37]">
                    <span>🎟</span>
                    <span>{{ activeLang === 'am' ? 'የመግቢያ ዲጂታል ማለፊያ' : 'Admission QR Pass' }}</span>
                </div>

                <div class="p-4 bg-white rounded-2xl shadow-xl flex items-center justify-center max-w-[280px]">
                    <div v-html="qrSvg" class="w-full h-auto"></div>
                </div>

                <div>
                    <div class="font-bold text-sm text-white">{{ guest.name }}</div>
                    <div class="text-xs text-amber-400 font-medium mt-0.5">
                        {{ activeLang === 'am' ? 'ይህንን QR ኮድ በበሩ መግቢያ ላይ ያሳዩ' : 'Show this QR code at the door for check-in' }}
                    </div>
                </div>

                <a
                    :href="route('public.invitation.qr-download', { token: guest.token })"
                    class="py-2 px-4 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold flex items-center space-x-1.5 transition"
                    download
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>{{ activeLang === 'am' ? 'ማለፊያውን ወደ ስልክህ አስቀምጥ' : 'Save Pass to Phone' }}</span>
                </a>
            </div>

            <!-- Opt-out Link / Footer -->
            <footer class="pt-6 pb-12 text-center space-y-3 text-xs text-zinc-500">
                <div v-if="!isOptedOut">
                    <button
                        type="button"
                        @click="handleOptOut"
                        class="hover:text-zinc-300 underline transition"
                    >
                        {{ activeLang === 'am' ? 'ከዚህ በኋላ ማሳሰቢያ እንዳይላክልኝ' : "Don't send me reminders for this event" }}
                    </button>
                </div>
                <div v-else class="text-amber-500/80">
                    ✓ {{ activeLang === 'am' ? 'ከማሳሰቢያዎች ተሰርዘዋል' : 'You are opted out from reminders.' }}
                </div>

                <div class="text-[11px] text-zinc-600">
                    Powered by <strong class="text-zinc-400">Invite</strong> • invite.et
                </div>
            </footer>
        </main>
    </div>
</template>
