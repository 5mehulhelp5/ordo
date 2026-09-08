<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

use RuntimeException;

/**
 * Web Push message encryption per RFC 8291 ("Message Encryption for Web Push"), layered on the
 * generic "aes128gcm" HTTP content coding from RFC 8188. No vendor web-push library (this
 * module's own no-SDK convention, same reasoning as Model\WhatsApp\WhatsAppSender) - PHP's
 * openssl extension (>=8.1, this module targets >=8.4) covers every primitive needed: EC key
 * generation, ECDH (openssl_pkey_derive), HMAC-SHA256 (hash_hmac, for HKDF), and AES-128-GCM
 * (openssl_encrypt/openssl_decrypt). Instance methods, not static - same DI-interceptability
 * reason as every other hand-rolled crypto collaborator in this module.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8291
 * @see https://datatracker.ietf.org/doc/html/rfc8188
 */
class WebPushCrypto
{
    private const int RECORD_SIZE = 4096;

    public function __construct(
        private readonly Der $der,
        private readonly Base64Url $base64Url
    ) {
    }

    /**
     * @param string $payload The notification JSON, plaintext.
     * @param string $p256dhKeyB64url Subscription's ECDH public key (base64url), from the
     *   browser's PushSubscription.getKey('p256dh').
     * @param string $authKeyB64url Subscription's 16-byte auth secret (base64url), from
     *   PushSubscription.getKey('auth').
     * @return string The complete `aes128gcm`-encoded body, ready to POST as-is.
     */
    public function encrypt(string $payload, string $p256dhKeyB64url, string $authKeyB64url): string
    {
        $uaPublicPoint = $this->base64Url->decode($p256dhKeyB64url);
        $authSecret = $this->base64Url->decode($authKeyB64url);
        if (strlen($uaPublicPoint) !== 65 || strlen($authSecret) !== 16) {
            throw new RuntimeException(
                'Invalid subscription keys: expected a 65-byte p256dh point and a 16-byte auth secret.'
            );
        }

        // A fresh ephemeral EC keypair per message, as RFC 8291 requires ("as" = "application
        // server" in the RFC's own naming) - never reused across sends/subscriptions.
        $ephemeral = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($ephemeral === false) {
            throw new RuntimeException('Failed to generate ephemeral EC keypair.');
        }
        $details = openssl_pkey_get_details($ephemeral);
        $ephemeralX = $details['ec']['x'] ?? null;
        $ephemeralY = $details['ec']['y'] ?? null;
        if (!is_string($ephemeralX) || !is_string($ephemeralY)) {
            throw new RuntimeException('Failed to read ephemeral EC keypair details.');
        }
        $asPublicPoint = "\x04" . $this->pad32($ephemeralX) . $this->pad32($ephemeralY);

        // $key_length (a 3rd argument some examples pass) is deprecated as of PHP 8.4 - for a
        // P-256 ECDH exchange the output is always the curve's fixed 32-byte shared secret, never
        // truncated, so it was always redundant here.
        $sharedSecret = openssl_pkey_derive($this->der->publicKeyFromRawPoint($uaPublicPoint), $ephemeral);
        if ($sharedSecret === false || strlen($sharedSecret) !== 32) {
            throw new RuntimeException('ECDH key derivation failed.');
        }

        // RFC 8291 §3.3/3.4: derive a per-message "IKM" from the ECDH shared secret, the
        // subscription's own auth secret, and both public keys - this is what makes each
        // subscription's messages undecryptable without also knowing its auth secret, not just
        // the ECDH math.
        $keyInfo = "WebPush: info\x00" . $uaPublicPoint . $asPublicPoint;
        $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikm = $this->hkdfExpand($prkKey, $keyInfo, 32);

        // RFC 8188 aes128gcm content coding: a fresh random salt per message, HKDF-derive the
        // actual content-encryption key (CEK) and nonce from it and the IKM above.
        $salt = random_bytes(16);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = $this->hkdfExpand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdfExpand($prk, "Content-Encoding: nonce\x00", 12);

        // Single-record message (this module never sends a payload anywhere near RECORD_SIZE) -
        // RFC 8188 §2's delimiter octet 0x02 marks "last (and only) record", no padding needed.
        $paddedPlaintext = $payload . "\x02";

        $tag = '';
        $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM encryption failed.');
        }

        // RFC 8188 §2.1 header: salt(16) || record size(4, big-endian) || key id length(1) ||
        // key id (our ephemeral public point, so the recipient can redo the ECDH) || ciphertext+tag.
        // No Magento core alternative for building a raw byte from an int.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $header = $salt . pack('N', self::RECORD_SIZE) . chr(strlen($asPublicPoint) & 0xFF) . $asPublicPoint;

        return $header . $ciphertext . $tag;
    }

    /**
     * HKDF-Expand (RFC 5869 §2.3), single-block only (never needs more than 32 bytes of output
     * here) - the octet 0x01 is the one-byte counter for the first (and only) block.
     */
    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        return substr(hash_hmac('sha256', $info . "\x01", $prk, true), 0, $length);
    }

    /**
     * openssl_pkey_get_details() strips leading zero bytes from EC coordinates - both must be
     * exactly 32 bytes to form a valid uncompressed P-256 point.
     */
    private function pad32(string $bytes): string
    {
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    }
}
