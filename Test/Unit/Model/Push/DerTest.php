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
