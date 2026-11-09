<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
    design: {
        type: Object,
        required: true,
    },
    data: {
        type: Object,
        default: () => ({}),
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

const emit = defineEmits(['stage-ready']);

const stageRef = ref(null);
const loadedImages = ref({});

const canvasWidth = computed(() => props.width || props.design.width || 1080);
const canvasHeight = computed(() => props.height || props.design.height || 1528);

const stageConfig = computed(() => ({
    width: canvasWidth.value,
    height: canvasHeight.value,
}));

// Resolve placeholders for text
const resolveText = (rawText) => {
    if (!rawText) return '';
    let result = String(rawText);
    const replacements = {
        '{{guest_name}}': props.data?.guest_name || 'Almaz Bekele',
        '{{event_title}}': props.data?.event_title || 'Grand Celebration',
        '{{date}}': props.data?.date || 'Saturday, October 24, 2027',
        '{{time}}': props.data?.time || '4:00 PM',
        '{{venue}}': props.data?.venue || 'Sheraton Addis, Addis Ababa',
        '{{host_names}}': props.data?.host_names || 'Abebe & Chaltu',
        ...props.data,
    };

    for (const [key, val] of Object.entries(replacements)) {
        const token = key.startsWith('{{') ? key : `{{${key}}}`;
        result = result.replaceAll(token, String(val ?? ''));
    }
    return result;
};

// Background configuration
const backgroundConfig = computed(() => {
    const bg = props.design.background || { type: 'color', value: '#18181B' };
    const w = canvasWidth.value;
    const h = canvasHeight.value;

    if (bg.type === 'color') {
        return {
            x: 0,
            y: 0,
            width: w,
            height: h,
            fill: bg.value || '#18181B',
        };
    }

    if (bg.type === 'gradient') {
        return {
            x: 0,
            y: 0,
            width: w,
            height: h,
            fillLinearGradientStartPoint: { x: 0, y: 0 },
            fillLinearGradientEndPoint: { x: 0, y: h },
            fillLinearGradientColorStops: [0, bg.value || '#18181B', 1, '#09090b'],
        };
    }

    return {
        x: 0,
        y: 0,
        width: w,
        height: h,
        fill: '#18181B',
    };
});

// Load images for image layers and background
const preloadImages = () => {
    // Check background image
    if (props.design.background?.type === 'image' && props.design.background.value) {
        loadImage(props.design.background.value);
    }

    // Check layer images
    if (Array.isArray(props.design.layers)) {
        for (const layer of props.design.layers) {
            if (layer.type === 'image' && layer.src) {
                loadImage(layer.src);
            }
        }
    }
};

const loadImage = (src) => {
    if (!src || loadedImages.value[src]) return;

    const img = new window.Image();
    img.crossOrigin = 'Anonymous';
    img.onload = () => {
        loadedImages.value = {
            ...loadedImages.value,
            [src]: img,
        };
    };
    img.src = src;
};

watch(() => props.design, () => {
    preloadImages();
}, { deep: true, immediate: true });

onMounted(() => {
    preloadImages();
    if (stageRef.value) {
        emit('stage-ready', stageRef.value.getNode());
    }
});

// Build Konva configs for each layer
const getLayerConfig = (layer) => {
    const base = {
        id: layer.id,
        name: layer.id,
        x: layer.x,
        y: layer.y,
        rotation: layer.rotation || 0,
        opacity: layer.opacity !== undefined ? layer.opacity : 1,
        listening: false,
    };

    if (layer.type === 'text') {
        const displayText = props.previewMode ? resolveText(layer.text) : layer.text;
        return {
            ...base,
            text: displayText,
            fontFamily: layer.fontFamily || 'Noto Sans Ethiopic',
            fontSize: layer.fontSize || 36,
            fontStyle: layer.fontWeight >= 600 ? 'bold' : 'normal',
            fill: layer.fill || '#FAFAFA',
            align: layer.align || 'center',
            lineHeight: layer.lineHeight || 1.3,
            letterSpacing: layer.letterSpacing || 0,
            width: layer.width > 0 ? layer.width : undefined,
            height: layer.height > 0 ? layer.height : undefined,
            padding: 8,
        };
    }

    if (layer.type === 'shape') {
        if (layer.shape === 'circle') {
            const radius = (layer.width || 100) / 2;
            return {
                ...base,
                x: layer.x + radius,
                y: layer.y + radius,
                radius: radius,
                fill: layer.fill || 'transparent',
                stroke: layer.stroke || '#D4AF37',
                strokeWidth: layer.strokeWidth !== undefined ? layer.strokeWidth : 2,
            };
        }

        if (layer.shape === 'line') {
            return {
                ...base,
                points: [0, 0, layer.width || 200, 0],
                stroke: layer.stroke || '#D4AF37',
                strokeWidth: layer.strokeWidth !== undefined ? layer.strokeWidth : 2,
                lineCap: 'round',
            };
        }

        // Rect
        return {
            ...base,
            width: layer.width || 100,
            height: layer.height || 100,
            fill: layer.fill || 'transparent',
            stroke: layer.stroke || '#D4AF37',
            strokeWidth: layer.strokeWidth !== undefined ? layer.strokeWidth : 2,
            cornerRadius: 4,
        };
    }

    if (layer.type === 'image') {
        return {
            ...base,
            width: layer.width || 300,
            height: layer.height || 300,
            image: loadedImages.value[layer.src] || null,
        };
    }

    return base;
};

// Export stage as Data URL (PNG)
const exportDataUrl = (ratio = 2) => {
    if (!stageRef.value) return null;
    const stage = stageRef.value.getNode();
    return stage.toDataURL({
        pixelRatio: ratio || props.pixelRatio,
        mimeType: 'image/png',
    });
};

const getStage = () => {
    return stageRef.value ? stageRef.value.getNode() : null;
};

defineExpose({
    exportDataUrl,
    getStage,
});
</script>

<template>
    <div class="card-renderer inline-block relative select-none">
        <v-stage ref="stageRef" :config="stageConfig">
            <!-- Background Layer -->
            <v-layer>
                <v-image
                    v-if="design.background?.type === 'image' && loadedImages[design.background.value]"
                    :config="{
                        x: 0,
                        y: 0,
                        width: canvasWidth,
                        height: canvasHeight,
                        image: loadedImages[design.background.value]
                    }"
                />
                <v-rect v-else :config="backgroundConfig" />
            </v-layer>

            <!-- Elements / Content Layer -->
            <v-layer>
                <template v-for="layer in design.layers" :key="layer.id">
                    <v-text
                        v-if="layer.type === 'text'"
                        :config="getLayerConfig(layer)"
                    />
                    <v-image
                        v-else-if="layer.type === 'image'"
                        :config="getLayerConfig(layer)"
                    />
                    <v-circle
                        v-else-if="layer.type === 'shape' && layer.shape === 'circle'"
                        :config="getLayerConfig(layer)"
                    />
                    <v-line
                        v-else-if="layer.type === 'shape' && layer.shape === 'line'"
                        :config="getLayerConfig(layer)"
                    />
                    <v-rect
                        v-else-if="layer.type === 'shape'"
                        :config="getLayerConfig(layer)"
                    />
                </template>
            </v-layer>

            <!-- Branding / Watermark Layer -->
            <v-layer v-if="showWatermark">
                <!-- Bottom banner background -->
                <v-rect
                    :config="{
                        x: 0,
                        y: canvasHeight - 56,
                        width: canvasWidth,
                        height: 56,
                        fill: 'rgba(15, 15, 18, 0.75)',
                    }"
                />
                <!-- Watermark text -->
                <v-text
                    :config="{
                        x: 0,
                        y: canvasHeight - 44,
                        width: canvasWidth,
                        text: 'Created with Invite • invite.et',
                        fontFamily: 'Montserrat, sans-serif',
                        fontSize: 20,
                        fill: '#D4AF37',
                        align: 'center',
                    }"
                />
            </v-layer>
        </v-stage>
    </div>
</template>
