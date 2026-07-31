<?php

namespace Modules\Clinical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Database\Factories\NursingDiagnosisCatalogueFactory;
use Modules\Core\Models\BaseModel;

class NursingDiagnosisCatalogue extends BaseModel
{
    /** @use HasFactory<NursingDiagnosisCatalogueFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $table = 'nursing_diagnosis_catalogue';

    protected $fillable = [
        'code',
        'label',
        'definition',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return NursingDiagnosisCatalogueFactory::new();
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(CarePlanDiagnosis::class, 'catalogue_id');
    }
}
