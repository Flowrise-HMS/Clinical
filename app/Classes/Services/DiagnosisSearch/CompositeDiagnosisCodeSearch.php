<?php

namespace Modules\Clinical\Classes\Services\DiagnosisSearch;

use Illuminate\Support\Collection;
use Modules\Clinical\Contracts\DiagnosisCodeSearchContract;
use Modules\Clinical\Data\DiagnosisCodeSearchResult;

class CompositeDiagnosisCodeSearch implements DiagnosisCodeSearchContract
{
    public function __construct(
        protected WhoIcdDiagnosisCodeSearch $whoSearch,
        protected LocalDiagnosisCodeSearch $localSearch,
    ) {}

    public function search(string $term, int $limit = 15): Collection
    {
        $localResults = $this->localSearch->search($term, $limit);

        if ($localResults->isNotEmpty()) {
            $whoResults = $this->whoSearch->search($term, $limit);

            return $this->mergePreferringLocal($localResults, $whoResults, $limit);
        }

        return $this->whoSearch->search($term, $limit);
    }

    /**
     * @param  Collection<int, DiagnosisCodeSearchResult>  $localResults
     * @param  Collection<int, DiagnosisCodeSearchResult>  $whoResults
     * @return Collection<int, DiagnosisCodeSearchResult>
     */
    protected function mergePreferringLocal(Collection $localResults, Collection $whoResults, int $limit): Collection
    {
        $merged = collect();
        $seenCodes = [];

        foreach ($localResults as $result) {
            $key = strtolower((string) ($result->code ?: $result->label));
            $seenCodes[$key] = true;
            $merged->push($result);
        }

        foreach ($whoResults as $result) {
            $key = strtolower((string) ($result->code ?: $result->uri ?: $result->label));
            if (isset($seenCodes[$key])) {
                continue;
            }
            $seenCodes[$key] = true;
            $merged->push($result);
        }

        return $merged->take($limit)->values();
    }
}
