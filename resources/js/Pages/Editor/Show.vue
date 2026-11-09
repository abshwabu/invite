<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import axios from 'axios';

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
    categories: {
        type: Array,
        default: () => [],
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

// Deep clone design to make it locally reactive and editable
const design = ref(JSON.parse(JSON.stringify(props.design || {
    version: 1,
    width: 1080,
    height: 1528,
    background: { type: 'color', value: '#18181B' },
    layers: [],
})));

// Ensure design.layers is an array
if (!Array.isArray(design.value.layers)) {
    design.value.layers = [];
}

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
const activeSidebarTab = ref('insert'); // 'insert' | 'layers' | 'templates'
const mobileTab = ref(null); // 'tools' | 'properties' | null (mobile drawer)
const showGuestModal = ref(false);
const guestModalMessage = ref('');
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

const AVAILABLE_FONTS = [
    { name: 'Noto Sans Ethiopic', label: 'Noto Sans Ethiopic (ኢትዮጵያ / Sans)', sample: 'እንኳን ደስ አላችሁ • Celebrate' },
    { name: 'Noto Serif Ethiopic', label: 'Noto Serif Ethiopic (ኢትዮጵያ / Serif)', sample: 'የክብር ግብዣ • Formal' },
    { name: 'Cinzel', label: 'Cinzel (Classical Roman)', sample: 'CLASSICAL & NOBLE' },
    { name: 'Playfair Display', label: 'Playfair Display (Editorial Serif)', sample: 'Love, Joy & Elegance' },
    { name: 'Great Vibes', label: 'Great Vibes (Formal Calligraphy)', sample: 'Cordially Invites You' },
    { name: 'Montserrat', label: 'Montserrat (Modern Geometric)', sample: 'MODERN & MINIMAL' },
    { name: 'Oswald', label: 'Oswald (Bold Condensed)', sample: 'CELEBRATE TOGETHER' },
    { name: 'Caveat', label: 'Caveat (Warm Handwritten)', sample: 'Special Day With Us' },
];

const COLOR_PALETTES = [
    { name: 'Ethiopian Gold', value: '#D4AF37' },
    { name: 'Warm Amber', value: '#F59E0B' },
    { name: 'Pure White', value: '#FFFFFF' },
    { name: 'Off White', value: '#F4F4F5' },
    { name: 'Rich Black', value: '#18181B' },
    { name: 'Deep Onyx', value: '#09090B' },
    { name: 'Emerald Green', value: '#059669' },
    { name: 'Forest Green', value: '#064E3B' },
    { name: 'Royal Navy', value: '#1E3A8A' },
    { name: 'Burgundy Wine', value: '#831843' },
    { name: 'Rose Gold', value: '#FB7185' },
    { name: 'Champagne Cream', value: '#F7E7CE' },
    { name: 'Charcoal', value: '#3F3F46' },
];

const GRADIENT_PRESETS = [
    { label: 'Dark Gold Horizon', start: '#2A2008', end: '#0A0802' },
    { label: 'Obsidian Velvet', start: '#18181B', end: '#09090B' },
    { label: 'Royal Emerald', start: '#064E3B', end: '#022C22' },
    { label: 'Deep Burgundy', start: '#4C0519', end: '#1F020A' },
    { label: 'Midnight Blue', start: '#1E293B', end: '#0F172A' },
];

// Target save endpoint
const saveUrl = computed(() => {
    if (!props.event) return null;
    return props.target === 'thank_you'
        ? route('events.thank-you-design.update', { event: props.event.id })
        : route('events.design.update', { event: props.event.id });
});

// Currently selected primary layer
const primarySelectedLayer = computed(() => {
    if (selectedLayerIds.value.length === 0) return null;
    return design.value.layers.find(l => l.id === selectedLayerIds.value[0]) || null;
});

// Canvas Size
const canvasWidth = computed(() => design.value.width || 1080);
const canvasHeight = computed(() => design.value.height || 1528);

const stageConfig = computed(() => ({
    width: canvasWidth.value,
    height: canvasHeight.value,
}));

// Background configuration
const backgroundConfig = computed(() => {
    const bg = design.value.background || { type: 'color', value: '#18181B' };
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
        const stops = bg.gradientStops || [0, bg.value || '#2A2008', 1, '#09090B'];
        return {
            x: 0,
            y: 0,
            width: w,
            height: h,
            fillLinearGradientStartPoint: { x: 0, y: 0 },
            fillLinearGradientEndPoint: { x: 0, y: h },
            fillLinearGradientColorStops: stops,
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

// Placeholder Text Replacement
const resolveText = (rawText) => {
    if (!rawText) return '';
    let result = String(rawText);
    const replacements = {
        '{{guest_name}}': props.eventData?.guest_name || 'Almaz Bekele',
        '{{event_title}}': props.event?.title || props.eventData?.event_title || 'Grand Celebration',
        '{{date}}': props.eventData?.date || 'Saturday, October 24, 2027',
        '{{time}}': props.eventData?.time || '4:00 PM',
        '{{venue}}': props.event?.venue_name || props.eventData?.venue || 'Sheraton Addis, Addis Ababa',
        '{{host_names}}': props.event?.host_names || props.eventData?.host_names || 'Abebe & Chaltu',
    };

    for (const [key, val] of Object.entries(replacements)) {
        const token = key.startsWith('{{') ? key : `{{${key}}}`;
        result = result.replaceAll(token, String(val ?? ''));
    }
    return result;
};

// Check for text box overflows
const checkTextOverflows = () => {
    overflowWarnings.value = [];
    if (!stageRef.value) return;
    const stage = stageRef.value.getNode();
    if (!stage) return;

    for (const layer of design.value.layers) {
        if (layer.type === 'text') {
            const node = stage.findOne(`#${layer.id}`);
            if (node && layer.height > 0) {
                const textHeight = node.textHeight || (node.textArr?.length * (layer.fontSize * (layer.lineHeight || 1.3)));
                if (textHeight > layer.height + 4) {
                    overflowWarnings.value.push({
                        id: layer.id,
                        text: resolveText(layer.text).substring(0, 32) + '...',
                        overflow: Math.round(textHeight - layer.height),
                    });
                }
            }
        }
    }
};

// History Recording
const recordHistory = () => {
    if (isHistoryNavigating.value) return;

    // Prune forward states if we navigated back
    const cloned = JSON.parse(JSON.stringify(design.value));
    const newHistory = history.value.slice(0, historyIndex.value + 1);
    newHistory.push(cloned);

    // Limit to 50 steps
    if (newHistory.length > 50) {
        newHistory.shift();
    }

    history.value = newHistory;
    historyIndex.value = newHistory.length - 1;

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

// Auto-fit Zoom calculation
const calculateFitZoom = () => {
    if (!canvasContainerRef.value) return;
    const containerW = canvasContainerRef.value.clientWidth - 48; // padding
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

    // Debounce save 5s
    saveTimeout.value = setTimeout(() => {
        performSave();
    }, 5000);
};

const performSave = async () => {
    if (props.isGuest) {
        try {
            saveStatus.value = 'saving';
            localStorage.setItem('invite_guest_draft', JSON.stringify({
                template_id: currentTemplateId.value,
                design_json: design.value,
                updated_at: new Date().toISOString(),
            }));
            saveStatus.value = 'saved';
            isDirty.value = false;
        } catch (e) {
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
        });
        saveStatus.value = 'saved';
        isDirty.value = false;
    } catch (e) {
        saveStatus.value = 'error';
    }
};

// Layer Selection & Transformer Management
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
            return layer && !layer.locked;
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
    // If clicked on stage or background rect, deselect
    const clickedOnEmpty = e.target === e.target.getStage() || e.target.name() === 'background-rect' || e.target.name() === 'background-image';
    if (clickedOnEmpty) {
        deselectAll();
    }
};

// Node drag and transform handlers
const handleDragStart = (e) => {
    const id = e.target.id();
    if (!selectedLayerIds.value.includes(id)) {
        selectedLayerIds.value = [id];
        updateTransformer();
    }
};

const handleDragMove = (e, layer) => {
    if (layer.locked) return;

    const node = e.target;
    const threshold = 12;
    const stageW = canvasWidth.value;
    const stageH = canvasHeight.value;

    const layerW = (layer.width || 100) * (node.scaleX() || 1);
    const layerH = (layer.height || 100) * (node.scaleY() || 1);
    const centerX = node.x() + layerW / 2;
    const centerY = node.y() + layerH / 2;

    const guides = [];

    // Horizontal center snap (X center)
    if (Math.abs(centerX - stageW / 2) < threshold) {
        node.x(stageW / 2 - layerW / 2);
        guides.push({ points: [stageW / 2, 0, stageW / 2, stageH], stroke: '#D4AF37' });
    }

    // Vertical center snap (Y center)
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

// Layer Operations
const addTextLayer = (preset = 'heading') => {
    const id = crypto.randomUUID();
    let text = 'የክብር ግብዣ';
    let fontSize = 56;
    let fontWeight = 700;
    let fontFamily = 'Noto Sans Ethiopic';

    if (preset === 'subheading') {
        text = 'Cordially Invites You';
        fontSize = 38;
        fontWeight = 400;
        fontFamily = 'Playfair Display';
    } else if (preset === 'body') {
        text = 'To celebrate our joyous union with dinner, dancing & blessing';
        fontSize = 26;
        fontWeight = 400;
        fontFamily = 'Noto Sans Ethiopic';
    }

    const newLayer = {
        id,
        type: 'text',
        x: 140,
        y: 400 + (design.value.layers.length * 30),
        width: 800,
        height: 120,
        rotation: 0,
        opacity: 1,
        locked: false,
        text,
        fontFamily,
        fontSize,
        fontWeight,
        fill: '#FAFAFA',
        align: 'center',
        lineHeight: 1.3,
        letterSpacing: 0,
    };

    design.value.layers.push(newLayer);
    recordHistory();
    selectLayer(id);
};

const insertPlaceholder = (token) => {
    if (primarySelectedLayer.value && primarySelectedLayer.value.type === 'text') {
        primarySelectedLayer.value.text += ` ${token}`;
        recordHistory();
    } else {
        const id = crypto.randomUUID();
        const newLayer = {
            id,
            type: 'text',
            x: 140,
            y: 500,
            width: 800,
            height: 90,
            rotation: 0,
            opacity: 1,
            locked: false,
            text: token,
            fontFamily: 'Noto Sans Ethiopic',
            fontSize: 42,
            fontWeight: 600,
            fill: '#D4AF37',
            align: 'center',
            lineHeight: 1.2,
            letterSpacing: 0,
        };
        design.value.layers.push(newLayer);
        recordHistory();
        selectLayer(id);
    }
};

const addShapeLayer = (shape = 'rect') => {
    const id = crypto.randomUUID();
    let layerData = {
        id,
        type: 'shape',
        shape,
        x: 390,
        y: 550,
        width: 300,
        height: 200,
        rotation: 0,
        opacity: 1,
        locked: false,
        fill: 'transparent',
        stroke: '#D4AF37',
        strokeWidth: 2,
    };

    if (shape === 'line') {
        layerData = {
            ...layerData,
            width: 400,
            height: 4,
            strokeWidth: 2,
        };
    } else if (shape === 'circle') {
        layerData = {
            ...layerData,
            width: 250,
            height: 250,
        };
    }

    design.value.layers.push(layerData);
    recordHistory();
    selectLayer(id);
};

const promptImageUpload = () => {
    if (props.isGuest) {
        guestModalMessage.value = 'Upload custom images with an Invite account. Register now and your card draft will be safely imported!';
        showGuestModal.value = true;
        return;
    }
    imageFileInput.value?.click();
};

const handleImageUpload = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('image', file);

    try {
        saveStatus.value = 'saving';
        const res = await axios.post(route('editor.upload-image'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        const id = crypto.randomUUID();
        const newLayer = {
            id,
            type: 'image',
            src: res.data.url,
            x: 290,
            y: 400,
            width: 500,
            height: 500,
            rotation: 0,
            opacity: 1,
            locked: false,
        };

        loadImage(res.data.url);
        design.value.layers.push(newLayer);
        recordHistory();
        selectLayer(id);
        saveStatus.value = 'saved';
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to upload image. Please try another image file.');
        saveStatus.value = 'error';
    } finally {
        if (e.target) e.target.value = '';
    }
};

const promptReplaceImage = () => {
    if (props.isGuest) {
        guestModalMessage.value = 'Sign up to upload and replace custom photos!';
        showGuestModal.value = true;
        return;
    }
    replaceImageFileInput.value?.click();
};

const handleReplaceImage = async (e) => {
    const file = e.target.files?.[0];
    if (!file || !primarySelectedLayer.value) return;

    const formData = new FormData();
    formData.append('image', file);

    try {
        saveStatus.value = 'saving';
        const res = await axios.post(route('editor.upload-image'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        primarySelectedLayer.value.src = res.data.url;
        loadImage(res.data.url);
        recordHistory();
        saveStatus.value = 'saved';
    } catch (err) {
        alert(err.response?.data?.message || 'Failed to replace image.');
        saveStatus.value = 'error';
    } finally {
        if (e.target) e.target.value = '';
    }
};

const duplicateSelected = () => {
    if (!primarySelectedLayer.value) return;
    const clone = JSON.parse(JSON.stringify(primarySelectedLayer.value));
    clone.id = crypto.randomUUID();
    clone.x += 24;
    clone.y += 24;
    design.value.layers.push(clone);
    recordHistory();
    selectLayer(clone.id);
};

const deleteSelected = () => {
    if (selectedLayerIds.value.length === 0) return;
    design.value.layers = design.value.layers.filter(l => !selectedLayerIds.value.includes(l.id));
    deselectAll();
    recordHistory();
};

const toggleLockSelected = () => {
    if (!primarySelectedLayer.value) return;
    primarySelectedLayer.value.locked = !primarySelectedLayer.value.locked;
    recordHistory();
    nextTick(() => updateTransformer());
};

const reorderLayer = (direction) => {
    if (!primarySelectedLayer.value) return;
    const index = design.value.layers.findIndex(l => l.id === primarySelectedLayer.value.id);
    if (index === -1) return;

    const layers = [...design.value.layers];
    const item = layers.splice(index, 1)[0];

    if (direction === 'front') {
        layers.push(item);
    } else if (direction === 'back') {
        layers.unshift(item);
    } else if (direction === 'forward') {
        layers.splice(Math.min(layers.length, index + 1), 0, item);
    } else if (direction === 'backward') {
        layers.splice(Math.max(0, index - 1), 0, item);
    }

    design.value.layers = layers;
    recordHistory();
};

// Switch Template
const applyTemplate = (tmpl) => {
    if (!confirm('Applying a new template will replace your current design canvas. Continue?')) {
        return;
    }
    currentTemplateId.value = tmpl.id;
    design.value = JSON.parse(JSON.stringify(tmpl.design_json));
    preloadImages();
    deselectAll();
    recordHistory();
};

// Export PNG
const downloadPng = async () => {
    isExporting.value = true;
    try {
        const prevSelected = [...selectedLayerIds.value];
        const prevPreview = isPreviewMode.value;

        // Deselect transformer handles and turn on preview replacements
        selectedLayerIds.value = [];
        isPreviewMode.value = true;
        updateTransformer();
        await nextTick();

        const stage = stageRef.value?.getNode();
        if (!stage) return;

        // High resolution 2x export (2160 x 3056 px)
        const dataUrl = stage.toDataURL({
            pixelRatio: 2,
            mimeType: 'image/png',
        });

        const link = document.createElement('a');
        const filename = (props.event?.title || props.template?.name || 'invite')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            + `-${props.target || 'invitation'}.png`;

        link.download = filename;
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        selectedLayerIds.value = prevSelected;
        isPreviewMode.value = prevPreview;
        nextTick(() => updateTransformer());
    } finally {
        isExporting.value = false;
    }
};

// Keyboard Shortcuts
const handleKeyDown = (e) => {
    // Don't trigger if user is typing in input or textarea
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
        return;
    }

    // Undo: Ctrl+Z / Cmd+Z
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
        e.preventDefault();
        undo();
        return;
    }

    // Redo: Ctrl+Y or Ctrl+Shift+Z / Cmd+Shift+Z
    if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') ||
        ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'z')) {
        e.preventDefault();
        redo();
        return;
    }

    // Duplicate: Ctrl+D / Cmd+D
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
        e.preventDefault();
        duplicateSelected();
        return;
    }

    // Delete / Backspace
    if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        deleteSelected();
        return;
    }

    // Arrow keys nudge
    if (primarySelectedLayer.value && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
        e.preventDefault();
        const step = e.shiftKey ? 10 : 1;
        if (e.key === 'ArrowUp') primarySelectedLayer.value.y -= step;
        if (e.key === 'ArrowDown') primarySelectedLayer.value.y += step;
        if (e.key === 'ArrowLeft') primarySelectedLayer.value.x -= step;
        if (e.key === 'ArrowRight') primarySelectedLayer.value.x += step;
        recordHistory();
        nextTick(() => updateTransformer());
    }
};

// Layer Config builder for Konva nodes
const getLayerConfig = (layer) => {
    const isSelected = selectedLayerIds.value.includes(layer.id);
    const base = {
        id: layer.id,
        name: layer.id,
        x: layer.x,
        y: layer.y,
        rotation: layer.rotation || 0,
        opacity: layer.opacity !== undefined ? layer.opacity : 1,
        draggable: !isPreviewMode.value && !layer.locked,
        listening: !isPreviewMode.value,
    };

    if (layer.type === 'text') {
        const textToDisplay = isPreviewMode.value ? resolveText(layer.text) : layer.text;
        return {
            ...base,
            text: textToDisplay,
            fontFamily: layer.fontFamily || 'Noto Sans Ethiopic',
            fontSize: layer.fontSize || 36,
            fontStyle: layer.fontWeight >= 600 ? 'bold' : 'normal',
            fill: layer.fill || '#FAFAFA',
            align: layer.align || 'center',
            lineHeight: layer.lineHeight || 1.3,
            letterSpacing: layer.letterSpacing || 0,
            width: layer.width > 0 ? layer.width : undefined,
            height: layer.height > 0 ? layer.height : undefined,
            padding: 6,
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

// Lifecycle Hooks
onMounted(() => {
    // Record initial history snapshot
    history.value = [JSON.parse(JSON.stringify(design.value))];
    historyIndex.value = 0;

    preloadImages();
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', calculateFitZoom);

    // Initial zoom fit
    nextTick(() => {
        calculateFitZoom();
    });

    // Check localStorage if guest mode
    if (props.isGuest) {
        try {
            const stored = localStorage.getItem('invite_guest_draft');
            if (stored && !props.template) {
                const parsed = JSON.parse(stored);
                if (parsed.design_json) {
                    design.value = parsed.design_json;
                    currentTemplateId.value = parsed.template_id || null;
                    preloadImages();
                }
            }
        } catch (e) {}
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('resize', calculateFitZoom);
    if (saveTimeout.value) {
        clearTimeout(saveTimeout.value);
    }
});

// Watch preview mode toggle
watch(isPreviewMode, (active) => {
    if (active) {
        deselectAll();
        nextTick(() => {
            checkTextOverflows();
        });
    } else {
        overflowWarnings.value = [];
    }
});
</script>

<template>
    <div class="h-screen w-screen bg-[#0E0E11] text-zinc-100 flex flex-col overflow-hidden select-none font-sans">
        <Head :title="event ? `${event.title} - Card Editor` : 'Card Editor'" />

        <!-- Hidden File Inputs -->
        <input
            ref="imageFileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/svg+xml"
            class="hidden"
            @change="handleImageUpload"
        />
        <input
            ref="replaceImageFileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/svg+xml"
            class="hidden"
            @change="handleReplaceImage"
        />

        <!-- Top Header Bar -->
        <header class="h-14 bg-zinc-950 border-b border-zinc-800 px-4 flex items-center justify-between shrink-0 z-30">
            <!-- Left: Back link & Title -->
            <div class="flex items-center space-x-3">
                <Link
                    :href="event ? route('dashboard') : route('templates.index')"
                    class="p-2 rounded-lg text-zinc-400 hover:text-white hover:bg-zinc-800 transition"
                    title="Exit Editor"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>

                <div class="flex items-center space-x-2">
                    <span class="text-sm font-semibold tracking-wide text-zinc-100 max-w-[200px] truncate sm:max-w-xs">
                        {{ event?.title || template?.name || 'Custom Invitation' }}
                    </span>
                    <span
                        class="text-[11px] font-medium px-2 py-0.5 rounded uppercase tracking-wider"
                        :class="target === 'thank_you' ? 'bg-amber-950/80 text-amber-300 border border-amber-800/60' : 'bg-zinc-800 text-zinc-300 border border-zinc-700'"
                    >
                        {{ target === 'thank_you' ? 'Thank You Card' : 'Invitation Card' }}
                    </span>
                </div>
            </div>

            <!-- Center: Autosave Status -->
            <div class="hidden md:flex items-center space-x-2 text-xs">
                <span
                    v-if="saveStatus === 'saved'"
                    class="flex items-center space-x-1.5 text-emerald-400 bg-emerald-950/50 px-2.5 py-1 rounded-full border border-emerald-800/40"
                >
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>All changes saved</span>
                </span>
                <span
                    v-else-if="saveStatus === 'saving'"
                    class="flex items-center space-x-1.5 text-amber-400 bg-amber-950/50 px-2.5 py-1 rounded-full border border-amber-800/40"
                >
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Saving...</span>
                </span>
                <span
                    v-else-if="saveStatus === 'unsaved'"
                    class="flex items-center space-x-1.5 text-zinc-400 bg-zinc-900 px-2.5 py-1 rounded-full border border-zinc-800"
                >
                    <span class="w-2 h-2 rounded-full bg-zinc-500"></span>
                    <span>Unsaved changes</span>
                </span>
                <span
                    v-else-if="saveStatus === 'error'"
                    class="flex items-center space-x-1.5 text-rose-400 bg-rose-950/50 px-2.5 py-1 rounded-full border border-rose-800/40"
                >
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>Save error</span>
                </span>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center space-x-2">
                <!-- Preview as Guest Switcher -->
                <button
                    type="button"
                    @click="isPreviewMode = !isPreviewMode"
                    :class="isPreviewMode ? 'bg-[#D4AF37] text-black font-semibold' : 'bg-zinc-800 hover:bg-zinc-700 text-zinc-200'"
                    class="px-3 py-1.5 rounded-lg text-xs flex items-center space-x-1.5 transition"
                    title="Toggle Guest Preview"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span class="hidden sm:inline">{{ isPreviewMode ? 'Exit Preview' : 'Preview as Guest' }}</span>
                </button>

                <!-- Save Button -->
                <button
                    type="button"
                    @click="performSave"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 transition flex items-center space-x-1"
                >
                    <svg class="w-3.5 h-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    <span>Save</span>
                </button>

                <!-- Export PNG Button -->
                <button
                    type="button"
                    @click="downloadPng"
                    :disabled="isExporting"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-gradient-to-r from-[#D4AF37] to-[#F59E0B] hover:brightness-110 text-black shadow-md transition flex items-center space-x-1.5 disabled:opacity-50"
                >
                    <svg v-if="!isExporting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Download PNG</span>
                </button>

                <!-- Guest Mode Import/Sign Up Trigger -->
                <Link
                    v-if="isGuest"
                    :href="route('register')"
                    class="hidden sm:inline-flex px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white transition"
                >
                    Sign Up to Send
                </Link>
            </div>
        </header>

        <!-- Main Workspace Area -->
        <div class="flex-1 flex overflow-hidden relative">
            <!-- Left Tool Sidebar (Desktop) -->
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

                <!-- Tab Content -->
                <div class="flex-1 overflow-y-auto p-4 space-y-6 text-sm">
                    <!-- INSERT TAB -->
                    <div v-if="activeSidebarTab === 'insert'" class="space-y-6">
                        <!-- Add Text Section -->
                        <div>
                            <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider mb-2.5">Text</h4>
                            <div class="grid grid-cols-1 gap-2">
                                <button
                                    type="button"
                                    @click="addTextLayer('heading')"
                                    class="w-full text-left px-3.5 py-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 transition flex items-center justify-between"
                                >
                                    <span class="font-bold text-base text-zinc-100">Add Heading</span>
                                    <span class="text-xs text-zinc-500">56px</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addTextLayer('subheading')"
                                    class="w-full text-left px-3.5 py-2.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 transition flex items-center justify-between"
                                >
                                    <span class="font-medium text-sm text-zinc-200">Add Subheading</span>
                                    <span class="text-xs text-zinc-500">38px</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addTextLayer('body')"
                                    class="w-full text-left px-3.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 transition flex items-center justify-between"
                                >
                                    <span class="text-xs text-zinc-300">Add Body Text</span>
                                    <span class="text-xs text-zinc-500">26px</span>
                                </button>
                            </div>
                        </div>

                        <!-- Insert Placeholders -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">Placeholders</h4>
                                <span class="text-[11px] text-[#D4AF37]">Auto-filled</span>
                            </div>
                            <p class="text-xs text-zinc-500 mb-2.5">
                                Insert variables replaced with recipient or event data:
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{guest_name}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;guest_name&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Guest Full Name</div>
                                </button>
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{event_title}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;event_title&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Event Title</div>
                                </button>
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{date}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;date&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Event Date</div>
                                </button>
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{time}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;time&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Start Time</div>
                                </button>
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{venue}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;venue&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Venue Name</div>
                                </button>
                                <button
                                    type="button"
                                    @click="insertPlaceholder('{{host_names}}')"
                                    class="px-2.5 py-2 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 hover:border-[#D4AF37]/50 text-left transition"
                                >
                                    <div class="text-xs font-mono font-medium text-amber-300">&#123;&#123;host_names&#125;&#125;</div>
                                    <div class="text-[10px] text-zinc-400 mt-0.5">Hosts / Couple</div>
                                </button>
                            </div>
                        </div>

                        <!-- Shapes -->
                        <div>
                            <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider mb-2.5">Shapes & Lines</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="addShapeLayer('rect')"
                                    class="p-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex flex-col items-center justify-center space-y-1.5 transition"
                                >
                                    <div class="w-8 h-6 border-2 border-[#D4AF37] rounded-sm"></div>
                                    <span class="text-[11px] text-zinc-300">Rectangle</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addShapeLayer('circle')"
                                    class="p-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex flex-col items-center justify-center space-y-1.5 transition"
                                >
                                    <div class="w-7 h-7 border-2 border-[#D4AF37] rounded-full"></div>
                                    <span class="text-[11px] text-zinc-300">Circle</span>
                                </button>
                                <button
                                    type="button"
                                    @click="addShapeLayer('line')"
                                    class="p-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 flex flex-col items-center justify-center space-y-1.5 transition"
                                >
                                    <div class="w-8 h-0.5 bg-[#D4AF37] my-3"></div>
                                    <span class="text-[11px] text-zinc-300">Line</span>
                                </button>
                            </div>
                        </div>

                        <!-- Upload Image -->
                        <div>
                            <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider mb-2.5">Images</h4>
                            <button
                                type="button"
                                @click="promptImageUpload"
                                class="w-full py-3 px-4 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-dashed border-zinc-700 hover:border-[#D4AF37] flex flex-col items-center justify-center space-y-1.5 transition"
                            >
                                <svg class="w-6 h-6 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-xs font-medium text-zinc-200">Upload Image / Photo</span>
                                <span class="text-[10px] text-zinc-500">PNG, JPG, WebP, SVG (Max 8MB)</span>
                            </button>
                        </div>
                    </div>

                    <!-- LAYERS TAB -->
                    <div v-else-if="activeSidebarTab === 'layers'" class="space-y-2">
                        <div v-if="design.layers.length === 0" class="text-center py-10 text-zinc-500 text-xs">
                            No elements added yet. Click "Insert" to add text, shapes or photos.
                        </div>

                        <!-- Layers List (Reverse so top layers are first) -->
                        <div
                            v-for="(layer, idx) in [...design.layers].reverse()"
                            :key="layer.id"
                            @click="selectLayer(layer.id)"
                            :class="selectedLayerIds.includes(layer.id) ? 'bg-zinc-800 border-[#D4AF37]' : 'bg-zinc-900/80 border-zinc-800 hover:bg-zinc-800/60'"
                            class="p-2.5 rounded-lg border flex items-center justify-between cursor-pointer transition text-xs"
                        >
                            <div class="flex items-center space-x-2.5 overflow-hidden">
                                <!-- Type Icon -->
                                <span class="text-zinc-400 shrink-0">
                                    <svg v-if="layer.type === 'text'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                                    </svg>
                                    <svg v-else-if="layer.type === 'image'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z" />
                                    </svg>
                                </span>

                                <span class="truncate font-medium text-zinc-200">
                                    {{ layer.type === 'text' ? (layer.text || 'Empty Text') : (layer.shape || layer.type) }}
                                </span>
                            </div>

                            <div class="flex items-center space-x-1 shrink-0">
                                <!-- Lock Toggle -->
                                <button
                                    type="button"
                                    @click.stop="layer.locked = !layer.locked; recordHistory(); updateTransformer();"
                                    class="p-1 text-zinc-400 hover:text-zinc-200 rounded"
                                    :title="layer.locked ? 'Unlock Layer' : 'Lock Layer'"
                                >
                                    <svg v-if="layer.locked" class="w-3.5 h-3.5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z" />
                                    </svg>
                                </button>

                                <!-- Delete -->
                                <button
                                    type="button"
                                    @click.stop="selectLayer(layer.id); deleteSelected();"
                                    class="p-1 text-zinc-500 hover:text-rose-400 rounded"
                                    title="Delete Layer"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- TEMPLATES TAB -->
                    <div v-else-if="activeSidebarTab === 'templates'" class="space-y-4">
                        <div v-for="cat in categories" :key="cat.id" class="space-y-2">
                            <h4 class="text-xs uppercase font-bold text-zinc-400 tracking-wider">{{ cat.name }}</h4>
                            <div class="grid grid-cols-2 gap-2">
                                <div
                                    v-for="tmpl in cat.templates"
                                    :key="tmpl.id"
                                    @click="applyTemplate(tmpl)"
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

            <!-- Center Canvas Workspace -->
            <main
                ref="canvasContainerRef"
                class="flex-1 bg-[#0A0A0D] flex flex-col items-center justify-center p-4 relative overflow-auto"
                @click="deselectAll"
            >
                <!-- Overflow Warning Banner -->
                <div
                    v-if="isPreviewMode && overflowWarnings.length > 0"
                    class="absolute top-4 left-1/2 -translate-x-1/2 z-30 max-w-lg w-full bg-amber-950/90 border border-amber-600/80 rounded-xl p-3 shadow-2xl backdrop-blur text-xs text-amber-200 flex items-start space-x-2.5 animate-bounce-short"
                >
                    <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="flex-1">
                        <div class="font-bold text-amber-100">Text Overflow Warning</div>
                        <div class="mt-0.5">
                            {{ overflowWarnings.length }} text box is overflowing its bounds with sample data. Increase box height or decrease font size to avoid clipping.
                        </div>
                    </div>
                </div>

                <!-- Konva Canvas Wrapper scaled with reactive zoom -->
                <div
                    class="shadow-2xl rounded-sm transition-transform duration-75 relative origin-center"
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
                            <!-- Background Layer -->
                            <v-layer>
                                <v-image
                                    v-if="design.background?.type === 'image' && loadedImages[design.background.value]"
                                    :config="{
                                        name: 'background-image',
                                        x: 0,
                                        y: 0,
                                        width: canvasWidth,
                                        height: canvasHeight,
                                        image: loadedImages[design.background.value]
                                    }"
                                />
                                <v-rect
                                    v-else
                                    :config="{
                                        name: 'background-rect',
                                        ...backgroundConfig
                                    }"
                                />
                            </v-layer>

                            <!-- Content Elements Layer -->
                            <v-layer>
                                <template v-for="layer in design.layers" :key="layer.id">
                                    <v-text
                                        v-if="layer.type === 'text'"
                                        :config="getLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                    <v-image
                                        v-else-if="layer.type === 'image'"
                                        :config="getLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                    <v-circle
                                        v-else-if="layer.type === 'shape' && layer.shape === 'circle'"
                                        :config="getLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                    <v-line
                                        v-else-if="layer.type === 'shape' && layer.shape === 'line'"
                                        :config="getLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                    <v-rect
                                        v-else-if="layer.type === 'shape'"
                                        :config="getLayerConfig(layer)"
                                        @click="selectLayer(layer.id, $event.evt.shiftKey)"
                                        @tap="selectLayer(layer.id)"
                                        @dragstart="handleDragStart"
                                        @dragmove="handleDragMove($event, layer)"
                                        @dragend="handleDragEnd($event, layer)"
                                        @transformend="handleTransformEnd($event, layer)"
                                    />
                                </template>
                            </v-layer>

                            <!-- Snapping Guides Layer -->
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

                            <!-- Transformer Layer -->
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

                            <!-- Free-tier Watermark (Visible if !hasRemoveBranding) -->
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
                                        text: 'Created with Invite • invite.et',
                                        fontFamily: 'Montserrat, sans-serif',
                                        fontSize: 20,
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
                <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-20 bg-zinc-950/90 border border-zinc-800 rounded-full px-4 py-2 shadow-xl backdrop-blur flex items-center space-x-3 text-xs">
                    <!-- Undo -->
                    <button
                        type="button"
                        @click="undo"
                        :disabled="historyIndex <= 0"
                        class="p-1 text-zinc-400 hover:text-white disabled:opacity-30 transition"
                        title="Undo (Ctrl+Z)"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                    </button>

                    <!-- Redo -->
                    <button
                        type="button"
                        @click="redo"
                        :disabled="historyIndex >= history.length - 1"
                        class="p-1 text-zinc-400 hover:text-white disabled:opacity-30 transition"
                        title="Redo (Ctrl+Y)"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6" />
                        </svg>
                    </button>

                    <div class="w-px h-4 bg-zinc-800"></div>

                    <!-- Zoom Out -->
                    <button
                        type="button"
                        @click="zoom = Math.max(0.15, parseFloat((zoom - 0.1).toFixed(2)))"
                        class="p-1 text-zinc-400 hover:text-white transition"
                        title="Zoom Out"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </button>

                    <!-- Zoom Percentage -->
                    <span class="font-mono text-zinc-300 w-12 text-center">
                        {{ Math.round(zoom * 100) }}%
                    </span>

                    <!-- Zoom In -->
                    <button
                        type="button"
                        @click="zoom = Math.min(1.5, parseFloat((zoom + 0.1).toFixed(2)))"
                        class="p-1 text-zinc-400 hover:text-white transition"
                        title="Zoom In"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>

                    <!-- Fit Screen -->
                    <button
                        type="button"
                        @click="calculateFitZoom"
                        class="text-[11px] font-medium text-[#D4AF37] hover:underline px-1.5 transition"
                    >
                        Fit
                    </button>
                </div>
            </main>

            <!-- Right Properties Panel (Desktop) -->
            <aside class="w-80 bg-zinc-950 border-l border-zinc-800 flex flex-col shrink-0 z-20 hidden md:flex">
                <div class="h-12 border-b border-zinc-800 px-4 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-300">
                        {{ primarySelectedLayer ? `${primarySelectedLayer.type.toUpperCase()} PROPERTIES` : 'BACKGROUND CANVAS' }}
                    </span>
                    <button
                        v-if="primarySelectedLayer"
                        type="button"
                        @click="deselectAll"
                        class="text-xs text-zinc-500 hover:text-zinc-300"
                    >
                        Deselect
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-6 text-xs">
                    <!-- IF TEXT LAYER SELECTED -->
                    <div v-if="primarySelectedLayer && primarySelectedLayer.type === 'text'" class="space-y-4">
                        <!-- Text Input -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1.5">Text Content</label>
                            <textarea
                                v-model="primarySelectedLayer.text"
                                @input="recordHistory"
                                rows="3"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-2.5 text-zinc-100 text-sm focus:border-[#D4AF37] focus:ring-1 focus:ring-[#D4AF37]"
                            ></textarea>
                        </div>

                        <!-- Font Family Picker -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1.5">Font Family</label>
                            <select
                                v-model="primarySelectedLayer.fontFamily"
                                @change="recordHistory"
                                class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-2.5 text-zinc-100 text-xs focus:border-[#D4AF37] focus:ring-1 focus:ring-[#D4AF37]"
                            >
                                <option
                                    v-for="font in AVAILABLE_FONTS"
                                    :key="font.name"
                                    :value="font.name"
                                    :style="{ fontFamily: font.name }"
                                >
                                    {{ font.label }}
                                </option>
                            </select>
                            <div
                                class="mt-2 p-2 rounded bg-zinc-900/60 border border-zinc-800 text-sm text-[#D4AF37] text-center truncate"
                                :style="{ fontFamily: primarySelectedLayer.fontFamily }"
                            >
                                {{ AVAILABLE_FONTS.find(f => f.name === primarySelectedLayer.fontFamily)?.sample || 'እንኳን ደስ አላችሁ' }}
                            </div>
                        </div>

                        <!-- Font Size & Weight -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-zinc-400 font-semibold mb-1.5">Size (px)</label>
                                <input
                                    type="number"
                                    v-model.number="primarySelectedLayer.fontSize"
                                    @input="recordHistory"
                                    min="12"
                                    max="200"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 text-xs focus:border-[#D4AF37]"
                                />
                            </div>
                            <div>
                                <label class="block text-zinc-400 font-semibold mb-1.5">Weight</label>
                                <select
                                    v-model.number="primarySelectedLayer.fontWeight"
                                    @change="recordHistory"
                                    class="w-full bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 text-xs focus:border-[#D4AF37]"
                                >
                                    <option :value="400">Regular (400)</option>
                                    <option :value="600">Semi-Bold (600)</option>
                                    <option :value="700">Bold (700)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Text Color -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1.5">Text Color</label>
                            <div class="flex items-center space-x-2 mb-2">
                                <input
                                    type="color"
                                    v-model="primarySelectedLayer.fill"
                                    @input="recordHistory"
                                    class="w-8 h-8 rounded border border-zinc-700 bg-transparent cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="primarySelectedLayer.fill"
                                    @input="recordHistory"
                                    class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 font-mono text-xs uppercase"
                                />
                            </div>
                            <!-- Quick Palette -->
                            <div class="grid grid-cols-7 gap-1.5">
                                <button
                                    v-for="color in COLOR_PALETTES"
                                    :key="color.value"
                                    type="button"
                                    @click="primarySelectedLayer.fill = color.value; recordHistory();"
                                    :style="{ backgroundColor: color.value }"
                                    class="w-full aspect-square rounded border border-zinc-700/60 hover:scale-110 transition"
                                    :title="color.name"
                                ></button>
                            </div>
                        </div>

                        <!-- Alignment -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1.5">Alignment</label>
                            <div class="grid grid-cols-3 gap-1 bg-zinc-900 p-1 rounded-lg border border-zinc-800">
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.align = 'left'; recordHistory();"
                                    :class="primarySelectedLayer.align === 'left' ? 'bg-zinc-800 text-[#D4AF37]' : 'text-zinc-400'"
                                    class="py-1 rounded text-center transition"
                                >
                                    Left
                                </button>
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.align = 'center'; recordHistory();"
                                    :class="primarySelectedLayer.align === 'center' ? 'bg-zinc-800 text-[#D4AF37]' : 'text-zinc-400'"
                                    class="py-1 rounded text-center transition"
                                >
                                    Center
                                </button>
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.align = 'right'; recordHistory();"
                                    :class="primarySelectedLayer.align === 'right' ? 'bg-zinc-800 text-[#D4AF37]' : 'text-zinc-400'"
                                    class="py-1 rounded text-center transition"
                                >
                                    Right
                                </button>
                            </div>
                        </div>

                        <!-- Line Height & Letter Spacing -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-zinc-400 font-semibold mb-1">Line Height ({{ primarySelectedLayer.lineHeight || 1.3 }})</label>
                                <input
                                    type="range"
                                    v-model.number="primarySelectedLayer.lineHeight"
                                    @input="recordHistory"
                                    min="0.8"
                                    max="2.5"
                                    step="0.05"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                            <div>
                                <label class="block text-zinc-400 font-semibold mb-1">Letter Spacing ({{ primarySelectedLayer.letterSpacing || 0 }})</label>
                                <input
                                    type="range"
                                    v-model.number="primarySelectedLayer.letterSpacing"
                                    @input="recordHistory"
                                    min="-2"
                                    max="20"
                                    step="1"
                                    class="w-full accent-[#D4AF37]"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- IF IMAGE LAYER SELECTED -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'image'" class="space-y-4">
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-2">Image</label>
                            <div class="w-full aspect-video rounded-lg overflow-hidden bg-zinc-900 border border-zinc-800 mb-2 flex items-center justify-center">
                                <img
                                    v-if="primarySelectedLayer.src"
                                    :src="primarySelectedLayer.src"
                                    class="w-full h-full object-contain"
                                    alt="Layer Preview"
                                />
                            </div>
                            <button
                                type="button"
                                @click="promptReplaceImage"
                                class="w-full py-2 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-200 border border-zinc-700 text-xs transition"
                            >
                                Replace Image...
                            </button>
                        </div>

                        <!-- Opacity Slider -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1">Opacity ({{ Math.round((primarySelectedLayer.opacity ?? 1) * 100) }}%)</label>
                            <input
                                type="range"
                                v-model.number="primarySelectedLayer.opacity"
                                @input="recordHistory"
                                min="0.05"
                                max="1"
                                step="0.05"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>
                    </div>

                    <!-- IF SHAPE LAYER SELECTED -->
                    <div v-else-if="primarySelectedLayer && primarySelectedLayer.type === 'shape'" class="space-y-4">
                        <!-- Fill Color -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-zinc-400 font-semibold">Fill Color</label>
                                <button
                                    type="button"
                                    @click="primarySelectedLayer.fill = 'transparent'; recordHistory();"
                                    class="text-[10px] text-zinc-400 hover:underline"
                                >
                                    Transparent
                                </button>
                            </div>
                            <div class="flex items-center space-x-2 mb-2">
                                <input
                                    type="color"
                                    :value="primarySelectedLayer.fill === 'transparent' ? '#000000' : primarySelectedLayer.fill"
                                    @input="primarySelectedLayer.fill = $event.target.value; recordHistory();"
                                    class="w-8 h-8 rounded border border-zinc-700 bg-transparent cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="primarySelectedLayer.fill"
                                    @input="recordHistory"
                                    class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 font-mono text-xs uppercase"
                                />
                            </div>
                        </div>

                        <!-- Stroke Color & Width -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1.5">Border / Stroke</label>
                            <div class="flex items-center space-x-2 mb-2">
                                <input
                                    type="color"
                                    v-model="primarySelectedLayer.stroke"
                                    @input="recordHistory"
                                    class="w-8 h-8 rounded border border-zinc-700 bg-transparent cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="primarySelectedLayer.stroke"
                                    @input="recordHistory"
                                    class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 font-mono text-xs uppercase"
                                />
                            </div>

                            <label class="block text-zinc-400 font-semibold mb-1">Stroke Width ({{ primarySelectedLayer.strokeWidth || 0 }}px)</label>
                            <input
                                type="range"
                                v-model.number="primarySelectedLayer.strokeWidth"
                                @input="recordHistory"
                                min="0"
                                max="20"
                                step="1"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>

                        <!-- Opacity Slider -->
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-1">Opacity ({{ Math.round((primarySelectedLayer.opacity ?? 1) * 100) }}%)</label>
                            <input
                                type="range"
                                v-model.number="primarySelectedLayer.opacity"
                                @input="recordHistory"
                                min="0.05"
                                max="1"
                                step="0.05"
                                class="w-full accent-[#D4AF37]"
                            />
                        </div>
                    </div>

                    <!-- IF NO LAYER SELECTED: BACKGROUND CONTROLS -->
                    <div v-else class="space-y-4">
                        <div>
                            <label class="block text-zinc-400 font-semibold mb-2">Background Type</label>
                            <div class="grid grid-cols-2 gap-1 bg-zinc-900 p-1 rounded-lg border border-zinc-800">
                                <button
                                    type="button"
                                    @click="design.background.type = 'color'; recordHistory();"
                                    :class="design.background.type === 'color' ? 'bg-zinc-800 text-[#D4AF37]' : 'text-zinc-400'"
                                    class="py-1.5 rounded text-center transition"
                                >
                                    Solid Color
                                </button>
                                <button
                                    type="button"
                                    @click="design.background.type = 'gradient'; recordHistory();"
                                    :class="design.background.type === 'gradient' ? 'bg-zinc-800 text-[#D4AF37]' : 'text-zinc-400'"
                                    class="py-1.5 rounded text-center transition"
                                >
                                    Gradient
                                </button>
                            </div>
                        </div>

                        <!-- Solid Background Color -->
                        <div v-if="design.background.type === 'color'">
                            <label class="block text-zinc-400 font-semibold mb-1.5">Color</label>
                            <div class="flex items-center space-x-2 mb-2">
                                <input
                                    type="color"
                                    v-model="design.background.value"
                                    @input="recordHistory"
                                    class="w-8 h-8 rounded border border-zinc-700 bg-transparent cursor-pointer"
                                />
                                <input
                                    type="text"
                                    v-model="design.background.value"
                                    @input="recordHistory"
                                    class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg p-2 text-zinc-100 font-mono text-xs uppercase"
                                />
                            </div>
                            <!-- Quick Palette -->
                            <div class="grid grid-cols-7 gap-1.5">
                                <button
                                    v-for="color in COLOR_PALETTES"
                                    :key="color.value"
                                    type="button"
                                    @click="design.background.value = color.value; recordHistory();"
                                    :style="{ backgroundColor: color.value }"
                                    class="w-full aspect-square rounded border border-zinc-700/60 hover:scale-110 transition"
                                    :title="color.name"
                                ></button>
                            </div>
                        </div>

                        <!-- Gradient Presets -->
                        <div v-if="design.background.type === 'gradient'" class="space-y-2">
                            <label class="block text-zinc-400 font-semibold mb-1.5">Gradient Styles</label>
                            <div class="space-y-1.5">
                                <button
                                    v-for="(grad, i) in GRADIENT_PRESETS"
                                    :key="i"
                                    type="button"
                                    @click="design.background.value = grad.start; design.background.gradientStops = [0, grad.start, 1, grad.end]; recordHistory();"
                                    :style="{ background: `linear-gradient(to right, ${grad.start}, ${grad.end})` }"
                                    class="w-full py-2.5 px-3 rounded-lg border border-zinc-700 text-left text-xs font-medium text-zinc-100 flex items-center justify-between hover:border-[#D4AF37] transition"
                                >
                                    <span>{{ grad.label }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Layer Actions (Duplicate, Reorder, Delete) -->
                    <div v-if="primarySelectedLayer" class="pt-4 border-t border-zinc-800 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                @click="duplicateSelected"
                                class="py-2 px-3 rounded-lg bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 transition text-center"
                            >
                                Duplicate
                            </button>
                            <button
                                type="button"
                                @click="toggleLockSelected"
                                :class="primarySelectedLayer.locked ? 'text-amber-400 border-amber-800/80 bg-amber-950/40' : 'text-zinc-200 bg-zinc-900 border-zinc-800'"
                                class="py-2 px-3 rounded-lg hover:bg-zinc-800 border transition text-center"
                            >
                                {{ primarySelectedLayer.locked ? 'Unlock' : 'Lock Layer' }}
                            </button>
                        </div>

                        <!-- Arrange / Reorder -->
                        <div class="grid grid-cols-4 gap-1 text-[11px] text-zinc-300">
                            <button
                                type="button"
                                @click="reorderLayer('front')"
                                class="p-1.5 rounded bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-center"
                                title="Bring to Front"
                            >
                                Front
                            </button>
                            <button
                                type="button"
                                @click="reorderLayer('forward')"
                                class="p-1.5 rounded bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-center"
                                title="Bring Forward"
                            >
                                Forward
                            </button>
                            <button
                                type="button"
                                @click="reorderLayer('backward')"
                                class="p-1.5 rounded bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-center"
                                title="Send Backward"
                            >
                                Backward
                            </button>
                            <button
                                type="button"
                                @click="reorderLayer('back')"
                                class="p-1.5 rounded bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-center"
                                title="Send to Back"
                            >
                                Back
                            </button>
                        </div>

                        <button
                            type="button"
                            @click="deleteSelected"
                            class="w-full py-2 px-3 rounded-lg bg-rose-950/30 hover:bg-rose-900/50 border border-rose-800/60 text-rose-300 transition text-center mt-2"
                        >
                            Delete Layer
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Mobile Drawer Toolbar (Visible only on small screens) -->
        <div class="md:hidden bg-zinc-950 border-t border-zinc-800 p-2 flex items-center justify-around text-xs shrink-0 z-30">
            <button
                type="button"
                @click="mobileTab = mobileTab === 'insert' ? null : 'insert'"
                :class="mobileTab === 'insert' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center py-1"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>Add</span>
            </button>
            <button
                type="button"
                @click="mobileTab = mobileTab === 'props' ? null : 'props'"
                :class="mobileTab === 'props' ? 'text-[#D4AF37]' : 'text-zinc-400'"
                class="flex flex-col items-center py-1"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <span>Edit</span>
            </button>
            <button
                type="button"
                @click="undo"
                :disabled="historyIndex <= 0"
                class="flex flex-col items-center py-1 text-zinc-400 disabled:opacity-30"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span>Undo</span>
            </button>
            <button
                type="button"
                @click="downloadPng"
                class="flex flex-col items-center py-1 text-[#D4AF37]"
            >
                <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <span>Export</span>
            </button>
        </div>

        <!-- Mobile Sheet Modal -->
        <Modal :show="mobileTab !== null" @close="mobileTab = null">
            <div class="p-5 bg-zinc-900 text-zinc-100 max-h-[75vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-zinc-800 mb-4">
                    <h3 class="font-bold text-sm uppercase text-[#D4AF37]">
                        {{ mobileTab === 'insert' ? 'Add Elements' : 'Properties' }}
                    </h3>
                    <button type="button" @click="mobileTab = null" class="text-zinc-400 hover:text-white">
                        ✕
                    </button>
                </div>

                <div v-if="mobileTab === 'insert'" class="space-y-4">
                    <button
                        type="button"
                        @click="addTextLayer('heading'); mobileTab = null;"
                        class="w-full py-2.5 px-3 rounded bg-zinc-800 text-left text-sm font-bold"
                    >
                        + Heading
                    </button>
                    <button
                        type="button"
                        @click="addTextLayer('body'); mobileTab = null;"
                        class="w-full py-2.5 px-3 rounded bg-zinc-800 text-left text-sm"
                    >
                        + Body Text
                    </button>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="insertPlaceholder('{{guest_name}}'); mobileTab = null;"
                            class="p-2 rounded bg-zinc-800 text-xs font-mono text-amber-300 text-left"
                        >
                            &#123;&#123;guest_name&#125;&#125;
                        </button>
                        <button
                            type="button"
                            @click="insertPlaceholder('{{event_title}}'); mobileTab = null;"
                            class="p-2 rounded bg-zinc-800 text-xs font-mono text-amber-300 text-left"
                        >
                            &#123;&#123;event_title&#125;&#125;
                        </button>
                    </div>
                    <button
                        type="button"
                        @click="promptImageUpload(); mobileTab = null;"
                        class="w-full py-2.5 px-3 rounded bg-zinc-800 text-left text-sm"
                    >
                        + Upload Image
                    </button>
                </div>

                <div v-else-if="mobileTab === 'props'" class="space-y-4">
                    <div v-if="primarySelectedLayer && primarySelectedLayer.type === 'text'" class="space-y-3">
                        <label class="block text-xs text-zinc-400">Edit Text</label>
                        <textarea
                            v-model="primarySelectedLayer.text"
                            @input="recordHistory"
                            rows="2"
                            class="w-full bg-zinc-800 rounded p-2 text-sm text-white"
                        ></textarea>
                        <button
                            type="button"
                            @click="deleteSelected(); mobileTab = null;"
                            class="w-full py-2 bg-rose-900/50 text-rose-300 rounded text-xs mt-3"
                        >
                            Delete Layer
                        </button>
                    </div>
                    <div v-else class="text-zinc-400 text-xs text-center py-6">
                        Tap a layer on the canvas first to view and edit its properties.
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Guest Modal -->
        <Modal :show="showGuestModal" @close="showGuestModal = false">
            <div class="p-6 bg-zinc-950 text-zinc-100 border border-zinc-800 rounded-2xl">
                <div class="w-12 h-12 rounded-full bg-amber-500/10 text-[#D4AF37] flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <h3 class="text-lg font-bold text-center text-zinc-100">
                    Sign Up to Continue
                </h3>
                <p class="mt-2 text-sm text-zinc-400 text-center">
                    {{ guestModalMessage || 'Create a free account to upload your photos, add guest lists, and send invitations. Your card design draft will be preserved automatically!' }}
                </p>

                <div class="mt-6 flex flex-col space-y-2">
                    <Link
                        :href="route('register')"
                        class="w-full py-2.5 px-4 rounded-xl text-center font-semibold bg-[#D4AF37] hover:bg-amber-400 text-black text-sm transition"
                    >
                        Create Free Account
                    </Link>
                    <Link
                        :href="route('login')"
                        class="w-full py-2.5 px-4 rounded-xl text-center font-medium bg-zinc-900 hover:bg-zinc-800 text-zinc-300 text-sm transition"
                    >
                        Log In to Existing Account
                    </Link>
                    <button
                        type="button"
                        @click="showGuestModal = false"
                        class="text-xs text-zinc-500 hover:text-zinc-300 py-1"
                    >
                        Continue Editing as Guest
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
@keyframes bounce-short {
    0%, 100% { transform: translate(-50%, 0); }
    50% { transform: translate(-50%, -6px); }
}
.animate-bounce-short {
    animation: bounce-short 1.5s ease-in-out infinite;
}
</style>
