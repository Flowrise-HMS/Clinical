<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\CarePlanResource;
use Modules\Clinical\Models\CarePlan;

class ViewCarePlan extends ViewRecord
{
    protected static string $resource = CarePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printPdf')
                ->label('Print / PDF')
                ->icon('heroicon-m-printer')
                ->url(fn (CarePlan $record): string => route('clinical.care-plans.pdf', $record))
                ->openUrlInNewTab()
                ->visible(fn (CarePlan $record): bool => auth()->user()?->can('view', $record) ?? false),
        ];
    }
}
