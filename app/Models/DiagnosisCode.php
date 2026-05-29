<?php

namespace Modules\Clinical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\BaseModel;

class DiagnosisCode extends BaseModel
{
    use HasUuids;

    protected $fillable = [
        'code',
        'description',
        'category',
        'nhis_covered',
        'source',
        'is_active',
    ];

    protected $casts = [
        'nhis_covered' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function bootBelongsToBranch(): void
    {
        // Diagnosis codes are system-wide reference data, not branch-specific.
        // Intentionally empty to override the BelongsToBranch trait.
    }
}
