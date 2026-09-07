<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Email;

/**
 * SendGrid's own documented Event Webhook signature scheme: ECDSA (prime256v1/P-256) over
 * `timestamp . rawBody`, verified against the base64-encoded public verification key shown on
 * the account's Event Webhook settings page — that key is already a full base64-encoded DER
 * SubjectPublicKeyInfo, so making it usable by openssl_verify() is just PEM-armoring it, no
 * manual ASN.1 prefix construction needed (unlike some other providers' raw-EC-point key formats).
 *
 * A small, dedicated collaborator (same reasoning as Sms\CallbackUrlBuilder) so
 * Controller\Email\StatusCallback's own logic reads as "verify, then process", not tangled up
 * with the PEM-wrapping/openssl details.
 */
class SendGridSignatureValidator
{
    public function isValid(string $publicKeyBase64, string $timestamp, string $rawBody, string $signatureBase64): bool
    {
        if ($publicKeyBase64 === '' || $signatureBase64 === '') {
            return false;
        }

        $publicKeyResource = openssl_pkey_get_public($this->toPem($publicKeyBase64));
        if ($publicKeyResource === false) {
            return false;
        }

        // No Magento core alternative decodes a base64 string.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false) {
            return false;
        }

        return openssl_verify($timestamp . $rawBody, $signature, $publicKeyResource, OPENSSL_ALGO_SHA256) === 1;
    }

    private function toPem(string $publicKeyBase64): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($publicKeyBase64, 64) . "-----END PUBLIC KEY-----\n";
    }
}
