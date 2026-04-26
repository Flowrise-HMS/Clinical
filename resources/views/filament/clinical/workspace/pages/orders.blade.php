<x-filament-panels::page>
    {{ $this->infolist() }}
    {{-- Orders Content --}}
    <div class="p-6 bg-gray-50 min-h-[calc(100vh-8rem)]">
        @if($currentPatient)
            <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Service Requests / Orders</h3>

                @if($serviceRequests->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($serviceRequests as $request)
                            <div class="border border-gray-200 rounded-lg overflow-hidden">
                                {{-- Request Header --}}
                                <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="font-medium text-gray-900">{{ $request->request_number ?? 'N/A' }}</span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            @if($request->priority?->value === 'emergency') bg-danger-100 text-danger-700
                                            @elseif($request->priority?->value === 'urgent') bg-warning-100 text-warning-700
                                            @else bg-gray-100 text-gray-700
                                            @endif">
                                            {{ $request->priority?->getLabel() ?? 'Normal' }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            @if($request->status?->value === 'completed') bg-success-100 text-success-700
                                            @elseif($request->status?->value === 'cancelled') bg-gray-100 text-gray-700
                                            @else bg-primary-100 text-primary-700
                                            @endif">
                                            {{ $request->status?->getLabel() ?? 'Pending' }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $request->created_at?->format('M d, Y H:i') ?? '' }}
                                    </div>
                                </div>

                                {{-- Request Items --}}
                                <div class="p-4">
                                    @if($request->items->isNotEmpty())
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-left text-xs text-gray-500 uppercase tracking-wide border-b">
                                                    <th class="pb-2 pr-4">Service</th>
                                                    <th class="pb-2 pr-4">Qty</th>
                                                    <th class="pb-2 pr-4">Status</th>
                                                    <th class="pb-2">Fulfilled By</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100">
                                                @foreach($request->items as $item)
                                                    <tr>
                                                        <td class="py-2 pr-4 text-gray-900">
                                                            {{ $item->service?->name ?? 'Unknown Service' }}
                                                            @if($item->serviceVariant)
                                                                <span class="text-gray-500">({{ $item->serviceVariant->name }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2 pr-4 text-gray-600">{{ $item->quantity }}</td>
                                                        <td class="py-2 pr-4">
                                                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                                                @if($item->status?->value === 'completed') bg-success-100 text-success-700
                                                                @elseif($item->status?->value === 'cancelled') bg-gray-100 text-gray-700
                                                                @else bg-warning-100 text-warning-700
                                                                @endif">
                                                                {{ $item->status?->getLabel() ?? 'Pending' }}
                                                            </span>
                                                        </td>
                                                        <td class="py-2 text-gray-500">
                                                            {{ $item->fulfilledBy?->name ?? '—' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <p class="text-gray-500 text-sm">No items in this request</p>
                                    @endif

                                    @if($request->notes)
                                        <div class="mt-3 pt-3 border-t">
                                            <p class="text-sm text-gray-600">{{ $request->notes }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <p>No service requests found</p>
                    </div>
                @endif
            </div>
        @else
            {{-- No Patient Selected --}}
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Patient Selected</h3>
                <p class="text-gray-500">Select a patient to view their orders</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
