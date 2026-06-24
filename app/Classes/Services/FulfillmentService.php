<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Filament\Support\MarRecordDoseFormSchema;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Pharmacy\Classes\Services\DispenseService;

class FulfillmentService
{
    public function __construct(
        protected MedicationAdministrationService $medicationService,
        protected MedicationFulfillmentPolicy $policy,
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

        if ($item->service?->category?->code === ServiceCategoryCode::MED) {
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
            'payment_status' => $item->payment_status,
            'financial_hold' => $item->hasActiveFinancialHold(),
        ];
    }

    public function getFormSchema(RequestItem $item): array
    {
        $type = $this->getType($item);
        $context = $this->getContextInfo($item);

        $schema = [
            TextEntry::make('context')
                ->hiddenLabel()
                ->html()
                ->state(function () use ($context) {
                    return view('clinical::clinical.fulfillment-context', $context)->render();
                }),
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
            $schema[] = Textarea::make('notes')->label('Notes')->rows(3);
        } else {
            $schema[] = DateTimePicker::make('started_at')->label('Started At')->default(now());
            $schema[] = DateTimePicker::make('ended_at')->label('Ended At')->default(now());
            if ($type !== 'diagnostic') {
                $schema[] = Textarea::make('notes')->label('Notes')->rows(3);
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
        if ($item->prescriptionDetail?->isInFacility()) {
            return MarRecordDoseFormSchema::forSingleItem($item);
        }

        return MarRecordDoseFormSchema::dispenseFields($item);
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
        if ($item->prescriptionDetail?->isInFacility()) {
            $this->medicationService->administer($item, $data, $data['notes'] ?? null, $user);

            return;
        }

        if (isset($data['medication_id'])) {
            app(DispenseService::class)
                ->dispense($item, $data, $user);

            return;
        }

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
        if ($item->service?->category?->code === ServiceCategoryCode::MED || $item->prescriptionDetail) {
            throw new \InvalidArgumentException('Medication orders must be fulfilled via MAR or pharmacy dispense.');
        }

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
