<?php

namespace App\Exceptions\Accounting;

use RuntimeException;

/**
 * Base for the refusals the accounting engine raises at its own boundaries.
 *
 * These are not faults. Every one of them is a rule the books enforce against
 * something a user asked for — a voucher dated into a closed month, an entry
 * whose sides do not agree, a receipt applying more than an invoice has left.
 * The user needs to read the message and change the form.
 *
 * They share a base class so the handler can render all of them the same way,
 * back to the form with the message attached, instead of each new accounting
 * rule needing its own entry in bootstrap/app.php and rendering as a 500 until
 * somebody remembers to add one.
 */
abstract class AccountingException extends RuntimeException
{
}
