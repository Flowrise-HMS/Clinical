<?php

namespace Modules\Clinical\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Clinical\Classes\Services\MedicationDoseScheduleService;
use Modules\Clinical\Models\MedicationDoseReminderLog;
use Modules\Clinical\Notifications\MedicationDueDoseNotification;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Models\Branch;

class SendMarDoseRemindersCommand extends Command
{
    protected $signature = 'clinical:mar-dose-reminders';

    protected $description = 'Send due/overdue medication dose reminders to clinical staff';

    public function handle(MedicationDoseScheduleService $scheduleService, BranchService $branchService): int
    {
        if (! config('clinical.mar_reminders.enabled', true)) {
            $this->info('MAR reminders are disabled.');

            return self::SUCCESS;
        }

        $branchId = $branchService->getDefaultBranchId();
        $branch = $branchId ? Branch::find($branchId) : null;
        $dueSlots = $scheduleService->getDueSoonSlots($branch);
        $sent = 0;

        foreach ($dueSlots as $entry) {
            $item = $entry['request_item'];
            $slot = $entry['slot'];
            $type = $entry['reminder_type'];

            $exists = MedicationDoseReminderLog::query()
                ->where('request_item_id', $item->id)
                ->where('dose_slot_sequence', $slot->sequence)
                ->where('reminder_type', $type)
                ->exists();

            if ($exists) {
                continue;
            }

            $recipients = $this->resolveRecipients($item, $branch);

            foreach ($recipients as $user) {
                $user->notify(new MedicationDueDoseNotification($item, $slot, $type));
            }

            MedicationDoseReminderLog::create([
                'request_item_id' => $item->id,
                'dose_slot_sequence' => $slot->sequence,
                'reminder_type' => $type,
                'sent_at' => now(),
            ]);

            $sent++;
        }

        $this->info("Sent {$sent} MAR dose reminder batch(es).");

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function resolveRecipients($item, ?Branch $branch)
    {
        $query = User::query();

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        return $query->get()->filter(fn (User $user) => $user->can('administer_medication'));
    }
}
