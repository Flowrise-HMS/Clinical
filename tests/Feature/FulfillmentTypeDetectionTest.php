<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\FulfillmentService;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Diagnostics\Enums\DiagnosticDiscipline;
use Modules\Diagnostics\Models\DiagnosticFulfillment;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff', 'Diagnostics']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->user = User::factory()->create(['branch_id' => null]);
});

function requestItemForService(Service $service, Patient $patient): RequestItem
{
    $serviceRequest = ServiceRequest::factory()->forPatient($patient)->create();

    return RequestItem::factory()->forRequest($serviceRequest)->forService($service)->create();
}

it('treats radiology-category services as diagnostic even without a diagnostic profile', function (): void {
    $service = Service::withoutEvents(fn (): Service => Service::factory()
        ->forCategory($this->serviceCategory(['code' => ServiceCategoryCode::RAD->value]))
        ->create(['requires_payment_before' => false]));

    $item = requestItemForService($service, $this->patient);

    expect(app(FulfillmentService::class)->getType($item))->toBe('diagnostic');
});

it('keeps consultation services generic', function (): void {
    $service = Service::factory()
        ->forCategory($this->serviceCategory(['code' => ServiceCategoryCode::CON->value]))
        ->create(['requires_payment_before' => false]);

    $item = requestItemForService($service, $this->patient);

    expect(app(FulfillmentService::class)->getType($item))->toBe('generic');
});

it('records a narrative result for a radiology service that has no profile', function (): void {
    $service = Service::withoutEvents(fn (): Service => Service::factory()
        ->forCategory($this->serviceCategory(['code' => ServiceCategoryCode::RAD->value]))
        ->create(['requires_payment_before' => false]));

    $item = requestItemForService($service, $this->patient);

    $this->actingAs($this->user);

    app(FulfillmentService::class)->fulfill($item, [
        'report_conclusion' => 'No acute cardiopulmonary abnormality.',
    ], $this->user);

    $fulfillment = DiagnosticFulfillment::query()->where('request_item_id', $item->id)->firstOrFail();

    expect($fulfillment->discipline)->toBe(DiagnosticDiscipline::RADIOLOGY)
        ->and($fulfillment->latestReportVersion?->conclusion)->toBe('No acute cardiopulmonary abnormality.')
        ->and($item->fresh()->status->value)->toBe('completed');
});

it('lets a lab technician submit findings and a file from the workspace results tab', function (): void {
    Gate::before(fn (): bool => true);
    Filament::setCurrentPanel(Filament::getDefaultPanel());
    Storage::fake(config('diagnostics.result_files.disk'));

    $service = Service::factory()
        ->forCategory($this->serviceCategory(['code' => ServiceCategoryCode::RAD->value]))
        ->create(['name' => 'Abdominal Ultrasound', 'requires_payment_before' => false]);

    $item = requestItemForService($service, $this->patient);

    Role::findOrCreate('lab_technician', 'web');
    $this->user->assignRole('lab_technician');
    $this->actingAs($this->user);

    Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->set('activeTab', 'submit-results')
        ->set('serviceRequestData.request_item_id', $item->id)
        ->assertSee('Findings / Result')
        ->assertSee('Attach report files (optional)')
        ->set('labResultData.report_conclusion', 'Liver and spleen normal in size.')
        ->set('labResultData.result_files', [UploadedFile::fake()->image('scan.jpg')])
        ->call('saveLabResult')
        ->assertNotified('Lab result submitted');

    $fulfillment = DiagnosticFulfillment::query()->where('request_item_id', $item->id)->firstOrFail();

    expect($fulfillment->latestReportVersion?->conclusion)->toBe('Liver and spleen normal in size.')
        ->and($fulfillment->resultFiles()->count())->toBe(1)
        ->and($item->fresh()->status->value)->toBe('completed');
});
