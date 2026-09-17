<?php

namespace Modules\Clinical\Exceptions;

use Modules\Clinical\Support\DischargeReadiness;

class DischargeBlockedException extends \RuntimeException
{
    public function __construct(public readonly DischargeReadiness $readiness)
    {
        parent::__construct(__('Discharge is blocked: :items', ['items' => $readiness->summary()]));
    }
}
