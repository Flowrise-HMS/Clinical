<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Enums\WardNotePriority;
use Modules\Clinical\Enums\WardNoteStatus;
use Modules\Clinical\Enums\WardNoteType;
use Modules\Clinical\Enums\WardShift;
use Modules\Clinical\Models\WardInchargeNote;
use Modules\Core\Classes\Services\WardAccessService;
use Modules\Core\Models\Location;

class WardInchargeNoteService
{
    public function __construct(
        protected WardAccessService $wardAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Location $ward, array $data, ?User $author = null): WardInchargeNote
    {
        $author ??= Auth::user();
        $noteType = $data['note_type'] instanceof WardNoteType
            ? $data['note_type']
            : enum_from(WardNoteType::class, $data['note_type'] ?? WardNoteType::DAILY_AFFAIRS);

        $requiresAck = array_key_exists('requires_acknowledgement', $data)
            ? (bool) $data['requires_acknowledgement']
            : in_array($noteType, [WardNoteType::SHIFT_HANDOVER, WardNoteType::DIRECTIVE], true);

        $shift = $data['shift'] ?? null;
        if ($shift instanceof WardShift) {
            $shift = $shift->value;
        }

        $priority = $data['priority'] ?? WardNotePriority::NORMAL->value;
        if ($priority instanceof WardNotePriority) {
            $priority = $priority->value;
        }

        $note = WardInchargeNote::create([
            'branch_id' => $ward->branch_id,
            'location_id' => $ward->id,
            'note_type' => $noteType->value,
            'shift' => $shift,
            'note_date' => isset($data['note_date']) ? Carbon::parse($data['note_date'])->toDateString() : now()->toDateString(),
            'priority' => $priority,
            'title' => trim((string) $data['title']),
            'body' => trim((string) $data['body']),
            'structured_payload' => $data['structured_payload'] ?? null,
            'related_encounter_id' => $data['related_encounter_id'] ?? null,
            'related_bed_id' => $data['related_bed_id'] ?? null,
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'requires_acknowledgement' => $requiresAck,
            'escalated_to_role' => $data['escalated_to_role'] ?? null,
            'escalated_at' => filled($data['escalated_to_role'] ?? null) ? now() : null,
            'status' => WardNoteStatus::OPEN->value,
            'authored_by' => $author?->id ?? Auth::id(),
        ]);

        if ($note->escalated_to_role) {
            $this->notifyEscalation($note, $ward);
        } elseif ($note->note_type === WardNoteType::SHIFT_HANDOVER) {
            $this->notifyWardIncharges($note, $ward, $author);
        }

        return $note;
    }

    public function acknowledge(WardInchargeNote $note, ?User $user = null): WardInchargeNote
    {
        $user ??= Auth::user();

        $note->update([
            'acknowledged_by' => $user?->id,
            'acknowledged_at' => now(),
            'status' => $note->status === WardNoteStatus::OPEN ? WardNoteStatus::ACKNOWLEDGED : $note->status,
        ]);

        return $note->fresh();
    }

    public function resolve(WardInchargeNote $note, ?string $resolutionNotes = null, ?User $user = null): WardInchargeNote
    {
        $user ??= Auth::user();

        $note->update([
            'status' => WardNoteStatus::RESOLVED,
            'resolution_notes' => $resolutionNotes,
            'resolved_by' => $user?->id,
            'resolved_at' => now(),
        ]);

        return $note->fresh();
    }

    public function escalate(WardInchargeNote $note, string $targetRole = 'super_admin'): WardInchargeNote
    {
        $note->update([
            'escalated_to_role' => $targetRole,
            'escalated_at' => now(),
            'priority' => WardNotePriority::URGENT,
        ]);

        if ($note->ward) {
            $this->notifyEscalation($note, $note->ward);
        }

        return $note->fresh();
    }

    public function togglePin(WardInchargeNote $note): WardInchargeNote
    {
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return $note->fresh();
    }

    protected function notifyWardIncharges(WardInchargeNote $note, Location $ward, ?User $author): void
    {
        $recipients = $this->wardAccess->inchargeUsersForWard($ward)
            ->reject(fn (User $u): bool => $author !== null && $u->id === $author->id);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('Shift handover logged for :ward', ['ward' => $ward->name]))
            ->body($note->title)
            ->info()
            ->sendToDatabase($recipients);
    }

    protected function notifyEscalation(WardInchargeNote $note, Location $ward): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($ward) {
                $q->where('branch_id', $ward->branch_id)->orWhereNull('branch_id');
            })
            ->whereHas('roles', fn ($r) => $r->where('name', $note->escalated_to_role))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title(__('Ward escalation from :ward', ['ward' => $ward->name]))
            ->body($note->title)
            ->warning()
            ->sendToDatabase($recipients);
    }
}
