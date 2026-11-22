<?php

namespace Modules\Inventory\Services\StockSync;

use RuntimeException;

/**
 * Indicates that a queued stock synchronization operation is no longer required.
 */
class StockSyncNotRequiredException extends RuntimeException {}
