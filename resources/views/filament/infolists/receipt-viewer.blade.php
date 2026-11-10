@php
    $record = $getRecord();
    $receiptUrl = $record ? $record->getReceiptUrl() : null;
@endphp

@if ($receiptUrl)
    <div class="space-y-3">
        <div class="relative max-w-md rounded-lg overflow-hidden border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 p-2 shadow-sm">
            <a href="{{ $receiptUrl }}" target="_blank" rel="noopener noreferrer" class="group block relative cursor-zoom-in">
                <img
                    src="{{ $receiptUrl }}"
                    alt="Receipt Screenshot for {{ $record->payment_code }}"
                    class="w-full max-h-[480px] object-contain rounded transition duration-200 group-hover:scale-[1.02]"
                    loading="lazy"
                />
                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity rounded">
                    <span class="px-3 py-1.5 bg-white/90 text-gray-900 text-xs font-semibold rounded shadow">
                        Open Full Size (Zoom)
                    </span>
                </div>
            </a>
        </div>
        <div class="text-xs text-gray-500">
            Hash: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ $record->receipt_hash ?: 'N/A' }}</code>
            @if ($record->receipt_phash)
                • Perceptual Hash: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ $record->receipt_phash }}</code>
            @endif
        </div>
    </div>
@else
    <div class="text-sm text-gray-500 italic p-3 bg-gray-50 dark:bg-gray-800 rounded">
        No receipt screenshot was uploaded for this payment. Verification relies on the transaction reference number.
    </div>
@endif
