<?php

namespace Modules\Clinical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationDoseReminderLog extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'request_item_id',
        'dose_slot_sequence',
        'reminder_type',
        'sent_at',
    ];

    protected $casts = [
        'dose_slot_sequence' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }
}
