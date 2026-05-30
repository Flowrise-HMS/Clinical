<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Classes\Services\BranchService;

class PendingFulfillmentsWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected int $sorting = 3;

    protected int|string|array $columnSpan = 'full';

    public ?string $patientId = null;

    protected function getTableQuery(): Builder
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();
        $user = Auth::user();

        return RequestItem::query()
            ->when($this->patientId, fn ($q) => $q->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $this->patientId)))
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn (Builder $q) => $q->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId)))
            ->where(fn (Builder $q) => $q->whereDoesntHave('service.roles')
                ->orWhereHas('service.roles', fn (Builder $q) => $q->whereIn('name', $user->getRoleNames()->toArray())))
            ->with([
                'serviceRequest.patient',
                'serviceRequest.orderedBy',
                'service.category',
                'prescriptionDetail',
                'medicationAdministrations' => fn ($q) => $q->latest(),
            ])
            ->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('#')->rowIndex(),
            TextColumn::make('serviceRequest.patient.display_name')
                ->label('Patient')
                ->sortable(),
            TextColumn::make('service.name')
                ->label('Service')
                ->searchable()
                ->sortable(),
            TextColumn::make('service.category.name')
                ->label('Category')
                ->badge(),
            TextColumn::make('serviceRequest.orderedBy.name')
                ->label('Ordered By')
                ->sortable(),
            TextColumn::make('serviceRequest.created_at')
                ->label('Ordered At')
                ->since()
                ->sortable(),
            TextColumn::make('serviceRequest.priority')
                ->label('Priority')
                ->colors([
                    'danger' => 'emergency',
                    'warning' => 'urgent',
                    'primary' => 'routine',
                    'gray' => 'low',
                ])
                ->badge(),
            TextColumn::make('status')
                ->colors([
                    'warning' => 'pending',
                    'primary' => 'in_progress',
                    'success' => 'completed',
                    'gray' => 'cancelled',
                ])->badge(),
            TextColumn::make('remaining')
                ->label('Remaining')
                ->getStateUsing(function (RequestItem $record) {
                    $detail = $record->prescriptionDetail;
                    if (! $detail || ! $detail->total_administrations) {
                        return null;
                    }
                    $given = $record->medicationAdministrations()->sum('quantity_given');

                    return max(0, $detail->total_administrations - $given).'/'.$detail->total_administrations;
                })
                ->visible(fn ($record): bool => $record?->prescriptionDetail !== null),
            TextColumn::make('payment_status')
                ->label('Payment')
                ->badge()
                ->color(fn (RequestItem $record): string => $record->payment_status?->getColor() ?? 'gray')
                ->formatStateUsing(fn (RequestItem $record): string => $record->payment_status?->getLabel() ?? '—'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('fulfill')
                ->label(fn (RequestItem $record): string => $this->getActionLabel($record))
                ->icon(fn (RequestItem $record): string => $this->getActionIcon($record))
                ->color(fn (RequestItem $record): string => $this->getActionColor($record))
                ->button()
                ->visible(fn (RequestItem $record): bool => ! $record->isTerminal())
                ->modalHeading(fn (RequestItem $record): string => $this->getModalHeading($record))
                ->modalSubmitActionLabel(fn (RequestItem $record): string => $this->getSubmitLabel($record))
                ->schema(fn (RequestItem $record): array => $this->getFormSchema($record))
                ->action(fn (array $data, RequestItem $record) => $this->handleFulfillment($data, $record)),
        ];
    }

    protected function getActionLabel(RequestItem $record): string
    {
        return match (app(FulfillmentService::class)->getType($record)) {
            'medication' => 'Administer',
            'diagnostic' => 'Record Results',
            default => 'Fulfill',
        };
    }

    protected function getActionIcon(RequestItem $record): string
    {
        return match (app(FulfillmentService::class)->getType($record)) {
            'medication' => 'heroicon-m-beaker',
            'diagnostic' => 'heroicon-m-clipboard-document-check',
            default => 'heroicon-m-check-circle',
        };
    }

    protected function getActionColor(RequestItem $record): string
    {
        return match (app(FulfillmentService::class)->getType($record)) {
            'medication' => 'success',
            'diagnostic' => 'primary',
            default => 'success',
        };
    }

    protected function getModalHeading(RequestItem $record): string
    {
        $type = app(FulfillmentService::class)->getType($record);
        $patientName = $record->serviceRequest?->patient?->full_name ?? 'Unknown';

        return match ($type) {
            'medication' => "Administer Medications — {$patientName}",
            'diagnostic' => "Record Results — {$record->service?->name}",
            default => "Fulfill — {$record->service?->name}",
        };
    }

    protected function getSubmitLabel(RequestItem $record): string
    {
        return match (app(FulfillmentService::class)->getType($record)) {
            'medication' => 'Administer Selected',
            'diagnostic' => 'Submit Results',
            default => 'Mark Fulfilled',
        };
    }

    protected function getFormSchema(RequestItem $record): array
    {
        $fulfillmentService = app(FulfillmentService::class);

        if ($fulfillmentService->getType($record) === 'medication') {
            $patientId = $record->serviceRequest?->patient_id;
            $medicationService = app(MedicationAdministrationService::class);
            $items = $medicationService->getPendingItems($patientId);
            return [


                Repeater::make('administrations')
                    ->schema([
                        Hidden::make('request_item_id'),
                        Checkbox::make('selected')
                            ->default(true)
                            ->label(fn ($get) => $get('medication_info'))
                            ->inline(),
                        Hidden::make('medication_info'),
                        TimePicker::make('started_at')->default('08:00'),
                        TimePicker::make('ended_at')->default('08:00'),
                        TextInput::make('quantity_given')
                            ->numeric()
                            ->default(1)
                            ->minValue(1),
                        Select::make('dose_unit_id')
                            ->label('Dose Unit')
                            ->options(fn () => \Modules\Core\Models\Unit::pluck('label', 'id'))
                            ->searchable()
                            ->placeholder('Select unit'),
                    ])
                    ->columns(6)
                    ->defaultItems(function () use ($items) {
                        return $items->map(function ($item) {
                            $detail = $item->prescriptionDetail;
                            $remaining = app(MedicationAdministrationService::class)
                                ->getRemainingDoses($item);

                            return [
                                'request_item_id' => $item->id,
                                'selected' => true,
                                'medication_info' => $item->service?->name
                                    .' ('.($detail?->dosage ?? '').' '.($detail?->route ?? '').')'
                                    .' — '.($detail?->frequency ?? '')
                                    .' ['.$remaining.' remaining]',
                                'started_at' => '08:00',
                                'ended_at' => '08:00',
                                'quantity_given' => 1,
                                'dose_unit_id' => $detail?->dose_unit_id,
                            ];
                        })->toArray();
                    })
                    ->addable(false)
                    ->reorderable(false)
                    ->deletable(false),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ];
        }

        return $fulfillmentService->getFormSchema($record);
    }

    protected function handleFulfillment(array $data, RequestItem $record): void
    {
        $fulfillmentService = app(FulfillmentService::class);
        $type = $fulfillmentService->getType($record);

        try {
            if ($type === 'medication') {
                $result = app(MedicationAdministrationService::class)
                    ->administerBatch($data['administrations'] ?? [], $data['notes'] ?? null);

                if (! empty($result['created'])) {
                    Notification::make()
                        ->title('Medications administered')
                        ->body(implode(', ', $result['created']))
                        ->success()
                        ->send();
                }

                if (! empty($result['errors'])) {
                    Notification::make()
                        ->title('Some items could not be administered')
                        ->body(implode("\n", $result['errors']))
                        ->danger()
                        ->persistent()
                        ->send();
                }
            } else {
                $fulfillmentService->fulfill($record, $data);

                Notification::make()
                    ->title($record->service?->name.' fulfilled successfully')
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Fulfillment failed')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getTableHeaderActions(): array
    {
        return [];
    }

    protected function getTablePollingInterval(): ?string
    {
        return '30s';
    }
}
