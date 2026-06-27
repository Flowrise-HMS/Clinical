<x-filament-widgets::widget>
    <x-filament::section heading="My Tasks ({{ $tasks->count() }}) Pending" icon="heroicon-m-clipboard-document-check">
        <div>
            @if($tasks->isNotEmpty())
                <div class="space-y-2">
                    @foreach($tasks->take(5) as $task)
                        <div class="flex items-start gap-3 p-2 rounded-lg hover:bg-gray-50 transition-colors group">
                            <input type="checkbox"
                                   wire:change="completeTask({{ $task->id }})"
                                   class="mt-1 rounded text-primary-600
                                          focus:ring-primary-500 cursor-pointer">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 group-hover:text-primary-700 transition-colors">
                                    {{ $task->title }}
                                </p>
                                <p class="text-xs text-gray-500 mt-0.5 truncate">
                                    {{ $task->clientIdentity()->displayName() }}
                                </p>
                            </div>
                            @if($task->due_date && \Carbon\Carbon::parse($task->due_date)->isPast())
                                <span class="flex-shrink-0 px-1.5 py-0.5 bg-danger-100 text-danger-700 text-xs rounded font-medium">
                                    Overdue
                                </span>
                            @endif
                        </div>
                    @endforeach

                    @if($tasks->count() > 5)
                        <p class="text-xs text-gray-500 text-center pt-2">
                            +{{ $tasks->count() - 5 }} more tasks
                        </p>
                    @endif
                </div>
            @else
                <div class="flex items-center justify-center text-center py-6 rounded-lg">
                    <x-heroicon-m-check-circle class="w-8 h-8 mx-auto text-success-400 mb-2" />
                    <p class="text-sm text-gray-600">All caught up!</p>
                    <p class="text-xs text-gray-400 mt-1">No pending tasks assigned to you</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
