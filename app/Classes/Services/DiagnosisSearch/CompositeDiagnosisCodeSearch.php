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
        $whoResults = $this->whoSearch->search($term, $limit);

        if ($whoResults->isNotEmpty()) {
            $localResults = $this->localSearch->search($term, $limit);

            return $this->mergePreferringWho($whoResults, $localResults, $limit);
        }

        return $this->localSearch->search($term, $limit);
    }

    /**
     * @param  Collection<int, DiagnosisCodeSearchResult>  $whoResults
     * @param  Collection<int, DiagnosisCodeSearchResult>  $localResults
     * @return Collection<int, DiagnosisCodeSearchResult>
     */
    protected function mergePreferringWho(Collection $whoResults, Collection $localResults, int $limit): Collection
    {
        $merged = collect();
        $seenCodes = [];

        foreach ($whoResults as $result) {
            $key = strtolower((string) ($result->code ?: $result->uri ?: $result->label));
            $seenCodes[$key] = true;
            $merged->push($result);
        }

        foreach ($localResults as $result) {
            $key = strtolower((string) ($result->code ?: $result->label));
            if (isset($seenCodes[$key])) {
                continue;
            }
            $seenCodes[$key] = true;
            $merged->push($result);
        }

        return $merged->take($limit)->values();
    }
}
