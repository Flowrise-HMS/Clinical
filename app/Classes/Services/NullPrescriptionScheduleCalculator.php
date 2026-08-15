<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\Carbon;
use Modules\Clinical\Contracts\PrescriptionScheduleCalculatorContract;

class NullPrescriptionScheduleCalculator implements PrescriptionScheduleCalculatorContract
{
    /**
     * @return list<object{sequence: int, dueAt: Carbon}>
     */
    public function buildDoseSchedule(object $detail): array
    {
        return [];
    }
}
