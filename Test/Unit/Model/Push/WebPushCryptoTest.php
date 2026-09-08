<?php
declare(strict_types=1);

// Namespaced function overrides: an unqualified openssl_pkey_new()/openssl_pkey_get_details()/
// openssl_encrypt() call inside Ordo\Automation\Model\Push resolves to this namespace's function
// first (PHP's own namespace-fallback rule), letting the three genuinely-defensive OpenSSL-failure
// branches in WebPushCrypto::encrypt() be exercised without adding any test-only seam to the
// production class. Each defaults to delegating to the real global function, so every other test
// in this file (and Der/VapidTokenBuilder, which share the namespace) is unaffected unless a test
// explicitly flips its flag.
namespace Ordo\Automation\Model\Push;

$GLOBALS['ordo_test_force_pkey_new_failure'] = false;
$GLOBALS['ordo_test_force_pkey_details_failure'] = false;
$GLOBALS['ordo_test_force_encrypt_failure'] = false;
// Shared with VapidTokenBuilderTest, which lives in this same namespace and calls
// openssl_sign() - both classes' unqualified calls resolve to this one override.
$GLOBALS['ordo_test_force_sign_failure'] = false;

function openssl_pkey_new(array $options = [])
{
    if ($GLOBALS['ordo_test_force_pkey_new_failure'] ?? false) {
        return false;
    }
    return \openssl_pkey_new($options);
}

function openssl_pkey_get_details($key)
{
    if ($GLOBALS['ordo_test_force_pkey_details_failure'] ?? false) {
        return false;
    }
    return \openssl_pkey_get_details($key);
}

function openssl_encrypt(string $data, string $cipherAlgo, string $passphrase, int $options = 0, string $iv = '', &$tag = null, string $aad = '', int $tagLength = 16)
{
    if ($GLOBALS['ordo_test_force_encrypt_failure'] ?? false) {
        return false;
    }
    return \openssl_encrypt($data, $cipherAlgo, $passphrase, $options, $iv, $tag, $aad, $tagLength);
}

function openssl_sign(string $data, &$signature, $privateKey, int|string $algo = OPENSSL_ALGO_SHA1): bool
{
    if ($GLOBALS['ordo_test_force_sign_failure'] ?? false) {
        return false;
    }
    return \openssl_sign($data, $signature, $privateKey, $algo);
}

namespace Ordo\Automation\Test\Unit\Model\Push;

use Ordo\Automation\Model\Push\BaseSixtyFourUrl;
use Ordo\Automation\Model\Push\Der;
use Ordo\Automation\Model\Push\WebPushCrypto;
use PHPUnit\Framework\TestCase;

/**
 * The real risk with a hand-rolled RFC 8291/8188 implementation isn't a thrown exception - it's
 * silently-wrong bytes a browser would just fail to decrypt with no error surfaced back to this
 * module at all. So rather than mocking anything, this drives WebPushCrypto::encrypt() end to
 * end and decrypts the result with an independent, from-scratch reimplementation of the
 * *receiving* side of the same RFCs (this test's own decrypt() method) - proving the ECDH
 * agreement, HKDF labels/lengths, and AES-128-GCM framing are all mutually consistent, the same
 * property a real browser's own native decryption would need.
 */
class WebPushCryptoTest extends TestCase
{
    private WebPushCrypto $webPushCrypto;
    private BaseSixtyFourUrl $base64Url;
    private Der $der;

    protected function setUp(): void
    {
        $this->der = new Der();
        $this->base64Url = new BaseSixtyFourUrl();
        $this->webPushCrypto = new WebPushCrypto($this->der, $this->base64Url);
    }

    public function testEncryptedPayloadDecryptsBackToTheOriginalPlaintext(): void
    {
        [$subscriberScalar, $subscriberPoint] = self::generateRawKeypair();
        $authSecret = random_bytes(16);
        $plaintext = json_encode(['title' => 'Order shipped', 'body' => 'Your order is on its way!']);

        $body = $this->webPushCrypto->encrypt(
            $plaintext,
            $this->base64Url->encode($subscriberPoint),
            $this->base64Url->encode($authSecret)
        );

        $decrypted = $this->decrypt($body, $subscriberScalar, $subscriberPoint, $authSecret);

        self::assertSame($plaintext, $decrypted);
    }

    public function testTwoEncryptionsOfTheSamePayloadProduceDifferentCiphertext(): void
    {
        [, $subscriberPoint] = self::generateRawKeypair();
        $authSecret = random_bytes(16);
        $plaintext = 'same message both times';

        $bodyA = $this->webPushCrypto->encrypt($plaintext, $this->base64Url->encode($subscriberPoint), $this->base64Url->encode($authSecret));
        $bodyB = $this->webPushCrypto->encrypt($plaintext, $this->base64Url->encode($subscriberPoint), $this->base64Url->encode($authSecret));

        // A fresh ephemeral keypair + salt per call (RFC 8291's own requirement) means the two
        // outputs must never collide even for identical input - if they ever do, the "ephemeral"
        // key or salt generation has silently become deterministic/reused, which would let an
        // observer correlate messages or, worse, break AES-GCM's security entirely under nonce
        // reuse.
        self::assertNotSame($bodyA, $bodyB);
    }

    public function testEncryptThrowsWhenThePublicPointIsTheWrongLength(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid subscription keys: expected a 65-byte p256dh point and a 16-byte auth secret.'
        );

        $this->webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode(str_repeat("\x04", 64)),
            $this->base64Url->encode(random_bytes(16))
        );
    }

    public function testEncryptThrowsWhenTheAuthSecretIsTheWrongLength(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Invalid subscription keys: expected a 65-byte p256dh point and a 16-byte auth secret.'
        );

        [, $subscriberPoint] = self::generateRawKeypair();

        $this->webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode($subscriberPoint),
            $this->base64Url->encode(random_bytes(15))
        );
    }

    public function testEncryptThrowsWhenEcdhDerivationFails(): void
    {
        // A mocked Der whose publicKeyFromRawPoint() hands back a real EC key, just on a
        // different curve (secp384r1) than the ephemeral P-256 key encrypt() generates
        // internally - openssl_pkey_derive() between two different-curve keys fails (confirmed
        // directly), the same shape of failure a corrupted/foreign subscription point could
        // trigger, without needing to fabricate a byte string that merely happens to look
        // superficially like a valid point.
        $mismatchedCurveKey = openssl_pkey_new(['curve_name' => 'secp384r1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($mismatchedCurveKey);
        $publicKey = openssl_pkey_get_public($details['key']);

        $der = $this->createStub(Der::class);
        $der->method('publicKeyFromRawPoint')->willReturn($publicKey);

        $webPushCrypto = new WebPushCrypto($der, $this->base64Url);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ECDH key derivation failed.');

        $webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode(str_repeat("\x04", 65)),
            $this->base64Url->encode(random_bytes(16))
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['ordo_test_force_pkey_new_failure'] = false;
        $GLOBALS['ordo_test_force_pkey_details_failure'] = false;
        $GLOBALS['ordo_test_force_encrypt_failure'] = false;
        $GLOBALS['ordo_test_force_sign_failure'] = false;
    }

    public function testEncryptThrowsWhenEphemeralKeypairGenerationFails(): void
    {
        $GLOBALS['ordo_test_force_pkey_new_failure'] = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate ephemeral EC keypair.');

        $this->webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode(str_repeat("\x04", 65)),
            $this->base64Url->encode(random_bytes(16))
        );
    }

    public function testEncryptThrowsWhenEphemeralKeypairDetailsCannotBeRead(): void
    {
        $GLOBALS['ordo_test_force_pkey_details_failure'] = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read ephemeral EC keypair details.');

        $this->webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode(str_repeat("\x04", 65)),
            $this->base64Url->encode(random_bytes(16))
        );
    }

    public function testEncryptThrowsWhenAesGcmEncryptionFails(): void
    {
        $GLOBALS['ordo_test_force_encrypt_failure'] = true;

        [, $subscriberPoint] = self::generateRawKeypair();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AES-128-GCM encryption failed.');

        $this->webPushCrypto->encrypt(
            'payload',
            $this->base64Url->encode($subscriberPoint),
            $this->base64Url->encode(random_bytes(16))
        );
    }

    /**
     * Independent reimplementation of the RFC 8291 *receiving* side - deliberately not calling
     * anything in WebPushCrypto so this can actually catch a bug in it.
     */
    private function decrypt(string $body, string $uaPrivateScalar, string $uaPublicPoint, string $authSecret): string
    {
        $salt = substr($body, 0, 16);
        $idLen = ord($body[20]);
        $asPublicPoint = substr($body, 21, $idLen);
        $ciphertextWithTag = substr($body, 21 + $idLen);
        $ciphertext = substr($ciphertextWithTag, 0, -16);
        $tag = substr($ciphertextWithTag, -16);

        $uaPrivateKey = $this->der->privateKeyFromRawScalar($uaPrivateScalar, $uaPublicPoint);
        $asPublicKey = $this->der->publicKeyFromRawPoint($asPublicPoint);
        $sharedSecret = openssl_pkey_derive($asPublicKey, $uaPrivateKey);
        self::assertNotFalse($sharedSecret);

        $keyInfo = "WebPush: info\x00" . $uaPublicPoint . $asPublicPoint;
        $prkKey = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikm = substr(hash_hmac('sha256', $keyInfo . "\x01", $prkKey, true), 0, 32);

        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);

        $padded = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        self::assertNotFalse($padded);

        // Strip the single trailing 0x02 "last record" delimiter (RFC 8188 §2).
        return substr($padded, 0, -1);
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
