<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\RequestItem;

class FulfillmentService
{
    public function __construct(
        protected MedicationAdministrationService $medicationService,
        protected TaskService $taskService,
        protected ?object $diagnosticService = null
    ) {
        $diagnosticClass = 'Modules\\Diagnostics\\Classes\\Services\\DiagnosticResultService';
        if (class_exists($diagnosticClass)) {
            $this->diagnosticService = app($diagnosticClass);
        }
    }

    public function getType(RequestItem $item): string
    {
        if ($item->prescriptionDetail !== null) {
            return 'medication';
        }

        if ($this->diagnosticService && $this->diagnosticService->getProfile($item) !== null) {
            return 'diagnostic';
        }

        return 'generic';
    }

    public function getContextInfo(RequestItem $item): array
    {
        $serviceRequest = $item->serviceRequest;
        $service = $item->service;
        $orderedBy = $serviceRequest?->orderedBy;

        return [
            'service_name' => $service?->name ?? 'Unknown',
            'category_name' => $service?->category?->name ?? '-',
            'ordered_by' => $orderedBy?->name ?? 'N/A',
            'ordered_at' => $serviceRequest?->created_at?->format('Y-m-d H:i') ?? 'N/A',
            'priority' => $serviceRequest?->priority?->getLabel() ?? '-',
            'status' => $item->status?->getLabel() ?? '-',
        ];
    }

    public function getFormSchema(RequestItem $item): array
    {
        $type = $this->getType($item);
        $context = $this->getContextInfo($item);

        $contextHtml = view('clinical::clinical.fulfillment-context', $context)->render();

        $schema = [
            TextEntry::make('context')
                ->hiddenLabel()
                ->html()
                ->state($contextHtml),
        ];

        if ($type === 'medication') {
            foreach ($this->getMedicationFormFields($item) as $field) {
                $schema[] = $field;
            }
        } elseif ($type === 'diagnostic') {
            $diagnosticFields = $this->diagnosticService?->getFormSchema($item) ?? $this->getGenericFormFields();
            foreach ($diagnosticFields as $field) {
                $schema[] = $field;
            }
        } else {
            foreach ($this->getGenericFormFields() as $field) {
                $schema[] = $field;
            }
        }

        if ($type === 'medication') {
            $schema[] = Textarea::make('notes')->label('Notes')->rows(2);
        } else {
            $schema[] = DateTimePicker::make('started_at')->label('Started At')->default(now());
            $schema[] = DateTimePicker::make('ended_at')->label('Ended At')->default(now());
            if ($type !== 'diagnostic') {
                $schema[] = Textarea::make('notes')->label('Notes')->rows(2);
            }
        }

        return $schema;
    }

    public function fulfill(RequestItem $item, array $data, ?User $user = null): void
    {
        $user = $user ?? Auth::user();
        $type = $this->getType($item);

        match ($type) {
            'medication' => $this->fulfillMedication($item, $data, $user),
            'diagnostic' => $this->fulfillDiagnostic($item, $data, $user),
            default => $this->fulfillGeneric($item, $data, $user),
        };
    }

    protected function getMedicationFormFields(RequestItem $item): array
    {
        $items = $this->medicationService->getPendingItems(
            $item->serviceRequest?->patient_id
        );

        return [
            Repeater::make('administrations')
                ->schema([
                    Hidden::make('request_item_id'),
                    Checkbox::make('selected')->default(true)->label(fn ($get) => $get('medication_info'))->inline(),
                    Hidden::make('medication_info'),
                    TimePicker::make('started_at')->default('08:00'),
                    TimePicker::make('ended_at')->default('08:00'),
                    TextInput::make('quantity_given')->numeric()->default(1)->minValue(1),
                ])
                ->columns(5)
                ->defaultItems(function () use ($items) {
                    return $items->map(function ($item) {
                        $detail = $item->prescriptionDetail;
                        $remaining = $this->medicationService->getRemainingDoses($item);

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
                        ];
                    })->toArray();
                })
                ->addable(false)
                ->reorderable(false)
                ->deletable(false),
        ];
    }

    protected function getGenericFormFields(): array
    {
        return [
            Repeater::make('results')
                ->label('Results')
                ->schema([
                    TextInput::make('key')->label('Field')->required(),
                    TextInput::make('value')->label('Value')->required(),
                ])
                ->columns(2)
                ->defaultItems(0),
        ];
    }

    protected function fulfillMedication(RequestItem $item, array $data, ?User $user): void
    {
        $result = $this->medicationService->administerBatch(
            $data['administrations'] ?? [],
            $data['notes'] ?? null,
            $user
        );

        if (! empty($result['errors'])) {
            throw new \RuntimeException(implode("\n", $result['errors']));
        }
    }

    protected function fulfillDiagnostic(RequestItem $item, array $data, ?User $user): void
    {
        if ($this->diagnosticService) {
            $this->diagnosticService->submit($item, $data, $user);
        }
    }

    protected function fulfillGeneric(RequestItem $item, array $data, ?User $user): void
    {
        DB::transaction(function () use ($item, $data, $user) {
            $task = $item->tasks()->create([
                'status' => TaskStatus::COMPLETED,
                'performed_by' => $user->id,
                'started_at' => $data['started_at'] ?? now(),
                'completed_at' => $data['ended_at'] ?? now(),
                'results' => $this->buildGenericResults($data),
                'notes' => $data['notes'] ?? null,
                'outcome' => TaskOutcome::COMPLETED,
            ]);

            if ($task->started_at && $task->completed_at) {
                $start = $task->started_at instanceof Carbon
                    ? $task->started_at : Carbon::parse($task->started_at);
                $end = $task->completed_at instanceof Carbon
                    ? $task->completed_at : Carbon::parse($task->completed_at);
                $task->update(['duration_minutes' => $start->diffInMinutes($end)]);
            }

            $item->markAsFulfilled($user->id);
        });
    }

    protected function buildGenericResults(array $data): array
    {
        $results = [];

        if (! empty($data['results'])) {
            foreach ($data['results'] as $row) {
                if (! empty($row['key'])) {
                    $results[$row['key']] = $row['value'];
                }
            }
        }

        return $results;
    }
}
