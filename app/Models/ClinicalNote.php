<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Modules\Clinical\Database\Factories\ClinicalNoteFactory;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Patient\Models\Patient;

class ClinicalNote extends Model
{
    /** @use HasFactory<ClinicalNoteFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'note_type',
        'noteable_type',
        'noteable_id',
        'patient_id',
        'author_id',
        'encounter_id',
        'service_request_id',
        'status',
        'subject',
        'content',
        'attachments',
        'is_signed',
        'signed_at',
        'signed_by',
    ];

    protected $casts = [
        'note_type' => NoteType::class,
        'status' => NoteStatus::class,
        'content' => 'array',
        'attachments' => 'array',
        'is_signed' => 'boolean',
        'signed_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return ClinicalNoteFactory::new();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'author_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'signed_by');
    }

    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDraft(): bool
    {
        return $this->status === NoteStatus::DRAFT;
    }

    public function isSigned(): bool
    {
        return $this->status === NoteStatus::SIGNED;
    }

    public function isAmended(): bool
    {
        return $this->status === NoteStatus::AMENDED;
    }

    public function canBeEdited(): bool
    {
        return $this->status->canBeEdited();
    }

    public function sign(int $signedBy): void
    {
        if ($this->isSigned()) {
            throw new \InvalidArgumentException('Note is already signed');
        }

        $this->update([
            'is_signed' => true,
            'signed_at' => now(),
            'signed_by' => $signedBy,
            'status' => NoteStatus::SIGNED,
        ]);
    }

    public function amend(string $reason, int $amendedBy): void
    {
        if (! $this->isSigned()) {
            throw new \InvalidArgumentException('Only signed notes can be amended');
        }

        $content = $this->content ?? [];
        $content['_amendments'][] = [
            'reason' => $reason,
            'amended_by' => $amendedBy,
            'amended_at' => now()->toIso8601String(),
        ];

        $this->update([
            'content' => $content,
            'status' => NoteStatus::AMENDED,
        ]);
    }

    public function getContentHtmlAttribute(): string
    {
        $content = $this->content ?? [];

        if ($this->noteType->requiresStructuredContent()) {
            return $this->renderStructuredContent($content);
        }

        return $content['text'] ?? '';
    }

    protected function renderStructuredContent(array $content): string
    {
        $html = '<div class="clinical-note">';

        foreach ($content as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue;
            }

            $label = ucwords(str_replace('_', ' ', $key));
            $html .= "<p><strong>{$label}:</strong> ";

            if (is_array($value)) {
                $html .= '<ul>';
                foreach ($value as $item) {
                    $html .= '<li>'.e($item).'</li>';
                }
                $html .= '</ul>';
            } else {
                $html .= e($value);
            }

            $html .= '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    public function getSummaryAttribute(): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        $content = $this->content ?? [];

        if (isset($content['summary'])) {
            return $content['summary'];
        }

        if (isset($content['text'])) {
            return Str::limit(strip_tags($content['text']), 100);
        }

        return $this->noteType->getLabel();
    }
}
