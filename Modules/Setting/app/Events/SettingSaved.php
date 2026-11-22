<?php

namespace Modules\Setting\Events;

use Illuminate\Queue\SerializesModels;
use Modules\Setting\Models\Setting;

class SettingSaved
{
    use SerializesModels;

    /**
     * The setting model.
     */
    public Setting $setting;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
    }
}
