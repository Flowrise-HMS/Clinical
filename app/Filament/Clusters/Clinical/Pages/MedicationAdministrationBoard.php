<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Filament\Clusters\Clinical\ClinicalCluster;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Services\BranchService;

class MedicationAdministrationBoard extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'MAR Board';

    protected static ?string $title = 'Medication Administration Board';

    protected static ?string $cluster = ClinicalCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        try {
            return app(\Modules\Core\Settings\FeatureSettings::class)->mar_board_enabled;
        } catch (\Throwable) {
            return true;
        }
    }

    protected string $view = 'clinical::clinical.pages.medication-administration-board';

    public function table(Table $table): Table
    {
        $policy = app(MedicationFulfillmentPolicy::class);
        $branchId = app(BranchService::class)->getDefaultBranchId();

        return $table
            ->query(
                RequestItem::query()
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'in_facility'))
                    ->when($branchId, fn (Builder $q) => $q->whereHas(
                        'serviceRequest',
                        fn (Builder $q) => $q->where('branch_id', $branchId)
                    ))
                    ->with(['serviceRequest.patient', 'service', 'prescriptionDetail', 'medicationAdministrations'])
            )
            ->defaultSort('prescriptionDetail.next_dose_at')
            ->columns([
                TextColumn::make('serviceRequest.patient.display_name')->label('Patient'),
                TextColumn::make('service.name')->label('Medication'),
                TextColumn::make('prescriptionDetail.frequency')->label('SIG'),
                TextColumn::make('remaining')
                    ->label('Doses left')
                    ->getStateUsing(fn (RequestItem $record) => $policy->countGivenDoses($record).'/'.($record->prescriptionDetail?->total_administrations ?? '∞')),
                TextColumn::make('prescriptionDetail.next_dose_at')
                    ->label('Next due')
                    ->dateTime('M j H:i')
                    ->sortable(),
                TextColumn::make('mar_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (RequestItem $record) {
                        $next = $record->prescriptionDetail?->next_dose_at;
                        if (! $next) {
                            return 'scheduled';
                        }
                        if ($next->isPast()) {
                            return 'overdue';
                        }
                        if ($next->lte(now()->addMinutes((int) config('clinical.mar_reminders.lead_minutes', 15)))) {
                            return 'due_soon';
                        }

                        return 'upcoming';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'overdue' => 'danger',
                        'due_soon' => 'warning',
                        'upcoming' => 'info',
                        default => 'gray',
                    }),
            ])
            ->poll('30s');
    }
}
