<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Campaign\Action;

/**
 * Result of ContextTargetResolver::resolveCustomerOrVisitor() - whichever identifier a
 * triggering context actually carried (a customer-only trigger like order_placed gives
 * customerId, the anonymous visitor_tag_added trigger gives visitorId), or neither.
 */
class ContextTarget
{
    public function __construct(
        public readonly ?int $customerId,
        public readonly ?string $visitorId
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->customerId === null && $this->visitorId === null;
    }
}
