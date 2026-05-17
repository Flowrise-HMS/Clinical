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
</div>
