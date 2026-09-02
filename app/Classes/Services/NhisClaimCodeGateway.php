<?php

namespace Modules\Clinical\Classes\Services;

use Filament\Notifications\Notification;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Support\ModuleAvailability;

/**
 * Boundary-safe bridge to the Insurance module's NHIA OTAC claim-code
 * generation. Insurance classes are resolved by string so Clinical carries no
 * hard dependency on the module (see ForbiddenOptionalModuleImportRule).
 */
class NhisClaimCodeGateway
{
    private const SERVICE_CLASS = 'Modules\\Insurance\\Services\\Otac\\NhisAttendanceService';

    public function available(): bool
    {
        return $this->service() !== null;
    }

    /**
     * Attempt OTAC claim-code generation for the encounter.
     *
     * @return array{status: string, ccc: ?string, message: ?string}|null null when the integration is unavailable
     */
    public function generateFor(Encounter $encounter): ?array
    {
        $service = $this->service();

        if ($service === null) {
            return null;
        }

        return $service->generateForEncounter($encounter)->toArray();
    }

    /**
     * @param  array{status: string, ccc: ?string, message: ?string}|null  $result
     */
    public function notify(?array $result, bool $verbose = false): void
    {
        if ($result === null) {
            return;
        }

        match ($result['status']) {
            'generated' => Notification::make()
                ->title('NHIS claim code generated')
                ->body("Claim check code {$result['ccc']} was generated from NHIA.")
                ->success()
                ->send(),
            'failed' => Notification::make()
                ->title('NHIS claim code generation failed')
                ->body(trim(($result['message'] ?? 'NHIA rejected the request.').' You can enter the code manually or retry from the encounter.'))
                ->danger()
                ->persistent()
                ->send(),
            default => $verbose
                ? Notification::make()
                    ->title('NHIS claim code not generated')
                    ->body($result['message'] ?? 'Generation was skipped.')
                    ->warning()
                    ->send()
                : null,
        };
    }

    private function service(): ?object
    {
        if (! config('insurance.enabled', true) || ! ModuleAvailability::insuranceEnabled()) {
            return null;
        }

        $class = self::SERVICE_CLASS;

        if (! class_exists($class) || ! app()->bound($class)) {
            return null;
        }

        try {
            $service = app($class);

            return $service->isEnabled() ? $service : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
