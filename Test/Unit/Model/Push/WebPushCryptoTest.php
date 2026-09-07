<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use Ordo\Automation\Model\Push\Base64Url;
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
    private Base64Url $base64Url;
    private Der $der;

    protected function setUp(): void
    {
        $this->der = new Der();
        $this->base64Url = new Base64Url();
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
