<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    appName: {
        type: String,
        default: 'Invite',
    },
});

const page = usePage();
const activeLang = ref('en');
</script>

<template>
    <Head :title="activeLang === 'am' ? 'የአገልግሎት ውሎች' : 'Terms of Service'" />

    <div class="min-h-screen bg-sand text-ink flex flex-col font-sans selection:bg-meskel selection:text-ink">
        <!-- Navigation Header -->
        <header class="bg-paper border-b border-mist sticky top-0 z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <Link :href="route('welcome')" class="font-serif font-bold text-2xl text-ink tracking-tight flex items-center gap-2">
                    <span class="w-7 h-7 rounded-sm bg-meskel inline-flex items-center justify-center text-ink text-xs font-black shadow-xs">✉</span>
                    <span>{{ appName }}</span>
                </Link>

                <div class="flex items-center gap-4 text-sm">
                    <button
                        type="button"
                        @click="activeLang = activeLang === 'en' ? 'am' : 'en'"
                        class="px-3 py-1 rounded-sm border border-mist bg-paper hover:bg-sand transition-colors font-medium text-xs"
                    >
                        {{ activeLang === 'en' ? 'አማርኛ' : 'English' }}
                    </button>
                    <Link :href="route('welcome')" class="text-xs text-ink/70 hover:text-ink transition-colors">
                        {{ activeLang === 'am' ? 'ወደ ዋና ገጽ' : 'Back to Home' }}
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 py-12">
            <article class="bg-paper border border-mist rounded-sm p-6 sm:p-12 shadow-xs">
                <!-- English Version -->
                <div v-if="activeLang === 'en'" class="space-y-8 text-ink/90 text-sm sm:text-base leading-relaxed">
                    <div class="border-b border-mist pb-6">
                        <span class="text-xs uppercase tracking-wider font-semibold text-meskel-dark font-sans">Legal</span>
                        <h1 class="font-serif text-3xl sm:text-4xl text-ink font-bold mt-1">Terms of Service</h1>
                        <p class="text-xs text-ink/60 mt-2">Effective Date: January 1, 2026 • Last Updated: November 2026</p>
                    </div>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">1. Acceptance of Terms</h2>
                        <p>
                            By creating an account, generating invitations, sending campaigns, or accessing the {{ appName }} platform ("Service"), you agree to be bound by these Terms of Service. If you do not agree to these terms, you must discontinue using the Service immediately.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">2. Permitted Use & Prohibited Content</h2>
                        <p>
                            {{ appName }} is built to celebrate life moments, weddings, memorials, religious holidays, and community gatherings. You agree NOT to use the platform to:
                        </p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li>Create or distribute spam, unauthorized promotional blasts, or unsolicited commercial messages.</li>
                            <li>Publish defamatory, abusive, harassing, threatening, hateful, or sexually explicit material.</li>
                            <li>Conduct fraudulent schemes, deceptive ticketing, phishing, or impersonation of individuals or organizations.</li>
                            <li>Violate any applicable local, regional, or national laws of the Federal Democratic Republic of Ethiopia.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">3. Host Responsibilities & Guest Communications</h2>
                        <p>
                            As an event organizer ("Host"), you warrant that you have obtained appropriate consent from your guests before uploading their phone numbers or email addresses to {{ appName }}. You are responsible for ensuring that all event details and digital invitations distributed through your account accurately reflect your event.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">4. Subscriptions, Payments & Quotas</h2>
                        <p>
                            Certain features, higher sending quotas, and white-label branding require a paid tier subscription. Payments in Ethiopia are currently processed via manual bank transfer or Telebirr.
                        </p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li>Once proof of payment is submitted and approved by an administrator, the selected tier is activated.</li>
                            <li>Subscriptions grant a specified card-sending quota for the duration of the term. Unused quotas do not roll over past the subscription expiry date.</li>
                            <li>When a subscription expires or quota is reached, new message sending is paused, but existing published cards and door QR check-in remain active.</li>
                            <li>Payments are non-refundable once approved and quota has been partially or fully utilized.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">5. Single-Use QR Check-in & Security</h2>
                        <p>
                            Each guest receives a unique, unguessable cryptographic token and QR code. Check-in is strictly single-use to prevent duplicate entries. {{ appName }} is not liable for unauthorized physical entry caused by hosts sharing private scan links or master scanner access.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">6. Moderation, Suspension & Termination</h2>
                        <p>
                            We reserve the right to suspend or terminate accounts, cancel active campaigns, or remove events that violate these terms or receive verified abuse reports. If an event or user is suspended for abuse, no refund will be issued.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">7. Limitation of Liability</h2>
                        <p>
                            To the maximum extent permitted by applicable law, {{ appName }} and its operators shall not be liable for any indirect, incidental, consequential, or punitive damages arising from the delivery, non-delivery, or delay of telecom SMS gateways, third-party network outages, or unauthorized third-party access.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">8. Contact Us</h2>
                        <p>
                            If you have questions regarding these Terms of Service or wish to report a violation, please contact us at <a href="mailto:support@invite.et" class="text-meskel-dark font-medium underline">support@invite.et</a>.
                        </p>
                    </section>
                </div>

                <!-- Amharic Version -->
                <div v-else class="space-y-8 text-ink/90 text-sm sm:text-base leading-relaxed">
                    <div class="border-b border-mist pb-6">
                        <span class="text-xs uppercase tracking-wider font-semibold text-meskel-dark font-sans">ህጋዊ መመሪያ</span>
                        <h1 class="font-serif text-3xl sm:text-4xl text-ink font-bold mt-1">የአገልግሎት ውሎች</h1>
                        <p class="text-xs text-ink/60 mt-2">የተተገበረበት ቀን፡ ጥር 2018 ዓ.ም • የመጨረሻ ማሻሻያ፡ ህዳር 2018 ዓ.ም</p>
                    </div>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">1. የውሉ ተቀባይነት</h2>
                        <p>
                            የ{{ appName }} አካውንት በመክፈት፣ ግብዣዎችን በማዘጋጀት ወይም መልዕክቶችን በመላክ በእነዚህ የአገልግሎት ውሎች ለመገዛት ተስማምተዋል። በእነዚህ ውሎች ካልተስማሙ አገልግሎቱን መጠቀም ማቆም ይኖርብዎታል።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">2. የተፈቀደ አጠቃቀም እና የተከለከሉ ይዘቶች</h2>
                        <p>
                            {{ appName }} ለሠርግ፣ ለልደት፣ ለሃይማኖታዊ በዓላት፣ ለተዝካር እና ለማህበራዊ ዝግጅቶች የተዘጋጀ ነው። ተጠቃሚዎች የሚከተሉትን እንዳያደርጉ በጥብቅ ተከልክለዋል፡
                        </p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li>ያልተፈቀደ አላስፈላጊ የማስታወቂያ መልዕክት (Spam) መላክ።</li>
                            <li>ስም የሚያጠፉ፣ የሚያስፈራሩ፣ የጥላቻ ወይም የስነ-ምግባር ብልግና ያለባቸውን ይዘቶች ማሰራጨት።</li>
                            <li>የማጭበርበር፣ ሀሰተኛ ትኬቶችን የመሸጥ ወይም የሌላ ሰውን ማንነት የመጠቀም ተግባራት።</li>
                            <li>የኢትዮጵያ ፌዴራላዊ ዴሞክራሲያዊ ሪፐብሊክ ህጎችን የሚጥሱ ማናቸውም እንቅስቃሴዎች።</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">3. የአዘጋጆች ኃላፊነት</h2>
                        <p>
                            የእንግዶችን ስልክ ቁጥር ወይም ኢሜይል ወደ ሲስተሙ ከማስገባትዎ በፊት የእንግዶቹን ፈቃድ ማግኘት የእርስዎ ኃላፊነት ነው። በግብዣ ካርዱ ላይ ለሚቀመጡ መረጃዎች በሙሉ አስተናጋጁ ሙሉ ኃላፊነት ይወስዳል።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">4. ክፍያዎች እና ኮታ</h2>
                        <p>
                            ተጨማሪ ካርዶችን ለመላክ የሚደረጉ ክፍያዎች በኢትዮጵያ ውስጥ ባሉ ባንኮች ወይም በቴሌብር በኩል ይፈጸማሉ። የክፍያ ማረጋገጫ በአድሚን ከተረጋገጠ በኋላ ጥቅሉ ወዲያውኑ ይነቃል። የተገዛው ኮታ ጊዜው ካለፈ በኋላ አይተላለፍም።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">5. የQR ኮድ ደህንነት</h2>
                        <p>
                            እያንዳንዱ እንግዳ በአንድ ጊዜ ብቻ የሚያገለግል የQR ኮድ ይደርሰዋል። ኮዱ አንዴ በበር ላይ ከተቃኘ በኋላ እንደገና አያገለግልም።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">6. ያግኙን</h2>
                        <p>
                            ማናቸውም ጥያቄዎች ወይም ጥቆማዎች ካሉዎት በ <a href="mailto:support@invite.et" class="text-meskel-dark font-medium underline">support@invite.et</a> ሊያገኙን ይችላሉ።
                        </p>
                    </section>
                </div>
            </article>
        </main>

        <!-- Footer -->
        <footer class="bg-paper py-8 border-t border-mist text-xs text-ink/70 text-center">
            <p>© 2026 {{ appName }}. All rights reserved.</p>
        </footer>
    </div>
</template>
