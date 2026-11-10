<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Control Bar: Search & Filters -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search Input -->
                <div>
                    <label for="ornament-search" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Search Ornaments
                    </label>
                    <div class="relative">
                        <input
                            id="ornament-search"
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by ID, name, Amharic, or tags..."
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-amber-500 focus:border-amber-500 pl-9"
                        />
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Set Filter Dropdown -->
                <div>
                    <label for="ornament-set-filter" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Filter by Set
                    </label>
                    <select
                        id="ornament-set-filter"
                        wire:model.live="setFilter"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                    >
                        <option value="all">All Sets ({{ count(\App\Services\OrnamentRegistry::all()) }})</option>
                        @foreach ($this->getSets() as $setName => $count)
                            <option value="{{ $setName }}">
                                {{ ucwords(str_replace('-', ' ', $setName)) }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Anchor Filter Dropdown -->
                <div>
                    <label for="ornament-anchor-filter" class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Filter by Anchor
                    </label>
                    <select
                        id="ornament-anchor-filter"
                        wire:model.live="anchorFilter"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-amber-500 focus:border-amber-500"
                    >
                        <option value="all">All Anchors</option>
                        @foreach ($this->getAnchors() as $anchorName => $count)
                            <option value="{{ $anchorName }}">
                                {{ ucfirst($anchorName) }} ({{ $count }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Tint Slots & Live Palette Controls -->
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <!-- Live Color Pickers -->
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Live Tint Tokens:
                        </span>

                        <!-- Slot c1 -->
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700">
                            <input
                                type="color"
                                wire:model.live="c1"
                                class="w-6 h-6 rounded cursor-pointer border-0 p-0 bg-transparent"
                                title="Primary Slot (c1)"
                            />
                            <div class="text-xs">
                                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">c1:</span>
                                <span class="font-mono text-gray-500 dark:text-gray-400 uppercase">{{ $c1 }}</span>
                            </div>
                        </div>

                        <!-- Slot c2 -->
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700">
                            <input
                                type="color"
                                wire:model.live="c2"
                                class="w-6 h-6 rounded cursor-pointer border-0 p-0 bg-transparent"
                                title="Accent Slot (c2)"
                            />
                            <div class="text-xs">
                                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">c2:</span>
                                <span class="font-mono text-gray-500 dark:text-gray-400 uppercase">{{ $c2 }}</span>
                            </div>
                        </div>

                        <!-- Slot c3 -->
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-900 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700">
                            <input
                                type="color"
                                wire:model.live="c3"
                                class="w-6 h-6 rounded cursor-pointer border-0 p-0 bg-transparent"
                                title="Shadow/Soft Slot (c3)"
                            />
                            <div class="text-xs">
                                <span class="font-mono font-medium text-gray-900 dark:text-gray-100">c3:</span>
                                <span class="font-mono text-gray-500 dark:text-gray-400 uppercase">{{ $c3 }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Presets & Preview Canvas Background -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Presets -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Presets:</span>
                            <button
                                type="button"
                                wire:click="applyPreset('royal-gold')"
                                class="text-xs px-2.5 py-1 rounded bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-300 dark:border-amber-800 transition"
                            >
                                Royal Gold
                            </button>
                            <button
                                type="button"
                                wire:click="applyPreset('emerald-copper')"
                                class="text-xs px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800 transition"
                            >
                                Emerald
                            </button>
                            <button
                                type="button"
                                wire:click="applyPreset('midnight-rose')"
                                class="text-xs px-2.5 py-1 rounded bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800 transition"
                            >
                                Midnight Rose
                            </button>
                            <button
                                type="button"
                                wire:click="applyPreset('classic-monochrome')"
                                class="text-xs px-2.5 py-1 rounded bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 transition"
                            >
                                Monochrome
                            </button>
                        </div>

                        <!-- Canvas Bg Switcher -->
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-900 p-1 rounded-lg border border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                wire:click="$set('previewBg', 'light')"
                                class="px-2 py-0.5 text-xs rounded {{ $previewBg === 'light' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-semibold shadow-sm' : 'text-gray-500 dark:text-gray-400' }}"
                            >
                                Light
                            </button>
                            <button
                                type="button"
                                wire:click="$set('previewBg', 'dark')"
                                class="px-2 py-0.5 text-xs rounded {{ $previewBg === 'dark' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-semibold shadow-sm' : 'text-gray-500 dark:text-gray-400' }}"
                            >
                                Dark
                            </button>
                            <button
                                type="button"
                                wire:click="$set('previewBg', 'checkered')"
                                class="px-2 py-0.5 text-xs rounded {{ $previewBg === 'checkered' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-semibold shadow-sm' : 'text-gray-500 dark:text-gray-400' }}"
                            >
                                Grid
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @php
            $ornaments = $this->getOrnaments();
        @endphp

        <!-- Results Counter & Info Bar -->
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-1">
            <div>
                Showing <span class="font-semibold text-gray-900 dark:text-gray-100">{{ count($ornaments) }}</span> of <span class="font-semibold text-gray-900 dark:text-gray-100">{{ count(\App\Services\OrnamentRegistry::all()) }}</span> ornaments
                @if ($setFilter !== 'all')
                    in set <span class="font-semibold text-amber-600 dark:text-amber-400">"{{ $setFilter }}"</span>
                @endif
                @if ($search !== '')
                    matching <span class="font-semibold text-amber-600 dark:text-amber-400">"{{ $search }}"</span>
                @endif
            </div>
            <div>
                All vector artwork rendered SVG live with active tint slots.
            </div>
        </div>

        <!-- Ornament Grid -->
        @if (count($ornaments) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                @foreach ($ornaments as $item)
                    @php
                        $bgClass = match ($previewBg) {
                            'dark' => 'bg-slate-900 border-slate-800 text-white',
                            'checkered' => 'bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] dark:bg-[radial-gradient(#374151_1px,transparent_1px)] [background-size:12px_12px] bg-slate-50 dark:bg-slate-900',
                            default => 'bg-[#FAF7F2] border-[#E8E1D5]',
                        };
                    @endphp
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden flex flex-col transition hover:shadow-md hover:border-amber-400 dark:hover:border-amber-500 group">
                        <!-- Preview Box -->
                        <div class="relative w-full h-48 p-4 flex items-center justify-center {{ $bgClass }} border-b border-gray-100 dark:border-gray-700/60 overflow-hidden">
                            @if (!empty($item['tinted_svg']))
                                <div class="w-full h-full flex items-center justify-center [&>svg]:max-h-full [&>svg]:max-w-full [&>svg]:w-auto [&>svg]:h-auto">
                                    {!! $item['tinted_svg'] !!}
                                </div>
                            @else
                                <div class="text-xs text-red-500 italic">SVG not available</div>
                            @endif

                            <!-- Floating Action Buttons -->
                            <div class="absolute top-2 right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <button
                                    type="button"
                                    wire:click="downloadSvg('{{ $item['id'] }}', true)"
                                    title="Download Tinted SVG"
                                    class="p-1.5 rounded-md bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-200 shadow hover:bg-amber-500 hover:text-white transition"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </button>
                                <button
                                    type="button"
                                    wire:click="downloadSvg('{{ $item['id'] }}', false)"
                                    title="Download Raw Original SVG"
                                    class="p-1.5 rounded-md bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-200 shadow hover:bg-gray-700 hover:text-white transition text-[10px] font-mono px-2"
                                >
                                    RAW
                                </button>
                            </div>

                            <!-- Set Badge -->
                            <span class="absolute bottom-2 left-2 text-[10px] font-medium uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/80 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 backdrop-blur-sm border border-gray-200/50 dark:border-gray-700/50">
                                {{ $item['set'] }}
                            </span>

                            <!-- Anchor Badge -->
                            <span class="absolute bottom-2 right-2 text-[10px] font-mono px-1.5 py-0.5 rounded bg-gray-900/60 text-white backdrop-blur-sm">
                                {{ $item['anchor'] }}
                            </span>
                        </div>

                        <!-- Card Info & Metadata -->
                        <div class="p-4 flex-1 flex flex-col justify-between space-y-3">
                            <div>
                                <div class="font-mono text-xs font-bold text-gray-900 dark:text-gray-100 truncate" title="{{ $item['id'] }}">
                                    {{ $item['id'] }}
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-300 font-medium truncate mt-0.5" title="{{ $item['name_en'] }}">
                                    {{ $item['name_en'] }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 font-amharic truncate" title="{{ $item['name_am'] }}">
                                    {{ $item['name_am'] }}
                                </div>
                            </div>

                            <!-- Dimension & Aspect Details -->
                            <div class="pt-2 border-t border-gray-100 dark:border-gray-700/50 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                <span>{{ $item['viewBox'] }}</span>
                                <span>asp: {{ $item['defaultAspect'] }}</span>
                            </div>

                            <!-- Tags -->
                            @if (!empty($item['tags']))
                                <div class="flex flex-wrap gap-1 pt-1">
                                    @foreach (array_slice($item['tags'], 0, 4) as $tag)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                            #{{ $tag }}
                                        </span>
                                    @endforeach
                                    @if (count($item['tags']) > 4)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-50 dark:bg-gray-800 text-gray-400">
                                            +{{ count($item['tags']) - 4 }}
                                        </span>
                                    @endif
                                </div>
                            @endif

                            <!-- Bottom Download Button -->
                            <div class="pt-2">
                                <button
                                    type="button"
                                    wire:click="downloadSvg('{{ $item['id'] }}', true)"
                                    class="w-full text-xs font-medium py-1.5 px-3 rounded-lg bg-amber-50 text-amber-800 hover:bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300 dark:hover:bg-amber-900/60 border border-amber-200 dark:border-amber-800 transition flex items-center justify-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download SVG
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- Empty State -->
            <div class="p-12 text-center bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                <div class="w-12 h-12 mx-auto rounded-full bg-amber-50 dark:bg-amber-950 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">No ornaments match your filter</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Try adjusting your search terms or clearing the set/anchor filters to browse the full library.
                </p>
                <button
                    type="button"
                    wire:click="$set('search', ''); $set('setFilter', 'all'); $set('anchorFilter', 'all')"
                    class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-medium transition"
                >
                    Reset All Filters
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
