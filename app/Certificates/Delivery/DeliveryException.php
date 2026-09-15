<?php

namespace App\Certificates\Delivery;

use RuntimeException;

/**
 * A transient delivery failure (network, timeout, 5xx) that the queue
 * should retry. Permanent failures are returned as DeliveryResult::failed().
 */
class DeliveryException extends RuntimeException {}
