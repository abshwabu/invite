<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Total Cards Consumed</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $this->getTotalCardsConsumed() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">SMS Cards</span>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $this->getSmsCards() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Email Cards</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $this->getEmailCards() }}</p>
        </div>
        <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Manual Adjustments</span>
            <p class="text-2xl font-bold text-purple-600 mt-1">{{ $this->getAdjustmentCards() }}</p>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
