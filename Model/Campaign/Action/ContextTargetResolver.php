<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Campaign\Action;

/**
 * Extracted after a design-review pass found this exact 8-line block, byte-for-byte identical,
 * duplicated across three campaign actions (ShowPopup, Notify, NpsSurvey) - each targets
 * whichever identifier the triggering context actually has: context["customer_id"] for
 * customer-only triggers (order_placed, tag_added, ...), context["visitor_id"] for the anonymous
 * visitor_tag_added trigger. nullableString() (trim-to-null for optional string params) was
 * separately duplicated in two of those three. No behavior change versus the original inline
 * code in any of the three - same edge cases (customer_id present but <= 0 treated as absent,
 * visitor_id present but empty string treated as absent).
 */
class ContextTargetResolver
{
    /**
     * @param array<string, mixed> $context
     */
    public function resolveCustomerOrVisitor(array $context): ContextTarget
    {
        $customerId = isset($context['customer_id']) ? (int) $context['customer_id'] : null;
        $customerId = ($customerId !== null && $customerId > 0) ? $customerId : null;

        $visitorId = isset($context['visitor_id']) ? (string) $context['visitor_id'] : null;
        $visitorId = ($visitorId !== null && $visitorId !== '') ? $visitorId : null;

        return new ContextTarget($customerId, $visitorId);
    }

    public function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
