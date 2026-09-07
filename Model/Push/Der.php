<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * The smallest possible hand-rolled ASN.1 DER encoder needed to turn a raw P-256 point/scalar
 * (the only format the Web Push ecosystem ever hands you — browser subscription keys, this
 * module's own stored VAPID keys) into something PHP's openssl_* functions accept, and back.
 * No vendor crypto/ASN.1 library — matches this module's existing hand-rolled-crypto precedent
 * (Model\WhatsApp\WhatsAppSignatureValidator, Model\Email\SendGridSignatureValidator), including
 * instance (not static) methods for the same DI-interceptability reason those use.
 *
 * Every length is computed from the actual content here, never hardcoded — a fixed byte-offset
 * table for "the DER prefix" is exactly the kind of thing that silently breaks the moment an
 * input is one byte shorter/longer than whoever copied the table expected.
 */
class Der
{
    /** OID 1.2.840.10045.2.1 (id-ecPublicKey) — content bytes only, no tag/length. */
    private const string OID_EC_PUBLIC_KEY = "\x2a\x86\x48\xce\x3d\x02\x01";

    /** OID 1.2.840.10045.3.1.7 (prime256v1 / secp256r1 / P-256) — content bytes only. */
    private const string OID_PRIME256V1 = "\x2a\x86\x48\xce\x3d\x03\x01\x07";

    /**
     * @param string $point Raw uncompressed EC point (0x04 || X(32) || Y(32)), 65 bytes.
     */
    public function publicKeyFromRawPoint(string $point): OpenSSLAsymmetricKey
    {
        $spki = $this->sequence(
            $this->sequence($this->oid(self::OID_EC_PUBLIC_KEY) . $this->oid(self::OID_PRIME256V1)),
            $this->bitString($point)
        );

        $key = openssl_pkey_get_public($this->pem($spki, 'PUBLIC KEY'));
        if ($key === false) {
            throw new RuntimeException('Failed to load EC public key from raw point.');
        }

        return $key;
    }

    /**
     * @param string $scalar Raw 32-byte private key scalar (d).
     * @param string $point Raw uncompressed public point (0x04 || X(32) || Y(32)) matching $scalar.
     */
    public function privateKeyFromRawScalar(string $scalar, string $point): OpenSSLAsymmetricKey
    {
        $ecPrivateKey = $this->sequence(
            $this->integer("\x01") .
            $this->octetString($scalar) .
            $this->contextTag(0, $this->oid(self::OID_PRIME256V1)) .
            $this->contextTag(1, $this->bitString($point))
        );

        $key = openssl_pkey_get_private($this->pem($ecPrivateKey, 'EC PRIVATE KEY'));
        if ($key === false) {
            throw new RuntimeException('Failed to load EC private key from raw scalar.');
        }

        return $key;
    }

    /**
     * openssl_sign() with an EC key returns a DER `SEQUENCE { INTEGER r, INTEGER s }` — JWS's
     * ES256 (RFC 7518 §3.4) instead wants the fixed-size raw concatenation r(32) || s(32), so
     * every VAPID JWT signature needs this conversion.
     *
     * @return array{r: string, s: string} each exactly 32 raw bytes.
     */
    public function parseEcdsaSignature(string $der): array
    {
        $offset = 0;
        $this->expectTag($der, $offset, 0x30);
        $this->readLength($der, $offset);
        $r = $this->readDerInteger($der, $offset);
        $s = $this->readDerInteger($der, $offset);

        return ['r' => $this->fixedWidth($r, 32), 's' => $this->fixedWidth($s, 32)];
    }

    private function expectTag(string $der, int &$offset, int $expectedTag): void
    {
        if ($offset >= strlen($der) || ord($der[$offset]) !== $expectedTag) {
            throw new RuntimeException('Unexpected DER tag while parsing ECDSA signature.');
        }
        $offset++;
    }

    private function readLength(string $der, int &$offset): int
    {
        $first = ord($der[$offset++]);
        if ($first < 0x80) {
            return $first;
        }
        $numBytes = $first & 0x7F;
        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }
        return $length;
    }

    private function readDerInteger(string $der, int &$offset): string
    {
        $this->expectTag($der, $offset, 0x02);
        $length = $this->readLength($der, $offset);
        $value = substr($der, $offset, $length);
        $offset += $length;

        // Strip a leading 0x00 sign-padding byte, if present (added whenever the integer's MSB
        // would otherwise be mistaken for a sign bit) - not part of the actual numeric value.
        return ltrim($value, "\x00") === '' ? "\x00" : ltrim($value, "\x00");
    }

    private function fixedWidth(string $bytes, int $length): string
    {
        if (strlen($bytes) > $length) {
            // A genuinely-longer value only ever happens if a stray sign byte survived - drop
            // from the left, the numeric value's low-order bytes are always at the end.
            $bytes = substr($bytes, -$length);
        }

        return str_pad($bytes, $length, "\x00", STR_PAD_LEFT);
    }

    private function pem(string $der, string $label): string
    {
        return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
    }

    private function tlv(int $tag, string $content): string
    {
        // No Magento core alternative for building a raw DER byte.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        return chr($tag & 0xFF) . $this->length(strlen($content)) . $content;
    }

    private function length(int $length): string
    {
        if ($length < 0x80) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            return chr($length & 0xFF);
        }

        $bytes = ltrim(pack('N', $length), "\x00");
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        return chr((0x80 | strlen($bytes)) & 0xFF) . $bytes;
    }

    private function sequence(string ...$children): string
    {
        return $this->tlv(0x30, implode('', $children));
    }

    private function oid(string $content): string
    {
        return $this->tlv(0x06, $content);
    }

    private function octetString(string $content): string
    {
        return $this->tlv(0x04, $content);
    }

    private function bitString(string $content): string
    {
        // Leading byte = number of unused bits in the final octet - always 0 here, every value
        // this class encodes (an EC point) is a whole number of bytes.
        return $this->tlv(0x03, "\x00" . $content);
    }

    private function integer(string $content): string
    {
        return $this->tlv(0x02, $content);
    }

    private function contextTag(int $tagNumber, string $content): string
    {
        return $this->tlv(0xA0 | $tagNumber, $content);
    }
}
