<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\Task;
use Modules\Clinical\Models\VitalSign;
use Modules\Clinical\Policies\ServiceRequestPolicy;
use Modules\Clinical\Policies\TaskPolicy;
use Modules\Patient\Models\Patient;

class ClinicalWorkspaceService
{
    protected ?Patient $currentPatient = null;

    protected ?Encounter $currentEncounter = null;

    public function setPatient(?Patient $patient): self
    {
        $this->currentPatient = $patient;
        if ($patient) {
            $this->currentEncounter = $patient->activeEncounter;
        }

        return $this;
    }

    public function setEncounter(?Encounter $encounter): self
    {
        $this->currentEncounter = $encounter;
        if ($encounter) {
            $this->currentPatient = $encounter->patient;
        }

        return $this;
    }

    public function getPatient(): ?Patient
    {
        return $this->currentPatient;
    }

    public function getEncounter(): ?Encounter
    {
        return $this->currentEncounter;
    }

    public function getTimelineEvents(int $limit = 50)
    {
        if (! $this->currentPatient) {
            return null;
        }

        $events = collect();
        $patientId = $this->currentPatient->id;
        $encounterId = $this->currentEncounter?->id;

        $query = Encounter::query()
            ->where('patient_id', $patientId)
            ->when($encounterId, fn ($q) => $q->where('id', $encounterId))
            ->orderBy('admitted_at', 'desc')
            ->limit($limit);

        foreach ($query->get() as $encounter) {
            $events->push($this->createEncounterEvent($encounter));
        }

        $vitalsQuery = VitalSign::query()
            ->where('patient_id', $patientId)
            ->when($encounterId, fn ($q) => $q->where('encounter_id', $encounterId))
            ->orderBy('recorded_at', 'desc')
            ->limit($limit);

        foreach ($vitalsQuery->get() as $vital) {
            $events->push($this->createVitalSignEvent($vital));
        }

        $notesQuery = ClinicalNote::query()
            ->where('patient_id', $patientId)
            ->when($encounterId, fn ($q) => $q->where('encounter_id', $encounterId))
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        foreach ($notesQuery->get() as $note) {
            $events->push($this->createNoteEvent($note));
        }

        $ordersQuery = ServiceRequest::query()
            ->where('patient_id', $patientId)
            ->when($encounterId, fn ($q) => $q->where('encounter_id', $encounterId))
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        foreach ($ordersQuery->get() as $order) {
            $events->push($this->createOrderEvent($order));
        }

        return $events?->sortByDesc('occurred_at')?->take($limit)?->values() ?? collect();
    }

    protected function createEncounterEvent(Encounter $encounter): array
    {
        $statusLabel = $encounter->status?->getLabel() ?? 'Active';

        return [
            'id' => 'encounter_'.$encounter->id,
            'type' => 'encounter',
            'icon' => 'heroicon-o-user-plus',
            'title' => 'Patient Encounter - '.$statusLabel,
            'description' => ucfirst($encounter->type?->getLabel() ?? 'Unknown').' encounter started',
            'occurred_at' => $encounter->admitted_at,
            'creator' => $encounter->admittedBy?->full_name,
            'is_critical' => $encounter->type?->value === 'emergency',
            'metadata' => [
                'Status' => $statusLabel,
                'Type' => $encounter->type?->getLabel() ?? 'Unknown',
                'Location' => $encounter->location?->name ?? 'Not assigned',
            ],
            'is_editable' => Auth::user()->can('update', $encounter),
        ];
    }

    protected function createVitalSignEvent(VitalSign $vital): array
    {
        $metadata = [];
        if ($vital->systolic_bp && $vital->diastolic_bp) {
            $metadata['BP'] = "{$vital->systolic_bp}/{$vital->diastolic_bp}";
        }
        if ($vital->heart_rate) {
            $metadata['HR'] = $vital->heart_rate.' bpm';
        }
        if ($vital->temperature) {
            $metadata['Temp'] = $vital->temperature.'°C';
        }
        if ($vital->spo2) {
            $metadata['SpO2'] = $vital->spo2.'%';
        }
        if ($vital->respiratory_rate) {
            $metadata['RR'] = $vital->respiratory_rate;
        }

        return [
            'id' => 'vital_'.$vital->id,
            'type' => 'vitals',
            'icon' => 'heroicon-o-heart',
            'title' => 'Vital Signs Recorded',
            'description' => 'Routine vital signs check',
            'occurred_at' => $vital->recorded_at,
            'creator' => $vital->recordedBy?->full_name,
            'is_critical' => $vital->isAbnormalBloodPressure() || $vital->isLowOxygenSaturation(),
            'metadata' => $metadata,
            'is_editable' => Auth::user()->can('update', $vital),
        ];
    }

    protected function createNoteEvent(ClinicalNote $note): array
    {
        $noteType = $note->note_type?->getLabel() ?? 'Clinical';
        $content = is_array($note->content) ? ($note->content['text'] ?? '') : ($note->content ?? '');

        return [
            'id' => 'note_'.$note->id,
            'type' => 'note',
            'icon' => 'heroicon-o-clipboard-document',
            'title' => $noteType.' Note',
            'description' => Str::limit(strip_tags($content), 100),
            'occurred_at' => $note->created_at,
            'creator' => $note->author?->full_name,
            'is_critical' => false,
            'metadata' => [
                'Type' => $noteType,
                'Status' => $note->isSigned() ? 'Signed' : 'Draft',
            ],
            'is_editable' => ! $note->isSigned() && Auth::user()->can('update', $note),
        ];
    }

    protected function createOrderEvent(ServiceRequest $order): array
    {
        $itemCount = $order->items()->count();

        return [
            'id' => 'order_'.$order->id,
            'type' => 'order',
            'icon' => 'heroicon-o-arrow-up-circle',
            'title' => 'Service Request Created',
            'description' => ucfirst($order->type?->getLabel() ?? 'Service').' order with '.$itemCount.' item(s)',
            'occurred_at' => $order->created_at,
            'creator' => $order->orderedBy?->full_name,
            'is_critical' => $order->priority?->value === 'emergency',
            'metadata' => [
                'Type' => $order->type?->getLabel() ?? 'Unknown',
                'Priority' => $order->priority?->getLabel() ?? 'Normal',
                'Status' => $order->status?->getLabel() ?? 'Pending',
            ],
            'is_editable' => Auth::user()->can('update', $order),
        ];
    }

    public function getCriticalPatients()
    {
        $user = Auth::user();

        $query = Patient::query()
            ->whereHas('encounters', function ($q) {
                $q->where('status', 'in_progress')
                    ->where('type', 'emergency');
            })
            ->with(['latestEncounter', 'latestVitals']);

        if (! $user->can('view_all_patients')) {
            $query->whereHas('encounters.participants', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->limit(10)->get();
    }

    public function getRecentPatients(int $limit = 5)
    {
        $user = Auth::id();

        return Patient::query()
            ->whereHas('encounters', function ($query) use ($user) {
                $query->where(function ($q) use ($user) {
                    $q->where('status', 'in_progress')
                        ->orWhereHas('participants', function ($q) use ($user) {
                            $q->where('user_id', $user);
                        });
                });
            })
            ->with(['latestEncounter', 'latestVitals'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function getMyPendingTasks()
    {
        $user = Auth::user();

        $query = Task::query()
            ->whereIn('status', [TaskStatus::PENDING, TaskStatus::IN_PROGRESS])
            ->where(function ($q) use ($user) {
                $q->where('performed_by', $user->id)
                    ->orWhereNull('performed_by');
            })
            ->with(['requestItem.service', 'requestItem.serviceRequest.patient']);

        if (! $user->can('view_all_patients')) {
            $query->whereHas('requestItem.serviceRequest.patient.encounters.participants', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        return $query->limit(20)->get();
    }

    public function getEncounterThreads()
    {
        if (! $this->currentPatient) {
            return null;
        }

        return $this->currentPatient->encounters()
            ->orderBy('admitted_at', 'desc')
            ->get()
            ->groupBy(fn ($e) => $e->type?->getLabel() ?? 'Other');
    }

    public function getLatestVitals(): ?VitalSign
    {
        if (! $this->currentPatient) {
            return null;
        }

        return $this->currentPatient->latestVitals;
    }

    public function canViewPatient(Patient $patient): bool
    {
        $user = Auth::user();

        if ($user->can('view_all_patients')) {
            return true;
        }

        if ($user->can('view_any_patient')) {
            return true;
        }

        return $patient->encounters()
            ->where('status', 'in_progress')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    public function canCreateClinicalNote(): bool
    {
        return Auth::user()->can('create', ClinicalNote::class);
    }

    public function canCreateVitalSign(): bool
    {
        return Auth::user()->can('create', VitalSign::class);
    }

    public function canCreateServiceRequest(): bool
    {
        return app(ServiceRequestPolicy::class)->create(Auth::user());
    }

    public function canCreateTask(): bool
    {
        return app(TaskPolicy::class)->create(Auth::user());
    }

    public function canDischargePatient(): bool
    {
        return Auth::user()->can('can_discharge');
    }

    public function canPrescribe(): bool
    {
        return Auth::user()->can('can_prescribe');
    }

    public function canViewDeceasedPatient(): bool
    {
        return Auth::user()->can('can_view_deceased');
    }
}
