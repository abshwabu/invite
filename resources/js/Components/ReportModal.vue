<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    reportUrl: {
        type: String,
        required: true,
    },
    lang: {
        type: String,
        default: 'en',
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    reason: 'spam',
    description: '',
    reporter_email: '',
});

const submitted = ref(false);

const submit = () => {
    form.post(props.reportUrl, {
        preserveScroll: true,
        onSuccess: () => {
            submitted.value = true;
        },
    });
};

const close = () => {
    submitted.value = false;
    form.reset();
    form.clearErrors();
    emit('close');
};
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/75 backdrop-blur-xs font-sans text-left"
        @click.self="close"
    >
        <div class="relative w-full max-w-md bg-zinc-900 border border-zinc-800 rounded-2xl p-6 shadow-2xl text-zinc-100">
            <!-- Close Button -->
            <button
                type="button"
                @click="close"
                class="absolute top-4 right-4 text-zinc-400 hover:text-white transition"
                aria-label="Close"
            >
                ✕
            </button>

            <!-- Success State -->
            <div v-if="submitted" class="text-center py-6 space-y-4">
                <div class="w-12 h-12 mx-auto rounded-full bg-green-950/80 border border-green-500/40 text-green-400 flex items-center justify-center text-xl font-bold">
                    ✓
                </div>
                <h3 class="font-serif text-lg font-bold text-white">
                    {{ lang === 'am' ? 'ሪፖርትዎ ደርሶናል' : 'Report Submitted' }}
                </h3>
                <p class="text-xs text-zinc-400 leading-relaxed max-w-xs mx-auto">
                    {{ lang === 'am'
                        ? 'ጥቆማዎ ለግምገማ ቡድናችን ተልኳል። ደህንነትዎን ለመጠበቅ አስፈላጊውን እርምጃ እንወስዳለን።'
                        : 'Thank you for helping keep our platform safe. Our moderation team has received your report and will review it promptly.' }}
                </p>
                <div class="pt-2">
                    <button
                        type="button"
                        @click="close"
                        class="px-5 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-xs font-semibold text-zinc-200 transition"
                    >
                        {{ lang === 'am' ? 'ዝጋ' : 'Close' }}
                    </button>
                </div>
            </div>

            <!-- Form State -->
            <div v-else class="space-y-4">
                <div>
                    <h3 class="font-serif text-lg font-bold text-white flex items-center gap-2">
                        <span>🛡</span>
                        <span>{{ lang === 'am' ? 'ይህንን ግብዣ ሪፖርት ያድርጉ' : 'Report This Invitation' }}</span>
                    </h3>
                    <p class="text-xs text-zinc-400 mt-1">
                        {{ lang === 'am'
                            ? 'ይህ ዝግጅት የፖሊሲ ጥሰት፣ ማጭበርበር ወይም አላስፈላጊ መልዕክት አለበት ብለው ካመኑ እባክዎ ሪፖርት ያድርጉ።'
                            : 'If this card or event violates platform policies, contains fraudulent details, spam, or harassment, please let us know.' }}
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Reason -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 mb-1">
                            {{ lang === 'am' ? 'ምክንያት' : 'Reason for Report' }} <span class="text-red-400">*</span>
                        </label>
                        <select
                            v-model="form.reason"
                            class="w-full rounded-xl bg-zinc-800 border-zinc-700 text-xs text-zinc-200 focus:border-amber-400 focus:ring-amber-400"
                            required
                        >
                            <option value="spam">{{ lang === 'am' ? 'አላስፈላጊ ማስታወቂያ (Spam)' : 'Spam / Unsolicited advertising' }}</option>
                            <option value="inappropriate">{{ lang === 'am' ? 'ተገቢ ያልሆነ ይዘት' : 'Inappropriate or offensive content' }}</option>
                            <option value="fraud">{{ lang === 'am' ? 'የማጭበርበር ሙከራ / ሀሰተኛ ዝግጅት' : 'Fraud / Fake event / Scam' }}</option>
                            <option value="harassment">{{ lang === 'am' ? 'ማስፈራራት ወይም ስም ማጥፋት' : 'Harassment or defamation' }}</option>
                            <option value="other">{{ lang === 'am' ? 'ሌላ ምክንያት' : 'Other' }}</option>
                        </select>
                        <p v-if="form.errors.reason" class="text-xs text-red-400 mt-1">{{ form.errors.reason }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 mb-1">
                            {{ lang === 'am' ? 'ተጨማሪ ዝርዝር (አማራጭ)' : 'Additional details (optional)' }}
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            maxlength="1000"
                            class="w-full rounded-xl bg-zinc-800 border-zinc-700 text-xs text-zinc-200 focus:border-amber-400 focus:ring-amber-400"
                            :placeholder="lang === 'am' ? 'ስለ ሁኔታው ተጨማሪ መረጃ ያካፍሉ...' : 'Provide any additional context for our team...'"
                        ></textarea>
                        <p v-if="form.errors.description" class="text-xs text-red-400 mt-1">{{ form.errors.description }}</p>
                    </div>

                    <!-- Reporter Email -->
                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 mb-1">
                            {{ lang === 'am' ? 'የእርስዎ ኢሜይል (አማራጭ)' : 'Your email (optional)' }}
                        </label>
                        <input
                            type="email"
                            v-model="form.reporter_email"
                            class="w-full rounded-xl bg-zinc-800 border-zinc-700 text-xs text-zinc-200 focus:border-amber-400 focus:ring-amber-400"
                            :placeholder="lang === 'am' ? 'እርስዎ ጋር ለመገናኘት...' : 'For follow-up if needed...'"
                        />
                        <p v-if="form.errors.reporter_email" class="text-xs text-red-400 mt-1">{{ form.errors.reporter_email }}</p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="close"
                            class="px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-xs text-zinc-300 transition"
                        >
                            {{ lang === 'am' ? 'ይቅር' : 'Cancel' }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-500 text-xs font-semibold text-white transition disabled:opacity-50"
                        >
                            {{ form.processing ? (lang === 'am' ? 'በመላክ ላይ...' : 'Submitting...') : (lang === 'am' ? 'ሪፖርት ላክ' : 'Submit Report') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
