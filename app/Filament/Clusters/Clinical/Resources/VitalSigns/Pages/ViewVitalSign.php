<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;

class ViewVitalSign extends ViewRecord
{
    protected static string $resource = VitalSignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('activities')
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn () => VitalSignResource::getUrl('activities', ['record' => $this->getRecord()])),
        ];
    }
}
