<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\AdAudience;

/**
 * Normalizes and hashes an email address to the exact spec BOTH Google Ads Customer Match and
 * Meta Custom Audiences require: trim whitespace, lowercase, then SHA-256, hex-encoded. Neither
 * platform ever accepts a raw email — this is the one, shared PII-handling chokepoint both
 * SyncClientInterface implementations go through, so there is exactly one place normalization
 * can be gotten wrong, not two.
 *
 * @see https://developers.google.com/google-ads/api/docs/remarketing/audience-types/customer-match
 * @see https://developers.facebook.com/docs/marketing-api/audiences/guides/custom-audiences/#hash
 */
class PiiHasher
{
    public function hashEmail(string $email): string
    {
        $normalized = strtolower(trim($email));
        return hash('sha256', $normalized);
    }

    /**
     * @param array<int, string> $emails
     * @return array<int, string>
     */
    public function hashEmails(array $emails): array
    {
        return array_values(array_map($this->hashEmail(...), $emails));
    }
}
