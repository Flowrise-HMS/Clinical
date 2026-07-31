<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Modules\Clinical\Filament\Clusters\Workspace\Concerns\ManagesCarePlan;

class CarePlanWorkspace extends Page
{
    use HasPageShield;
    use HasPatientContext;
    use ManagesCarePlan;

    protected static ?string $title = 'Care Plans';

    protected static ?string $navigationLabel = 'Care Plans';

    protected static ?string $slug = 'care-plans';

    /**
     * Standalone panel page (not inside WorkspaceCluster).
     * Clustered pages are hidden from the main sidebar, and the cluster
     * index redirects to the first clustered page — which stole Clinical Workspace.
     */
    protected static ?string $cluster = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-m-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    protected string $view = 'clinical::clinical.workspace.care-plan-workspace';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_clinical_workspace')
                ->label('Clinical Workspace')
                ->icon('heroicon-m-heart')
                ->color('gray')
                ->url(fn (): string => ClinicalWorkspace::getUrl(
                    filled($this->patientId) ? ['patientId' => $this->patientId] : []
                )),
        ];
    }
}
