<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { renderDesignToCanvas } from '@/services/renderDesign';

const props = defineProps({
    design: {
        type: Object,
        required: true,
    },
    data: {
        type: Object,
        default: () => ({}),
    },
    lang: {
        type: String,
        default: 'en', // 'en' | 'am' | 'both'
    },
    palette: {
        type: Object,
        default: null,
    },
    pixelRatio: {
        type: Number,
        default: 1,
    },
    showWatermark: {
        type: Boolean,
        default: false,
    },
    previewMode: {
        type: Boolean,
        default: false,
    },
    width: {
        type: Number,
        default: null,
    },
    height: {
        type: Number,
        default: null,
    },
});

const emit = defineEmits(['stage-ready', 'canvas-ready']);

const canvasRef = ref(null);
const containerRef = ref(null);
const displayScale = ref(1);

const originalWidth = computed(() => props.width || props.design?.width || 1080);
const originalHeight = computed(() => props.height || props.design?.height || 1528);

const render = () => {
    if (!canvasRef.value || !props.design) return;

    const canvas = canvasRef.value;
    const targetScale = props.previewMode ? 0.5 : 1.0;

    renderDesignToCanvas(canvas, props.design, {
        data: props.data,
        lang: props.lang,
        palette: props.palette,
        scale: targetScale,
    });

    // Draw Watermark if required
    if (props.showWatermark) {
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.save();
            const w = canvas.width;
            const h = canvas.height;
            const barH = 48 * targetScale;
            ctx.fillStyle = 'rgba(15, 15, 18, 0.75)';
            ctx.fillRect(0, h - barH, w, barH);

            ctx.fillStyle = '#D4AF37';
            ctx.font = `600 ${16 * targetScale}px Montserrat, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Created with Teri • teri.et', w / 2, h - barH / 2);
            ctx.restore();
        }
    }

    emit('canvas-ready', canvas);
};

const exportDataUrl = (ratio = 2) => {
    if (!props.design) return null;

    // Render on an offscreen high-res canvas for crystal clear download
    const offscreen = document.createElement('canvas');
    renderDesignToCanvas(offscreen, props.design, {
        data: props.data,
        lang: props.lang,
        palette: props.palette,
        scale: ratio,
    });

    if (props.showWatermark) {
        const ctx = offscreen.getContext('2d');
        if (ctx) {
            ctx.save();
            const w = offscreen.width;
            const h = offscreen.height;
            const barH = 56 * ratio;
            ctx.fillStyle = 'rgba(15, 15, 18, 0.75)';
            ctx.fillRect(0, h - barH, w, barH);

            ctx.fillStyle = '#D4AF37';
            ctx.font = `600 ${18 * ratio}px Montserrat, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Created with Teri • teri.et', w / 2, h - barH / 2);
            ctx.restore();
        }
    }

    return offscreen.toDataURL('image/png');
};

defineExpose({
    exportDataUrl,
    getCanvas: () => canvasRef.value,
    getNode: () => canvasRef.value,
});

watch(
    () => [props.design, props.data, props.lang, props.palette, props.showWatermark],
    () => {
        nextTick(render);
    },
    { deep: true }
);

onMounted(() => {
    nextTick(render);
});
</script>

<template>
    <div ref="containerRef" class="relative max-w-full flex justify-center items-center select-none overflow-hidden">
        <canvas
            ref="canvasRef"
            class="max-w-full h-auto rounded-lg shadow-lg"
            :style="{
                aspectRatio: `${originalWidth} / ${originalHeight}`,
            }"
        />
    </div>
</template>
