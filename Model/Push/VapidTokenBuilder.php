<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

use Magento\Framework\Stdlib\DateTime\DateTime;
use RuntimeException;

/**
 * Builds the `Authorization: vapid t=<JWT>, k=<public key>` header every Web Push request needs,
 * per RFC 8292 ("Voluntary Application Server Identification (VAPID) for Web Push"). A push
 * service (Chrome's FCM endpoint, Mozilla's autopush, etc.) checks this JWT is signed by the same
 * key as the `applicationServerKey` the browser subscribed with, proving this server (and not an
 * unrelated third party who somehow learned a subscription's endpoint) is the legitimate sender.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8292
 */
class VapidTokenBuilder
{
    private const int TTL_SECONDS = 12 * 3600;

    public function __construct(
        private readonly DateTime $dateTime,
        private readonly Der $der,
        private readonly Base64Url $base64Url
    ) {
    }

    /**
     * @param string $endpoint The subscription's own push service URL - only its origin is
     *   actually used (the `aud` claim), per spec.
     * @param string $vapidPublicKeyB64url This site's VAPID public key, base64url, as stored in
     *   config (Helper\Config::getVapidPublicKey()).
     * @param string $vapidPrivateKeyB64url This site's VAPID private key, base64url (decrypted).
     * @param string $subject `mailto:` address or `https://` URL identifying this site's operator.
     */
    public function buildAuthorizationHeader(
        string $endpoint,
        string $vapidPublicKeyB64url,
        string $vapidPrivateKeyB64url,
        string $subject
    ): string {
        $origin = $this->originOf($endpoint);

        $header = $this->base64Url->encode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64Url->encode((string) json_encode([
            'aud' => $origin,
            'exp' => $this->dateTime->gmtTimestamp() + self::TTL_SECONDS,
            'sub' => $subject,
        ]));
        $signingInput = $header . '.' . $payload;

        $privateKey = $this->der->privateKeyFromRawScalar(
            $this->base64Url->decode($vapidPrivateKeyB64url),
            $this->base64Url->decode($vapidPublicKeyB64url)
        );

        $derSignature = '';
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign VAPID JWT.');
        }
        $ecdsa = $this->der->parseEcdsaSignature((string) $derSignature);
        $jwt = $signingInput . '.' . $this->base64Url->encode($ecdsa['r'] . $ecdsa['s']);

        return sprintf('vapid t=%s, k=%s', $jwt, $vapidPublicKeyB64url);
    }

    private function originOf(string $url): string
    {
        // No Magento core alternative parses a URL into its component parts.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException(sprintf('Cannot determine origin of push endpoint "%s".', $url));
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
