<x-filament-panels::page class="p-0 bg-gray-50 dark:bg-gray-950">
    <div class="min-h-screen">

        <div class="max-w-5xl mx-auto px-6 pt-8 pb-12">

            @if($currentPatient)

                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 p-16">

                    @if($this->getTimelineEvents()->isNotEmpty())
                        <!-- Timeline with events will go here later -->
                        <div class="text-center py-12 text-gray-400">
                            Timeline events will be displayed here
                        </div>
                    @else
                        {{-- Clean Empty State - Closer to your screenshot --}}
                        <div class="flex flex-col items-center justify-center py-16 text-center">

                            <!-- Large Clock Icon (Minimal & Clean) -->
                            <div class="mb-10">
                                <svg width="180" height="180" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="100" cy="100" r="82" stroke="#E5E7EB" stroke-width="28" stroke-linecap="round"/>
                                    <circle cx="100" cy="100" r="82" stroke="#9CA3AF" stroke-width="12" stroke-linecap="round"/>
                                    <line x1="100" y1="55" x2="100" y2="105" stroke="#6B7280" stroke-width="14" stroke-linecap="round"/>
                                    <line x1="100" y1="105" x2="140" y2="125" stroke="#6B7280" stroke-width="14" stroke-linecap="round"/>
                                </svg>
                            </div>

                            <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-3">
                                No timeline events yet
                            </h2>
                            <p class="text-gray-500 dark:text-gray-400 max-w-md text-base">
                                Events will appear here as care progresses for this patient.
                            </p>
                        </div>
                    @endif

                </div>

            @else
                <!-- No Patient Selected -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl p-20 text-center">
                    <x-heroicon-o-user-circle class="w-24 h-24 mx-auto text-gray-300 mb-6" />
                    <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Patient Selected</h2>
                    <p class="text-gray-500 mt-3">Please select a patient from the workspace to view their timeline.</p>
                </div>
            @endif

        </div>
    </div>
</x-filament-panels::page>
