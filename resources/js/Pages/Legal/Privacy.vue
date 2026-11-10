<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    appName: {
        type: String,
        default: 'Invite',
    },
});

const activeLang = ref('en');
</script>

<template>
    <Head :title="activeLang === 'am' ? 'የግላዊነት ፖሊሲ' : 'Privacy Policy'" />

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
                        <span class="text-xs uppercase tracking-wider font-semibold text-meskel-dark font-sans">Privacy & Data Protection</span>
                        <h1 class="font-serif text-3xl sm:text-4xl text-ink font-bold mt-1">Privacy Policy</h1>
                        <p class="text-xs text-ink/60 mt-2">Effective Date: January 1, 2026 • Last Updated: November 2026</p>
                    </div>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">1. Information We Collect</h2>
                        <p>We collect only the minimum necessary information to facilitate event invitation design, delivery, and check-in:</p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li><strong>Account Data:</strong> Name, email address, phone number, and encrypted password or OTP records.</li>
                            <li><strong>Event & Design Data:</strong> Event names, dates, venues, categories, invitation templates, custom card layouts, and schedules.</li>
                            <li><strong>Guest Information:</strong> Names, phone numbers, and email addresses provided by the event host to dispatch digital cards.</li>
                            <li><strong>RSVP & Check-in Records:</strong> Attendance status, plus-one counts, meal preferences, view timestamps, and door check-in logs.</li>
                            <li><strong>Billing & Verification Data:</strong> Bank/Telebirr transaction reference numbers and receipt images uploaded for manual payment verification.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">2. How We Use Your Information</h2>
                        <p>We use this information strictly to:</p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li>Dispatch invitation, reminder, and thank-you messages via SMS or email on behalf of event hosts.</li>
                            <li>Display secure digital cards and generate single-use QR admission passes.</li>
                            <li>Verify manual subscription payments and enforce sending quota allowances.</li>
                            <li>Prevent fraud, enforce spam limits, and protect guests from abusive campaigns.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">3. Guest Data Retention & Automatic Purging</h2>
                        <p>
                            We respect guest privacy and do not retain guest personal identifiable information (PII) indefinitely.
                            By default, <strong>30 days after an event concludes</strong>, guest personal details (names, phone numbers, email addresses, and notes) are automatically purged or anonymized in our databases. Event attendance counts and aggregate metrics are preserved for the host's historical summary without retaining guest PII.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">4. Guest Rights & Opt-Out</h2>
                        <p>
                            Guests who receive invitations have the right at any time to:
                        </p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li><strong>Opt Out of Reminders:</strong> Click the "Unsubscribe from event reminders" link present in every notification or invitation footer.</li>
                            <li><strong>Report Abuse:</strong> Use the "Report this invitation" button on any public invitation or thank-you card to flag spam, harassment, or unauthorized communications.</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">5. Host Data Rights & Data Export</h2>
                        <p>
                            Hosts may download a complete structured JSON copy of all personal data, events, guest lists, and billing records at any time by navigating to their <strong>Profile &gt; Data &amp; Privacy &gt; Download My Data</strong>.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">6. Security & Storage</h2>
                        <p>
                            All payment receipts are stored in private, unlisted storage and are accessible only to authorized administrators through time-limited signed cryptographic tokens. Web sessions use secure HTTP-only cookies, and our infrastructure employs strict Content Security Policies (CSP) and HSTS transport encryption.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">7. Contact & Data Inquiries</h2>
                        <p>
                            For inquiries regarding your personal data, data deletion requests, or privacy concerns, please contact our privacy team at <a href="mailto:privacy@invite.et" class="text-meskel-dark font-medium underline">privacy@invite.et</a>.
                        </p>
                    </section>
                </div>

                <!-- Amharic Version -->
                <div v-else class="space-y-8 text-ink/90 text-sm sm:text-base leading-relaxed">
                    <div class="border-b border-mist pb-6">
                        <span class="text-xs uppercase tracking-wider font-semibold text-meskel-dark font-sans">የግላዊነት እና የመረጃ ደህንነት</span>
                        <h1 class="font-serif text-3xl sm:text-4xl text-ink font-bold mt-1">የግላዊነት ፖሊሲ</h1>
                        <p class="text-xs text-ink/60 mt-2">የተተገበረበት ቀን፡ ጥር 2018 ዓ.ም • የመጨረሻ ማሻሻያ፡ ህዳር 2018 ዓ.ም</p>
                    </div>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">1. የምንሰበስባቸው መረጃዎች</h2>
                        <p>አገልግሎቱን በአግባቡ ለመስጠት የሚያስፈልጉ ወሳኝ መረጃዎችን ብቻ እንሰበስባለን፡</p>
                        <ul class="list-disc pl-6 space-y-1.5 text-ink/80 text-sm">
                            <li><strong>የተጠቃሚ መረጃ፡</strong> ስም፣ ኢሜይል፣ ስልክ ቁጥር እና የይለፍ ቃል፤</li>
                            <li><strong>የዝግጅት መረጃ፡</strong> የዝግጅቱ ስም፣ ቀን፣ ቦታ፣ እና የግብዣ ካርድ ዲዛይን፤</li>
                            <li><strong>የእንግዶች መረጃ፡</strong> በአስተናጋጁ የተመዘገቡ የእንግዶች ስም፣ ስልክ ቁጥር እና ኢሜይል፤</li>
                            <li><strong>የመገኘት መረጃ፡</strong> የእንግዶች ምላሽ (RSVP) እና በበር ላይ የተቃኙ የQR ኮዶች መረጃ።</li>
                        </ul>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">2. የእንግዶች መረጃ አጠባበቅ እና ማጥፋት</h2>
                        <p>
                            የእንግዶችን ግላዊነት ለመጠበቅ፣ ዝግጅቱ ከተጠናቀቀ ከ<strong>30 ቀናት በኋላ</strong> የእንግዶች የግል መረጃዎች (ስም፣ ስልክ እና ኢሜይል) በራስ-ሰር ከመረጃ ቋታችን ይሰረዛሉ።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">3. መረጃን የማውረድ መብት</h2>
                        <p>
                            ተጠቃሚዎች በማንኛውም ጊዜ የራሳቸውን መረጃ ከፕሮፋይል ገጻቸው "Download My Data" የሚለውን በመጫን በJSON ፋይል ማውረድ ይችላሉ።
                        </p>
                    </section>

                    <section class="space-y-3">
                        <h2 class="font-serif text-xl font-bold text-ink">4. ያግኙን</h2>
                        <p>
                            ስለ ግላዊነት ጥያቄ ካለዎት በ <a href="mailto:privacy@invite.et" class="text-meskel-dark font-medium underline">privacy@invite.et</a> ይጻፉልን።
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
