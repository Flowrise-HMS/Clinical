<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Services\DiagnosisCodeService;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;

class LocalDiagnosisCodeSearch implements DiagnosisCodeSearchContract
{
    public function __construct(
        protected DiagnosisCodeService $diagnosisCodeService,
    ) {}

    public function search(string $term, int $limit = 15): Collection
    {
        return $this->diagnosisCodeService->search($term, limit: $limit)
            ->map(fn ($code): DiagnosisCodeSearchResult => new DiagnosisCodeSearchResult(
                localId: $code->id,
                code: $code->code,
                label: $code->description,
                source: 'local',
            ));
    }
}
