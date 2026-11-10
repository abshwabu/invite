<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import CardRenderer from '@/Components/CardRenderer.vue';
import axios from 'axios';

import {
    renderDesignToCanvas,
    renderBorderLayer,
    renderOrnamentLayer,
    renderPatternLayer,
    renderShapeLayer,
    renderImageLayer,
    renderTextLayer,
    resolvePlaceholders,
} from '@/services/renderDesign';
import {
    resolveColor,
    deriveHarmoniousPalette,
    applyPalette,
    DEFAULT_PALETTE,
} from '@/services/paletteResolver';
import {
    getAllOrnaments,
    getAllSets,
    getAllTags,
    findOrnament,
    loadTintedSvg,
} from '@/services/ornamentRegistry';
import { getCachedTintedImage, clearRasterCache } from '@/services/rasterCache';
import { renderBackgroundToContext } from '@/services/backgroundRenderer';

const props = defineProps({
    event: {
        type: Object,
        default: null,
    },
    target: {
        type: String,
        default: 'invitation', // 'invitation' or 'thank_you'
    },
    design: {
        type: Object,
        required: true,
    },
    template: {
        type: Object,
        default: null,
    },
    initialTemplateDesign: {
        type: Object,
        default: null,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    curatedPalettes: {
        type: Array,
        default: () => [],
    },
    allPalettes: {
        type: Object,
        default: () => ({}),
    },
    cardLanguage: {
        type: String,
        default: 'en', // 'en' | 'am' | 'both'
    },
    hasRemoveBranding: {
        type: Boolean,
        default: false,
    },
    isGuest: {
        type: Boolean,
        default: true,
    },
    eventData: {
        type: Object,
        default: () => ({}),
    },
});

// Normalize design to v2 contract
const normalizeDesign = (raw) => {
    const clone = JSON.parse(JSON.stringify(raw || {}));
    if (!clone.version || clone.version < 2) {
        clone.version = 2;
    }
    if (!clone.width) clone.width = 1080;
    if (!clone.height) clone.height = 1528;
    if (!clone.palette || typeof clone.palette !== 'object') {
        clone.palette = { ...DEFAULT_PALETTE };
    }
    if (!clone.background || typeof clone.background !== 'object') {
        clone.background = { type: 'color', value: '@bg' };
    }
    if (!clone.background.texture) {
        clone.background.texture = { kind: 'none', opacity: 0.08, seed: 1 };
    }
    if (clone.background.vignette === undefined) {
        clone.background.vignette = 0;
    }
    if (!Array.isArray(clone.layers)) {
        clone.layers = [];
    }
    return clone;
};

// Locally reactive design state
const design = ref(normalizeDesign(props.design));
const currentCardLang = ref(props.cardLanguage || 'en');
const currentTemplateId = ref(props.template?.id || props.event?.template_id || null);

// Viewport & Zoom State
const stageRef = ref(null);
const transformerRef = ref(null);
const canvasContainerRef = ref(null);
const zoom = ref(0.5);
const isPreviewMode = ref(false);
const isExporting = ref(false);
const saveStatus = ref('saved'); // 'saved' | 'saving' | 'unsaved' | 'error'
const isDirty = ref(false);
const saveTimeout = ref(null);

// Selection State
const selectedLayerIds = ref([]);
const activeSidebarTab = ref('insert'); // 'insert' | 'ornaments' | 'palette' | 'layers' | 'templates'
const mobileTab = ref(null); // 'tools' | 'ornaments' | 'palette' | 'layers' | 'properties' | null
const showGuestModal = ref(false);
const guestModalMessage = ref('');
const showResetModal = ref(false);
const overflowWarnings = ref([]);

// Snapping Guide Lines
const guideLines = ref([]);

// Image Cache for Canvas
const loadedImages = ref({});
const imageFileInput = ref(null);
const replaceImageFileInput = ref(null);

// History Stack for Undo/Redo (Max 50 steps)
const history = ref([]);
const historyIndex = ref(-1);
const isHistoryNavigating = ref(false);

// Background offscreen canvas cache for 60fps mobile drag
let bgOffscreenCanvas = null;

// Palette Tools
const singleColorPicker = ref('#800020');
const PALETTE_ROLES = [
    { key: 'bg', label: 'Background (@bg)', role: '@bg' },
    { key: 'surface', label: 'Card Surface (@surface)', role: '@surface' },
    { key: 'primary', label: 'Primary Accent (@primary)', role: '@primary' },
    { key: 'secondary', label: 'Secondary Gold (@secondary)', role: '@secondary' },
    { key: 'accent', label: 'Decorative Accent (@accent)', role: '@accent' },
    { key: 'ink', label: 'Body Text (@ink)', role: '@ink' },
    { key: 'muted', label: 'Subtext & Borders (@muted)', role: '@muted' },
];

// Ornament Search & Filters
const ornamentSearch = ref('');
const ornamentSelectedSet = ref('all');
const ornamentSelectedTag = ref('all');
const allAvailableSets = computed(() => ['all', ...getAllSets()]);
const allAvailableTags = computed(() => ['all', ...getAllTags().slice(0, 24)]);

const filteredOrnaments = computed(() => {
    let list = getAllOrnaments();
    if (ornamentSelectedSet.value !== 'all') {
        list = list.filter(o => o.set === ornamentSelectedSet.value);
    }
    if (ornamentSelectedTag.value !== 'all') {
        list = list.filter(o => o.tags && o.tags.includes(ornamentSelectedTag.value));
    }
    if (ornamentSearch.value.trim()) {
        const q = ornamentSearch.value.toLowerCase().trim();
        list = list.filter(o =>
            o.id.toLowerCase().includes(q) ||
            (o.name_en && o.name_en.toLowerCase().includes(q)) ||
            (o.name_am && o.name_am.includes(q)) ||
            (o.tags && o.tags.some(t => t.toLowerCase().includes(q)))
        );
    }
    return list;
});

const AVAILABLE_FONTS = [
    { name: 'Noto Serif Ethiopic', label: 'Noto Serif Ethiopic (የክብር ግብዣ • Serif)', sample: 'የክብር ግብዣ • Formal' },
    { name: 'Noto Sans Ethiopic', label: 'Noto Sans Ethiopic (እንኳን ደስ አላችሁ • Sans)', sample: 'እንኳን ደስ አላችሁ • Celebrate' },
    { name: 'Cinzel', label: 'Cinzel (Classical Roman)', sample: 'CLASSICAL & NOBLE' },
    { name: 'Playfair Display', label: 'Playfair Display (Editorial Serif)', sample: 'Love, Joy & Elegance' },
    { name: 'Great Vibes', label: 'Great Vibes (Formal Calligraphy)', sample: 'Cordially Invites You' },
    { name: 'Montserrat', label: 'Montserrat (Modern Geometric)', sample: 'MODERN & MINIMAL' },
    { name: 'Oswald', label: 'Oswald (Bold Condensed)', sample: 'CELEBRATE TOGETHER' },
    { name: 'Caveat', label: 'Caveat (Warm Handwritten)', sample: 'Special Day With Us' },
];

// Save Endpoint URL
const saveUrl = computed(() => {
    if (!props.event) return null;
    return props.target === 'thank_you'
        ? route('events.thank-you-design.update', { event: props.event.id })
        : route('events.design.update', { event: props.event.id });
});

// Primary selected layer
const primarySelectedLayer = computed(() => {
    if (selectedLayerIds.value.length === 0) return null;
    return design.value.layers.find(l => l.id === selectedLayerIds.value[0]) || null;
});

// Canvas dimensions
const canvasWidth = computed(() => design.value.width || 1080);
const canvasHeight = computed(() => design.value.height || 1528);

const stageConfig = computed(() => ({
    width: canvasWidth.value,
    height: canvasHeight.value,
}));

// Effective Palette
const effectivePalette = computed(() => ({
    ...DEFAULT_PALETTE,
    ...(design.value.palette || {}),
}));

// Pre-render background onto offscreen canvas for 60fps performance
const updateBackgroundCache = () => {
    if (typeof document === 'undefined') return;
    const w = canvasWidth.value;
    const h = canvasHeight.value;

    if (!bgOffscreenCanvas) {
        bgOffscreenCanvas = document.createElement('canvas');
    }
    bgOffscreenCanvas.width = w;
    bgOffscreenCanvas.height = h;

    const ctx = bgOffscreenCanvas.getContext('2d');
    if (ctx) {
        ctx.clearRect(0, 0, w, h);
        renderBackgroundToContext(ctx, w, h, design.value.background, effectivePalette.value);
    }
};

// Preload Images
const preloadImages = () => {
    if (design.value.background?.type === 'image' && design.value.background.value) {
        loadImage(design.value.background.value);
    }
    for (const layer of design.value.layers) {
        if (layer.type === 'image' && layer.src) {
            loadImage(layer.src);
        }
    }
    updateBackgroundCache();
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
        transformerRef.value?.getNode()?.getLayer()?.batchDraw();
    };
    img.src = src;
};

// Placeholder resolver helper
const resolveText = (rawText) => {
    return resolvePlaceholders(rawText, {
        guest_name: props.eventData?.guest_name || 'Almaz Bekele',
        event_title: props.event?.title || props.eventData?.event_title || 'Grand Celebration',
        date: props.eventData?.date || 'Saturday, October 24, 2027',
        time: props.eventData?.time || '4:00 PM',
        venue: props.event?.venue_name || props.eventData?.venue || 'Sheraton Addis, Addis Ababa',
        host_names: props.event?.host_names || props.eventData?.host_names || 'Abebe & Chaltu',
    });
};

// History recording
const recordHistory = () => {
    if (isHistoryNavigating.value) return;

    const cloned = JSON.parse(JSON.stringify(design.value));
    const newHistory = history.value.slice(0, historyIndex.value + 1);
    newHistory.push(cloned);

    if (newHistory.length > 50) {
        newHistory.shift();
    }

    history.value = newHistory;
    historyIndex.value = newHistory.length - 1;

    updateBackgroundCache();
    markDirty();
};

const undo = () => {
    if (historyIndex.value > 0) {
        isHistoryNavigating.value = true;
        historyIndex.value--;
        design.value = JSON.parse(JSON.stringify(history.value[historyIndex.value]));
        preloadImages();
        nextTick(() => {
            updateTransformer();
            isHistoryNavigating.value = false;
            markDirty();
        });
    }
};

const redo = () => {
    if (historyIndex.value < history.value.length - 1) {
        isHistoryNavigating.value = true;
        historyIndex.value++;
        design.value = JSON.parse(JSON.stringify(history.value[historyIndex.value]));
        preloadImages();
        nextTick(() => {
            updateTransformer();
            isHistoryNavigating.value = false;
            markDirty();
        });
    }
};

// Auto-fit Zoom
const calculateFitZoom = () => {
    if (!canvasContainerRef.value) return;
    const containerW = canvasContainerRef.value.clientWidth - 48;
    const containerH = canvasContainerRef.value.clientHeight - 48;
    if (containerW <= 0 || containerH <= 0) return;

    const scaleX = containerW / canvasWidth.value;
    const scaleY = containerH / canvasHeight.value;
    const bestFit = Math.min(scaleX, scaleY, 0.9);
    zoom.value = Math.max(0.15, Math.min(1.2, parseFloat(bestFit.toFixed(2))));
};

// Autosave Engine
const markDirty = () => {
    isDirty.value = true;
    saveStatus.value = 'unsaved';

    if (saveTimeout.value) {
        clearTimeout(saveTimeout.value);
    }

    saveTimeout.value = setTimeout(() => {
        performSave();
    }, 3000);
};

const performSave = async () => {
    if (props.isGuest) {
        try {
            saveStatus.value = 'saving';
            localStorage.setItem('invite_guest_draft', JSON.stringify({
                template_id: currentTemplateId.value,
                design_json: design.value,
                card_language: currentCardLang.value,
                updated_at: new Date().toISOString(),
            }));
            saveStatus.value = 'saved';
            isDirty.value = false;
        } catch {
            saveStatus.value = 'error';
        }
        return;
    }

    if (!saveUrl.value) return;

    try {
        saveStatus.value = 'saving';
        await axios.patch(saveUrl.value, {
            design: design.value,
            template_id: currentTemplateId.value,
            card_language: currentCardLang.value,
        });
        saveStatus.value = 'saved';
        isDirty.value = false;
    } catch {
        saveStatus.value = 'error';
    }
};

// Transformer and Selection
const updateTransformer = () => {
    if (!transformerRef.value || !stageRef.value) return;
    const transformer = transformerRef.value.getNode();
    const stage = stageRef.value.getNode();

    if (isPreviewMode.value || selectedLayerIds.value.length === 0) {
        transformer.nodes([]);
        transformer.getLayer()?.batchDraw();
        return;
    }

    const selectedNodes = selectedLayerIds.value
        .map(id => stage.findOne(`#${id}`))
        .filter(Boolean)
        .filter(node => {
            const layer = design.value.layers.find(l => l.id === node.id());
            return layer && !layer.locked && !layer.hidden;
        });

    transformer.nodes(selectedNodes);
    transformer.getLayer()?.batchDraw();
};

const selectLayer = (layerId, isMulti = false) => {
    if (isPreviewMode.value) return;

    if (isMulti) {
        if (selectedLayerIds.value.includes(layerId)) {
            selectedLayerIds.value = selectedLayerIds.value.filter(id => id !== layerId);
        } else {
            selectedLayerIds.value = [...selectedLayerIds.value, layerId];
        }
    } else {
        selectedLayerIds.value = [layerId];
    }

    nextTick(() => {
        updateTransformer();
    });
};

const deselectAll = () => {
    selectedLayerIds.value = [];
    guideLines.value = [];
    nextTick(() => {
        updateTransformer();
    });
};

const handleStageClick = (e) => {
    const clickedOnEmpty = e.target === e.target.getStage() || e.target.name() === 'background-shape';
    if (clickedOnEmpty) {
        deselectAll();
    }
};

// Snapping and dragging
const handleDragStart = (e) => {
    const id = e.target.id();
    if (!selectedLayerIds.value.includes(id)) {
        selectedLayerIds.value = [id];
        updateTransformer();
    }
};

const handleDragMove = (e, layer) => {
    if (layer.locked || layer.hidden) return;

    const node = e.target;
    const threshold = 12;
    const stageW = canvasWidth.value;
    const stageH = canvasHeight.value;

    const layerW = (layer.width || 100) * (node.scaleX() || 1);
    const layerH = (layer.height || 100) * (node.scaleY() || 1);
    const centerX = node.x() + layerW / 2;
    const centerY = node.y() + layerH / 2;

    const guides = [];

    // Horizontal center snap
    if (Math.abs(centerX - stageW / 2) < threshold) {
        node.x(stageW / 2 - layerW / 2);
        guides.push({ points: [stageW / 2, 0, stageW / 2, stageH], stroke: '#D4AF37' });
    }

    // Vertical center snap
    if (Math.abs(centerY - stageH / 2) < threshold) {
        node.y(stageH / 2 - layerH / 2);
        guides.push({ points: [0, stageH / 2, stageW, stageH / 2], stroke: '#D4AF37' });
    }

    // Snap to left edge
    if (Math.abs(node.x()) < threshold) {
        node.x(0);
        guides.push({ points: [0, 0, 0, stageH], stroke: '#38BDF8' });
    }

    // Snap to right edge
    if (Math.abs(node.x() + layerW - stageW) < threshold) {
        node.x(stageW - layerW);
        guides.push({ points: [stageW, 0, stageW, stageH], stroke: '#38BDF8' });
    }

    guideLines.value = guides;
};

const handleDragEnd = (e, layer) => {
    guideLines.value = [];
    if (layer.locked) return;

    const node = e.target;
    layer.x = Math.round(node.x());
    layer.y = Math.round(node.y());

    recordHistory();
};

const handleTransformEnd = (e, layer) => {
    if (layer.locked) return;

    const node = e.target;
    const scaleX = node.scaleX();
    const scaleY = node.scaleY();

    node.scaleX(1);
    node.scaleY(1);

    layer.x = Math.round(node.x());
    layer.y = Math.round(node.y());
    layer.rotation = Math.round(node.rotation());
    layer.width = Math.max(20, Math.round(node.width() * scaleX));
    layer.height = Math.max(20, Math.round(node.height() * scaleY));

    recordHistory();
};

// Configuration for each layer rendered on the Konva stage via custom sceneFunc
const getKonvaLayerConfig = (layer) => {
    const isHidden = !!layer.hidden;
    const isLocked = !!layer.locked;

    return {
        id: layer.id,
        name: layer.id,
        x: layer.x,
        y: layer.y,
        width: layer.width || 100,
        height: layer.height || 100,
        rotation: layer.rotation || 0,
        opacity: isHidden ? 0 : (layer.opacity !== undefined ? layer.opacity : 1),
        draggable: !isPreviewMode.value && !isLocked && !isHidden,
        listening: !isPreviewMode.value && !isHidden,
        hitFunc: (context, shape) => {
            context.beginPath();
            context.rect(0, 0, shape.width(), shape.height());
            context.closePath();
            context.fillStrokeShape(shape);
        },
        sceneFunc: (context, shape) => {
            if (isHidden) return;
            const ctx = context._context;
            const w = shape.width();
            const h = shape.height();

            ctx.save();

            // Flip X & Y
            if (layer.flipX || layer.flipY) {
                const cx = w / 2;
                const cy = h / 2;
                ctx.translate(cx, cy);
                ctx.scale(layer.flipX ? -1 : 1, layer.flipY ? -1 : 1);
                ctx.translate(-cx, -cy);
            }

            if (layer.blendMode && layer.blendMode !== 'normal') {
                ctx.globalCompositeOperation = layer.blendMode;
            }

            const pal = effectivePalette.value;
            const renderOptions = {
                data: props.eventData,
                lang: currentCardLang.value,
                palette: pal,
                scale: 1,
            };

            switch (layer.type) {
                case 'ornament': {
                    const cached = getCachedTintedImage(
                        layer.assetId,
                        layer.tint,
                        pal,
                        () => {
                            stageRef.value?.getNode()?.batchDraw();
                        }
                    );
                    if (cached) {
                        ctx.drawImage(cached, 0, 0, w, h);
                    }
                    break;
                }
                case 'border':
                    renderBorderLayer(ctx, layer, 0, 0, w, h, 1, pal);
                    break;
                case 'pattern':
                    renderPatternLayer(ctx, layer, 0, 0, w, h, 1, pal, {
                        ...renderOptions,
                        svgRasterizer: (svgStr, tw, th) => {
                            return getCachedTintedImage(layer.assetId, layer.tint, pal);
                        },
                    });
                    break;
                case 'shape':
                    renderShapeLayer(ctx, layer, 0, 0, w, h, 1, pal);
                    break;
                case 'image':
                    if (loadedImages.value[layer.src]) {
                        renderImageLayer(ctx, layer, 0, 0, w, h, 1, {
                            ...renderOptions,
                            imageLoader: () => loadedImages.value[layer.src],
                        });
                    } else if (layer.src) {
                        loadImage(layer.src);
                    }
                    break;
                case 'text':
                    renderTextLayer(ctx, layer, 0, 0, w, h, 1, currentCardLang.value, pal, renderOptions);
                    break;
                case 'group':
                    if (Array.isArray(layer.children)) {
                        for (const child of layer.children) {
                            ctx.save();
                            ctx.translate(child.x, child.y);
                            renderShapeLayer(ctx, child, 0, 0, child.width, child.height, 1, pal);
                            ctx.restore();
                        }
                    }
                    break;
            }

            ctx.restore();
            context.fillStrokeShape(shape);
        },
    };
};

// Add Element Handlers
const addTextLayer = (preset = 'heading') => {
    const id = crypto.randomUUID();
    let text = 'የክብር ግብዣ';
    let am = 'የክብር ግብዣ';
    let en = 'Cordially Invites You';
    let fontSize = 48;
    let fontWeight = 700;
    let fontFamily = 'Noto Serif Ethiopic';

    if (preset === 'subheading') {
        text = 'Cordially Invites You';
        am = 'በታላቅ ደስታ ይጋብዝዎታል';
        en = 'Cordially Invites You';
        fontSize = 32;
        fontWeight = 400;
        fontFamily = 'Playfair Display';
    } else if (preset === 'body') {
        text = 'To celebrate our joyous union with dinner, dancing & blessing';
        am = 'በዚህ ታላቅ የደስታ ቀን አብረውን እንዲያከብሩ ጥሪያችንን እናቀርባለን';
        en = 'To celebrate our joyous union with dinner, dancing & blessing';
        fontSize = 24;
        fontWeight = 400;
        fontFamily = 'Noto Sans Ethiopic';
    }

    const newLayer = {
        id,
        name: `Text (${preset})`,
        type: 'text',
        x: 140,
        y: 400 + (design.value.layers.length * 30),
        width: 800,
        height: 100,
        rotation: 0,
        opacity: 1,
        locked: false,
        text,
        i18n: { en, am },
        fontFamily,
        fontSize,
        fontWeight,
        fill: '@ink',
        align: 'center',
        lineHeight: 1.3,
        letterSpacing: 0,
        curve: 0,
        stroke: null,
        strokeWidth: 0,
        shadow: null,
        autoFit: null,
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

const insertPlaceholder = (token) => {
    if (primarySelectedLayer.value && primarySelectedLayer.value.type === 'text') {
        if (primarySelectedLayer.value.i18n) {
            primarySelectedLayer.value.i18n.en = (primarySelectedLayer.value.i18n.en || '') + ` ${token}`;
            primarySelectedLayer.value.i18n.am = (primarySelectedLayer.value.i18n.am || '') + ` ${token}`;
        } else {
            primarySelectedLayer.value.text = (primarySelectedLayer.value.text || '') + ` ${token}`;
        }
        recordHistory();
    } else {
        const id = crypto.randomUUID();
        const newLayer = {
            id,
            name: `Token ${token}`,
            type: 'text',
            x: 140,
            y: 500,
            width: 800,
            height: 90,
            rotation: 0,
            opacity: 1,
            locked: false,
            text: token,
            i18n: { en: token, am: token },
            fontFamily: 'Noto Serif Ethiopic',
            fontSize: 40,
            fontWeight: 600,
            fill: '@primary',
            align: 'center',
            lineHeight: 1.2,
            letterSpacing: 0,
        };
        design.value.layers.push(newLayer);
        recordHistory();
        selectLayer(id);
    }
};

const addOrnamentLayer = (ornament) => {
    const id = crypto.randomUUID();
    const aspect = ornament.defaultAspect || 1;
    const width = 240;
    const height = Math.round(240 / aspect);

    const newLayer = {
        id,
        name: ornament.name_en,
        type: 'ornament',
        assetId: ornament.id,
        tint: {
            c1: '@primary',
            c2: '@secondary',
            c3: '@accent',
        },
        x: Math.round((canvasWidth.value - width) / 2),
        y: Math.round((canvasHeight.value - height) / 2),
        width,
        height,
        rotation: 0,
        opacity: 1,
        locked: false,
        flipX: false,
        flipY: false,
        blendMode: 'normal',
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

const addBorderLayer = (style = 'double-rule') => {
    const id = crypto.randomUUID();
    const newLayer = {
        id,
        name: `Border (${style})`,
        type: 'border',
        style,
        x: 0,
        y: 0,
        width: canvasWidth.value,
        height: canvasHeight.value,
        inset: 48,
        stroke: '@primary',
        strokeWidth: 2,
        gap: 6,
        rotation: 0,
        opacity: 1,
        locked: false,
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

const addPatternLayer = (assetId = 'tilet-corner-01') => {
    const id = crypto.randomUUID();
    const newLayer = {
        id,
        name: 'Pattern Band',
        type: 'pattern',
        assetId,
        x: 0,
        y: 40,
        width: canvasWidth.value,
        height: 60,
        tileWidth: 60,
        tileHeight: 60,
        gapX: 4,
        gapY: 0,
        tint: { c1: '@primary', c2: '@secondary' },
        rotation: 0,
        opacity: 1,
        locked: false,
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

const addShapeLayer = (shape = 'rect') => {
    const id = crypto.randomUUID();
    const newLayer = {
        id,
        name: `Shape (${shape})`,
        type: 'shape',
        shape,
        x: 390,
        y: 550,
        width: shape === 'line' ? 300 : 200,
        height: shape === 'line' ? 4 : 200,
        rotation: 0,
        opacity: 1,
        locked: false,
        fill: shape === 'line' ? '@secondary' : 'transparent',
        stroke: '@primary',
        strokeWidth: 2,
        cornerRadius: 4,
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

// Image Upload
const promptImageUpload = () => {
    imageFileInput.value?.click();
};

const handleImageUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (props.isGuest) {
        const reader = new FileReader();
        reader.onload = (loadEvent) => {
            const dataUrl = loadEvent.target.result;
            insertImageLayer(dataUrl);
        };
        reader.readAsDataURL(file);
        return;
    }

    const formData = new FormData();
    formData.append('image', file);

    try {
        const res = await axios.post(route('events.design.upload-image'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.url) {
            insertImageLayer(res.data.url);
        }
    } catch {
        alert('Failed to upload image. Please try again.');
    }
};

const insertImageLayer = (src) => {
    const id = crypto.randomUUID();
    const newLayer = {
        id,
        name: 'Photo',
        type: 'image',
        src,
        x: 340,
        y: 500,
        width: 400,
        height: 400,
        mask: 'circle',
        maskOffsetX: 0,
        maskOffsetY: 0,
        rotation: 0,
        opacity: 1,
        locked: false,
    };

    loadImage(src);
    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

// Palette Operations (Task 4)
const selectAlternatePalette = (pal) => {
    design.value = applyPalette(design.value, {
        bg: pal.bg,
        surface: pal.surface,
        primary: pal.primary,
        secondary: pal.secondary,
        accent: pal.accent,
        ink: pal.ink,
        muted: pal.muted,
    });
    updateBackgroundCache();
    recordHistory();
    stageRef.value?.getNode()?.batchDraw();
};

const updatePaletteRole = (role, newHex) => {
    if (!design.value.palette) {
        design.value.palette = { ...DEFAULT_PALETTE };
    }
    design.value.palette[role] = newHex;
    updateBackgroundCache();
    recordHistory();
    stageRef.value?.getNode()?.batchDraw();
};

const generateFromSingleColor = () => {
    const newPalette = deriveHarmoniousPalette(singleColorPicker.value);
    design.value = applyPalette(design.value, newPalette);
    updateBackgroundCache();
    recordHistory();
    stageRef.value?.getNode()?.batchDraw();
};

// Reset to Template (Task 9)
const handleResetToTemplate = () => {
    const original = props.initialTemplateDesign || props.template?.design_json;
    if (original) {
        design.value = normalizeDesign(original);
        recordHistory();
        updateBackgroundCache();
        selectedLayerIds.value = [];
        showResetModal.value = false;
        stageRef.value?.getNode()?.batchDraw();
    }
};

// Layer Reordering & Management (Task 2)
const moveLayerUp = (id) => {
    const idx = design.value.layers.findIndex(l => l.id === id);
    if (idx < design.value.layers.length - 1) {
        const item = design.value.layers.splice(idx, 1)[0];
        design.value.layers.splice(idx + 1, 0, item);
        recordHistory();
    }
};

const moveLayerDown = (id) => {
    const idx = design.value.layers.findIndex(l => l.id === id);
    if (idx > 0) {
        const item = design.value.layers.splice(idx, 1)[0];
        design.value.layers.splice(idx - 1, 0, item);
        recordHistory();
    }
};

const deleteLayer = (id) => {
    design.value.layers = design.value.layers.filter(l => l.id !== id);
    selectedLayerIds.value = selectedLayerIds.value.filter(selId => selId !== id);
    recordHistory();
    nextTick(() => updateTransformer());
};

const groupSelectedLayers = () => {
    if (selectedLayerIds.value.length < 2) return;
    const ids = [...selectedLayerIds.value];
    const children = design.value.layers.filter(l => ids.includes(l.id));
    const remaining = design.value.layers.filter(l => !ids.includes(l.id));

    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    for (const c of children) {
        minX = Math.min(minX, c.x);
        minY = Math.min(minY, c.y);
        maxX = Math.max(maxX, c.x + c.width);
        maxY = Math.max(maxY, c.y + c.height);
    }

    const groupId = crypto.randomUUID();
    const groupLayer = {
        id: groupId,
        name: `Group (${children.length})`,
        type: 'group',
        x: minX,
        y: minY,
        width: Math.max(20, maxX - minX),
        height: Math.max(20, maxY - minY),
        rotation: 0,
        opacity: 1,
        locked: false,
        children: children.map(c => ({
            ...c,
            x: c.x - minX,
            y: c.y - minY,
        })),
    };

    remaining.push(groupLayer);
    design.value.layers = remaining;
    selectedLayerIds.value = [groupId];
    recordHistory();
    nextTick(() => updateTransformer());
};

const ungroupSelectedLayer = () => {
    if (!primarySelectedLayer.value || primarySelectedLayer.value.type !== 'group') return;
    const group = primarySelectedLayer.value;
    const idx = design.value.layers.findIndex(l => l.id === group.id);
    if (idx === -1) return;

    const unpacked = (group.children || []).map(c => ({
        ...c,
        x: c.x + group.x,
        y: c.y + group.y,
    }));

    design.value.layers.splice(idx, 1, ...unpacked);
    selectedLayerIds.value = unpacked.map(u => u.id);
    recordHistory();
    nextTick(() => updateTransformer());
};

// Keyboard Shortcuts
const handleKeyDown = (e) => {
    const isCtrlOrMeta = e.ctrlKey || e.metaKey;
    if (isCtrlOrMeta && (e.key === 'z' || e.key === 'Z')) {
        e.preventDefault();
        if (e.shiftKey) redo();
        else undo();
    } else if (isCtrlOrMeta && (e.key === 'y' || e.key === 'Y')) {
        e.preventDefault();
        redo();
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
        const active = document.activeElement;
        const isInput = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable);
        if (!isInput && selectedLayerIds.value.length > 0) {
            e.preventDefault();
            selectedLayerIds.value.forEach(id => deleteLayer(id));
        }
    }
};

// Export PNG
const exportCardPng = () => {
    isExporting.value = true;
    try {
        const offscreen = document.createElement('canvas');
        renderDesignToCanvas(offscreen, design.value, {
            data: props.eventData,
            lang: currentCardLang.value,
            palette: effectivePalette.value,
            scale: 2,
        });
        const link = document.createElement('a');
        link.download = `${props.event?.title || 'card-design'}.png`;
        link.href = offscreen.toDataURL('image/png');
        link.click();
    } catch (err) {
        alert('Could not export PNG. ' + err.message);
    } finally {
        isExporting.value = false;
    }
};

onMounted(() => {
    history.value = [JSON.parse(JSON.stringify(design.value))];
    historyIndex.value = 0;

    preloadImages();
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', calculateFitZoom);

    nextTick(() => {
        calculateFitZoom();
    });

    if (props.isGuest) {
        try {
            const stored = localStorage.getItem('invite_guest_draft');
            if (stored) {
                const parsed = JSON.parse(stored);
                if (parsed.design_json) {
                    design.value = normalizeDesign(parsed.design_json);
                    if (parsed.card_language) currentCardLang.value = parsed.card_language;
                    updateBackgroundCache();
                    history.value = [JSON.parse(JSON.stringify(design.value))];
                }
            }
        } catch {
            // Ignore
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('resize', calculateFitZoom);
    if (saveTimeout.value) clearTimeout(saveTimeout.value);
    clearRasterCache();
});

watch(() => design.value.palette, () => {
    updateBackgroundCache();
}, { deep: true });

watch(() => currentCardLang.value, () => {
    stageRef.value?.getNode()?.batchDraw();
});
</script>

<template>
    <div class="h-screen flex flex-col bg-zinc-950 text-zinc-100 font-sans select-none overflow-hidden">
        <Head :title="event ? `Editor: ${event.title}` : 'Design Card • Teri'" />

        <!-- Hidden File Inputs -->
        <input ref="imageFileInput" type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
        <input ref="replaceImageFileInput" type="file" accept="image/*" class="hidden" @change="handleImageUpload" />

        <!-- TOP BAR -->
        <header class="h-14 bg-zinc-900 border-b border-zinc-800 px-4 flex items-center justify-between z-30 shrink-0">
            <!-- Left: Back link & Title -->
            <div class="flex items-center space-x-3">
                <Link
                    v-if="event"
                    :href="route('events.show', { event: event.id })"
                    class="p-2 rounded-lg text-zinc-400 hover:text-zinc-100 hover:bg-zinc-800 transition"
                    title="Back to Event"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <Link v-else :href="route('home')" class="p-2 text-zinc-400 hover:text-zinc-100">
                    <ApplicationLogo class="w-6 h-6 text-[#D4AF37]" />
                </Link>
                <div>
                    <h1 class="text-sm font-semibold text-zinc-200 truncate max-w-[200px] md:max-w-md">
                        {{ event ? event.title : (template?.name || 'Custom Invitation') }}
                    </h1>
                    <div class="flex items-center space-x-2 text-[11px] text-zinc-400">
                        <span class="capitalize">{{ target === 'thank_you' ? 'Thank-You Card' : 'Invitation Card' }}</span>
                        <span>•</span>
                        <span>v2 ({{ canvasWidth }} × {{ canvasHeight }})</span>
                    </div>
                </div>
            </div>

            <!-- Center: Language Switcher & Save Status -->
            <div class="flex items-center space-x-3">
                <!-- Card Language Toggle (Task 5) -->
                <div class="flex items-center bg-zinc-950 rounded-lg p-0.5 border border-zinc-800 text-xs">
                    <button
                        type="button"
                        @click="currentCardLang = 'en'; markDirty();"
                        :class="currentCardLang === 'en' ? 'bg-zinc-800 text-[#D4AF37] font-semibold' : 'text-zinc-400 hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded transition"
                        title="English"
                    >
                        EN
                    </button>
                    <button
                        type="button"
                        @click="currentCardLang = 'am'; markDirty();"
                        :class="currentCardLang === 'am' ? 'bg-zinc-800 text-[#D4AF37] font-semibold' : 'text-zinc-400 hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded transition"
                        title="Amharic"
                    >
                        አማ
                    </button>
                    <button
                        type="button"
                        @click="currentCardLang = 'both'; markDirty();"
                        :class="currentCardLang === 'both' ? 'bg-zinc-800 text-[#D4AF37] font-semibold' : 'text-zinc-400 hover:text-zinc-200'"
                        class="px-2.5 py-1 rounded transition"
                        title="Both English and Amharic"
                    >
                        Both
                    </button>
                </div>

                <!-- Autosave Status -->
                <div class="hidden sm:flex items-center space-x-1.5 text-xs">
                    <span
                        class="w-2 h-2 rounded-full"
                        :class="{
                            'bg-emerald-500': saveStatus === 'saved',
                            'bg-amber-500 animate-pulse': saveStatus === 'saving',
                            'bg-zinc-500': saveStatus === 'unsaved',
                            'bg-rose-500': saveStatus === 'error'
                        }"
                    />
                    <span class="text-zinc-400 text-[11px]">
                        {{ saveStatus === 'saved' ? 'Saved' : (saveStatus === 'saving' ? 'Saving...' : 'Unsaved') }}
                    </span>
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center space-x-2">
                <!-- Reset to Template Button (Task 9) -->
                <button
                    v-if="initialTemplateDesign || template"
                    type="button"
                    @click="showResetModal = true"
                    class="px-2.5 py-1.5 text-xs text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800 rounded-lg transition hidden md:flex items-center space-x-1"
                    title="Reset to Template Design"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </button>

                <!-- Preview Toggle -->
                <button
                    type="button"
                    @click="isPreviewMode = !isPreviewMode; deselectAll();"
                    :class="isPreviewMode ? 'bg-[#D4AF37] text-zinc-950 font-semibold' : 'bg-zinc-800 text-zinc-300 hover:bg-zinc-700'"
                    class="px-3 py-1.5 text-xs rounded-lg transition flex items-center space-x-1"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>{{ isPreviewMode ? 'Edit' : 'Preview' }}</span>
                </button>

                <!-- Save Button -->
                <button
                    type="button"
                    @click="performSave"
                    class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-zinc-800 hover:bg-zinc-700 text-[#D4AF37] border border-zinc-700 hover:border-[#D4AF37] transition"
                >
                    Save
                </button>

                <!-- Export PNG Button -->
                <button
                    type="button"
                    @click="exportCardPng"
                    :disabled="isExporting"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gradient-to-r from-[#D4AF37] to-[#B38F24] hover:brightness-110 text-zinc-950 shadow transition flex items-center space-x-1"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>{{ isExporting ? 'Exporting...' : 'Export' }}</span>
                </button>
            </div>
        </header>

        <!-- MAIN WORKSPACE -->
        <div class="flex-1 flex overflow-hidden relative">
            <!-- LEFT TOOL SIDEBAR (Desktop) -->
            <aside class="w-80 bg-zinc-950 border-r border-zinc-800 flex flex-col shrink-0 z-20 hidden md:flex">
                <!-- Sidebar Tabs -->
                <div class="flex border-b border-zinc-800 text-xs font-medium">
                    <button
                        type="button"
                        @click="activeSidebarTab = 'insert'"
                        :class="activeSidebarTab === 'insert' ? 'text-[#D4AF37] border-b-2 border-[#D4AF37] bg-zinc-900/60' : 'text-zinc-400 hover:text-zinc-200'"
                        class="flex-1 py-3 text-center transition"
                    >
                        Insert
                    </button>
                    <button
                        type="button"
                        @click="activeSidebarTab = 'ornaments'"
                        :class="activeSidebarTab === 'ornaments' ? 'text-[#D4AF37] border-b-2 border-[#D4AF37] bg-zinc-900/60' : 'text-zinc-400 hover:text-zinc-200'"
                        class="flex-1 py-3 text-center transition"
                    >
                        Ornaments
                    </button>
                    <button
                        type="button"
                        @click="activeSidebarTab = 'palette'"
                        :class="activeSidebarTab === 'palette' ? 'text-[#D4AF37] border-b-2 border-[#D4AF37] bg-zinc-900/60' : 'text-zinc-400 hover:text-zinc-200'"
                        class="flex-1 py-3 text-center transition"
                    >
                        Palette
                    </button>
                    <button
                        type="button"
                        @click="activeSidebarTab = 'layers'"
                        :class="activeSidebarTab === 'layers' ? 'text-[#D4AF37] border-b-2 border-[#D4AF37] bg-zinc-900/60' : 'text-zinc-400 hover:text-zinc-200'"
                        class="flex-1 py-3 text-center transition flex items-center justify-center space-x-1"
                    >
                        <span>Layers</span>
                        <span class="text-[10px] px-1.5 py-0.2 rounded-full bg-zinc-800 text-zinc-300">{{ design.layers.length }}</span>
                    </button>
                    <button
                        type="button"
                        @click="activeSidebarTab = 'templates'"
                        :class="activeSidebarTab === 'templates' ? 'text-[#D4AF37] border-b-2 border-[#D4AF37] bg-zinc-900/60' : 'text-zinc-400 hover:text-zinc-200'"
                        class="flex-1 py-3 text-center transition"
                    >
                        Templates
                    </button>
                </div>

                <!-- TAB CONTENT -->
                <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
                    <!-- 1. INSERT TAB -->
                    <div v-if="activeSidebarTab === 'insert'" class="space-y-5">
                        <!-- Add Text Presets -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Add Typography</h3>
                            <div class="grid grid-cols-1 gap-2">
                                <button
                                    type="button"
                                    @click="addTextLayer('heading')"
                                    class="w-full text-left p-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition flex items-center justify-between"
                                >
                                    <div>
                                        <div class="font-serif font-bold text-base text-zinc-100">የክብር ግብዣ • Title</div>
                                        <div class="text-[11px] text-zinc-500">Noto Serif Ethiopic, Bold, 48px</div>
                                    </div>
                                    <span class="text-[#D4AF37] text-lg font-light">+</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addTextLayer('subheading')"
                                    class="w-full text-left p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition flex items-center justify-between"
                                >
                                    <div>
                                        <div class="font-serif italic text-sm text-zinc-200">Cordially Invites You</div>
                                        <div class="text-[11px] text-zinc-500">Playfair Display, 32px</div>
                                    </div>
                                    <span class="text-[#D4AF37] text-lg font-light">+</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addTextLayer('body')"
                                    class="w-full text-left p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition flex items-center justify-between"
                                >
                                    <div>
                                        <div class="text-xs text-zinc-300">Event Details & Description</div>
                                        <div class="text-[11px] text-zinc-500">Noto Sans Ethiopic, 24px</div>
                                    </div>
                                    <span class="text-[#D4AF37] text-lg font-light">+</span>
                                </button>
                            </div>
                        </div>

                        <!-- Add Decorative Borders (Task 6) -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Frames & Borders</h3>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <button
                                    type="button"
                                    @click="addBorderLayer('double-rule')"
                                    class="p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition text-center"
                                >
                                    Double Rule Frame
                                </button>
                                <button
                                    type="button"
                                    @click="addBorderLayer('ornate-corners')"
                                    class="p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition text-center"
                                >
                                    Ornate Corners
                                </button>
                                <button
                                    type="button"
                                    @click="addBorderLayer('tilet-band')"
                                    class="p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition text-center"
                                >
                                    Tilet Band
                                </button>
                                <button
                                    type="button"
                                    @click="addBorderLayer('single-rule')"
                                    class="p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition text-center"
                                >
                                    Single Rule
                                </button>
                            </div>
                        </div>

                        <!-- Add Pattern Band (Task 6) -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Patterns</h3>
                            <button
                                type="button"
                                @click="addPatternLayer()"
                                class="w-full p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition text-xs flex items-center justify-center space-x-2"
                            >
                                <span>✦ Add Pattern Band</span>
                            </button>
                        </div>

                        <!-- Dynamic Placeholders -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Dynamic Placeholders</h3>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="token in ['{{guest_name}}', '{{event_title}}', '{{date}}', '{{time}}', '{{venue}}', '{{host_names}}']"
                                    :key="token"
                                    type="button"
                                    @click="insertPlaceholder(token)"
                                    class="text-[11px] px-2 py-1 rounded-md bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] text-zinc-300 font-mono transition"
                                >
                                    {{ token }}
                                </button>
                            </div>
                        </div>

                        <!-- Basic Shapes -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Shapes</h3>
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="s in [
                                        { name: 'rect', label: 'Box' },
                                        { name: 'circle', label: 'Circle' },
                                        { name: 'arch', label: 'Arch' },
                                        { name: 'line', label: 'Divider' }
                                    ]"
                                    :key="s.name"
                                    type="button"
                                    @click="addShapeLayer(s.name)"
                                    class="p-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37] transition flex flex-col items-center justify-center text-xs"
                                >
                                    <span>{{ s.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Upload Image -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Upload Media</h3>
                            <button
                                type="button"
                                @click="promptImageUpload"
                                class="w-full py-3 px-4 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-dashed border-zinc-700 hover:border-[#D4AF37] flex flex-col items-center justify-center space-y-1 transition"
                            >
                                <svg class="w-5 h-5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs font-medium text-zinc-200">Upload Photo / Portrait</span>
                                <span class="text-[10px] text-zinc-500">Supports Circle, Arch, Oval Masks</span>
                            </button>
                        </div>
                    </div>

                    <!-- 2. ORNAMENTS TAB (Task 3) -->
                    <div v-else-if="activeSidebarTab === 'ornaments'" class="space-y-3">
                        <div class="space-y-2">
                            <input
                                v-model="ornamentSearch"
                                type="text"
                                placeholder="Search ornaments (cross, floral, tilet...)"
                                class="w-full px-3 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg text-xs text-zinc-200 focus:border-[#D4AF37] focus:ring-0 placeholder-zinc-500"
                            />

                            <!-- Set Selector -->
                            <div class="flex items-center space-x-2">
                                <label class="text-[11px] text-zinc-400 shrink-0">Set:</label>
                                <select
                                    v-model="ornamentSelectedSet"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded text-xs py-1 px-2 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                >
                                    <option v-for="s in allAvailableSets" :key="s" :value="s">
                                        {{ s.charAt(0).toUpperCase() + s.slice(1) }}
                                    </option>
                                </select>
                            </div>

                            <!-- Tag Filter Pills -->
                            <div class="flex flex-wrap gap-1 max-h-20 overflow-y-auto custom-scrollbar">
                                <button
                                    v-for="tag in allAvailableTags"
                                    :key="tag"
                                    type="button"
                                    @click="ornamentSelectedTag = tag"
                                    :class="ornamentSelectedTag === tag ? 'bg-[#D4AF37] text-zinc-950 font-bold' : 'bg-zinc-900 text-zinc-400 hover:text-zinc-200'"
                                    class="text-[10px] px-2 py-0.5 rounded-full border border-zinc-800 transition"
                                >
                                    {{ tag }}
                                </button>
                            </div>
                        </div>

                        <!-- Ornaments Grid -->
                        <div class="grid grid-cols-2 gap-2 max-h-[calc(100vh-280px)] overflow-y-auto custom-scrollbar pr-1">
                            <div
                                v-for="ornament in filteredOrnaments"
                                :key="ornament.id"
                                @click="addOrnamentLayer(ornament)"
                                class="group cursor-pointer p-2.5 rounded-lg border border-zinc-800 hover:border-[#D4AF37] bg-zinc-900/60 hover:bg-zinc-800/80 transition flex flex-col items-center justify-between"
                                :title="`${ornament.name_en} (${ornament.name_am})`"
                            >
                                <div class="w-full aspect-square flex items-center justify-center p-1.5 overflow-hidden">
                                    <div
                                        class="w-full h-full flex items-center justify-center text-zinc-300 group-hover:text-[#D4AF37] transition"
                                        v-html="loadTintedSvg(ornament.id, { c1: effectivePalette.primary, c2: effectivePalette.secondary, c3: effectivePalette.accent }, effectivePalette)"
                                    />
                                </div>
                                <div class="w-full mt-1.5 text-center">
                                    <div class="text-[11px] font-medium text-zinc-300 group-hover:text-zinc-100 truncate">
                                        {{ ornament.name_en }}
                                    </div>
                                    <div class="text-[9px] text-zinc-500 truncate">{{ ornament.name_am }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. PALETTE TAB (Task 4: The Key Feature) -->
                    <div v-else-if="activeSidebarTab === 'palette'" class="space-y-5">
                        <!-- Current Palette Roles -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Current Color Roles</h3>
                                <span class="text-[10px] text-zinc-500">Click to change role</span>
                            </div>
                            <div class="space-y-1.5 bg-zinc-900/80 p-2.5 rounded-lg border border-zinc-800">
                                <div
                                    v-for="role in PALETTE_ROLES"
                                    :key="role.key"
                                    class="flex items-center justify-between py-1 border-b border-zinc-800/60 last:border-0"
                                >
                                    <div class="flex items-center space-x-2">
                                        <label
                                            class="w-6 h-6 rounded-md border border-zinc-700 shadow-sm cursor-pointer relative overflow-hidden shrink-0"
                                            :style="{ backgroundColor: resolveColor(`@${role.key}`, effectivePalette) }"
                                        >
                                            <input
                                                type="color"
                                                :value="resolveColor(`@${role.key}`, effectivePalette)"
                                                @input="updatePaletteRole(role.key, $event.target.value)"
                                                class="opacity-0 absolute inset-0 cursor-pointer"
                                            />
                                        </label>
                                        <span class="text-xs text-zinc-200 font-medium">{{ role.label }}</span>
                                    </div>
                                    <span class="text-[11px] font-mono text-zinc-400 uppercase">
                                        {{ resolveColor(`@${role.key}`, effectivePalette) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- 1-Click Alternate Palettes for this Category (Task 4) -->
                        <div class="space-y-2">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">
                                12 Curated Category Palettes
                            </h3>
                            <div class="space-y-2 max-h-[300px] overflow-y-auto custom-scrollbar pr-1">
                                <div
                                    v-for="pal in curatedPalettes"
                                    :key="pal.id"
                                    @click="selectAlternatePalette(pal)"
                                    class="p-2.5 rounded-lg border border-zinc-800 hover:border-[#D4AF37] bg-zinc-900/70 hover:bg-zinc-800/90 cursor-pointer transition flex items-center justify-between group"
                                >
                                    <div>
                                        <div class="text-xs font-semibold text-zinc-200 group-hover:text-[#D4AF37] transition">
                                            {{ pal.name }}
                                        </div>
                                        <div class="text-[10px] text-zinc-500 capitalize">{{ pal.mood || pal.category }}</div>
                                    </div>
                                    <div class="flex items-center space-x-1 shrink-0">
                                        <span
                                            v-for="c in [pal.bg, pal.primary, pal.secondary, pal.accent, pal.ink]"
                                            :key="c"
                                            class="w-4 h-4 rounded-full border border-zinc-700 shadow-sm"
                                            :style="{ backgroundColor: c }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pick One Color & Generate Palette (Task 4) -->
                        <div class="space-y-2 bg-zinc-900/60 p-3 rounded-lg border border-zinc-800">
                            <h3 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">
                                Generate Harmonious Palette
                            </h3>
                            <p class="text-[11px] text-zinc-400">
                                Pick a single theme color (e.g. dress or brand) to instantly generate a complete 7-role palette.
                            </p>
                            <div class="flex items-center space-x-2 pt-1">
                                <label
                                    class="w-8 h-8 rounded-lg border border-zinc-700 shadow-sm cursor-pointer relative overflow-hidden shrink-0"
                                    :style="{ backgroundColor: singleColorPicker }"
                                >
                                    <input
                                        type="color"
                                        v-model="singleColorPicker"
                                        class="opacity-0 absolute inset-0 cursor-pointer"
                                    />
                                </label>
                                <input
                                    type="text"
                                    v-model="singleColorPicker"
                                    class="flex-1 px-2.5 py-1.5 bg-zinc-950 border border-zinc-800 rounded-lg text-xs font-mono text-zinc-200"
                                />
                                <button
                                    type="button"
                                    @click="generateFromSingleColor"
                                    class="px-3 py-1.5 bg-[#D4AF37] hover:brightness-110 text-zinc-950 font-semibold rounded-lg text-xs transition"
                                >
                                    Generate
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 4. LAYERS TAB (Task 2) -->
                    <div v-else-if="activeSidebarTab === 'layers'" class="space-y-3">
                        <!-- Group / Ungroup Actions -->
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-zinc-400 font-medium">{{ design.layers.length }} Layers</span>
                            <div class="flex items-center space-x-1.5">
                                <button
                                    v-if="selectedLayerIds.length >= 2"
                                    type="button"
                                    @click="groupSelectedLayers"
                                    class="px-2 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700 text-[11px] text-[#D4AF37] border border-zinc-700 font-medium transition"
                                >
                                    Group ({{ selectedLayerIds.length }})
                                </button>
                                <button
                                    v-if="primarySelectedLayer && primarySelectedLayer.type === 'group'"
                                    type="button"
                                    @click="ungroupSelectedLayer"
                                    class="px-2 py-0.5 rounded bg-zinc-800 hover:bg-zinc-700 text-[11px] text-[#D4AF37] border border-zinc-700 font-medium transition"
                                >
                                    Ungroup
                                </button>
                            </div>
                        </div>

                        <!-- Layers List (Reverse so top layers are first) -->
                        <div class="space-y-1.5 max-h-[calc(100vh-220px)] overflow-y-auto custom-scrollbar pr-1">
                            <div
                                v-for="layer in [...design.layers].reverse()"
                                :key="layer.id"
                                @click="selectLayer(layer.id, $event.shiftKey)"
                                :class="selectedLayerIds.includes(layer.id) ? 'bg-zinc-800 border-[#D4AF37]' : 'bg-zinc-900/80 border-zinc-800 hover:bg-zinc-800/60'"
                                class="p-2 rounded-lg border flex items-center justify-between cursor-pointer transition text-xs"
                            >
                                <div class="flex items-center space-x-2 overflow-hidden">
                                    <!-- Type Icons (Task 2) -->
                                    <span class="text-zinc-400 shrink-0">
                                        <svg v-if="layer.type === 'text'" class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                                        </svg>
                                        <svg v-else-if="layer.type === 'ornament'" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                        </svg>
                                        <svg v-else-if="layer.type === 'border'" class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <rect x="3" y="3" width="18" height="18" rx="2" stroke-width="2" />
                                        </svg>
                                        <svg v-else-if="layer.type === 'pattern'" class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                        </svg>
                                        <svg v-else-if="layer.type === 'image'" class="w-4 h-4 text-pink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else-if="layer.type === 'group'" class="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z" />
                                        </svg>
                                    </span>
                                    <span class="truncate font-medium text-zinc-200">
                                        {{ layer.name || (layer.type === 'text' ? (layer.i18n?.en || layer.text) : layer.type) }}
                                    </span>
                                </div>

                                <div class="flex items-center space-x-1 shrink-0">
                                    <!-- Move Up / Down (Task 2) -->
                                    <button
                                        type="button"
                                        @click.stop="moveLayerUp(layer.id)"
                                        class="p-1 text-zinc-500 hover:text-zinc-200 rounded"
                                        title="Move Up"
                                    >
                                        ▲
                                    </button>
                                    <button
                                        type="button"
                                        @click.stop="moveLayerDown(layer.id)"
                                        class="p-1 text-zinc-500 hover:text-zinc-200 rounded"
                                        title="Move Down"
                                    >
                                        ▼
                                    </button>

                                    <!-- Hide / Show Toggle (Task 2) -->
                                    <button
                                        type="button"
                                        @click.stop="layer.hidden = !layer.hidden; recordHistory(); updateTransformer();"
                                        class="p-1 text-zinc-400 hover:text-zinc-200 rounded"
                                        :title="layer.hidden ? 'Show Layer' : 'Hide Layer'"
                                    >
                                        <svg v-if="layer.hidden" class="w-3.5 h-3.5 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>

                                    <!-- Lock Toggle (Task 2) -->
                                    <button
                                        type="button"
                                        @click.stop="layer.locked = !layer.locked; recordHistory(); updateTransformer();"
                                        class="p-1 text-zinc-400 hover:text-zinc-200 rounded"
                                        :title="layer.locked ? 'Unlock Layer' : 'Lock Layer'"
                                    >
                                        <svg v-if="layer.locked" class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                        <svg v-else class="w-3.5 h-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                        </svg>
                                    </button>

                                    <!-- Delete -->
                                    <button
                                        type="button"
                                        @click.stop="deleteLayer(layer.id)"
                                        class="p-1 text-zinc-500 hover:text-rose-400 rounded"
                                        title="Delete Layer"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. TEMPLATES TAB -->
                    <div v-else-if="activeSidebarTab === 'templates'" class="space-y-4">
                        <div v-for="cat in categories" :key="cat.id" class="space-y-2">
                            <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">{{ cat.name }}</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="tmpl in cat.templates"
                                    :key="tmpl.id"
                                    @click="design = normalizeDesign(tmpl.design_json); currentTemplateId = tmpl.id; recordHistory(); updateBackgroundCache();"
                                    class="group cursor-pointer rounded-lg overflow-hidden border border-zinc-800 hover:border-[#D4AF37] transition bg-zinc-900"
                                >
                                    <div class="aspect-[3/4] bg-zinc-950 relative overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="tmpl.preview_url"
                                            :src="tmpl.preview_url"
                                            :alt="tmpl.name"
                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        />
                                        <span v-else class="text-[10px] text-zinc-500 px-2 text-center">{{ tmpl.name }}</span>
                                    </div>
                                    <div class="p-1.5 text-[11px] font-medium text-zinc-300 truncate text-center">
                                        {{ tmpl.name }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- CENTER WORKSPACE (Canvas) -->
            <main
                ref="canvasContainerRef"
                class="flex-1 bg-zinc-950 relative overflow-hidden flex items-center justify-center p-6"
                @click="deselectAll"
            >
                <div
                    class="relative shadow-2xl transition-transform duration-75"
                    :style="{
                        width: `${canvasWidth * zoom}px`,
                        height: `${canvasHeight * zoom}px`,
                    }"
                    @click.stop
                >
                    <div
                        class="origin-top-left"
                        :style="{
                            width: `${canvasWidth}px`,
                            height: `${canvasHeight}px`,
                            transform: `scale(${zoom})`,
                        }"
                    >
                        <v-stage
                            ref="stageRef"
                            :config="stageConfig"
                            @click="handleStageClick"
                            @tap="handleStageClick"
                        >
                            <!-- 1. Background Layer (Cached Offscreen Canvas for 60fps) -->
                            <v-layer>
                                <v-shape
                                    :config="{
                                        name: 'background-shape',
                                        x: 0,
                                        y: 0,
                                        width: canvasWidth,
                                        height: canvasHeight,
                                        listening: false,
                                        sceneFunc: (context, shape) => {
                                            if (bgOffscreenCanvas) {
                                                context._context.drawImage(bgOffscreenCanvas, 0, 0);
                                            } else {
                                                renderBackgroundToContext(context._context, canvasWidth, canvasHeight, design.background, effectivePalette);
                                            }
                                        }
                                    }"
                                />
                            </v-layer>

                            <!-- 2. Content Elements Layer (Canonical Rendering via sceneFunc) -->
                            <v-layer>
                                <template v-for="layer in design.layers" :key="layer.id">
                                    <v-shape
                                        :config="getKonvaLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                </template>
                            </v-layer>

                            <!-- 3. Snapping Guides Layer -->
                            <v-layer>
                                <v-line
                                    v-for="(guide, i) in guideLines"
                                    :key="i"
                                    :config="{
                                        points: guide.points,
                                        stroke: guide.stroke || '#D4AF37',
                                        strokeWidth: 2,
                                        dash: [6, 6],
                                        listening: false
                                    }"
                                />
                            </v-layer>

                            <!-- 4. Transformer Layer -->
                            <v-layer>
                                <v-transformer
                                    ref="transformerRef"
                                    :config="{
                                        enabledAnchors: [
                                            'top-left', 'top-center', 'top-right',
                                            'middle-right', 'middle-left',
                                            'bottom-left', 'bottom-center', 'bottom-right'
                                        ],
                                        rotateAnchorOffset: 30,
                                        borderStroke: '#D4AF37',
                                        borderStrokeWidth: 1.5,
                                        anchorStroke: '#D4AF37',
                                        anchorFill: '#FFFFFF',
                                        anchorSize: 10,
                                        anchorCornerRadius: 2,
                                    }"
                                />
                            </v-layer>

                            <!-- 5. Free-tier Watermark (Visible if !hasRemoveBranding) -->
                            <v-layer v-if="!hasRemoveBranding">
                                <v-rect
                                    :config="{
                                        x: 0,
                                        y: canvasHeight - 56,
                                        width: canvasWidth,
                                        height: 56,
                                        fill: 'rgba(15, 15, 18, 0.85)',
                                        listening: false
                                    }"
                                />
                                <v-text
                                    :config="{
                                        x: 0,
                                        y: canvasHeight - 44,
                                        width: canvasWidth,
                                        text: 'Created with Teri • teri.et',
                                        fontFamily: 'Montserrat, sans-serif',
                                        fontSize: 16,
                                        fontStyle: 'bold',
                                        fill: '#D4AF37',
                                        align: 'center',
                                        listening: false
                                    }"
                                />
                            </v-layer>
                        </v-stage>
                    </div>
                </div>

                <!-- Floating Bottom Controls (Zoom & Undo/Redo) -->
                <div class="absolute bottom-6 left-1/2 -translate-x-1/2 bg-zinc-900/90 backdrop-blur border border-zinc-800 rounded-full px-4 py-2 flex items-center space-x-3 shadow-xl z-20">
                    <!-- Undo -->
                    <button
                        type="button"
                        @click="undo"
                        :disabled="historyIndex <= 0"
                        class="p-1 text-zinc-400 hover:text-zinc-100 disabled:opacity-30 disabled:hover:text-zinc-400"
                        title="Undo (Ctrl+Z)"
                    >
                        ⟲
                    </button>
                    <!-- Redo -->
                    <button
                        type="button"
                        @click="redo"
                        :disabled="historyIndex >= history.length - 1"
                        class="p-1 text-zinc-400 hover:text-zinc-100 disabled:opacity-30 disabled:hover:text-zinc-400"
                        title="Redo (Ctrl+Y)"
                    >
                        ⟳
                    </button>

                    <div class="w-px h-4 bg-zinc-700" />

                    <!-- Zoom Controls -->
                    <button
                        type="button"
                        @click="zoom = Math.max(0.15, zoom - 0.1)"
                        class="text-zinc-400 hover:text-zinc-100 p-1"
                        title="Zoom Out"
                    >
                        −
                    </button>
                    <span class="text-xs font-mono text-zinc-300 w-12 text-center">{{ Math.round(zoom * 100) }}%</span>
                    <button
                        type="button"
                        @click="zoom = Math.min(1.5, zoom + 0.1)"
                        class="text-zinc-400 hover:text-zinc-100 p-1"
                        title="Zoom In"
                    >
                        +
                    </button>
                    <button
                        type="button"
                        @click="calculateFitZoom"
                        class="text-[11px] text-[#D4AF37] hover:underline px-1.5"
                    >
                        Fit
                    </button>
                </div>
            </main>

            <!-- RIGHT PROPERTIES PANEL (Desktop) -->
            <aside class="w-80 bg-zinc-950 border-l border-zinc-800 flex flex-col shrink-0 z-20 hidden md:flex">
                <div class="h-12 border-b border-zinc-800 px-4 flex items-center justify-between">
                    <span class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">
                        {{ primarySelectedLayer ? (primarySelectedLayer.name || primarySelectedLayer.type) : 'Card Background' }}
                    </span>
                    <button
                        v-if="primarySelectedLayer"
                        type="button"
                        @click="deleteLayer(primarySelectedLayer.id)"
                        class="text-rose-400 hover:text-rose-300 text-xs"
                    >
                        Delete
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4 custom-scrollbar">
                    <!-- 1. TEXT LAYER CONTROLS (Task 5) -->
                    <div v-if="primarySelectedLayer && primarySelectedLayer.type === 'text'" class="space-y-4">
                        <!-- Bilingual / i18n Copy Editor (Task 5) -->
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-zinc-400 flex items-center justify-between">
                                <span>Copy Text</span>
                                <!-- Missing Amharic Warning Badge (Task 5) -->
                                <span
                                    v-if="primarySelectedLayer.i18n && (!primarySelectedLayer.i18n.am || !primarySelectedLayer.i18n.am.trim())"
                                    class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 border border-amber-500/40 font-semibold"
                                >
                                    ⚠️ Missing Amharic
                                </span>
                            </label>

                            <!-- If i18n exists -->
                            <div v-if="primarySelectedLayer.i18n" class="space-y-2">
                                <div>
                                    <div class="text-[10px] text-zinc-500 mb-1">English (EN):</div>
                                    <textarea
                                        v-model="primarySelectedLayer.i18n.en"
                                        @input="recordHistory"
                                        rows="2"
                                        class="w-full px-2.5 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg text-xs text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                    />
                                </div>
                                <div>
                                    <div class="text-[10px] text-zinc-500 mb-1">Amharic (አማ):</div>
                                    <textarea
                                        v-model="primarySelectedLayer.i18n.am"
                                        @input="recordHistory"
                                        rows="2"
                                        placeholder="የአማርኛ ጽሑፍ እዚህ ያስገቡ..."
                                        class="w-full px-2.5 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg text-xs text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                    />
                                </div>
                            </div>
                            <!-- Plain Text Fallback -->
                            <div v-else class="space-y-1.5">
                                <textarea
                                    v-model="primarySelectedLayer.text"
                                    @input="recordHistory"
                                    rows="3"
                                    class="w-full px-2.5 py-1.5 bg-zinc-900 border border-zinc-800 rounded-lg text-xs text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                />
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.i18n = { en: primarySelectedLayer.text || '', am: '' }; recordHistory();"
                                    class="text-[11px] text-[#D4AF37] hover:underline"
                                >
                                    + Add Bilingual (EN / አማ) Support
                                </button>
                            </div>
                        </div>

                        <!-- Font Family -->
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Font Family</label>
                            <select
                                v-model="primarySelectedLayer.fontFamily"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option v-for="font in AVAILABLE_FONTS" :key="font.name" :value="font.name">
                                    {{ font.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Font Size & Weight -->
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-medium text-zinc-400">Size ({{ primarySelectedLayer.fontSize }}px)</label>
                                <input
                                    type="range"
                                    min="14"
                                    max="120"
                                    v-model.number="primarySelectedLayer.fontSize"
                                    @input="recordHistory"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-zinc-400">Weight</label>
                                <select
                                    v-model="primarySelectedLayer.fontWeight"
                                    @change="recordHistory"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                >
                                    <option :value="400">Normal (400)</option>
                                    <option :value="600">Semibold (600)</option>
                                    <option :value="700">Bold (700)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Color Role Picker -->
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Text Color / Role</label>
                            <div class="flex items-center space-x-2">
                                <span
                                    class="w-6 h-6 rounded-md border border-zinc-700 shrink-0"
                                    :style="{ backgroundColor: resolveColor(primarySelectedLayer.fill || '@ink', effectivePalette) }"
                                />
                                <select
                                    v-model="primarySelectedLayer.fill"
                                    @change="recordHistory"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                >
                                    <option value="@ink">Ink (@ink)</option>
                                    <option value="@primary">Primary (@primary)</option>
                                    <option value="@secondary">Secondary (@secondary)</option>
                                    <option value="@accent">Accent (@accent)</option>
                                    <option value="@muted">Muted (@muted)</option>
                                    <option value="#FFFFFF">White</option>
                                    <option value="#18181B">Dark</option>
                                </select>
                            </div>
                        </div>

                        <!-- Curved Text (Task 5) -->
                        <div class="space-y-1 pt-2 border-t border-zinc-800">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-medium text-zinc-400">
                                    Curved Text Radius: {{ primarySelectedLayer.curve ? `${primarySelectedLayer.curve}px` : 'Straight' }}
                                </label>
                                <button
                                    v-if="primarySelectedLayer.curve"
                                    type="button"
                                    @click="primarySelectedLayer.curve = 0; recordHistory();"
                                    class="text-[10px] text-zinc-500 hover:text-zinc-300"
                                >
                                    Reset
                                </button>
                            </div>
                            <input
                                type="range"
                                min="-400"
                                max="400"
                                step="10"
                                v-model.number="primarySelectedLayer.curve"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>

                        <!-- Stroke Controls (Task 5) -->
                        <div class="space-y-1 pt-2 border-t border-zinc-800">
                            <label class="text-xs font-medium text-zinc-400">Text Stroke ({{ primarySelectedLayer.strokeWidth || 0 }}px)</label>
                            <div class="flex items-center space-x-2">
                                <input
                                    type="range"
                                    min="0"
                                    max="8"
                                    v-model.number="primarySelectedLayer.strokeWidth"
                                    @input="recordHistory"
                                    class="flex-1 accent-[#D4AF37]"
                                />
                                <select
                                    v-model="primarySelectedLayer.stroke"
                                    @change="recordHistory"
                                    class="w-28 bg-zinc-900 border border-zinc-800 rounded text-xs py-1 text-zinc-200"
                                >
                                    <option :value="null">None</option>
                                    <option value="@primary">Primary</option>
                                    <option value="@secondary">Gold</option>
                                    <option value="#FFFFFF">White</option>
                                    <option value="#000000">Black</option>
                                </select>
                            </div>
                        </div>

                        <!-- AutoFit Toggle (Task 5) -->
                        <div class="flex items-center justify-between pt-2 border-t border-zinc-800">
                            <span class="text-xs text-zinc-300">Auto-fit size on overflow</span>
                            <input
                                type="checkbox"
                                :checked="!!primarySelectedLayer.autoFit"
                                @change="primarySelectedLayer.autoFit = $event.target.checked ? { minSize: 14 } : null; recordHistory();"
                                class="rounded bg-zinc-900 border-zinc-700 text-[#D4AF37] focus:ring-0"
                            />
                        </div>
                    </div>

                    <!-- 2. ORNAMENT LAYER CONTROLS (Task 3) -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'ornament'" class="space-y-4">
                        <div class="p-3 bg-zinc-900 rounded-lg border border-zinc-800 text-center">
                            <div class="text-xs font-semibold text-zinc-200">{{ primarySelectedLayer.name }}</div>
                            <div class="text-[10px] font-mono text-zinc-500">{{ primarySelectedLayer.assetId }}</div>
                        </div>

                        <!-- Tint Slots c1, c2, c3 (Task 3) -->
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-zinc-400">Tint Slots</label>
                            <div class="space-y-2">
                                <div
                                    v-for="slot in ['c1', 'c2', 'c3']"
                                    :key="slot"
                                    class="flex items-center justify-between p-2 rounded-lg bg-zinc-900 border border-zinc-800"
                                >
                                    <div class="flex items-center space-x-2">
                                        <span class="text-[11px] font-mono font-bold text-zinc-400 uppercase">{{ slot }}</span>
                                        <span
                                            class="w-5 h-5 rounded border border-zinc-700 shrink-0"
                                            :style="{ backgroundColor: resolveColor(primarySelectedLayer.tint?.[slot] || '@primary', effectivePalette) }"
                                        />
                                    </div>
                                    <select
                                        :value="primarySelectedLayer.tint?.[slot] || (slot === 'c1' ? '@primary' : (slot === 'c2' ? '@secondary' : '@accent'))"
                                        @change="primarySelectedLayer.tint = { ...(primarySelectedLayer.tint || {}), [slot]: $event.target.value }; recordHistory();"
                                        class="bg-zinc-950 border border-zinc-800 rounded text-xs py-1 px-2 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                                    >
                                        <option value="@primary">Primary (@primary)</option>
                                        <option value="@secondary">Secondary (@secondary)</option>
                                        <option value="@accent">Accent (@accent)</option>
                                        <option value="@ink">Ink (@ink)</option>
                                        <option value="@surface">Surface (@surface)</option>
                                        <option value="@muted">Muted (@muted)</option>
                                        <option value="#FFFFFF">White</option>
                                        <option value="#18181B">Dark</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Flip Controls (Task 3) -->
                        <div class="space-y-1 pt-2 border-t border-zinc-800">
                            <label class="text-xs font-medium text-zinc-400">Orientation</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.flipX = !primarySelectedLayer.flipX; recordHistory();"
                                    :class="primarySelectedLayer.flipX ? 'bg-[#D4AF37] text-zinc-950 font-bold' : 'bg-zinc-900 text-zinc-300'"
                                    class="py-1.5 px-3 rounded-lg border border-zinc-800 text-xs transition text-center"
                                >
                                    ⇄ Flip Horizontal
                                </button>
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.flipY = !primarySelectedLayer.flipY; recordHistory();"
                                    :class="primarySelectedLayer.flipY ? 'bg-[#D4AF37] text-zinc-950 font-bold' : 'bg-zinc-900 text-zinc-300'"
                                    class="py-1.5 px-3 rounded-lg border border-zinc-800 text-xs transition text-center"
                                >
                                    ⇅ Flip Vertical
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 3. BORDER LAYER CONTROLS (Task 6) -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'border'" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Border Style</label>
                            <select
                                v-model="primarySelectedLayer.style"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option value="double-rule">Double Rule Frame</option>
                                <option value="single-rule">Single Rule Frame</option>
                                <option value="ornate-corners">Ornate Corner Accents</option>
                                <option value="dotted">Dotted Rule</option>
                                <option value="tilet-band">Traditional Tilet Band</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Inset ({{ primarySelectedLayer.inset || 40 }}px)</label>
                            <input
                                type="range"
                                min="0"
                                max="120"
                                v-model.number="primarySelectedLayer.inset"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Stroke Width ({{ primarySelectedLayer.strokeWidth || 2 }}px)</label>
                            <input
                                type="range"
                                min="1"
                                max="16"
                                v-model.number="primarySelectedLayer.strokeWidth"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>

                        <div v-if="primarySelectedLayer.style === 'double-rule'" class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Gap ({{ primarySelectedLayer.gap || 6 }}px)</label>
                            <input
                                type="range"
                                min="2"
                                max="24"
                                v-model.number="primarySelectedLayer.gap"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Border Color Role</label>
                            <select
                                v-model="primarySelectedLayer.stroke"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option value="@primary">Primary (@primary)</option>
                                <option value="@secondary">Secondary (@secondary)</option>
                                <option value="@accent">Accent (@accent)</option>
                                <option value="@muted">Muted (@muted)</option>
                                <option value="#FFFFFF">White</option>
                            </select>
                        </div>
                    </div>

                    <!-- 4. PATTERN LAYER CONTROLS (Task 6) -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'pattern'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xs font-medium text-zinc-400">Tile Width ({{ primarySelectedLayer.tileWidth || 60 }}px)</label>
                                <input
                                    type="range"
                                    min="20"
                                    max="160"
                                    v-model.number="primarySelectedLayer.tileWidth"
                                    @input="recordHistory"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                            <div>
                                <label class="text-xs font-medium text-zinc-400">Tile Height ({{ primarySelectedLayer.tileHeight || 60 }}px)</label>
                                <input
                                    type="range"
                                    min="20"
                                    max="160"
                                    v-model.number="primarySelectedLayer.tileHeight"
                                    @input="recordHistory"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Tile Gap X ({{ primarySelectedLayer.gapX || 0 }}px)</label>
                            <input
                                type="range"
                                min="0"
                                max="32"
                                v-model.number="primarySelectedLayer.gapX"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>
                    </div>

                    <!-- 5. IMAGE LAYER CONTROLS (Task 7: Image Masks) -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'image'" class="space-y-4">
                        <!-- Mask Selector (Task 7) -->
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Image Mask Shape</label>
                            <select
                                v-model="primarySelectedLayer.mask"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option value="circle">Circle Mask</option>
                                <option value="arch">Cathedral Arch Mask</option>
                                <option value="oval">Oval Mask</option>
                                <option value="rounded">Rounded Rectangle</option>
                                <option value="none">Unmasked (Rectangle)</option>
                            </select>
                        </div>

                        <!-- Drag-to-Reposition / Mask Offset (Task 7) -->
                        <div class="space-y-2 pt-2 border-t border-zinc-800">
                            <label class="text-xs font-medium text-zinc-400">Reposition Inside Mask</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <span class="text-[10px] text-zinc-500">Offset X: {{ primarySelectedLayer.maskOffsetX || 0 }}px</span>
                                    <input
                                        type="range"
                                        min="-150"
                                        max="150"
                                        v-model.number="primarySelectedLayer.maskOffsetX"
                                        @input="recordHistory"
                                        class="w-full accent-[#D4AF37]"
                                    />
                                </div>
                                <div>
                                    <span class="text-[10px] text-zinc-500">Offset Y: {{ primarySelectedLayer.maskOffsetY || 0 }}px</span>
                                    <input
                                        type="range"
                                        min="-150"
                                        max="150"
                                        v-model.number="primarySelectedLayer.maskOffsetY"
                                        @input="recordHistory"
                                        class="w-full accent-[#D4AF37]"
                                    />
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="promptImageUpload"
                            class="w-full py-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-lg text-xs text-zinc-200 transition"
                        >
                            Replace Image
                        </button>
                    </div>

                    <!-- 6. SHAPE LAYER CONTROLS -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'shape'" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Shape</label>
                            <select
                                v-model="primarySelectedLayer.shape"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option value="rect">Rectangle</option>
                                <option value="circle">Circle</option>
                                <option value="arch">Arch</option>
                                <option value="line">Line</option>
                                <option value="star">Star</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Fill Color</label>
                            <select
                                v-model="primarySelectedLayer.fill"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200"
                            >
                                <option value="transparent">Transparent</option>
                                <option value="@surface">Surface (@surface)</option>
                                <option value="@primary">Primary (@primary)</option>
                                <option value="@secondary">Secondary (@secondary)</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Stroke Color</label>
                            <select
                                v-model="primarySelectedLayer.stroke"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200"
                            >
                                <option value="@primary">Primary (@primary)</option>
                                <option value="@secondary">Secondary (@secondary)</option>
                                <option value="@accent">Accent (@accent)</option>
                            </select>
                        </div>
                    </div>

                    <!-- 7. BACKGROUND PANEL (Task 8: When No Layer Selected) -->
                    <div v-else class="space-y-4">
                        <!-- Background Type -->
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Background Type</label>
                            <div class="grid grid-cols-2 gap-1 bg-zinc-900 p-1 rounded-lg">
                                <button
                                    type="button"
                                    @click="design.background = { type: 'color', value: '@bg', texture: design.background?.texture || { kind: 'none' }, vignette: design.background?.vignette || 0 }; recordHistory();"
                                    :class="design.background?.type === 'color' ? 'bg-zinc-800 text-[#D4AF37] font-semibold' : 'text-zinc-400'"
                                    class="py-1 rounded text-xs transition"
                                >
                                    Solid Color
                                </button>
                                <button
                                    type="button"
                                    @click="design.background = { type: 'gradient', value: { type: 'linear', angle: 180, stops: [[0, '@bg'], [1, '@surface']] }, texture: design.background?.texture || { kind: 'none' }, vignette: design.background?.vignette || 0 }; recordHistory();"
                                    :class="design.background?.type === 'gradient' ? 'bg-zinc-800 text-[#D4AF37] font-semibold' : 'text-zinc-400'"
                                    class="py-1 rounded text-xs transition"
                                >
                                    Gradient
                                </button>
                            </div>
                        </div>

                        <!-- Solid Background Color Role -->
                        <div v-if="design.background?.type === 'color'" class="space-y-1">
                            <label class="text-xs font-medium text-zinc-400">Background Color / Role</label>
                            <select
                                v-model="design.background.value"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200"
                            >
                                <option value="@bg">Default Background (@bg)</option>
                                <option value="@surface">Surface (@surface)</option>
                                <option value="#FAF7F2">Cream Paper</option>
                                <option value="#18181B">Dark Onyx</option>
                            </select>
                        </div>

                        <!-- Procedural Textures (Task 8) -->
                        <div class="space-y-2 pt-2 border-t border-zinc-800">
                            <label class="text-xs font-medium text-zinc-400">Procedural Texture</label>
                            <select
                                :value="design.background?.texture?.kind || 'none'"
                                @change="design.background.texture = { ...(design.background.texture || { opacity: 0.08, seed: 1 }), kind: $event.target.value }; recordHistory();"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg text-xs py-1.5 px-2.5 text-zinc-200 focus:border-[#D4AF37] focus:ring-0"
                            >
                                <option value="none">None (Clean)</option>
                                <option value="paper">Fine Paper Texture</option>
                                <option value="linen">Natural Woven Linen</option>
                                <option value="grain">Subtle Film Grain</option>
                                <option value="watercolor">Soft Watercolor Wash</option>
                            </select>

                            <div v-if="design.background?.texture?.kind && design.background.texture.kind !== 'none'" class="space-y-1">
                                <span class="text-[10px] text-zinc-500">
                                    Texture Strength ({{ Math.round((design.background.texture.opacity || 0.08) * 100) }}%)
                                </span>
                                <input
                                    type="range"
                                    min="0.02"
                                    max="0.25"
                                    step="0.01"
                                    v-model.number="design.background.texture.opacity"
                                    @input="recordHistory"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                        </div>

                        <!-- Vignette Darkness (Task 8) -->
                        <div class="space-y-1 pt-2 border-t border-zinc-800">
                            <label class="text-xs font-medium text-zinc-400">
                                Edge Vignette: {{ Math.round((design.background?.vignette || 0) * 100) }}%
                            </label>
                            <input
                                type="range"
                                min="0"
                                max="1"
                                step="0.05"
                                v-model.number="design.background.vignette"
                                @input="recordHistory"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Mobile Bottom Toolbar (Task 9) -->
        <div class="md:hidden h-14 bg-zinc-900 border-t border-zinc-800 flex items-center justify-around px-2 z-30 shrink-0 text-[10px]">
            <button
                type="button"
                @click="mobileTab = mobileTab === 'insert' ? null : 'insert'"
                :class="mobileTab === 'insert' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center p-1"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Insert</span>
            </button>
            <button
                type="button"
                @click="mobileTab = mobileTab === 'ornaments' ? null : 'ornaments'"
                :class="mobileTab === 'ornaments' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center p-1"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
                <span>Ornaments</span>
            </button>
            <button
                type="button"
                @click="mobileTab = mobileTab === 'palette' ? null : 'palette'"
                :class="mobileTab === 'palette' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center p-1"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4 5 5 0 015-5c.66 0 1.3.13 1.88.37A6 6 0 0118 7a6 6 0 016 6 5 5 0 01-5 5H7z" />
                </svg>
                <span>Palette</span>
            </button>
            <button
                type="button"
                @click="mobileTab = mobileTab === 'layers' ? null : 'layers'"
                :class="mobileTab === 'layers' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center p-1"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span>Layers</span>
            </button>
            <button
                type="button"
                @click="mobileTab = mobileTab === 'properties' ? null : 'properties'"
                :class="mobileTab === 'properties' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center p-1"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <span>Properties</span>
            </button>
        </div>

        <!-- Mobile Bottom Sheet Drawer (Task 9) -->
        <div
            v-if="mobileTab"
            class="md:hidden fixed inset-0 z-40 flex flex-col justify-end bg-black/60 backdrop-blur-sm"
            @click.self="mobileTab = null"
        >
            <div class="bg-zinc-900 border-t border-zinc-800 rounded-t-2xl max-h-[75vh] flex flex-col overflow-hidden shadow-2xl">
                <div class="p-3 border-b border-zinc-800 flex items-center justify-between shrink-0">
                    <h3 class="text-xs font-bold text-zinc-200 uppercase tracking-wider">
                        {{ mobileTab.toUpperCase() }}
                    </h3>
                    <button type="button" @click="mobileTab = null" class="p-1 text-zinc-400 hover:text-zinc-100">
                        ✕
                    </button>
                </div>
                <div class="p-4 overflow-y-auto custom-scrollbar space-y-4">
                    <div v-if="mobileTab === 'palette'" class="space-y-4">
                        <div class="space-y-2">
                            <h4 class="text-xs font-semibold text-zinc-300">Alternate Palettes</h4>
                            <div class="grid grid-cols-1 gap-2">
                                <div
                                    v-for="pal in curatedPalettes"
                                    :key="pal.id"
                                    @click="selectAlternatePalette(pal); mobileTab = null;"
                                    class="p-2.5 rounded-lg border border-zinc-800 bg-zinc-950 flex items-center justify-between"
                                >
                                    <span class="text-xs text-zinc-200">{{ pal.name }}</span>
                                    <div class="flex space-x-1">
                                        <span v-for="c in [pal.bg, pal.primary, pal.secondary, pal.accent, pal.ink]" :key="c" class="w-3.5 h-3.5 rounded-full" :style="{ backgroundColor: c }" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="mobileTab === 'ornaments'" class="space-y-3">
                        <input
                            v-model="ornamentSearch"
                            type="text"
                            placeholder="Search ornaments..."
                            class="w-full px-3 py-1.5 bg-zinc-950 border border-zinc-800 rounded-lg text-xs text-zinc-200"
                        />
                        <div class="grid grid-cols-2 gap-2">
                            <div
                                v-for="ornament in filteredOrnaments.slice(0, 30)"
                                :key="ornament.id"
                                @click="addOrnamentLayer(ornament); mobileTab = null;"
                                class="p-2 rounded-lg border border-zinc-800 bg-zinc-950 flex flex-col items-center justify-center text-center cursor-pointer"
                            >
                                <div class="w-12 h-12 flex items-center justify-center" v-html="loadTintedSvg(ornament.id, { c1: effectivePalette.primary, c2: effectivePalette.secondary }, effectivePalette)" />
                                <span class="text-[10px] text-zinc-300 truncate w-full mt-1">{{ ornament.name_en }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else-if="mobileTab === 'layers'" class="space-y-2">
                        <div
                            v-for="layer in [...design.layers].reverse()"
                            :key="layer.id"
                            @click="selectLayer(layer.id); mobileTab = 'properties';"
                            class="p-2 rounded border border-zinc-800 bg-zinc-950 flex items-center justify-between text-xs"
                        >
                            <span class="text-zinc-200 truncate">{{ layer.name || layer.type }}</span>
                            <button type="button" @click.stop="deleteLayer(layer.id)" class="text-rose-400">✕</button>
                        </div>
                    </div>
                    <div v-else-if="mobileTab === 'insert'" class="space-y-3">
                        <button type="button" @click="addTextLayer('heading'); mobileTab = null;" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs text-left text-zinc-200">
                            + Add Heading Text
                        </button>
                        <button type="button" @click="addTextLayer('body'); mobileTab = null;" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs text-left text-zinc-200">
                            + Add Body Text
                        </button>
                        <button type="button" @click="addBorderLayer('double-rule'); mobileTab = null;" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs text-left text-zinc-200">
                            + Add Border
                        </button>
                        <button type="button" @click="promptImageUpload(); mobileTab = null;" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs text-left text-zinc-200">
                            + Upload Photo
                        </button>
                    </div>
                    <div v-else-if="mobileTab === 'properties'" class="space-y-4">
                        <div v-if="!primarySelectedLayer" class="text-xs text-zinc-400 text-center py-4">
                            No layer selected. Tap a layer on canvas or choose from Layers tab.
                        </div>
                        <div v-else class="space-y-3 text-xs">
                            <div>Selected: <span class="font-bold text-[#D4AF37]">{{ primarySelectedLayer.name || primarySelectedLayer.type }}</span></div>
                            <div v-if="primarySelectedLayer.type === 'text'">
                                <textarea v-if="primarySelectedLayer.i18n" v-model="primarySelectedLayer.i18n.en" @input="recordHistory" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs" rows="2" />
                                <textarea v-else v-model="primarySelectedLayer.text" @input="recordHistory" class="w-full p-2 bg-zinc-950 border border-zinc-800 rounded text-xs" rows="2" />
                            </div>
                            <button type="button" @click="deleteLayer(primarySelectedLayer.id); mobileTab = null;" class="w-full p-2 bg-rose-950 border border-rose-800 text-rose-300 rounded text-xs font-semibold">
                                Delete Layer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reset to Template Confirmation Modal (Task 9) -->
        <Modal :show="showResetModal" @close="showResetModal = false" max-width="md">
            <div class="p-6 bg-zinc-900 text-zinc-100 rounded-xl space-y-4">
                <h3 class="text-base font-bold text-zinc-100">Reset Design to Template?</h3>
                <p class="text-xs text-zinc-400 leading-relaxed">
                    This will replace your current edits with the original layout, copy and styling from the template.
                    You can always use <span class="text-zinc-200 font-semibold">Undo</span> to revert if needed.
                </p>
                <div class="flex items-center justify-end space-x-2 pt-2">
                    <button
                        type="button"
                        @click="showResetModal = false"
                        class="px-4 py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-xs font-medium text-zinc-300 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleResetToTemplate"
                        class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-xs font-semibold text-white transition"
                    >
                        Reset Design
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
    height: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #27272a;
    border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3f3f46;
}
</style>
