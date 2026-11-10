<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CardRenderer from '@/Components/CardRenderer.vue';
import ReportModal from '@/Components/ReportModal.vue';

const props = defineProps({
    event: {
        type: Object,
        required: true,
    },
    guest: {
        type: Object,
        required: true,
    },
    design: {
        type: Object,
        required: true,
    },
    placeholderData: {
        type: Object,
        default: () => ({}),
    },
    hasRemoveBranding: {
        type: Boolean,
        default: false,
    },
    ethiopianDate: {
        type: Object,
        default: null,
    },
    locale: {
        type: String,
        default: 'en',
    },
});

const activeLang = ref(props.locale || 'en');
const cardRendererRef = ref(null);
const showReportModal = ref(false);
</script>

<template>
    <Head :title="`Thank You • ${event.title}`">
        <meta name="robots" content="noindex, nofollow" />
        <meta property="og:title" :content="`Thank You • ${event.title}`" />
        <meta property="og:description" :content="`A note of gratitude from the hosts of ${event.title}`" />
        <meta property="og:type" content="article" />
    </Head>

    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col items-center py-6 px-3 sm:px-6">
        <!-- Top Language Selector -->
        <div class="w-full max-w-md flex items-center justify-between mb-4 px-2">
            <div class="text-xs text-slate-400 font-medium">
                {{ event.title }}
            </div>
            <div class="flex items-center space-x-1 bg-slate-800/80 p-1 rounded-xl text-xs border border-slate-700">
                <button
                    type="button"
                    @click="activeLang = 'en'"
                    class="px-2.5 py-0.5 rounded-lg transition"
                    :class="activeLang === 'en' ? 'bg-purple-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                >
                    EN
                </button>
                <button
                    type="button"
                    @click="activeLang = 'am'"
                    class="px-2.5 py-0.5 rounded-lg transition"
                    :class="activeLang === 'am' ? 'bg-purple-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                >
                    አማ
                </button>
            </div>
        </div>

        <!-- Main Card Canvas Container -->
        <div class="w-full max-w-md bg-slate-950 rounded-3xl shadow-2xl border border-slate-800 overflow-hidden flex flex-col items-center">
            <!-- Header Badge -->
            <div class="w-full pt-4 pb-2 text-center">
                <span class="inline-flex items-center space-x-1.5 px-3.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-semibold">
                    <span>💌</span>
                    <span>{{ activeLang === 'am' ? 'የምስጋና ካርድ' : 'Thank-You Note' }}</span>
                </span>
            </div>

            <!-- Card Visual -->
            <div class="w-full px-4 py-3 flex justify-center">
                <div class="w-full max-w-[340px] aspect-[1080/1528] rounded-2xl overflow-hidden shadow-xl border border-slate-800/60 bg-slate-900">
                    <CardRenderer
                        ref="cardRendererRef"
                        :design="design"
                        :data="placeholderData"
                        :showWatermark="!hasRemoveBranding"
                        :previewMode="true"
                    />
                </div>
            </div>

            <!-- Guest Greeting -->
            <div class="w-full px-6 py-4 text-center">
                <h1 class="text-xl font-bold text-white">
                    {{ activeLang === 'am' ? `ውድ ${guest.name}፣ እናመሰግናለን!` : `Thank You, ${guest.name}!` }}
                </h1>
                <p class="text-xs text-slate-400 mt-1">
                    {{ activeLang === 'am' ? 'ከእኛ ጋር ስላሳለፉት ጊዜ ከልብ እናመሰግናለን።' : 'We are deeply grateful for your presence, warmth, and support.' }}
                </p>
            </div>

            <!-- Personal Host Note (Optional) -->
            <div v-if="event.thankyou_note" class="w-full px-6 pb-4">
                <div class="p-4 rounded-2xl bg-slate-900/90 border border-slate-800 relative">
                    <div class="text-[11px] font-semibold text-purple-400 uppercase tracking-wider mb-1 flex items-center space-x-1.5">
                        <span>✍️</span>
                        <span>{{ activeLang === 'am' ? 'ከአዘጋጆቹ የተላከ መልእክት' : 'A Personal Note From The Hosts' }}</span>
                    </div>
                    <p class="text-xs text-slate-200 leading-relaxed whitespace-pre-line italic">
                        "{{ event.thankyou_note }}"
                    </p>
                    <div v-if="event.host_names" class="text-right text-[11px] font-bold text-slate-400 mt-2">
                        — {{ event.host_names }}
                    </div>
                </div>
            </div>

            <!-- Event Photos Gallery Link (Optional) -->
            <div v-if="event.thankyou_photo_url" class="w-full px-6 pb-4">
                <a
                    :href="event.thankyou_photo_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-purple-600/30 flex items-center justify-center space-x-2 transition active:scale-98"
                >
                    <span>📸</span>
                    <span>{{ activeLang === 'am' ? 'የዝግጅቱን ፎቶዎች ይመልከቱ' : 'View Event Photo Album' }}</span>
                    <span>↗</span>
                </a>
            </div>

            <!-- Event Details Recap -->
            <div class="w-full px-6 py-4 bg-slate-900/60 border-t border-slate-800 text-xs text-slate-400 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">{{ activeLang === 'am' ? 'ዝግጅት' : 'Event' }}</span>
                    <span class="font-bold text-slate-200">{{ event.title }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-slate-500">{{ activeLang === 'am' ? 'ቀን' : 'Date' }}</span>
                    <div class="text-right">
                        <span class="text-slate-200 font-medium">{{ placeholderData.date }}</span>
                        <div v-if="ethiopianDate" class="text-[10px] text-amber-400">
                            {{ ethiopianDate.formatted }}
                        </div>
                    </div>
                </div>

                <div v-if="event.venue_name" class="flex items-center justify-between">
                    <span class="text-slate-500">{{ activeLang === 'am' ? 'ቦታ' : 'Venue' }}</span>
                    <span class="text-slate-200 font-medium">{{ event.venue_name }}</span>
                </div>

                <div v-if="event.host_names" class="flex items-center justify-between">
                    <span class="text-slate-500">{{ activeLang === 'am' ? 'አዘጋጆች' : 'Hosts' }}</span>
                    <span class="text-slate-200 font-medium">{{ event.host_names }}</span>
                </div>
            </div>
        </div>

        <!-- Footer / Branding -->
        <footer class="mt-6 text-center text-[11px] text-slate-500 space-y-2">
            <div v-if="!hasRemoveBranding">
                {{ activeLang === 'am' ? 'በጠሪ የተዘጋጀ' : 'Powered by' }} <strong class="text-purple-400">Teri · ጠሪ</strong>
            </div>
            <div>
                {{ activeLang === 'am' ? 'ዲጂታል የካርድ እና የዝግጅት መድረክ' : 'Event invitation & thank-you platform' }}
            </div>
            <div>
                <button
                    type="button"
                    @click="showReportModal = true"
                    class="text-slate-500 hover:text-red-400 underline transition"
                >
                    {{ activeLang === 'am' ? 'ይህንን ግብዣ ሪፖርት ያድርጉ' : 'Report this invitation' }}
                </button>
            </div>
        </footer>

        <!-- Abuse Report Modal -->
        <ReportModal
            :show="showReportModal"
            :report-url="route('public.thankyou.report', { token: guest.token })"
            :lang="activeLang"
            @close="showReportModal = false"
        />
    </div>
</template>
