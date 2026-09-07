<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\WhatsApp;

/**
 * Meta's own documented webhook signature scheme (shared across all Meta platform webhooks,
 * WhatsApp included): HMAC-SHA256 over the raw request body, keyed by the app's own App Secret,
 * sent as the "X-Hub-Signature-256" header in the form "sha256=<hex digest>". A small, dedicated
 * collaborator so Controller\WhatsApp\Webhook's own logic reads as "verify, then process" - same
 * reasoning as Sms\CallbackUrlBuilder / Email\SendGridSignatureValidator.
 *
 * @see https://developers.facebook.com/docs/graph-api/webhooks/getting-started#validating-payloads
 */
class WhatsAppSignatureValidator
{
    private const string SIGNATURE_PREFIX = 'sha256=';

    public function isValid(string $appSecret, string $rawBody, string $signatureHeader): bool
    {
        if ($appSecret === '' || !str_starts_with($signatureHeader, self::SIGNATURE_PREFIX)) {
            return false;
        }

        $provided = substr($signatureHeader, strlen(self::SIGNATURE_PREFIX));
        $expected = hash_hmac('sha256', $rawBody, $appSecret);

        return hash_equals($expected, $provided);
    }
}
