<div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-4 space-y-1 text-sm">
    <div class="flex justify-between">
        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $service_name ?? 'Unknown Service' }}</span>
        <span class="text-gray-500">{{ $category_name ?? '' }}</span>
    </div>
    <div class="flex justify-between text-gray-600 dark:text-gray-400">
        <span>Ordered by: <strong>{{ $ordered_by ?? 'N/A' }}</strong></span>
        <span>{{ $ordered_at ?? '' }}</span>
    </div>
    @if(!empty($priority) || !empty($status))
        <div class="flex justify-between text-gray-500 dark:text-gray-400">
            <span>Priority: {{ $priority }}</span>
            <span>Status: {{ $status }}</span>
        </div>
    @endif
    <div class="flex justify-between items-center">
        <span class="text-gray-500 dark:text-gray-400">Payment:</span>
        @php
            $ps = $payment_status ?? null;
            $badgeColors = [
                'paid' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'partial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'unpaid' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'void' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            ];
            $color = $ps ? ($badgeColors[$ps->value] ?? 'bg-gray-100 text-gray-600') : 'bg-gray-100 text-gray-600';
        @endphp
        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
            {{ $ps ? $ps->getLabel() : 'N/A' }}
        </span>
    </div>
    @if($ps && $ps->value === 'unpaid')
        <div class="mt-2 p-2 rounded bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300 text-xs">
            This service has not been paid yet. Please confirm payment before proceeding.
        </div>
    @endif
</div>
