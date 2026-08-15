<?php

namespace Modules\Clinical\Contracts;

use Carbon\Carbon;

interface PrescriptionScheduleCalculatorContract
{
    /**
     * @return list<object{sequence: int, dueAt: Carbon}>
     */
    public function buildDoseSchedule(object $detail): array;
}
