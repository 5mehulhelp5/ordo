<?php
declare(strict_types=1);

namespace Ordo\Automation\Api\AdAudience;

/**
 * One ad platform's audience-sync integration (Google Ads Customer Match, Meta Custom
 * Audiences). Registered by platform key in di.xml (Model\AdAudience\SyncClientPool). Swappable
 * the same way Sms\SmsSenderInterface is — Cron\SyncAdAudiences only ever depends on this
 * interface, never on a platform SDK/HTTP call directly.
 */
interface SyncClientInterface
{
    /**
     * @param array<int, string> $hashedEmails already SHA-256 hex hashed (Model\AdAudience\
     *     PiiHasher) — an implementation must never receive or transmit a raw email.
     * @return string|null the platform's own audience id, when this call created one for the
     *     first time (null if $externalAudienceId was already set and is unchanged)
     * @throws \Throwable on any failure to sync — an implementation reports failure honestly,
     *     it never swallows an error itself; Cron\SyncAdAudiences is the one place that catches,
     *     logs, and records the outcome per row.
     */
    public function sync(?string $externalAudienceId, array $hashedEmails): ?string;
}
