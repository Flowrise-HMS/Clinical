<?php

namespace Modules\Clinical\Classes\Services;

use Modules\Clinical\Data\DiagnosisCodeSearchResult;
use Modules\Clinical\Models\DiagnosisCode;

class IcdCatalogueService
{
    public function remember(DiagnosisCodeSearchResult $result): ?DiagnosisCode
    {
        $existing = $this->findCached($result);

        if ($existing instanceof DiagnosisCode) {
            return $existing;
        }

        if (! filled($result->code) && ! filled($result->uri) && ! filled($result->externalId)) {
            return null;
        }

        return DiagnosisCode::create([
            'code' => $result->code,
            'description' => $result->label,
            'category' => null,
            'nhis_covered' => false,
            'source' => 'who',
            'is_active' => true,
            'icd_entity_id' => $result->externalId,
            'icd_uri' => $result->uri,
        ]);
    }

    public function localId(DiagnosisCodeSearchResult $result): ?string
    {
        return $this->remember($result)?->id;
    }

    protected function findCached(DiagnosisCodeSearchResult $result): ?DiagnosisCode
    {
        $query = DiagnosisCode::query()->where('source', 'who');

        if (filled($result->externalId)) {
            $query->where('icd_entity_id', $result->externalId);
        } elseif (filled($result->uri)) {
            $query->where('icd_uri', $result->uri);
        } elseif (filled($result->code)) {
            $query->where('code', $result->code);
        } else {
            return null;
        }

        return $query->first();
    }
}
