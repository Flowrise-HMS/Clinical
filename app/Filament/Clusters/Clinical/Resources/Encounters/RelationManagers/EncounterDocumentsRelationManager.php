<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Modules\Core\Filament\RelationManagers\MediaDocumentsRelationManager;
use Modules\Patient\Enums\DocumentType;

class EncounterDocumentsRelationManager extends MediaDocumentsRelationManager
{
    protected static function documentTypeEnum(): ?string
    {
        return DocumentType::class;
    }
}
