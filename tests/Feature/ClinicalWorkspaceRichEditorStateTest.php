<?php

use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Tests\TestCase;

uses(TestCase::class);

it('defines nested RichEditor state keys for Livewire entangle', function (): void {
    $defaults = (new ReflectionClass(ClinicalWorkspace::class))->getDefaultProperties();

    expect($defaults['consultationData'])->toBe(['notes' => null])
        ->and($defaults['referralData'])->toBe(['notes' => null])
        ->and($defaults['noteFormData'])->toBe(['content' => null])
        ->and($defaults['dischargeData'])->toBe(['discharge_notes' => null])
        ->and($defaults['adtFormData'])->toBe([
            'transfer_notes' => null,
            'transfer_out_notes' => null,
            'transfer_in_notes' => null,
        ]);
});

it('restores nested RichEditor state keys when form states are reset', function (): void {
    $page = (new ReflectionClass(ClinicalWorkspace::class))->newInstanceWithoutConstructor();
    $page->consultationData = ['notes' => '<p>temp</p>'];
    $page->referralData = [];
    $page->noteFormData = [];
    $page->dischargeData = [];
    $page->adtFormData = [];
    $page->encounterFormData = ['foo' => 'bar'];
    $page->consultationChiefComplaint = 'pain';
    $page->consultationNotes = 'old';
    $page->diagnosisFormData = ['x' => 1];
    $page->vitalsData = ['y' => 1];
    $page->serviceRequestData = ['z' => 1];
    $page->labResultData = ['a' => 1];
    $page->allergyData = ['b' => 1];
    $page->medicationData = ['c' => 1];

    $method = new ReflectionMethod(ClinicalWorkspace::class, 'resetFormStates');
    $method->invoke($page);

    expect($page->consultationData)->toBe(['notes' => null])
        ->and($page->referralData)->toBe(['notes' => null])
        ->and($page->noteFormData)->toBe(['content' => null])
        ->and($page->dischargeData)->toBe(['discharge_notes' => null])
        ->and($page->adtFormData)->toBe([
            'transfer_notes' => null,
            'transfer_out_notes' => null,
            'transfer_in_notes' => null,
        ])
        ->and($page->consultationChiefComplaint)->toBe('')
        ->and($page->encounterFormData)->toBe([]);
});
