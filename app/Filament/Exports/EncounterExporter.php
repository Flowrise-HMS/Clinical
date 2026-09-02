<?php

namespace Modules\Clinical\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Clinical\Models\Encounter;

class EncounterExporter extends Exporter
{
    protected static ?string $model = Encounter::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('encounter_number'),
            ExportColumn::make('patient.mrn'),
            ExportColumn::make('patient.first_name'),
            ExportColumn::make('patient.last_name'),
            ExportColumn::make('type'),
            ExportColumn::make('status'),
            ExportColumn::make('priority'),
            ExportColumn::make('coverage_type'),
            ExportColumn::make('chief_complaint'),
            ExportColumn::make('branch.name'),
            ExportColumn::make('department.name'),
            ExportColumn::make('location.name'),
            ExportColumn::make('admitted_at'),
            ExportColumn::make('discharged_at'),
            ExportColumn::make('discharge_disposition'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your encounter export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
