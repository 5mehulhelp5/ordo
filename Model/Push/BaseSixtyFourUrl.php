<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

/**
 * The Web Push ecosystem (browser subscription keys, VAPID keys/JWT segments) uses base64url
 * (RFC 4648 §5) throughout, always unpadded — PHP's base64_encode()/base64_decode() are plain
 * base64 (RFC 4648 §4, '+'/'/' alphabet, '=' padding), so every boundary needs this translation.
 *
 * Instance methods, not static (same reasoning as every other hand-rolled crypto/encoding
 * collaborator in this module - Model\Email\SendGridSignatureValidator, Model\WhatsApp\
 * WhatsAppSignatureValidator - a static method can't be intercepted by a plugin/proxy).
 */
class BaseSixtyFourUrl
{
    public function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function decode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        // No Magento core alternative decodes a base64(url) string.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);

        return $decoded !== false ? $decoded : '';
    }
}
