<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Html5Qrcode } from 'html5-qrcode';

const props = defineProps({
    scanner: {
        type: Object,
        required: true,
    },
    event: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        default: () => ({ total_guests: 0, checked_in: 0 }),
    },
});

// UI View Modes
const activeTab = ref('camera'); // 'camera' | 'search' | 'stats'
const isScanning = ref(false);
const cameraError = ref(null);
const isTorchOn = ref(false);
const hasTorch = ref(false);

// Result Overlay State
const scanResult = ref(null); // { type: 'success'|'already_used'|'invalid'|'wrong_event', title: '', message: '', party_size: 1, guest_name: '' }
const autoResetTimer = ref(null);
const autoResetProgress = ref(100);

// Stats & Guest Counter
const totalGuests = ref(props.stats.total_guests);
const checkedInCount = ref(props.stats.checked_in);

// Offline Mode & Sync Queue
const isOnline = ref(navigator.onLine);
const offlineQueue = ref([]);
const isSyncing = ref(false);
const offlineManifest = ref([]);
let html5QrCode = null;

// Audio Context for Chimes & Buzzers
let audioCtx = null;
const getAudioContext = () => {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
    return audioCtx;
};

const playSuccessSound = () => {
    try {
        const ctx = getAudioContext();
        const now = ctx.currentTime;
        const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
        notes.forEach((freq, idx) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, now + idx * 0.08);
            gain.gain.setValueAtTime(0.3, now + idx * 0.08);
            gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.08 + 0.25);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(now + idx * 0.08);
            osc.stop(now + idx * 0.08 + 0.26);
        });
    } catch (e) {
        // Audio playback error fallback
    }
};

const playErrorSound = () => {
    try {
        const ctx = getAudioContext();
        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(140, now);
        osc.frequency.setValueAtTime(110, now + 0.15);
        gain.gain.setValueAtTime(0.4, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start(now);
        osc.stop(now + 0.46);
    } catch (e) {
        // Audio playback error fallback
    }
};

const triggerHaptic = (type) => {
    if (navigator.vibrate) {
        if (type === 'success') {
            navigator.vibrate([100, 50, 100]);
        } else {
            navigator.vibrate([300, 100, 300, 100, 300]);
        }
    }
};

// ==========================================
// IndexedDB for Offline Caching & Queuing
// ==========================================
const DB_NAME = `invite_scanner_${props.scanner.id}`;
const DB_VERSION = 1;

const openDB = () => {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('manifest')) {
                db.createObjectStore('manifest', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('queue')) {
                db.createObjectStore('queue', { autoIncrement: true });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
};

const saveOfflineManifest = async (guests) => {
    try {
        const db = await openDB();
        const tx = db.transaction('manifest', 'readwrite');
        const store = tx.objectStore('manifest');
        await store.clear();
        for (const guest of guests) {
            store.put(guest);
        }
        offlineManifest.value = guests;
    } catch (e) {
        console.warn('Failed to cache manifest', e);
    }
};

const queueOfflineScan = async (scanData) => {
    try {
        const db = await openDB();
        const tx = db.transaction('queue', 'readwrite');
        tx.objectStore('queue').add(scanData);
        offlineQueue.value.push(scanData);
    } catch (e) {
        offlineQueue.value.push(scanData);
    }
};

const loadOfflineQueue = async () => {
    try {
        const db = await openDB();
        const tx = db.transaction('queue', 'readonly');
        const store = tx.objectStore('queue');
        const req = store.getAll();
        req.onsuccess = () => {
            offlineQueue.value = req.result || [];
        };
    } catch (e) {
        // Ignore
    }
};

const clearOfflineQueue = async () => {
    try {
        const db = await openDB();
        const tx = db.transaction('queue', 'readwrite');
        tx.objectStore('queue').clear();
        offlineQueue.value = [];
    } catch (e) {
        offlineQueue.value = [];
    }
};

// ==========================================
// Camera & QR Scanner Lifecycle
// ==========================================
const initCamera = async () => {
    cameraError.value = null;
    try {
        html5QrCode = new Html5Qrcode('qr-reader');
        const config = {
            fps: 15,
            qrbox: { width: 260, height: 260 },
            aspectRatio: 1.0,
        };

        await html5QrCode.start(
            { facingMode: 'environment' },
            config,
            (decodedText) => {
                handleQrScanned(decodedText);
            },
            () => {
                // Ignore frame-by-frame non-match
            }
        );

        isScanning.value = true;

        // Check torch capability
        try {
            const capabilities = html5QrCode.getRunningTrackCapabilities();
            hasTorch.value = !!(capabilities && capabilities.torch);
        } catch (e) {
            hasTorch.value = false;
        }
    } catch (err) {
        cameraError.value = 'Camera access denied or unavailable. You can use manual search fallback below.';
        isScanning.value = false;
    }
};

const stopCamera = async () => {
    if (html5QrCode && isScanning.value) {
        try {
            await html5QrCode.stop();
            await html5QrCode.clear();
        } catch (e) {
            // Ignore stop errors
        }
        isScanning.value = false;
    }
};

const toggleTorch = async () => {
    if (!html5QrCode || !hasTorch.value) return;
    try {
        isTorchOn.value = !isTorchOn.value;
        await html5QrCode.applyVideoConstraints({
            advanced: [{ torch: isTorchOn.value }],
        });
    } catch (e) {
        isTorchOn.value = false;
    }
};

// ==========================================
// Scan Processing
// ==========================================
let isProcessingScan = false;

const handleQrScanned = async (qrPayload) => {
    if (isProcessingScan || scanResult.value) return;
    isProcessingScan = true;

    // Check online status
    if (!navigator.onLine) {
        handleOfflineScan(qrPayload);
        isProcessingScan = false;
        return;
    }

    try {
        const response = await axios.post(
            route('api.scan'),
            {
                scanner_token: props.scanner.token,
                qr_payload: qrPayload,
                device_info: navigator.userAgent,
            },
            {
                headers: { 'X-Scanner-Token': props.scanner.token },
            }
        );

        const data = response.data;
        if (data.status === 'success') {
            triggerSuccess(data.guest?.name || 'Guest', data.guest?.party_size || 1);
        } else {
            triggerError(data.status, data.message);
        }
    } catch (err) {
        const errorData = err.response?.data || {};
        const status = errorData.status || (err.response?.status === 409 ? 'already_used' : 'invalid');
        const message = errorData.message || (status === 'already_used' ? 'Already checked in' : 'Invalid / wrong event');
        triggerError(status, message, errorData.guest);
    } finally {
        isProcessingScan = false;
    }
};

// Offline scan handler
const handleOfflineScan = (qrPayload) => {
    const parts = qrPayload.split(':');
    if (parts.length !== 5 || parts[0] !== 'INVITE') {
        triggerError('invalid', 'Invalid QR code format');
        return;
    }

    const eventId = parseInt(parts[1], 10);
    const guestId = parseInt(parts[2], 10);

    if (eventId !== props.event.id) {
        triggerError('wrong_event', 'QR code belongs to a different event');
        return;
    }

    // Check cached manifest
    const cachedGuest = offlineManifest.value.find((g) => g.id === guestId);
    const guestName = cachedGuest ? cachedGuest.name : `Guest #${guestId}`;
    const partySize = cachedGuest ? cachedGuest.party_size : 1;

    // Check if already in offline queue
    const alreadyQueued = offlineQueue.value.find((item) => item.qr_payload === qrPayload);
    if (alreadyQueued) {
        triggerError('already_used', `Already scanned locally at ${new Date(alreadyQueued.scanned_at).toLocaleTimeString()}`);
        return;
    }

    // Queue for sync
    const scanItem = {
        qr_payload: qrPayload,
        guest_name: guestName,
        party_size: partySize,
        scanned_at: new Date().toISOString(),
        device_info: navigator.userAgent,
    };
    queueOfflineScan(scanItem);

    triggerSuccess(guestName, partySize, true);
};

// ==========================================
// Result Overlays & Feedback
// ==========================================
const triggerSuccess = (name, partySize, isOffline = false) => {
    playSuccessSound();
    triggerHaptic('success');
    checkedInCount.value++;

    scanResult.value = {
        type: 'success',
        title: `Welcome, ${name}!`,
        message: isOffline ? 'Checked in offline (queued for sync)' : 'Admission Confirmed',
        party_size: partySize,
        guest_name: name,
        is_offline: isOffline,
    };

    startAutoReset();
};

const triggerError = (type, message, guest = null) => {
    playErrorSound();
    triggerHaptic('error');

    let title = 'Admission Denied';
    if (type === 'already_used') {
        title = 'Already Used!';
    } else if (type === 'wrong_event') {
        title = 'Wrong Event!';
    } else if (type === 'event_closed') {
        title = 'Event Closed!';
    }

    scanResult.value = {
        type: type === 'already_used' ? 'already_used' : 'invalid',
        title,
        message: message || 'Invalid / wrong event',
        party_size: guest?.party_size || null,
        guest_name: guest?.name || null,
    };

    startAutoReset();
};

const startAutoReset = () => {
    if (autoResetTimer.value) clearInterval(autoResetTimer.value);
    autoResetProgress.value = 100;

    const duration = 3000;
    const interval = 50;
    const step = (interval / duration) * 100;

    autoResetTimer.value = setInterval(() => {
        autoResetProgress.value -= step;
        if (autoResetProgress.value <= 0) {
            resetScanResult();
        }
    }, interval);
};

const resetScanResult = () => {
    if (autoResetTimer.value) {
        clearInterval(autoResetTimer.value);
        autoResetTimer.value = null;
    }
    scanResult.value = null;
    isProcessingScan = false;
};

// ==========================================
// Manual Fallback Search
// ==========================================
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);

const performSearch = async () => {
    if (!searchQuery.value || searchQuery.value.length < 2) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const res = await axios.get(route('api.scan.guests'), {
            params: {
                query: searchQuery.value,
                scanner_token: props.scanner.token,
            },
            headers: { 'X-Scanner-Token': props.scanner.token },
        });
        searchResults.value = res.data.guests || [];
    } catch (e) {
        // Fallback to searching offline manifest
        const q = searchQuery.value.toLowerCase();
        searchResults.value = offlineManifest.value
            .filter((g) => g.name.toLowerCase().includes(q) || (g.phone && g.phone.includes(q)))
            .slice(0, 15);
    } finally {
        isSearching.value = false;
    }
};

const manualCheckin = async (guest) => {
    if (guest.is_checked_in) {
        triggerError('already_used', `Already checked in at ${new Date(guest.checked_in_at).toLocaleTimeString()}`, guest);
        return;
    }

    try {
        const res = await axios.post(
            route('api.scan'),
            {
                scanner_token: props.scanner.token,
                guest_id: guest.id,
                device_info: navigator.userAgent,
            },
            {
                headers: { 'X-Scanner-Token': props.scanner.token },
            }
        );

        if (res.data.status === 'success') {
            guest.is_checked_in = true;
            triggerSuccess(guest.name, guest.party_size);
            activeTab.value = 'camera';
        } else {
            triggerError(res.data.status, res.data.message, guest);
        }
    } catch (err) {
        const errorData = err.response?.data || {};
        triggerError(errorData.status || 'invalid', errorData.message || 'Check-in failed', guest);
    }
};

// ==========================================
// Queue Syncing
// ==========================================
const syncOfflineScans = async () => {
    if (offlineQueue.value.length === 0 || isSyncing.value) return;
    isSyncing.value = true;
    try {
        const scans = offlineQueue.value.map((item) => ({
            qr_payload: item.qr_payload,
            scanned_at: item.scanned_at,
            device_info: item.device_info,
        }));

        const res = await axios.post(
            route('api.scan.sync'),
            {
                scanner_token: props.scanner.token,
                scans,
            },
            {
                headers: { 'X-Scanner-Token': props.scanner.token },
            }
        );

        if (res.data.status === 'ok') {
            await clearOfflineQueue();
            alert(`Synced ${res.data.synced} scan(s) successfully!`);
        }
    } catch (e) {
        alert('Failed to sync offline scans. Will retry when connection stabilizes.');
    } finally {
        isSyncing.value = false;
    }
};

// ==========================================
// Lifecycle Hooks & Listeners
// ==========================================
const handleOnlineStatus = () => {
    isOnline.value = navigator.onLine;
    if (isOnline.value && offlineQueue.value.length > 0) {
        syncOfflineScans();
    }
};

onMounted(async () => {
    window.addEventListener('online', handleOnlineStatus);
    window.addEventListener('offline', handleOnlineStatus);

    await loadOfflineQueue();

    // Fetch offline manifest for local validation
    try {
        const res = await axios.get(route('api.scan.offline-manifest'), {
            params: { scanner_token: props.scanner.token },
            headers: { 'X-Scanner-Token': props.scanner.token },
        });
        if (res.data?.guests) {
            await saveOfflineManifest(res.data.guests);
        }
    } catch (e) {
        // Ignore manifest load error
    }

    initCamera();
});

onUnmounted(() => {
    window.removeEventListener('online', handleOnlineStatus);
    window.removeEventListener('offline', handleOnlineStatus);
    stopCamera();
    if (autoResetTimer.value) clearInterval(autoResetTimer.value);
});

const logoutScanner = () => {
    router.post(route('scanner.logout', { token: props.scanner.token }));
};
</script>

<template>
    <Head :title="`Door Check-in • ${event.title}`" />

    <div class="h-screen w-screen bg-black text-white flex flex-col justify-between overflow-hidden select-none fixed inset-0 font-sans">
        <!-- Top App Bar -->
        <header class="p-4 bg-slate-900/90 backdrop-blur border-b border-slate-800 z-30 flex items-center justify-between">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-full bg-purple-600/30 border border-purple-500/50 flex items-center justify-center text-sm font-bold text-purple-300">
                    📷
                </div>
                <div>
                    <h1 class="text-sm font-bold text-white leading-tight truncate max-w-[180px] sm:max-w-xs">{{ event.title }}</h1>
                    <div class="text-[11px] text-slate-400 flex items-center space-x-1.5">
                        <span>{{ scanner.name }}</span>
                        <span>•</span>
                        <span :class="isOnline ? 'text-emerald-400 font-semibold' : 'text-amber-400 font-bold'">
                            {{ isOnline ? 'Online' : 'Offline' }}
                        </span>
                        <span v-if="offlineQueue.length > 0" class="px-1.5 py-0.2 bg-amber-500/30 text-amber-300 rounded text-[10px] font-bold">
                            {{ offlineQueue.length }} queued
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="flex items-center space-x-2">
                <button
                    v-if="hasTorch && activeTab === 'camera'"
                    type="button"
                    @click="toggleTorch"
                    class="p-2 rounded-xl border transition"
                    :class="isTorchOn ? 'bg-amber-400 text-black border-amber-300 shadow-lg shadow-amber-400/30' : 'bg-slate-800 text-slate-300 border-slate-700'"
                    title="Toggle Flashlight"
                >
                    💡
                </button>

                <button
                    v-if="offlineQueue.length > 0 && isOnline"
                    type="button"
                    @click="syncOfflineScans"
                    :disabled="isSyncing"
                    class="px-2.5 py-1.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold transition flex items-center space-x-1"
                >
                    <span>🔄 Sync</span>
                </button>

                <button
                    type="button"
                    @click="logoutScanner"
                    class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition"
                    title="Exit Scanner"
                >
                    Exit
                </button>
            </div>
        </header>

        <!-- Main View Area -->
        <main class="flex-1 relative overflow-hidden bg-slate-950 flex flex-col">
            <!-- CAMERA VIEW TAB -->
            <div v-show="activeTab === 'camera'" class="relative flex-1 flex flex-col items-center justify-center bg-black overflow-hidden">
                <!-- HTML5 QR Video Element -->
                <div id="qr-reader" class="w-full h-full max-w-md mx-auto overflow-hidden"></div>

                <!-- Camera Target Frame Overlay -->
                <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <div class="w-64 h-64 border-2 border-purple-500/70 rounded-3xl relative shadow-[0_0_0_9999px_rgba(0,0,0,0.55)]">
                        <!-- Corner Markers -->
                        <div class="absolute -top-1 -left-1 w-6 h-6 border-t-4 border-l-4 border-purple-400 rounded-tl-xl"></div>
                        <div class="absolute -top-1 -right-1 w-6 h-6 border-t-4 border-r-4 border-purple-400 rounded-tr-xl"></div>
                        <div class="absolute -bottom-1 -left-1 w-6 h-6 border-b-4 border-l-4 border-purple-400 rounded-bl-xl"></div>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 border-b-4 border-r-4 border-purple-400 rounded-br-xl"></div>

                        <!-- Scanning Line Animation -->
                        <div class="w-full h-0.5 bg-purple-400 absolute top-0 shadow-[0_0_12px_#c084fc] animate-scan-beam"></div>
                    </div>
                </div>

                <!-- Camera Instructions & Error -->
                <div class="absolute bottom-6 left-0 right-0 text-center px-4 pointer-events-none">
                    <div v-if="cameraError" class="p-3 bg-red-500/80 rounded-2xl text-xs text-white max-w-xs mx-auto shadow-lg pointer-events-auto">
                        {{ cameraError }}
                        <button
                            type="button"
                            @click="activeTab = 'search'"
                            class="block w-full mt-2 py-1 bg-white text-black font-bold rounded-lg text-xs"
                        >
                            Open Manual Guest Search
                        </button>
                    </div>
                    <div v-else class="text-xs text-slate-400 bg-slate-900/80 backdrop-blur px-4 py-1.5 rounded-full inline-block shadow-md border border-slate-800">
                        Align guest QR code inside target
                    </div>
                </div>
            </div>

            <!-- MANUAL SEARCH TAB -->
            <div v-show="activeTab === 'search'" class="flex-1 flex flex-col p-4 bg-slate-900 overflow-y-auto">
                <div class="mb-4">
                    <h2 class="text-sm font-bold text-white">Manual Guest Check-In</h2>
                    <p class="text-xs text-slate-400">Search guest by full name or phone number.</p>
                    <div class="mt-3 flex items-center space-x-2">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @input="performSearch"
                            placeholder="Type guest name or phone..."
                            class="flex-1 bg-slate-800 border-slate-700 text-white rounded-xl text-xs focus:ring-purple-500 focus:border-purple-500 placeholder-slate-500"
                        />
                        <button
                            type="button"
                            @click="performSearch"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl"
                        >
                            Search
                        </button>
                    </div>
                </div>

                <div v-if="isSearching" class="text-center py-8 text-xs text-slate-400">
                    Searching guest list...
                </div>

                <div v-else-if="searchResults.length === 0 && searchQuery.length >= 2" class="text-center py-8 text-xs text-slate-500">
                    No matching guests found.
                </div>

                <div v-else class="space-y-2 pb-6">
                    <div
                        v-for="guest in searchResults"
                        :key="guest.id"
                        class="p-3.5 bg-slate-800 rounded-2xl border border-slate-700 flex items-center justify-between"
                    >
                        <div>
                            <div class="font-bold text-sm text-white">{{ guest.name }}</div>
                            <div class="text-xs text-slate-400 flex items-center space-x-2 mt-0.5">
                                <span>Party of {{ guest.party_size }}</span>
                                <span v-if="guest.phone">• {{ guest.phone }}</span>
                            </div>
                        </div>

                        <div>
                            <button
                                v-if="!guest.is_checked_in"
                                type="button"
                                @click="manualCheckin(guest)"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-md transition active:scale-95"
                            >
                                Check In ✓
                            </button>
                            <span v-else class="px-3 py-1.5 bg-slate-700 text-slate-400 rounded-xl text-xs font-semibold">
                                Checked In
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STATS TAB -->
            <div v-show="activeTab === 'stats'" class="flex-1 p-4 bg-slate-900 overflow-y-auto space-y-4">
                <h2 class="text-sm font-bold text-white">Door Attendance Overview</h2>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-4 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-xs text-slate-400">Total Expected</div>
                        <div class="text-3xl font-extrabold text-white mt-1">{{ totalGuests }}</div>
                    </div>

                    <div class="p-4 bg-slate-800 rounded-2xl border border-slate-700">
                        <div class="text-xs text-slate-400">Admitted Guests</div>
                        <div class="text-3xl font-extrabold text-emerald-400 mt-1">{{ checkedInCount }}</div>
                    </div>
                </div>

                <div class="p-4 bg-slate-800 rounded-2xl border border-slate-700 space-y-2">
                    <div class="flex justify-between text-xs text-slate-400">
                        <span>Check-In Rate</span>
                        <span class="font-bold text-white">{{ totalGuests > 0 ? Math.round((checkedInCount / totalGuests) * 100) : 0 }}%</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-3 overflow-hidden">
                        <div
                            class="bg-emerald-500 h-full rounded-full transition-all duration-300"
                            :style="{ width: `${totalGuests > 0 ? Math.round((checkedInCount / totalGuests) * 100) : 0}%` }"
                        ></div>
                    </div>
                </div>

                <div class="p-4 bg-slate-800 rounded-2xl border border-slate-700 space-y-2">
                    <div class="text-xs font-bold text-white">Scanner Device Status</div>
                    <div class="text-xs text-slate-400 space-y-1">
                        <div>Connection: <strong :class="isOnline ? 'text-emerald-400' : 'text-amber-400'">{{ isOnline ? 'Online' : 'Offline' }}</strong></div>
                        <div>Offline Queue: <strong>{{ offlineQueue.length }} pending scan(s)</strong></div>
                        <div>Cached Manifest: <strong>{{ offlineManifest.length }} guests</strong></div>
                    </div>
                </div>
            </div>

            <!-- RESULT SCREEN OVERLAY (Large Color-Coded with Auto-Reset) -->
            <transition name="fade">
                <div
                    v-if="scanResult"
                    class="absolute inset-0 z-50 flex flex-col items-center justify-between p-6 text-center"
                    :class="{
                        'bg-emerald-600 text-white': scanResult.type === 'success',
                        'bg-red-600 text-white': scanResult.type === 'already_used' || scanResult.type === 'invalid',
                    }"
                >
                    <!-- Auto-reset progress bar at top -->
                    <div class="w-full bg-black/20 h-2 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-white/90 transition-all duration-75"
                            :style="{ width: `${autoResetProgress}%` }"
                        ></div>
                    </div>

                    <!-- Center Result Content -->
                    <div class="space-y-4 my-auto">
                        <div class="w-24 h-24 rounded-full mx-auto flex items-center justify-center text-5xl shadow-2xl bg-white/20 backdrop-blur-md">
                            <span v-if="scanResult.type === 'success'">✓</span>
                            <span v-else>✕</span>
                        </div>

                        <div>
                            <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                                {{ scanResult.title }}
                            </h2>

                            <p class="text-lg font-medium opacity-90 mt-2 max-w-sm mx-auto">
                                {{ scanResult.message }}
                            </p>
                        </div>

                        <!-- Party Size Badge for Admission -->
                        <div v-if="scanResult.type === 'success' && scanResult.party_size" class="pt-2">
                            <div class="inline-flex items-center space-x-2 px-5 py-2.5 bg-black/25 backdrop-blur-md rounded-2xl text-xl font-bold border border-white/30">
                                <span>👥 Party Size:</span>
                                <span class="text-2xl text-yellow-300 font-extrabold">{{ scanResult.party_size }}</span>
                            </div>
                        </div>

                        <div v-if="scanResult.guest_name && scanResult.type !== 'success'" class="text-sm font-semibold opacity-90">
                            Guest: {{ scanResult.guest_name }}
                        </div>
                    </div>

                    <!-- Reset Button -->
                    <div class="w-full max-w-xs pt-4">
                        <button
                            type="button"
                            @click="resetScanResult"
                            class="w-full py-4 bg-white text-black font-extrabold text-base rounded-2xl shadow-2xl active:scale-95 transition"
                        >
                            Next Guest →
                        </button>
                    </div>
                </div>
            </transition>
        </main>

        <!-- Bottom Navigation Bar (Tab switcher) -->
        <nav class="h-16 bg-slate-900 border-t border-slate-800 z-30 grid grid-cols-3">
            <button
                type="button"
                @click="activeTab = 'camera'"
                class="flex flex-col items-center justify-center transition"
                :class="activeTab === 'camera' ? 'text-purple-400 font-bold bg-slate-800/40' : 'text-slate-400 hover:text-slate-200'"
            >
                <span class="text-lg">📷</span>
                <span class="text-[10px] mt-0.5">Camera</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'search'"
                class="flex flex-col items-center justify-center transition"
                :class="activeTab === 'search' ? 'text-purple-400 font-bold bg-slate-800/40' : 'text-slate-400 hover:text-slate-200'"
            >
                <span class="text-lg">🔍</span>
                <span class="text-[10px] mt-0.5">Search</span>
            </button>

            <button
                type="button"
                @click="activeTab = 'stats'"
                class="flex flex-col items-center justify-center transition"
                :class="activeTab === 'stats' ? 'text-purple-400 font-bold bg-slate-800/40' : 'text-slate-400 hover:text-slate-200'"
            >
                <span class="text-lg">📊</span>
                <span class="text-[10px] mt-0.5">Stats ({{ checkedInCount }})</span>
            </button>
        </nav>
    </div>
</template>

<style scoped>
@keyframes scanBeam {
    0% { top: 0; }
    50% { top: 100%; }
    100% { top: 0; }
}
.animate-scan-beam {
    animation: scanBeam 2.5s infinite linear;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
