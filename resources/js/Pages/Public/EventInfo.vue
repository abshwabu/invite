<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    ethiopianDate: {
        type: Object,
        default: null,
    },
    isPast: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="min-h-screen bg-[#0E0E12] text-zinc-100 flex flex-col items-center justify-start p-4 sm:p-6 font-sans">
        <Head>
            <title>{{ event.title }} - Event Information</title>
            <meta name="description" :content="`Details and schedule for ${event.title}`" />
            <meta property="og:title" :content="event.title" />
            <meta property="og:description" :content="event.description || `Event hosted by ${event.host_names || 'hosts'}`" />
            <meta property="og:type" content="website" />
        </Head>

        <!-- Header -->
        <header class="w-full max-w-lg flex items-center justify-between py-4 mb-4 border-b border-zinc-800">
            <span class="text-xs uppercase tracking-widest font-bold text-[#D4AF37]">
                {{ event.category?.name_en || 'EVENT INFO' }}
            </span>
            <span v-if="event.is_invite_only" class="text-[10px] uppercase font-bold px-2 py-0.5 rounded bg-amber-950 text-amber-300 border border-amber-800">
                Invite-Only Entry
            </span>
        </header>

        <main class="w-full max-w-lg space-y-6">
            <!-- Event Hero -->
            <div class="text-center py-4 bg-zinc-900/60 rounded-3xl border border-zinc-800 p-6 space-y-2">
                <span class="text-3xl">🎉</span>
                <h1 class="text-2xl font-bold text-white">{{ event.title }}</h1>
                <p v-if="event.host_names" class="text-xs text-zinc-400">
                    Hosted by <strong class="text-zinc-200">{{ event.host_names }}</strong>
                </p>
            </div>

            <!-- Details Card -->
            <div class="bg-zinc-900/90 rounded-3xl border border-zinc-800 p-6 space-y-4 text-xs">
                <h2 class="text-xs font-bold uppercase tracking-wider text-[#D4AF37] pb-2 border-b border-zinc-800">
                    Date & Location
                </h2>

                <!-- Date -->
                <div class="bg-zinc-950/60 p-4 rounded-2xl border border-zinc-800/60 space-y-2">
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

                    <div v-if="ethiopianDate" class="pt-2 border-t border-zinc-800/50 text-[#D4AF37] font-medium flex items-center space-x-1.5">
                        <span>🇪🇹</span>
                        <span>የኢትዮጵያ ዘመን አቆጣጠር፡ <strong>{{ ethiopianDate.formattedAm }}</strong></span>
                    </div>
                </div>

                <!-- Venue & Maps -->
                <div class="bg-zinc-950/60 p-4 rounded-2xl border border-zinc-800/60 space-y-3">
                    <div class="flex items-start space-x-2 text-zinc-200">
                        <span class="text-base">📍</span>
                        <div>
                            <div class="font-semibold text-white">{{ event.venue_name || 'Venue TBA' }}</div>
                            <div v-if="event.venue_address" class="text-zinc-400 text-[11px]">{{ event.venue_address }}</div>
                        </div>
                    </div>

                    <a
                        v-if="event.map_url"
                        :href="event.map_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block w-full text-center py-2 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-200 font-semibold transition"
                    >
                        🗺 Open in Google Maps
                    </a>
                </div>

                <!-- Description -->
                <div v-if="event.description" class="p-4 rounded-2xl bg-zinc-950/40 text-zinc-300 leading-relaxed italic">
                    "{{ event.description }}"
                </div>
            </div>

            <footer class="pt-6 pb-12 text-center text-xs text-zinc-600">
                Powered by <strong class="text-zinc-400">Invite</strong> • invite.et
            </footer>
        </main>
    </div>
</template>
