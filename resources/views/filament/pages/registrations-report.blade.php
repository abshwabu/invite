<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Users</div>
            <div class="text-3xl font-extrabold text-gray-900 dark:text-white mt-2">{{ number_format($this->getTotalRegistrations()) }}</div>
            <div class="text-xs text-gray-400 mt-1">All-time registrations</div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Today</div>
            <div class="text-3xl font-extrabold text-primary-600 dark:text-primary-400 mt-2">{{ number_format($this->getTodayRegistrations()) }}</div>
            <div class="text-xs text-gray-400 mt-1">Registered today</div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-info-600 dark:text-info-400">Past 7 Days</div>
            <div class="text-3xl font-extrabold text-info-600 dark:text-info-400 mt-2">{{ number_format($this->getThisWeekRegistrations()) }}</div>
            <div class="text-xs text-gray-400 mt-1">Last 7 days signups</div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-success-600 dark:text-success-400">Past 30 Days</div>
            <div class="text-3xl font-extrabold text-success-600 dark:text-success-400 mt-2">{{ number_format($this->getThisMonthRegistrations()) }}</div>
            <div class="text-xs text-gray-400 mt-1">Last 30 days signups</div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
