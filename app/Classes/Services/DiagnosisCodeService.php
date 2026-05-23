<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Modules\Clinical\Models\DiagnosisCode;

class DiagnosisCodeService
{
    public function search(string $term, ?bool $nhisOnly = false, int $limit = 15): Collection
    {
        $query = DiagnosisCode::where('is_active', true);

        if ($nhisOnly) {
            $query->where('nhis_covered', true);
        }

        if (strlen($term) >= 2) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('code')->limit($limit)->get();
    }

    public function findByCode(string $code): ?DiagnosisCode
    {
        return DiagnosisCode::where('code', $code)->where('is_active', true)->first();
    }
}
