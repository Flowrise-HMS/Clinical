<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use App\Models\User;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class PharmacySoftBoundaryTest extends TestCase
{
    #[Test]
    public function optional_class_returns_null_for_dispense_when_pharmacy_disabled(): void
    {
        $this->requireModule('Pharmacy');

        $module = Module::find('Pharmacy');
        $this->assertNotNull($module);

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::pharmacyEnabled());

            $this->assertNull(OptionalClass::resolve(
                'Modules\\Pharmacy\\Classes\\Services\\DispenseService',
                'Pharmacy',
            ));
        } finally {
            $module->enable();
        }
    }

    #[Test]
    public function fulfillment_service_rejects_dispense_when_pharmacy_unavailable(): void
    {
        $this->requireModule('Pharmacy');

        $module = Module::find('Pharmacy');
        $this->assertNotNull($module);

        $service = app(FulfillmentService::class);
        $item = new RequestItem;
        $user = new User;

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::pharmacyEnabled());

            $method = new ReflectionMethod($service, 'fulfillMedication');
            $method->setAccessible(true);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Pharmacy dispense is not available.');

            $method->invoke($service, $item, ['medication_id' => 'test-id'], $user);
        } finally {
            $module->enable();
        }
    }

    #[Test]
    public function patient_actions_medication_order_is_disabled_without_pharmacy(): void
    {
        $this->requireModule('Pharmacy');

        $module = Module::find('Pharmacy');
        $this->assertNotNull($module);

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::pharmacyEnabled());

            $action = PatientActions::make()->medicationOrder();

            $this->assertTrue($action->isDisabled());
            $this->assertSame('Pharmacy module is not available.', $action->getTooltip());
        } finally {
            $module->enable();
        }
    }
}
