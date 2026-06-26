<?php

namespace App\Events;

use App\Models\Returns;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Returns $return;

    public function __construct(Returns $return)
    {
        $this->return = $return;
    }
}
