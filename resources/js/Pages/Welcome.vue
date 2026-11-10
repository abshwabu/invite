<script setup>
import { ref, computed, onMounted, onUnmounted, defineAsyncComponent, nextTick } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

// Lazy-load CardRenderer so the initial page bundle stays lightweight (<200KB)
const CardRenderer = defineAsyncComponent({
    loader: () => import('@/Components/CardRenderer.vue'),
    delay: 200,
});

const props = defineProps({
    appName: {
        type: String,
        default: 'Invite',
    },
    categories: {
        type: Array,
        default: () => [],
    },
    heroTemplates: {
        type: Object,
        default: () => ({}),
    },
    shelfTemplates: {
        type: Array,
        default: () => [],
    },
    tiers: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const { t, locale } = useI18n();

// ----------------------------------------------------
// Language & Localization
// ----------------------------------------------------
const currentLocale = computed({
    get: () => locale.value || page.props.locale || 'en',
    set: (val) => {
        locale.value = val;
    },
});

const switchLocale = (newLocale) => {
    if (newLocale === currentLocale.value) return;
    currentLocale.value = newLocale;
    try {
        sessionStorage.setItem('invite_lang_user_set', 'true');
    } catch (e) {}

    router.post(
        route('locale.update'),
        { locale: newLocale },
        { preserveScroll: true, preserveState: true }
    );
};

// ----------------------------------------------------
// Hero Occasions & Interactive Live Card
// ----------------------------------------------------
const occasions = [
    { key: 'wedding', label_en: 'Wedding', label_am: 'ሠርግ' },
    { key: 'funeral', label_en: 'Funeral', label_am: 'ተዝካር / ቀብር' },
    { key: 'birthday', label_en: 'Birthday', label_am: 'ልደት' },
    { key: 'eid', label_en: 'Eid', label_am: 'ዒድ' },
    { key: 'meskel', label_en: 'Meskel', label_am: 'መስቀል' },
    { key: 'graduation', label_en: 'Graduation', label_am: 'ምረቃ' },
];

const activeOccasion = ref('wedding');
const guestName = ref('Almaz Bekele');
const isTypingActive = ref(false);
const smsVisible = ref(false);
const cardRendererReady = ref(false);

const activeHeroTemplate = computed(() => {
    return props.heroTemplates[activeOccasion.value] || props.heroTemplates.wedding || null;
});

// Dynamic design injected with guest name token if needed
const heroDesign = computed(() => {
    if (!activeHeroTemplate.value || !activeHeroTemplate.value.design_json) return null;
    const json = JSON.parse(JSON.stringify(activeHeroTemplate.value.design_json));
    
    // Ensure {{guest_name}} exists in at least one layer so typing immediately updates canvas
    const hasToken = json.layers?.some((l) => l.text && l.text.includes('{{guest_name}}'));
    if (!hasToken && json.layers) {
        const textLayer = json.layers.find((l) => l.id === 'text-opening' || l.id === 'text-invitation');
        if (textLayer) {
            textLayer.text = currentLocale.value === 'am'
                ? 'ክቡር/ት {{guest_name}}፤ ' + textLayer.text
                : 'Cordially inviting {{guest_name}} — ' + textLayer.text;
        }
    }
    return json;
});

// Orchestrated load moment (name types in, then SMS bubble arrives)
let typingTimer = null;
const runHeroOrchestration = () => {
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const targetName = currentLocale.value === 'am' ? 'አልማዝ በቀለ' : 'Almaz Bekele';
    
    if (prefersReducedMotion) {
        guestName.value = targetName;
        smsVisible.value = true;
        cardRendererReady.value = true;
        return;
    }

    guestName.value = '';
    smsVisible.value = false;
    isTypingActive.value = true;

    let index = 0;
    typingTimer = setInterval(() => {
        if (index < targetName.length) {
            guestName.value += targetName[index];
            index++;
        } else {
            clearInterval(typingTimer);
            isTypingActive.value = false;
            setTimeout(() => {
                smsVisible.value = true;
            }, 300);
        }
    }, 55);
};

const onGuestNameInput = () => {
    if (typingTimer) {
        clearInterval(typingTimer);
        isTypingActive.value = false;
    }
    smsVisible.value = true;
};

const selectOccasion = (key) => {
    activeOccasion.value = key;
};

// ----------------------------------------------------
// The Shelf: Carousel & Live Re-Theming Modal
// ----------------------------------------------------
const shelfContainerRef = ref(null);
const activeShelfCategory = ref('all');
const selectedModalTemplate = ref(null);
const activeModalPaletteKey = ref('original');

const presetPalettes = {
    original: null, // use template's own palette
    garden: {
        name_en: 'Garden Spruce',
        name_am: 'የጓሮ አረንጓዴ',
        bg: '#F4FBF7',
        surface: '#FFFFFF',
        primary: '#1E5B47',
        secondary: '#D4AF37',
        accent: '#8E3B6E',
        ink: '#1C2140',
        muted: '#525875',
    },
    royal_gold: {
        name_en: 'Royal Gold',
        name_am: 'የወርቅ ቅብ',
        bg: '#FAF7EE',
        surface: '#FFFFFF',
        primary: '#B8860B',
        secondary: '#2A2A2A',
        accent: '#F4B400',
        ink: '#1C2140',
        muted: '#66614D',
    },
    midnight: {
        name_en: 'Midnight Indigo',
        name_am: 'ጥልቅ ሰማያዊ',
        bg: '#14182E',
        surface: '#1C2140',
        primary: '#F4B400',
        secondary: '#E4E3EE',
        accent: '#8E3B6E',
        ink: '#FFFFFF',
        muted: '#A5A9C4',
    },
    terracotta: {
        name_en: 'Warm Earth',
        name_am: 'ሞቃታማ አፈር',
        bg: '#FAF5F0',
        surface: '#FFFFFF',
        primary: '#C85A32',
        secondary: '#3B2314',
        accent: '#E69A68',
        ink: '#26140A',
        muted: '#7A6255',
    },
};

const filteredShelfTemplates = computed(() => {
    if (activeShelfCategory.value === 'all') {
        return props.shelfTemplates;
    }
    return props.shelfTemplates.filter((t) => t.category?.slug === activeShelfCategory.value);
});

const scrollShelf = (direction) => {
    if (!shelfContainerRef.value) return;
    const distance = 420 * direction;
    shelfContainerRef.value.scrollBy({ left: distance, behavior: 'smooth' });
};

const openTemplateModal = (template) => {
    selectedModalTemplate.value = template;
    activeModalPaletteKey.value = 'original';
};

const closeModal = () => {
    selectedModalTemplate.value = null;
};

const currentModalPalette = computed(() => {
    if (!selectedModalTemplate.value) return null;
    if (activeModalPaletteKey.value === 'original') {
        return selectedModalTemplate.value.palette;
    }
    return presetPalettes[activeModalPaletteKey.value];
});

// ----------------------------------------------------
// At the Door: Interactive Scanner Demo
// ----------------------------------------------------
const scannerState = ref(0); // 0 = idle camera, 1 = valid green, 2 = duplicate red

const triggerDoorScan = () => {
    if (scannerState.value === 0) {
        scannerState.value = 1; // Success
    } else if (scannerState.value === 1) {
        scannerState.value = 2; // Duplicate scan alert
    } else {
        scannerState.value = 1;
    }
};

const resetDoorScanner = () => {
    scannerState.value = 0;
};

// ----------------------------------------------------
// Lifecycle & Browser Detection
// ----------------------------------------------------
onMounted(() => {
    // Auto-detect browser language if user hasn't explicitly chosen
    try {
        const userExplicit = sessionStorage.getItem('invite_lang_user_set');
        if (!userExplicit) {
            const browserLang = (navigator.language || (navigator.languages && navigator.languages[0]) || '').toLowerCase();
            if (browserLang.startsWith('am') && currentLocale.value !== 'am') {
                switchLocale('am');
            }
        }
    } catch (e) {}

    // Allow static image to paint first, then enable card renderer for silky smooth LCP
    setTimeout(() => {
        cardRendererReady.value = true;
    }, 250);

    // Run orchestrated hero typing
    runHeroOrchestration();
});

onUnmounted(() => {
    if (typingTimer) clearInterval(typingTimer);
});
</script>

<template>
    <Head>
        <title>{{ currentLocale === 'am' ? 'ይጋብዙ - የኢትዮጵያ ዲጂታል የግብዣ ካርዶች እና የበር ላይ QR ማረጋገጫ' : 'Invite - Ethiopian Digital Invitation Cards & Single-Use QR Pass' }}</title>
        <meta name="description" :content="currentLocale === 'am' ? 'የሠርግ፣ የልደት፣ የተዝካር፣ የዒድ እና የምረቃ የግብዣ ካርዶችን በአማርኛ እና በእንግሊዝኛ ይንደፉ። በSMS ወይም በኢሜይል ይላኩ፣ በበር ላይ በነጠላ የQR ኮድ ያስገቡ።' : 'Design, customize, and deliver Ethiopian invitation cards in Amharic & English. Send via SMS and scan single-use QR passes at the door.'" />
        <link rel="alternate" hreflang="en" href="https://invite.et/" />
        <link rel="alternate" hreflang="am" href="https://invite.et/?lang=am" />
        <meta property="og:title" :content="currentLocale === 'am' ? 'ይጋብዙ - የኢትዮጵያ ዲጂታል የግብዣ ካርዶች' : 'Invite - Ethiopian Event Invitations & QR Verification'" />
        <meta property="og:description" content="Design in Amharic and English. Send via SMS and scan single-use QR tickets at the door." />
        <meta property="og:image" content="/images/og-invite.png" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="/images/og-invite.png" />
        
        <!-- JSON-LD Structured Data Schema -->
        <component :is="'script'" type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "WebApplication",
                "name": "Invite",
                "applicationCategory": "DesignApplication",
                "operatingSystem": "All",
                "offers": {
                    "@type": "AggregateOffer",
                    "priceCurrency": "ETB",
                    "lowPrice": "0",
                    "highPrice": "14999"
                },
                "inLanguage": ["en", "am"],
                "description": "Ethiopian invitation card design studio with SMS delivery and single-use door QR scanner."
            }
        </component>
    </Head>

    <div class="min-h-screen bg-paper text-ink font-sans selection:bg-meskel selection:text-ink">
        <!-- ==================================================== -->
        <!-- 1. HEADER (Sticky, Minimal, No Admin Links)          -->
        <!-- ==================================================== -->
        <header class="sticky top-0 z-40 bg-paper/90 backdrop-blur-md border-b border-mist">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-18 flex items-center justify-between">
                <!-- Brand Logo -->
                <Link href="/" class="flex items-center gap-3 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-meskel rounded-sm">
                    <span class="w-10 h-10 rounded-sm bg-ink text-meskel font-serif font-bold text-xl flex items-center justify-center shadow-sm">
                        ይ
                    </span>
                    <div class="flex flex-col">
                        <span class="font-serif font-bold text-2xl tracking-tight text-ink leading-tight">
                            {{ appName }}
                        </span>
                        <span class="text-[11px] text-ink/60 -mt-1 font-medium">
                            {{ currentLocale === 'am' ? 'ዲጂታል ካርዶች' : 'Ethiopian Invitations' }}
                        </span>
                    </div>
                </Link>

                <!-- Navigation Controls -->
                <nav class="flex items-center gap-3 sm:gap-6" aria-label="Main Navigation">
                    <!-- Language Switcher -->
                    <div class="inline-flex items-center bg-mist/60 rounded-pill p-1 border border-mist" role="group" aria-label="Language Switcher">
                        <button
                            type="button"
                            @click="switchLocale('en')"
                            :class="[
                                'px-2.5 py-1 text-xs rounded-pill font-medium transition-colors',
                                currentLocale === 'en' ? 'bg-ink text-white shadow-sm' : 'text-ink/70 hover:text-ink'
                            ]"
                        >
                            English
                        </button>
                        <button
                            type="button"
                            @click="switchLocale('am')"
                            :class="[
                                'px-2.5 py-1 text-xs rounded-pill font-medium transition-colors',
                                currentLocale === 'am' ? 'bg-ink text-white shadow-sm' : 'text-ink/70 hover:text-ink'
                            ]"
                        >
                            አማርኛ
                        </button>
                    </div>

                    <!-- User Log in / Dashboard -->
                    <template v-if="$page.props.auth?.user">
                        <Link
                            :href="route('dashboard')"
                            class="text-sm font-medium text-ink hover:text-highland transition-colors"
                        >
                            {{ currentLocale === 'am' ? 'ዳሽቦርድ' : 'Dashboard' }}
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="text-sm font-medium text-ink hover:text-highland transition-colors"
                        >
                            {{ t('nav.login') }}
                        </Link>
                    </template>

                    <!-- Primary Action -->
                    <Link
                        :href="activeHeroTemplate ? route('editor.show', activeHeroTemplate.id) : route('editor.show')"
                        class="hidden sm:inline-flex items-center justify-center px-4 py-2 text-sm font-medium rounded-sm bg-meskel text-ink hover:bg-meskel-dark transition-colors shadow-sm"
                    >
                        {{ t('nav.design_cta') }}
                    </Link>
                </nav>
            </div>
        </header>

        <!-- ==================================================== -->
        <!-- 2. HERO SECTION (Split Interactive Stage)            -->
        <!-- ==================================================== -->
        <section class="py-12 sm:py-20 lg:py-24 border-b border-mist relative overflow-hidden" aria-labelledby="hero-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
                    
                    <!-- Left Column: Copy & Interactive Customization -->
                    <div class="lg:col-span-6 flex flex-col items-start text-left">
                        <h1 id="hero-heading" class="font-serif text-3xl sm:text-5xl lg:text-6xl text-ink leading-[1.15] tracking-tight mb-6 max-w-prose">
                            {{ t('hero.headline') }}
                        </h1>

                        <p class="text-base sm:text-lg text-ink/75 leading-relaxed max-w-prose mb-8">
                            {{ t('hero.promise') }}
                        </p>

                        <!-- Occasion Filter Chips -->
                        <div class="w-full mb-8">
                            <label class="block text-xs font-semibold text-ink/70 mb-2">
                                {{ currentLocale === 'am' ? 'የዝግጅት አይነት ይምረጡ፡' : 'Select an occasion to switch design:' }}
                            </label>
                            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Occasion Selector">
                                <button
                                    v-for="occ in occasions"
                                    :key="occ.key"
                                    type="button"
                                    role="tab"
                                    :aria-selected="activeOccasion === occ.key"
                                    @click="selectOccasion(occ.key)"
                                    :class="[
                                        'px-3.5 py-1.5 text-xs sm:text-sm rounded-pill font-medium transition-all border',
                                        activeOccasion === occ.key
                                            ? 'bg-ink text-white border-ink shadow-sm'
                                            : 'bg-white text-ink/80 border-mist hover:border-ink/40'
                                    ]"
                                >
                                    {{ currentLocale === 'am' ? occ.label_am : occ.label_en }}
                                </button>
                            </div>
                        </div>

                        <!-- Live Guest Name Personalizer Input -->
                        <div class="w-full bg-white p-4 rounded-md border border-mist shadow-sm mb-8">
                            <label for="guest-name-input" class="block text-xs font-medium text-ink/80 mb-1.5">
                                {{ t('hero.type_name') }}
                            </label>
                            <div class="relative">
                                <input
                                    id="guest-name-input"
                                    v-model="guestName"
                                    @input="onGuestNameInput"
                                    type="text"
                                    :placeholder="t('hero.placeholder_name')"
                                    class="w-full px-3.5 py-2 text-sm text-ink bg-paper rounded-sm border border-mist focus:border-meskel focus:ring-1 focus:ring-meskel outline-none transition-colors"
                                />
                                <span v-if="isTypingActive" class="absolute right-3 top-2.5 flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-meskel opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-meskel"></span>
                                </span>
                            </div>
                            <p class="text-[11px] text-ink/60 mt-1.5">
                                {{ currentLocale === 'am' ? 'ስሙን ሲተይቡ በካርዱ እና በSMS መልእክቱ ላይ ወዲያውኑ ይታያል።' : 'Typing dynamically updates both the rendered card canvas and the SMS preview.' }}
                            </p>
                        </div>

                        <!-- Primary CTA -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 w-full sm:w-auto">
                            <Link
                                :href="activeHeroTemplate ? route('editor.show', activeHeroTemplate.id) : route('editor.show')"
                                class="inline-flex items-center justify-center px-6 py-3.5 text-base font-semibold rounded-sm bg-meskel text-ink hover:bg-meskel-dark transition-colors shadow-sm text-center"
                            >
                                {{ t('hero.design_with_this') }}
                            </Link>
                            <span class="text-xs text-ink/70 text-center sm:text-left">
                                {{ t('hero.no_account_needed') }}
                            </span>
                        </div>
                    </div>

                    <!-- Right Column: Live Card Canvas & Phone SMS Mockup -->
                    <div class="lg:col-span-6 flex flex-col items-center justify-center relative">
                        <!-- Dual Preview Layout -->
                        <div class="relative w-full max-w-lg mx-auto flex flex-col items-center">
                            
                            <!-- Main Stage: Live Invitation Card Frame -->
                            <div class="w-[280px] sm:w-[320px] aspect-[1080/1528] bg-white rounded-md shadow-2xl border border-mist p-2 relative z-10 overflow-hidden">
                                <!-- Card Renderer Container -->
                                <div class="w-full h-full relative rounded-sm overflow-hidden bg-paper flex items-center justify-center">
                                    <template v-if="cardRendererReady && heroDesign">
                                        <CardRenderer
                                            :design="heroDesign"
                                            :data="{ guest_name: guestName || (currentLocale === 'am' ? 'አልማዝ በቀለ' : 'Almaz Bekele') }"
                                            :lang="currentLocale"
                                            :palette="activeHeroTemplate?.palette"
                                            :preview-mode="true"
                                            class="w-full h-full object-contain"
                                        />
                                    </template>
                                    <template v-else-if="activeHeroTemplate">
                                        <img
                                            :src="currentLocale === 'am' ? (activeHeroTemplate.preview_path_am || activeHeroTemplate.preview_path) : activeHeroTemplate.preview_path"
                                            :alt="activeHeroTemplate.name"
                                            class="w-full h-full object-contain"
                                            loading="eager"
                                        />
                                    </template>
                                </div>
                            </div>

                            <!-- Companion Stage: CSS Phone SMS Mockup -->
                            <div
                                :class="[
                                    'w-[290px] sm:w-[320px] bg-white rounded-md border border-mist shadow-lg p-3.5 transition-all duration-500 z-20 mt-4 sm:-mt-14 sm:ml-24',
                                    smsVisible ? 'opacity-100 translate-y-0' : 'opacity-85 translate-y-2'
                                ]"
                            >
                                <div class="flex items-center justify-between text-[11px] text-ink/60 border-b border-mist/70 pb-2 mb-2.5">
                                    <span class="font-medium text-ink">Ethio Telecom • SMS</span>
                                    <span>2:15 PM</span>
                                </div>
                                <div class="bg-paper p-3 rounded-sm border border-mist/80 text-xs text-ink/90 leading-relaxed font-sans">
                                    <span class="font-semibold text-highland block mb-1">
                                        INVITE (የግብዣ መልእክት)
                                    </span>
                                    <p>
                                        {{ currentLocale === 'am'
                                            ? `ሰላም ${guestName || 'አልማዝ በቀለ'}! ወደ ዝግጅታችን በክብር ተጋብዘዋል። ዝርዝር መረጃ፣ RSVP እና የQR መግቢያ ካርድዎን ለመመልከት ይህን ሊንክ ይጫኑ፡`
                                            : `Selam ${guestName || 'Almaz Bekele'}! You are cordially invited to celebrate with us. Tap your private link for event details, RSVP & door check-in pass:`
                                        }}
                                    </p>
                                    <span class="text-highland underline font-medium block mt-1.5 cursor-pointer">
                                        invite.et/s/7a9b
                                    </span>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[10px] text-ink/50">
                                    <span>{{ t('hero.sms_label') }}</span>
                                    <span class="text-highland font-medium">✓ Delivered</span>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 3. THE SHELF (Scrollable Real Template Previews)      -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-white" aria-labelledby="shelf-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                    <div>
                        <h2 id="shelf-heading" class="font-serif text-2xl sm:text-4xl text-ink tracking-tight mb-2">
                            {{ t('shelf.title') }}
                        </h2>
                        <p class="text-sm sm:text-base text-ink/75 max-w-prose">
                            {{ t('shelf.subtitle') }}
                        </p>
                    </div>

                    <!-- Shelf Carousel Controls -->
                    <div class="hidden sm:flex items-center gap-2">
                        <button
                            type="button"
                            @click="scrollShelf(-1)"
                            class="p-2.5 rounded-sm border border-mist bg-paper hover:bg-mist text-ink transition-colors"
                            aria-label="Scroll left in templates"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="scrollShelf(1)"
                            class="p-2.5 rounded-sm border border-mist bg-paper hover:bg-mist text-ink transition-colors"
                            aria-label="Scroll right in templates"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Category Tabs with Count -->
                <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeShelfCategory === 'all'"
                        @click="activeShelfCategory = 'all'"
                        :class="[
                            'px-3.5 py-1.5 text-xs sm:text-sm rounded-pill font-medium whitespace-nowrap border transition-colors',
                            activeShelfCategory === 'all'
                                ? 'bg-ink text-white border-ink'
                                : 'bg-paper text-ink/80 border-mist hover:border-ink/30'
                        ]"
                    >
                        {{ t('shelf.all') }} ({{ shelfTemplates.length }})
                    </button>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeShelfCategory === cat.slug"
                        @click="activeShelfCategory = cat.slug"
                        :class="[
                            'px-3.5 py-1.5 text-xs sm:text-sm rounded-pill font-medium whitespace-nowrap border transition-colors',
                            activeShelfCategory === cat.slug
                                ? 'bg-ink text-white border-ink'
                                : 'bg-paper text-ink/80 border-mist hover:border-ink/30'
                        ]"
                    >
                        {{ currentLocale === 'am' ? cat.name_am : cat.name_en }} ({{ cat.templates_count || 0 }})
                    </button>
                </div>

                <!-- Scrollable Template Cards Grid / Carousel -->
                <div
                    ref="shelfContainerRef"
                    class="flex gap-6 overflow-x-auto pb-6 pt-2 snap-x snap-mandatory scroll-smooth"
                    tabindex="0"
                    aria-label="Template previews list"
                >
                    <div
                        v-for="tpl in filteredShelfTemplates"
                        :key="tpl.id"
                        @click="openTemplateModal(tpl)"
                        @keydown.enter="openTemplateModal(tpl)"
                        tabindex="0"
                        role="button"
                        class="flex-shrink-0 w-[210px] sm:w-[250px] bg-paper rounded-md border border-mist p-2.5 hover:border-meskel hover:shadow-lg transition-all cursor-pointer group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-meskel snap-start"
                        :aria-label="`Preview ${tpl.name}`"
                    >
                        <!-- Card Ratio Preview 1080x1528 -->
                        <div class="w-full aspect-[1080/1528] rounded-sm bg-white overflow-hidden border border-mist/80 mb-2.5 relative">
                            <img
                                :src="currentLocale === 'am' ? (tpl.preview_path_am || tpl.preview_path) : tpl.preview_path"
                                :alt="tpl.name"
                                class="w-full h-full object-contain group-hover:scale-102 transition-transform duration-300"
                                loading="lazy"
                                width="250"
                                height="354"
                            />
                            <div class="absolute inset-0 bg-ink/5 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <span class="bg-ink/85 text-white text-[11px] px-2.5 py-1 rounded-sm font-medium">
                                    {{ t('shelf.preview_label') }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Metadata -->
                        <div class="px-1">
                            <h3 class="text-xs sm:text-sm font-semibold text-ink truncate">
                                {{ currentLocale === 'am' && tpl.spec?.name_am ? tpl.spec.name_am : tpl.name }}
                            </h3>
                            <div class="flex items-center justify-between mt-1 text-[11px] text-ink/60">
                                <span class="capitalize">{{ tpl.category?.name_en || 'Ethiopian' }}</span>
                                <span class="text-highland font-medium">Ready to edit</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Template Preview & Live Re-Theming Modal -->
        <div
            v-if="selectedModalTemplate"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-ink/75 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            @click.self="closeModal"
            @keydown.esc="closeModal"
        >
            <div class="bg-white rounded-md border border-mist max-w-2xl w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-4 border-b border-mist mb-6">
                    <div>
                        <h3 class="font-serif text-xl sm:text-2xl text-ink">
                            {{ currentLocale === 'am' && selectedModalTemplate.spec?.name_am ? selectedModalTemplate.spec.name_am : selectedModalTemplate.name }}
                        </h3>
                        <p class="text-xs text-ink/70">
                            {{ selectedModalTemplate.category?.name_en }} • {{ t('shelf.modal_title') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="p-2 text-ink/60 hover:text-ink rounded-sm border border-transparent hover:border-mist"
                        :aria-label="t('shelf.close')"
                    >
                        ✕
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-6 items-center">
                    <!-- Live Canvas Preview with Palette Role Swapping -->
                    <div class="sm:col-span-7 flex justify-center">
                        <div class="w-[240px] sm:w-[270px] aspect-[1080/1528] rounded-sm border border-mist shadow-md overflow-hidden bg-paper flex items-center justify-center">
                            <CardRenderer
                                v-if="selectedModalTemplate.design_json"
                                :design="selectedModalTemplate.design_json"
                                :palette="currentModalPalette"
                                :lang="currentLocale"
                                :preview-mode="true"
                                class="w-full h-full object-contain"
                            />
                            <img
                                v-else
                                :src="selectedModalTemplate.preview_path"
                                :alt="selectedModalTemplate.name"
                                class="w-full h-full object-contain"
                            />
                        </div>
                    </div>

                    <!-- Live Palette Customizer & CTA -->
                    <div class="sm:col-span-5 flex flex-col justify-between h-full">
                        <div>
                            <span class="block text-xs font-semibold text-ink/80 mb-3">
                                {{ t('shelf.palette_roles') }}:
                            </span>
                            <div class="space-y-2 mb-6">
                                <button
                                    type="button"
                                    @click="activeModalPaletteKey = 'original'"
                                    :class="[
                                        'w-full flex items-center justify-between p-2 rounded-sm border text-xs font-medium transition-colors',
                                        activeModalPaletteKey === 'original'
                                            ? 'border-ink bg-mist/40 text-ink'
                                            : 'border-mist hover:border-ink/40 text-ink/80'
                                    ]"
                                >
                                    <span>{{ currentLocale === 'am' ? 'የመጀመሪያው ዲዛይን' : 'Original Spec' }}</span>
                                    <span class="w-4 h-4 rounded-full border border-mist" :style="{ backgroundColor: selectedModalTemplate.palette?.primary || '#1C2140' }"></span>
                                </button>

                                <button
                                    v-for="(pal, key) in presetPalettes"
                                    :key="key"
                                    v-show="key !== 'original'"
                                    type="button"
                                    @click="activeModalPaletteKey = key"
                                    :class="[
                                        'w-full flex items-center justify-between p-2 rounded-sm border text-xs font-medium transition-colors',
                                        activeModalPaletteKey === key
                                            ? 'border-ink bg-mist/40 text-ink'
                                            : 'border-mist hover:border-ink/40 text-ink/80'
                                    ]"
                                >
                                    <span>{{ currentLocale === 'am' ? pal.name_am : pal.name_en }}</span>
                                    <div class="flex items-center gap-1">
                                        <span class="w-3 h-3 rounded-full border border-mist" :style="{ backgroundColor: pal.primary }"></span>
                                        <span class="w-3 h-3 rounded-full border border-mist" :style="{ backgroundColor: pal.secondary }"></span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <div class="pt-4 border-t border-mist">
                            <Link
                                :href="route('editor.show', { template: selectedModalTemplate.id }) + (activeModalPaletteKey !== 'original' ? `?palette=${activeModalPaletteKey}` : '')"
                                class="w-full inline-flex items-center justify-center px-4 py-3 text-sm font-semibold rounded-sm bg-meskel text-ink hover:bg-meskel-dark transition-colors shadow-sm text-center"
                            >
                                {{ t('shelf.use_design') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================================================== -->
        <!-- 4. HOW IT WORKS (Sequential 4 Steps, Alternating)    -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-paper" aria-labelledby="how-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center max-w-2xl mx-auto mb-16">
                    <h2 id="how-heading" class="font-serif text-3xl sm:text-4xl text-ink tracking-tight mb-3">
                        {{ t('how.title') }}
                    </h2>
                    <p class="text-base text-ink/75 leading-relaxed">
                        {{ t('how.subtitle') }}
                    </p>
                </div>

                <!-- Sequence of Steps -->
                <div class="space-y-16 lg:space-y-24">
                    
                    <!-- Step 1: Pick and Edit -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-center">
                        <div class="lg:col-span-6 order-2 lg:order-1">
                            <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Step 1</span>
                            <h3 class="font-serif text-2xl sm:text-3xl text-ink mb-4">
                                {{ t('how.step1_title') }}
                            </h3>
                            <p class="text-base text-ink/75 leading-relaxed max-w-prose">
                                {{ t('how.step1_desc') }}
                            </p>
                        </div>
                        <div class="lg:col-span-6 order-1 lg:order-2 flex justify-center">
                            <!-- CSS-drawn Canvas Editor Frame -->
                            <div class="w-full max-w-md bg-white rounded-md border border-mist p-4 shadow-md">
                                <div class="flex items-center justify-between border-b border-mist pb-2 mb-3 text-xs text-ink/70">
                                    <span class="font-semibold text-ink">Invite Editor</span>
                                    <span>Font: Young Serif • Amharic</span>
                                </div>
                                <div class="bg-paper border border-mist/70 rounded-sm p-4 text-center">
                                    <span class="text-xs text-highland font-serif block mb-1">Helen & Dawit</span>
                                    <p class="font-serif text-sm text-ink font-bold">የሠርግ ጥሪ ግብዣ</p>
                                    <div class="mt-3 flex justify-center gap-1.5">
                                        <span class="w-4 h-4 rounded-full bg-meskel border border-mist"></span>
                                        <span class="w-4 h-4 rounded-full bg-highland border border-mist"></span>
                                        <span class="w-4 h-4 rounded-full bg-orchid border border-mist"></span>
                                        <span class="w-4 h-4 rounded-full bg-ink border border-mist"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Add Guests -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-center">
                        <div class="lg:col-span-6 order-1 flex justify-center">
                            <!-- CSS-drawn Contacts Import Mockup -->
                            <div class="w-full max-w-md bg-white rounded-md border border-mist p-4 shadow-md font-sans">
                                <div class="flex items-center justify-between border-b border-mist pb-2 mb-3 text-xs text-ink/70">
                                    <span class="font-semibold text-ink">Guest List Import</span>
                                    <span class="text-highland font-medium">+251 Validated</span>
                                </div>
                                <div class="divide-y divide-mist text-xs">
                                    <div class="py-2 flex items-center justify-between">
                                        <span class="font-medium text-ink">Abebe Kebede</span>
                                        <span class="text-ink/60">+251 91 123 4567</span>
                                        <span class="bg-paper px-2 py-0.5 rounded text-[11px] text-ink/80">Party of 2</span>
                                    </div>
                                    <div class="py-2 flex items-center justify-between">
                                        <span class="font-medium text-ink">Chaltu Tadesse</span>
                                        <span class="text-ink/60">+251 92 876 5432</span>
                                        <span class="bg-paper px-2 py-0.5 rounded text-[11px] text-ink/80">Party of 4</span>
                                    </div>
                                    <div class="py-2 flex items-center justify-between">
                                        <span class="font-medium text-ink">Dawit Haile</span>
                                        <span class="text-ink/60">+251 94 555 1234</span>
                                        <span class="bg-paper px-2 py-0.5 rounded text-[11px] text-ink/80">Party of 1</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-6 order-2">
                            <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Step 2</span>
                            <h3 class="font-serif text-2xl sm:text-3xl text-ink mb-4">
                                {{ t('how.step2_title') }}
                            </h3>
                            <p class="text-base text-ink/75 leading-relaxed max-w-prose">
                                {{ t('how.step2_desc') }}
                            </p>
                        </div>
                    </div>

                    <!-- Step 3: Send by SMS or Email -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-center">
                        <div class="lg:col-span-6 order-2 lg:order-1">
                            <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Step 3</span>
                            <h3 class="font-serif text-2xl sm:text-3xl text-ink mb-4">
                                {{ t('how.step3_title') }}
                            </h3>
                            <p class="text-base text-ink/75 leading-relaxed max-w-prose">
                                {{ t('how.step3_desc') }}
                            </p>
                        </div>
                        <div class="lg:col-span-6 order-1 lg:order-2 flex justify-center">
                            <!-- CSS-drawn Delivery Monitor Mockup -->
                            <div class="w-full max-w-md bg-white rounded-md border border-mist p-4 shadow-md font-sans">
                                <div class="flex items-center justify-between border-b border-mist pb-2 mb-3 text-xs">
                                    <span class="font-semibold text-ink">Campaign: Wedding Invites</span>
                                    <span class="text-highland font-medium">99.2% Delivered</span>
                                </div>
                                <div class="space-y-2">
                                    <div class="w-full bg-paper rounded-sm h-2 overflow-hidden">
                                        <div class="bg-highland h-full w-[99%]"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-ink/60 pt-1">
                                        <span>Sent: 450 guests</span>
                                        <span>Delivered: 446</span>
                                        <span>Opened: 389</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Guests RSVP & Door QR -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-14 items-center">
                        <div class="lg:col-span-6 order-1 flex justify-center">
                            <!-- CSS-drawn Guest Viewport Mockup -->
                            <div class="w-full max-w-sm bg-white rounded-md border border-mist p-4 shadow-md text-center">
                                <div class="w-16 h-16 bg-paper mx-auto rounded-sm border border-mist flex items-center justify-center p-1 mb-3">
                                    <!-- QR Vector Representation -->
                                    <svg class="w-full h-full text-ink" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm10-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14 0h4v4h-4v-4zm-4 4h4v4h-4v-4zm4-4h4v4h-4v-4zm-4-4h4v4h-4v-4z"/>
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-ink block mb-1">Door Verification Pass</span>
                                <span class="text-[11px] text-ink/60 block">Single-use token • Almaz Bekele</span>
                                <div class="mt-3 inline-flex items-center gap-1.5 px-3 py-1 rounded-pill bg-highland/10 text-highland text-xs font-medium">
                                    <span>RSVP: Attending (2)</span>
                                </div>
                            </div>
                        </div>
                        <div class="lg:col-span-6 order-2">
                            <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Step 4</span>
                            <h3 class="font-serif text-2xl sm:text-3xl text-ink mb-4">
                                {{ t('how.step4_title') }}
                            </h3>
                            <p class="text-base text-ink/75 leading-relaxed max-w-prose">
                                {{ t('how.step4_desc') }}
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 5. AT THE DOOR (Interactive Scanner Demo)            -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-white" aria-labelledby="door-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    
                    <!-- Scanner Phone Mockup Interactive Component -->
                    <div class="lg:col-span-6 flex justify-center">
                        <div class="w-[300px] sm:w-[340px] bg-ink rounded-xl p-4 shadow-2xl border-4 border-mist/80 text-white relative">
                            <!-- Speaker / Camera Notch -->
                            <div class="w-20 h-3 bg-ink-dark rounded-full mx-auto mb-3"></div>

                            <!-- Viewfinder / Screen Area -->
                            <div class="relative w-full h-[380px] rounded-md overflow-hidden flex flex-col justify-between p-4 transition-colors duration-300"
                                :class="[
                                    scannerState === 0 ? 'bg-ink-dark' : '',
                                    scannerState === 1 ? 'bg-highland' : '',
                                    scannerState === 2 ? 'bg-red-800' : ''
                                ]"
                            >
                                <!-- Camera Header -->
                                <div class="flex items-center justify-between text-xs text-white/70">
                                    <span>Invite Scanner v2.0</span>
                                    <span>Hall Entrance A</span>
                                </div>

                                <!-- State 0: Reticle Viewfinder -->
                                <div v-if="scannerState === 0" class="flex flex-col items-center justify-center my-auto">
                                    <div class="w-44 h-44 border-2 border-dashed border-meskel/70 rounded-md flex items-center justify-center relative">
                                        <div class="w-8 h-8 border-t-2 border-l-2 border-meskel absolute top-0 left-0"></div>
                                        <div class="w-8 h-8 border-t-2 border-r-2 border-meskel absolute top-0 right-0"></div>
                                        <div class="w-8 h-8 border-b-2 border-l-2 border-meskel absolute bottom-0 left-0"></div>
                                        <div class="w-8 h-8 border-b-2 border-r-2 border-meskel absolute bottom-0 right-0"></div>
                                        <span class="text-xs text-white/60">Align QR Code</span>
                                    </div>
                                </div>

                                <!-- State 1: Green Success Verified Screen -->
                                <div v-else-if="scannerState === 1" class="flex flex-col items-center justify-center my-auto text-center px-2 animate-fade-in">
                                    <div class="w-14 h-14 rounded-full bg-white text-highland flex items-center justify-center text-2xl font-bold mb-3 shadow-lg">
                                        ✓
                                    </div>
                                    <h4 class="font-serif text-xl font-bold text-white mb-1">
                                        {{ t('door.valid_title') }}
                                    </h4>
                                    <p class="text-xs text-white/90">
                                        {{ t('door.valid_detail') }}
                                    </p>
                                </div>

                                <!-- State 2: Red Duplicate Reject Screen -->
                                <div v-else class="flex flex-col items-center justify-center my-auto text-center px-2 animate-fade-in">
                                    <div class="w-14 h-14 rounded-full bg-white text-red-700 flex items-center justify-center text-2xl font-bold mb-3 shadow-lg">
                                        ✕
                                    </div>
                                    <h4 class="font-serif text-xl font-bold text-white mb-1">
                                        {{ t('door.duplicate_title') }}
                                    </h4>
                                    <p class="text-xs text-white/90">
                                        {{ t('door.duplicate_detail') }}
                                    </p>
                                </div>

                                <!-- Scanner Action Controls -->
                                <div class="pt-3 border-t border-white/20 flex items-center justify-between gap-2">
                                    <button
                                        type="button"
                                        @click="triggerDoorScan"
                                        class="flex-1 py-2 px-3 rounded-sm bg-meskel text-ink font-semibold text-xs text-center shadow hover:bg-meskel-dark transition-colors"
                                    >
                                        {{ scannerState === 0 ? t('door.scan_btn') : (scannerState === 1 ? 'Simulate Re-scan (Duplicate)' : 'Simulate Valid Scan') }}
                                    </button>
                                    <button
                                        v-if="scannerState !== 0"
                                        type="button"
                                        @click="resetDoorScanner"
                                        class="py-2 px-3 rounded-sm bg-white/20 text-white font-medium text-xs hover:bg-white/30 transition-colors"
                                    >
                                        {{ t('door.reset_btn') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Explanatory Text Column -->
                    <div class="lg:col-span-6">
                        <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Gate Security</span>
                        <h2 id="door-heading" class="font-serif text-3xl sm:text-4xl text-ink tracking-tight mb-4">
                            {{ t('door.title') }}
                        </h2>
                        <p class="text-lg text-ink/80 leading-relaxed mb-6 max-w-prose">
                            {{ t('door.subtitle') }}
                        </p>
                        <p class="text-sm text-ink/70 leading-relaxed mb-8 max-w-prose">
                            {{ t('door.explainer') }}
                        </p>

                        <div class="p-4 bg-paper rounded-md border border-mist text-xs text-ink/80 leading-relaxed">
                            <span class="font-semibold text-ink block mb-1">
                                {{ currentLocale === 'am' ? 'ምንም መተግበሪያ አያስፈልግም፡' : 'No specialized hardware or app installation required:' }}
                            </span>
                            {{ currentLocale === 'am'
                                ? 'የዝግጅቱ አስተናጋጆች በየትኛውም የስልክ ካሜራ ብራውዘር በቀላሉ ኮዱን ይቃኛሉ። የተላለፉ ወይም የተደገሙ ካርዶች ወዲያውኑ ድምጽ በማሰማት ውድቅ ይደረጋሉ።'
                                : 'Entrance coordinators scan tickets straight from any standard mobile Safari or Chrome browser. Re-used tickets trigger an audible reject buzz and highlight the exact timestamp of initial check-in.'
                            }}
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 6. BUILT FOR ETHIOPIA (2-Column Typographic List)    -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-paper" aria-labelledby="ethiopia-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="mb-14">
                    <span class="text-xs font-semibold text-highland uppercase tracking-wider block mb-2">Cultural & Technical Design</span>
                    <h2 id="ethiopia-heading" class="font-serif text-3xl sm:text-4xl text-ink tracking-tight mb-3">
                        {{ t('ethiopia.title') }}
                    </h2>
                    <p class="text-base text-ink/75 max-w-prose">
                        {{ t('ethiopia.subtitle') }}
                    </p>
                </div>

                <!-- Two-Column Editorial Typographic List (NO repetitive cards) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-14 gap-y-10 border-t border-mist pt-10">
                    
                    <!-- Item 1 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item1_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item1_desc') }}
                        </p>
                    </div>

                    <!-- Item 2 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item4_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item4_desc') }}
                        </p>
                    </div>

                    <!-- Item 3 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item2_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item2_desc') }}
                        </p>
                    </div>

                    <!-- Item 4 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item5_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item5_desc') }}
                        </p>
                    </div>

                    <!-- Item 5 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item3_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item3_desc') }}
                        </p>
                    </div>

                    <!-- Item 6 -->
                    <div class="flex flex-col">
                        <h3 class="font-serif text-lg font-bold text-ink mb-1.5">
                            {{ t('ethiopia.item6_title') }}
                        </h3>
                        <p class="text-sm text-ink/75 leading-relaxed max-w-prose">
                            {{ t('ethiopia.item6_desc') }}
                        </p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 7. ALSO INCLUDED (Semantic Definition List <dl>)     -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-white" aria-labelledby="included-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="max-w-2xl mb-12">
                    <h2 id="included-heading" class="font-serif text-2xl sm:text-3xl text-ink tracking-tight mb-2">
                        {{ t('included.title') }}
                    </h2>
                    <p class="text-sm sm:text-base text-ink/75">
                        {{ t('included.subtitle') }}
                    </p>
                </div>

                <!-- Definition List Structure -->
                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 divide-y md:divide-y-0 md:divide-x divide-mist border-t border-b border-mist py-8">
                    
                    <div class="pt-6 md:pt-0 md:px-4 first:pl-0 last:pr-0">
                        <dt class="font-serif font-bold text-base text-ink mb-2">
                            {{ t('included.thank_you_title') }}
                        </dt>
                        <dd class="text-xs sm:text-sm text-ink/75 leading-relaxed">
                            {{ t('included.thank_you_desc') }}
                        </dd>
                    </div>

                    <div class="pt-6 md:pt-0 md:px-4">
                        <dt class="font-serif font-bold text-base text-ink mb-2">
                            {{ t('included.reminders_title') }}
                        </dt>
                        <dd class="text-xs sm:text-sm text-ink/75 leading-relaxed">
                            {{ t('included.reminders_desc') }}
                        </dd>
                    </div>

                    <div class="pt-6 md:pt-0 md:px-4">
                        <dt class="font-serif font-bold text-base text-ink mb-2">
                            {{ t('included.custom_messages_title') }}
                        </dt>
                        <dd class="text-xs sm:text-sm text-ink/75 leading-relaxed">
                            {{ t('included.custom_messages_desc') }}
                        </dd>
                    </div>

                    <div class="pt-6 md:pt-0 md:px-4">
                        <dt class="font-serif font-bold text-base text-ink mb-2">
                            {{ t('included.counter_title') }}
                        </dt>
                        <dd class="text-xs sm:text-sm text-ink/75 leading-relaxed">
                            {{ t('included.counter_desc') }}
                        </dd>
                    </div>

                </dl>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 8. PRICING TIERS (Live Database Tiers, Gold Highlight) -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-paper" aria-labelledby="pricing-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <h2 id="pricing-heading" class="font-serif text-3xl sm:text-4xl text-ink tracking-tight mb-3">
                        {{ t('pricing.title') }}
                    </h2>
                    <p class="text-base text-ink/75 font-medium">
                        {{ t('pricing.subtitle') }}
                    </p>
                </div>

                <!-- Tiers Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6 items-stretch mb-8">
                    <div
                        v-for="tier in tiers"
                        :key="tier.id"
                        :class="[
                            'bg-white rounded-md p-5 flex flex-col justify-between transition-all relative border',
                            tier.slug === 'gold'
                                ? 'border-2 border-meskel shadow-xl ring-2 ring-meskel/30 order-first md:order-none'
                                : 'border-mist shadow-sm hover:border-ink/40'
                        ]"
                    >
                        <!-- Gold Highlight Badge -->
                        <div v-if="tier.slug === 'gold'" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-meskel text-ink text-[11px] font-bold px-3 py-0.5 rounded-pill shadow-sm whitespace-nowrap">
                            {{ t('pricing.most_popular') }}
                        </div>

                        <div>
                            <!-- Tier Name -->
                            <h3 class="font-serif text-xl font-bold text-ink mb-1">
                                {{ currentLocale === 'am' && tier.name_am ? tier.name_am : tier.name_en }}
                            </h3>
                            <p class="text-xs text-ink/65 mb-4 line-clamp-2">
                                {{ currentLocale === 'am' && tier.description_am ? tier.description_am : tier.description_en }}
                            </p>

                            <!-- Price in ETB -->
                            <div class="mb-5 pb-4 border-b border-mist">
                                <span class="text-2xl sm:text-3xl font-bold text-ink">
                                    {{ Number(tier.price_amount) === 0 ? '0' : Number(tier.price_amount).toLocaleString() }}
                                </span>
                                <span class="text-xs font-semibold text-ink/60 ml-1">ETB</span>
                            </div>

                            <!-- Quota & Validity Specifications -->
                            <ul class="space-y-2 text-xs text-ink/80 mb-4">
                                <li class="flex items-center gap-2 font-semibold text-ink">
                                    <span class="text-highland">✓</span>
                                    <span>{{ t('pricing.quota', { quota: tier.card_quota?.toLocaleString() }) }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-highland">✓</span>
                                    <span>{{ tier.validity_days ? t('pricing.validity', { days: tier.validity_days }) : t('pricing.unlimited_validity') }}</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="text-highland">✓</span>
                                    <span>SMS & Email Delivery</span>
                                </li>
                                <li v-if="tier.features?.qr_checkin" class="flex items-center gap-2">
                                    <span class="text-highland">✓</span>
                                    <span>Single-use QR Scanner</span>
                                </li>
                            </ul>

                            <!-- Bank / Telebirr Manual Payment Activation Notice -->
                            <p v-if="Number(tier.price_amount) > 0" class="text-[11px] text-ink/75 mb-4 bg-amber-50/70 p-2.5 rounded border border-amber-200/60 leading-relaxed">
                                {{ currentLocale === 'am'
                                    ? 'በባንክ ዝውውር ወይም በቴሌብር ይክፈሉ። ክፍያዎን አረጋግጠን በ24 ሰዓት ውስጥ ካርዶችዎን እናስጀምራለን።'
                                    : 'Pay by bank transfer or Telebirr. We activate your cards within 24 hours after verifying your payment.'
                                }}
                            </p>
                        </div>

                        <!-- CTA Button -->
                        <div class="pt-4 border-t border-mist/80">
                            <template v-if="tier.slug === 'free'">
                                <Link
                                    :href="route('editor.show')"
                                    class="w-full inline-flex items-center justify-center py-2.5 px-3 text-xs font-semibold rounded-sm bg-mist text-ink hover:bg-mist-border transition-colors text-center"
                                >
                                    {{ t('pricing.free_cta') }}
                                </Link>
                            </template>
                            <template v-else>
                                <Link
                                    :href="$page.props.auth?.user ? route('plan.index', { tier: tier.slug }) : route('register', { tier: tier.slug })"
                                    :class="[
                                        'w-full inline-flex items-center justify-center py-2.5 px-3 text-xs font-semibold rounded-sm transition-colors text-center shadow-sm',
                                        tier.slug === 'gold'
                                            ? 'bg-meskel text-ink hover:bg-meskel-dark font-bold'
                                            : 'bg-ink text-white hover:bg-ink-light'
                                    ]"
                                >
                                    {{ t('pricing.choose_plan') }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Footnote Clarification -->
                <p class="text-xs text-center text-ink/60">
                    {{ currentLocale === 'am' ? '1 ካርድ = 1 መልእክት ለአንድ እንግዳ። ተጨማሪ ወርሃዊ ክፍያ የለም፤ የሚከፍሉት ለዝግጅትዎ ብቻ ነው።' : '1 card = 1 message to 1 guest. No recurring monthly subscriptions; pay per event.' }}
                </p>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 9. FAQ (Native <details> and <summary>)              -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-24 border-b border-mist bg-white" aria-labelledby="faq-heading">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Section Header -->
                <div class="text-center mb-12">
                    <h2 id="faq-heading" class="font-serif text-3xl sm:text-4xl text-ink tracking-tight mb-2">
                        {{ t('faq.title') }}
                    </h2>
                    <p class="text-base text-ink/75">
                        {{ t('faq.subtitle') }}
                    </p>
                </div>

                <!-- Native Details / Summary Disclosure List -->
                <div class="space-y-4">
                    
                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q1') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a1') }}
                        </p>
                    </details>

                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q2') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a2') }}
                        </p>
                    </details>

                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q3') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a3') }}
                        </p>
                    </details>

                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q4') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a4') }}
                        </p>
                    </details>

                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q5') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a5') }}
                        </p>
                    </details>

                    <details class="group bg-paper border border-mist rounded-sm p-4 transition-colors">
                        <summary class="font-serif text-base font-bold text-ink cursor-pointer list-none flex items-center justify-between">
                            <span>{{ t('faq.q6') }}</span>
                            <span class="text-ink/60 group-open:rotate-180 transition-transform">▼</span>
                        </summary>
                        <p class="mt-3 text-sm text-ink/80 leading-relaxed max-w-prose border-t border-mist/70 pt-3">
                            {{ t('faq.a6') }}
                        </p>
                    </details>

                </div>
            </div>
        </section>

        <!-- ==================================================== -->
        <!-- 10. FINAL CTA & FOOTER                               -->
        <!-- ==================================================== -->
        <section class="py-16 sm:py-20 bg-ink text-white text-center" aria-labelledby="cta-heading">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 id="cta-heading" class="font-serif text-3xl sm:text-5xl text-meskel tracking-tight mb-4">
                    {{ t('cta.title') }}
                </h2>
                <p class="text-base sm:text-lg text-white/80 max-w-prose mx-auto mb-8">
                    {{ t('cta.subtitle') }}
                </p>
                <Link
                    :href="route('editor.show')"
                    class="inline-flex items-center justify-center px-8 py-4 text-base font-semibold rounded-sm bg-meskel text-ink hover:bg-meskel-dark transition-colors shadow-lg"
                >
                    {{ t('cta.button') }}
                </Link>
            </div>
        </section>

        <footer class="bg-paper py-12 border-t border-mist text-xs text-ink/70">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
                <!-- Brand & Rights -->
                <div class="flex items-center gap-3">
                    <span class="font-serif font-bold text-lg text-ink">
                        {{ appName }}
                    </span>
                    <span>•</span>
                    <span>© 2026 {{ appName }}. {{ t('footer.rights') }}</span>
                </div>

                <!-- Footer Navigation Links (Strictly No Admin Paths) -->
                <div class="flex flex-wrap items-center gap-6">
                    <!-- Language Quick Switch -->
                    <button
                        type="button"
                        @click="switchLocale(currentLocale === 'en' ? 'am' : 'en')"
                        class="hover:text-ink transition-colors font-medium"
                    >
                        {{ currentLocale === 'en' ? 'አማርኛ' : 'English' }}
                    </button>
                    <Link :href="route('terms')" class="hover:text-ink transition-colors">{{ t('footer.terms') }}</Link>
                    <Link :href="route('privacy')" class="hover:text-ink transition-colors">{{ t('footer.privacy') }}</Link>
                    <a href="mailto:support@invite.et" class="hover:text-ink transition-colors">{{ t('footer.contact') }}</a>
                </div>
            </div>
        </footer>

    </div>
</template>
