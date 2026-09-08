<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Ordo\Automation\Model\Push\BaseSixtyFourUrl;
use Ordo\Automation\Model\Push\Der;
use Ordo\Automation\Model\Push\VapidTokenBuilder;
use PHPUnit\Framework\TestCase;

class VapidTokenBuilderTest extends TestCase
{
    private VapidTokenBuilder $builder;
    private BaseSixtyFourUrl $base64Url;
    private Der $der;
    private string $publicKeyB64url;
    private string $privateKeyB64url;

    protected function setUp(): void
    {
        $this->base64Url = new BaseSixtyFourUrl();
        $this->der = new Der();

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturn(1_700_000_000);
        $this->builder = new VapidTokenBuilder($dateTime, $this->der, $this->base64Url);

        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);
        $x = str_pad((string) $details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $d = str_pad((string) $details['ec']['d'], 32, "\x00", STR_PAD_LEFT);
        $this->publicKeyB64url = $this->base64Url->encode("\x04" . $x . $y);
        $this->privateKeyB64url = $this->base64Url->encode($d);
    }

    public function testHeaderShapeAndClaims(): void
    {
        $header = $this->builder->buildAuthorizationHeader(
            'https://fcm.googleapis.com/fcm/send/abc123',
            $this->publicKeyB64url,
            $this->privateKeyB64url,
            'mailto:ops@example.com'
        );

        self::assertMatchesRegularExpression('/^vapid t=[^,]+, k=' . preg_quote($this->publicKeyB64url, '/') . '$/', $header);

        preg_match('/^vapid t=([^,]+),/', $header, $matches);
        $jwt = $matches[1];
        [$jwtHeader, $jwtPayload, $jwtSignature] = explode('.', $jwt);

        $headerClaims = json_decode($this->base64Url->decode($jwtHeader), true);
        self::assertSame('ES256', $headerClaims['alg']);
        self::assertSame('JWT', $headerClaims['typ']);

        $payloadClaims = json_decode($this->base64Url->decode($jwtPayload), true);
        self::assertSame('https://fcm.googleapis.com', $payloadClaims['aud']);
        self::assertSame('mailto:ops@example.com', $payloadClaims['sub']);
        self::assertSame(1_700_000_000 + 12 * 3600, $payloadClaims['exp']);

        // The signature must actually verify against the public key it's paired with - proves
        // the DER-to-raw-r||s conversion round trips correctly against real OpenSSL verification.
        $signingInput = $jwtHeader . '.' . $jwtPayload;
        $rawSignature = $this->base64Url->decode($jwtSignature);
        self::assertSame(64, strlen($rawSignature));
        $derSignature = self::rawToDerSignature(substr($rawSignature, 0, 32), substr($rawSignature, 32, 32));

        $publicKey = $this->der->publicKeyFromRawPoint($this->base64Url->decode($this->publicKeyB64url));
        self::assertSame(1, openssl_verify($signingInput, $derSignature, $publicKey, OPENSSL_ALGO_SHA256));
    }

    public function testAudienceIsOriginOnlyNotFullEndpointPath(): void
    {
        $header = $this->builder->buildAuthorizationHeader(
            'https://updates.push.services.mozilla.com/wpush/v2/some-long-subscription-path',
            $this->publicKeyB64url,
            $this->privateKeyB64url,
            'mailto:ops@example.com'
        );

        preg_match('/^vapid t=([^,]+),/', $header, $matches);
        [, $jwtPayload] = explode('.', $matches[1]);
        $payloadClaims = json_decode($this->base64Url->decode($jwtPayload), true);

        self::assertSame('https://updates.push.services.mozilla.com', $payloadClaims['aud']);
    }

    public function testAudienceIncludesAnExplicitPort(): void
    {
        $header = $this->builder->buildAuthorizationHeader(
            'https://push.example.com:8443/subscription/abc123',
            $this->publicKeyB64url,
            $this->privateKeyB64url,
            'mailto:ops@example.com'
        );

        preg_match('/^vapid t=([^,]+),/', $header, $matches);
        [, $jwtPayload] = explode('.', $matches[1]);
        $payloadClaims = json_decode($this->base64Url->decode($jwtPayload), true);

        self::assertSame('https://push.example.com:8443', $payloadClaims['aud']);
    }

    public function testThrowsWhenTheEndpointHasNoDeterminableOrigin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine origin of push endpoint "/relative/path-only"');

        $this->builder->buildAuthorizationHeader(
            '/relative/path-only',
            $this->publicKeyB64url,
            $this->privateKeyB64url,
            'mailto:ops@example.com'
        );
    }

    protected function tearDown(): void
    {
        $GLOBALS['ordo_test_force_sign_failure'] = false;
    }

    /**
     * Uses the namespaced openssl_sign() override declared in WebPushCryptoTest.php - both
     * classes live in Ordo\Automation\Model\Push, so their unqualified calls resolve to the same
     * override function.
     */
    public function testThrowsWhenSigningFails(): void
    {
        $GLOBALS['ordo_test_force_sign_failure'] = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to sign VAPID JWT.');

        $this->builder->buildAuthorizationHeader(
            'https://fcm.googleapis.com/fcm/send/abc123',
            $this->publicKeyB64url,
            $this->privateKeyB64url,
            'mailto:ops@example.com'
        );
    }

    private static function rawToDerSignature(string $r, string $s): string
    {
        $encodeInt = static function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
                $bytes = "\x00" . $bytes;
            }
            return "\x02" . chr(strlen($bytes)) . $bytes;
        };

        $content = $encodeInt($r) . $encodeInt($s);
        return "\x30" . chr(strlen($content)) . $content;
    }
}
