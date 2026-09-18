<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Support\DischargeReadiness;
use Modules\Core\Contracts\PatientFinancialHoldChecker;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Support\ModuleAvailability;

/**
 * Checks whether an inpatient can safely leave. Each check's severity comes
 * from `clinical.discharge.readiness` so a facility can decide what blocks
 * and what merely warns. Optional peers (Pharmacy, Diagnostics, Billing,
 * Appointment) are only consulted when present.
 */
class DischargeReadinessService
{
    public function __construct(
        protected ClinicalWorkspaceService $workspaceService,
    ) {}

    /**
     * @param  array{follow_up_at?: ?string, has_signed_summary?: bool}  $context
     */
    public function assess(Encounter $encounter, array $context = []): DischargeReadiness
    {
        $items = [];

        $pendingItems = RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn ($q) => $q->where('encounter_id', $encounter->id))
            ->with(['service.category', 'serviceRequest:id,encounter_id'])
            ->get();

        if (ModuleAvailability::pharmacyEnabled()) {
            $pendingItems->loadMissing('prescriptionDetail');

            $inFacility = $pendingItems->filter(fn (RequestItem $item): bool => $this->context($item) === 'in_facility');
            $takeHome = $pendingItems->filter(fn (RequestItem $item): bool => $this->context($item) === 'take_home');

            $items[] = $this->item('pending_medication_doses', __('Scheduled doses still pending'), $inFacility->isEmpty(), $this->names($inFacility));
            $items[] = $this->item('undispensed_take_home_meds', __('Take-home medications not dispensed'), $takeHome->isEmpty(), $this->names($takeHome));
        } else {
            $items[] = $this->item('pending_medication_doses', __('Scheduled doses still pending'), true, null);
            $items[] = $this->item('undispensed_take_home_meds', __('Take-home medications not dispensed'), true, null);
        }

        $diagnostics = $pendingItems->filter(function (RequestItem $item): bool {
            $code = $item->service?->category?->code;
            $code = is_object($code) && isset($code->value) ? $code->value : $code;

            return in_array($code, [ServiceCategoryCode::LAB->value, ServiceCategoryCode::RAD->value], true);
        });

        $items[] = $this->item('pending_diagnostics', __('Lab or imaging results outstanding'), $diagnostics->isEmpty(), $this->names($diagnostics));

        $financialHold = false;

        if (ModuleAvailability::billingEnabled() && app()->bound(PatientFinancialHoldChecker::class) && $encounter->patient_id) {
            try {
                $financialHold = app(PatientFinancialHoldChecker::class)->requiresFinancialHold($encounter->patient_id, $encounter->id);
            } catch (\Throwable) {
                $financialHold = false;
            }
        }

        $items[] = $this->item('financial_hold', __('Outstanding balance on hold'), ! $financialHold, $financialHold ? __('Unpaid items require settlement') : null);

        $draftNotes = ClinicalNote::query()
            ->where('encounter_id', $encounter->id)
            ->where('status', NoteStatus::DRAFT->value)
            ->count();

        $items[] = $this->item('unsigned_notes', __('Unsigned clinical notes'), $draftNotes === 0, $draftNotes > 0 ? __(':count draft', ['count' => $draftNotes]) : null);

        $hasDiagnosis = EncounterDiagnosis::query()->where('encounter_id', $encounter->id)->exists();
        $items[] = $this->item('discharge_diagnosis', __('Discharge diagnosis recorded'), $hasDiagnosis, null);

        $requireSummary = (bool) config('clinical.discharge.require_signed_summary', true);
        $summarySigned = array_key_exists('has_signed_summary', $context)
            ? (bool) $context['has_signed_summary']
            : ($encounter->dischargeSummary()->whereIn('status', ['signed', 'amended'])->exists());
        $items[] = $this->item('discharge_summary_signed', __('Discharge summary signed'), ! $requireSummary || $summarySigned, null);

        $followUp = filled($context['follow_up_at'] ?? null)
            || ($encounter->patient && $this->workspaceService->getNextAppointmentForPatient($encounter->patient) !== null);
        $items[] = $this->item('follow_up_booked', __('Follow-up appointment booked'), $followUp, null);

        return new DischargeReadiness($items);
    }

    /**
     * @return array{key: string, label: string, passed: bool, severity: string, detail: ?string}
     */
    protected function item(string $key, string $label, bool $passed, ?string $detail): array
    {
        $severity = (string) config("clinical.discharge.readiness.{$key}", 'warning');

        return ['key' => $key, 'label' => $label, 'passed' => $passed, 'severity' => in_array($severity, ['blocking', 'warning', 'info'], true) ? $severity : 'warning', 'detail' => $detail];
    }

    protected function context(RequestItem $item): ?string
    {
        $detail = $item->prescriptionDetail ?? null;

        if ($detail === null) {
            return null;
        }

        $value = $detail->administration_context;

        return is_object($value) && isset($value->value) ? (string) $value->value : ($value === null ? null : (string) $value);
    }

    /**
     * @param  Collection<int, RequestItem>  $items
     */
    protected function names(Collection $items): ?string
    {
        if ($items->isEmpty()) {
            return null;
        }

        return $items->map(fn (RequestItem $item): string => $item->service?->name ?? __('Item'))->unique()->take(4)->implode(', ')
            .($items->count() > 4 ? ' +'.($items->count() - 4) : '');
    }
}
