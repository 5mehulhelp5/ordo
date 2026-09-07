<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push\Exception;

use RuntimeException;

/**
 * The push service itself reported this subscription no longer exists (HTTP 404/410) - the
 * browser unsubscribed, uninstalled, or the endpoint expired without this module ever hearing
 * about it directly. Model\Campaign\Action\SendPush catches this specifically to delete the dead
 * ordo_push_subscription row, distinct from a transient send failure that should just be logged
 * and retried on the next campaign dispatch.
 */
class SubscriptionGoneException extends RuntimeException
{
}
