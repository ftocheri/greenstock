<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown for every way InventoryQueryAssistant::translate() can fail — network/timeout, a
 * non-2xx response, a missing or malformed tool_use block, or a schema-valid-but-out-of-range
 * filter. The service logs the specific reason at the appropriate level before throwing; the
 * controller only ever needs to catch this one type and show the same generic message, since a
 * user can't act on "which of these five things went wrong" anyway.
 */
class InventoryQueryTranslationException extends RuntimeException
{
    //
}
