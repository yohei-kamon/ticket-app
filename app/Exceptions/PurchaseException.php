<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an order cannot be placed. The message can be shown to the user as is.
 */
class PurchaseException extends RuntimeException {}
