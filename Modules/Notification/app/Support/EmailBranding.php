<?php

namespace Modules\Notification\Support;

use Illuminate\Mail\Message;

class EmailBranding
{
    public function name(): string
    {
        return (string) (setting('app_name') ?: config('app.name', 'Forkiva'));
    }

    /**
     * Embed raster logos so recipients do not need a public storage URL.
     * Try the email logo, application logo, then the bundled PNG. Unsupported
     * formats (including SVG) are skipped for email-client compatibility.
     */
    public function logo(?Message $message = null): string
    {
        return getEmailLogoBase64();
    }
}
