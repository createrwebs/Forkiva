<?php

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GratuityCollected
{
    use Dispatchable, SerializesModels;

    /**
     * Create an event carrying the immutable gratuity source snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public array $snapshot) {}
}
