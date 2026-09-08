<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use Ordo\Automation\Model\Push\Der;
use PHPUnit\Framework\TestCase;

/**
 * Der builds keys used for two very different downstream purposes (ECDH, ECDSA signing) - these
 * tests prove both actually work against real OpenSSL, not just that they parse without error.
 */
class DerTest extends TestCase
{
    private Der $der;

    protected function setUp(): void
    {
        $this->der = new Der();
    }

    public function testPublicAndPrivateKeyFromRawBytesAgreeOnEcdh(): void
    {
        [$scalar, $point] = self::generateRawKeypair();
        [$otherScalar, $otherPoint] = self::generateRawKeypair();

        $privateKey = $this->der->privateKeyFromRawScalar($scalar, $point);
        $otherPublicKey = $this->der->publicKeyFromRawPoint($otherPoint);

        $otherPrivateKey = $this->der->privateKeyFromRawScalar($otherScalar, $otherPoint);
        $publicKey = $this->der->publicKeyFromRawPoint($point);

        $secretA = openssl_pkey_derive($otherPublicKey, $privateKey);
        $secretB = openssl_pkey_derive($publicKey, $otherPrivateKey);

        self::assertNotFalse($secretA);
        self::assertSame($secretA, $secretB);
        self::assertSame(32, strlen($secretA));
    }

    public function testPrivateKeyFromRawScalarCanSignAndPublicKeyFromRawPointCanVerify(): void
    {
        [$scalar, $point] = self::generateRawKeypair();
        $privateKey = $this->der->privateKeyFromRawScalar($scalar, $point);
        $publicKey = $this->der->publicKeyFromRawPoint($point);

        $message = 'the quick brown fox';
        $signature = '';
        self::assertTrue(openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        self::assertSame(1, openssl_verify($message, $signature, $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function testParseEcdsaSignatureRoundTripsAgainstRealOpensslSignature(): void
    {
        [$scalar, $point] = self::generateRawKeypair();
        $privateKey = $this->der->privateKeyFromRawScalar($scalar, $point);

        $message = 'vapid jwt signing input';
        $derSignature = '';
        openssl_sign($message, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        $parsed = $this->der->parseEcdsaSignature($derSignature);

        self::assertSame(32, strlen($parsed['r']));
        self::assertSame(32, strlen($parsed['s']));
    }

    public function testPublicKeyFromRawPointThrowsOnGarbageInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to load EC public key from raw point.');

        // A 65-byte string is the right length for an uncompressed point, but not one that's
        // actually on the P-256 curve - openssl_pkey_get_public() rejects it rather than
        // silently accepting an invalid point.
        $this->der->publicKeyFromRawPoint(str_repeat("\xFF", 65));
    }

    public function testPrivateKeyFromRawScalarThrowsOnGarbageInput(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to load EC private key from raw scalar.');

        $this->der->privateKeyFromRawScalar(str_repeat("\xFF", 32), str_repeat("\xFF", 65));
    }

    public function testParseEcdsaSignatureThrowsOnAnUnexpectedLeadingTag(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected DER tag while parsing ECDSA signature.');

        // 0x31 (SET) instead of the expected 0x30 (SEQUENCE) - a real ECDSA signature from
        // openssl_sign() never starts this way, only a corrupted/foreign blob would.
        $this->der->parseEcdsaSignature("\x31\x00");
    }

    public function testParseEcdsaSignatureTrimsAnOversizedIntegerToFixedWidth(): void
    {
        // Hand-built DER (not a real signature - parseEcdsaSignature() is pure format parsing,
        // it never validates the values are cryptographically meaningful): SEQUENCE { INTEGER
        // (33 significant bytes of 0x01), INTEGER (32 bytes of 0x02) }. A genuine ECDSA
        // signature over P-256 never produces an r/s this large - this exists purely to exercise
        // fixedWidth()'s "drop the excess from the left" branch, which a well-formed real
        // signature never reaches.
        $r = str_repeat("\x01", 33);
        $s = str_repeat("\x02", 32);
        $der = "\x30" . chr(2 + strlen($r) + 2 + strlen($s))
            . "\x02" . chr(strlen($r)) . $r
            . "\x02" . chr(strlen($s)) . $s;

        $parsed = $this->der->parseEcdsaSignature($der);

        self::assertSame(32, strlen($parsed['r']));
        self::assertSame(str_repeat("\x01", 32), $parsed['r']);
        self::assertSame($s, $parsed['s']);
    }

    /**
     * length()/readLength() both have a "long form" branch (DER lengths >= 128 encode as a
     * length-of-the-length byte followed by that many big-endian bytes) that no real key/
     * signature this class builds or parses ever reaches - every real SPKI/EC-private-key/
     * ECDSA-signature structure here stays under 128 bytes. Reflection calls both private
     * methods directly to exercise that branch as pure DER-encoding logic, independent of
     * whether a content that large is cryptographically realistic.
     */
    public function testLengthAndReadLengthRoundTripTheLongForm(): void
    {
        $length = new \ReflectionMethod(Der::class, 'length');
        $readLength = new \ReflectionMethod(Der::class, 'readLength');

        $encoded = $length->invoke($this->der, 300);
        // 300 = 0x012C, encoded as two content bytes after the 0x82 (long-form, 2 length bytes) marker.
        self::assertSame("\x82\x01\x2C", $encoded);

        $offset = 0;
        self::assertSame(300, $readLength->invokeArgs($this->der, [$encoded, &$offset]));
        self::assertSame(3, $offset);
    }

    /**
     * @return array{0: string, 1: string} [rawPrivateScalar(32 bytes), rawPublicPoint(65 bytes)]
     */
    private static function generateRawKeypair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);

        $x = str_pad((string) $details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad((string) $details['ec']['d'], 32, "\x00", STR_PAD_LEFT);

        return [$d, "\x04" . $x . $y];
    }
}
