<?php

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GratuityCollectionReversed
{
    use Dispatchable, SerializesModels;

    /**
     * Create an event carrying the immutable gratuity reversal snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public array $snapshot) {}
}
