<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
const page = usePage();
const container = ref(null);
const widgetId = ref(null);

const siteKey = computed(() => page.props.turnstileSiteKey || '');

onMounted(() => {
    if (!siteKey.value) return;

    const renderWidget = () => {
        if (window.turnstile && container.value) {
            try {
                widgetId.value = window.turnstile.render(container.value, {
                    sitekey: siteKey.value,
                    callback: (token) => {
                        emit('update:modelValue', token);
                    },
                    'expired-callback': () => {
                        emit('update:modelValue', '');
                    },
                    'error-callback': () => {
                        emit('update:modelValue', '');
                    },
                });
            } catch (e) {
                // Ignore render errors
            }
        }
    };

    if (window.turnstile) {
        renderWidget();
    } else {
        const existingScript = document.getElementById('cf-turnstile-script');
        if (!existingScript) {
            const script = document.createElement('script');
            script.id = 'cf-turnstile-script';
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            script.defer = true;
            script.onload = () => {
                if (window.turnstile) {
                    window.turnstile.ready(renderWidget);
                }
            };
            document.head.appendChild(script);
        } else {
            existingScript.addEventListener('load', renderWidget);
        }
    }
});

onUnmounted(() => {
    if (window.turnstile && widgetId.value !== null) {
        try {
            window.turnstile.remove(widgetId.value);
        } catch (e) {}
    }
});
</script>

<template>
    <div v-if="siteKey" class="my-3 flex justify-center">
        <div ref="container"></div>
    </div>
</template>
