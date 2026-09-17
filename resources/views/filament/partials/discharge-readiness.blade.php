{{-- Readiness checklist rendered inside the discharge form. Expects $readiness (array from DischargeReadiness::toArray()). --}}
@php
    $items = $readiness['items'] ?? [];
    $blocking = array_filter($items, fn ($i) => ! $i['passed'] && $i['severity'] === 'blocking');
    $styles = [
        'blocking' => 'text-danger-700 dark:text-danger-300',
        'warning' => 'text-warning-700 dark:text-warning-300',
        'info' => 'text-gray-600 dark:text-gray-300',
    ];
@endphp
<div class="rounded-lg border {{ $blocking === [] ? 'border-success-200 bg-success-50/60 dark:border-success-800 dark:bg-success-950/20' : 'border-danger-200 bg-danger-50/60 dark:border-danger-800 dark:bg-danger-950/20' }} p-3 text-sm">
    <div class="mb-2 font-semibold {{ $blocking === [] ? 'text-success-800 dark:text-success-200' : 'text-danger-800 dark:text-danger-200' }}">
        @if ($blocking === [])
            Ready for discharge
        @else
            {{ count($blocking) }} item(s) block discharge — resolve them or give an override reason
        @endif
    </div>
    <ul class="space-y-1">
        @foreach ($items as $item)
            <li class="flex items-start gap-2">
                @if ($item['passed'])
                    <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-success-500" />
                @elseif ($item['severity'] === 'blocking')
                    <x-heroicon-m-x-circle class="mt-0.5 h-4 w-4 shrink-0 text-danger-500" />
                @else
                    <x-heroicon-m-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0 text-warning-500" />
                @endif
                <span class="{{ $item['passed'] ? 'text-gray-600 dark:text-gray-300' : ($styles[$item['severity']] ?? $styles['info']) }}">
                    {{ $item['label'] }}
                    @if (! $item['passed'] && $item['detail'])
                        <span class="text-xs opacity-80">— {{ $item['detail'] }}</span>
                    @endif
                    @if (! $item['passed'] && $item['severity'] !== 'blocking')
                        <span class="text-xs opacity-60">({{ $item['severity'] }})</span>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
</div>
