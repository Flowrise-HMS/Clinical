<?php

namespace Modules\Clinical\Contracts;

use Illuminate\Support\Collection;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;

interface DiagnosisCodeSearchContract
{
    /**
     * @return Collection<int, DiagnosisCodeSearchResult>
     */
    public function search(string $term, int $limit = 15): Collection;
}
