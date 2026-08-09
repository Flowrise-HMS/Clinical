<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Widgets\CarePlanPreviousTableWidget;
use Modules\Clinical\Filament\Widgets\CarePlanRecentTableWidget;
use Modules\Clinical\Filament\Widgets\PatientDiagnosesWidget;
use Modules\Clinical\Filament\Widgets\PatientNotesWidget;
use Modules\Clinical\Filament\Widgets\PatientOrdersWidget;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsChartWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsHistoryWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsOverviewWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    foreach ([
        'View PatientProfile',
        'ViewAny Patient',
        'View Patient',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('loads a comprehensive set of patient-scoped clinical widgets on the profile', function (): void {
    $component = Livewire::actingAs($this->user)
        ->test(PatientProfile::class, ['patientId' => $this->patient->id])
        ->assertOk();

    foreach ([
        PatientVitalsOverviewWidget::class,
        PatientVitalsChartWidget::class,
        PatientVitalsHistoryWidget::class,
        PatientDiagnosesWidget::class,
        PatientNotesWidget::class,
        PatientOrdersWidget::class,
        PendingFulfillmentsWidget::class,
        CarePlanRecentTableWidget::class,
        CarePlanPreviousTableWidget::class,
        PatientTimelineWidget::class,
    ] as $widget) {
        $component->assertSeeLivewire($widget);
    }

    $widgets = invade($component->instance())->getFooterWidgets();
    $notesConfig = collect($widgets)
        ->first(fn ($configuration) => $configuration->widget === PatientNotesWidget::class);

    expect($notesConfig)->not->toBeNull()
        ->and($notesConfig->getProperties())->toBe([
            'patientId' => $this->patient->id,
        ]);
});
