<?php

namespace Modules\Inventory\Services\StockSync;

use RuntimeException;

/**
 * Report an invalid stock synchronization configuration that requires correction.
 */
class InvalidStockSyncConfigurationException extends RuntimeException {}
