<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\CanvasLayoutFactory;
use Modules\Patient\Models\Patient;

/**
 * A user's saved arrangement of an interactive canvas (node positions, sticky
 * notes, connectors and viewport) for one patient. Purely presentational: the
 * clinical data the canvas renders is never stored here.
 *
 * @property string $id
 * @property int $user_id
 * @property string $patient_id
 * @property string $canvas_key
 * @property ?string $context_id
 * @property array<string, mixed> $layout
 * @property-read User $user
 * @property-read Patient $patient
 */
class CanvasLayout extends Model
{
    /** @use HasFactory<CanvasLayoutFactory> */
    use HasFactory, HasUuids;

    public const KEY_TIMELINE = 'timeline';

    public const KEY_MEDICATIONS = 'medications';

    protected $table = 'clinical_canvas_layouts';

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'patient_id',
        'canvas_key',
        'context_id',
        'layout',
    ];

    protected $casts = [
        'layout' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return CanvasLayoutFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @param  Builder<CanvasLayout>  $query
     * @return Builder<CanvasLayout>
     */
    public function scopeForOwner(Builder $query, int $userId, string $patientId, string $canvasKey, ?string $contextId = null): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->where('canvas_key', $canvasKey)
            ->where('context_id', $contextId);
    }
}
