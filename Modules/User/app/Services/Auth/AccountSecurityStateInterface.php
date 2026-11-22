<?php

namespace Modules\User\Services\Auth;

use Modules\User\Models\User;

interface AccountSecurityStateInterface
{
    /**
     * Read from a fresh, locked user when issuing or consuming sensitive authorizations.
     */
    public function fingerprint(User $user): string;

    /**
     * Bind authorization to the bearer token or stateful session that requested it.
     */
    public function sessionScope(User $user): string;
}
