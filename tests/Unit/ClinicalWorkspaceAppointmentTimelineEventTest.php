<?php

namespace Modules\Clinical\Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Appointment\Enums\AppointmentType;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Tests\TestCase;

class ClinicalWorkspaceAppointmentTimelineEventTest extends TestCase
{
    public function test_create_appointment_timeline_event_includes_url_key_for_non_appointment_objects(): void
    {
        $service = new ClinicalWorkspaceService;
        $method = new \ReflectionMethod(ClinicalWorkspaceService::class, 'createAppointmentEvent');
        $method->setAccessible(true);

        $stub = new \stdClass;
        $stub->id = '00000000-0000-0000-0000-000000000001';
        $stub->status = AppointmentStatus::BOOKED;
        $stub->appointment_type = AppointmentType::OUTPATIENT;
        $stub->reason_text = 'Follow-up';
        $stub->priority = 5;
        $stub->start_at = Carbon::parse('2026-05-02 10:00:00');
        $stub->end_at = Carbon::parse('2026-05-02 10:30:00');

        $event = $method->invoke($service, $stub);

        $this->assertArrayHasKey('url', $event);
        $this->assertNull($event['url']);
        $this->assertSame('appointment', $event['type']);
        $this->assertStringContainsString('Booked', $event['title']);
    }
}
