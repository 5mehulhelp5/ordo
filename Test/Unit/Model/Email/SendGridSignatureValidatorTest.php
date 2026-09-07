<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Email;

use Ordo\Automation\Model\Email\SendGridSignatureValidator;
use PHPUnit\Framework\TestCase;

/**
 * Uses a real, freshly-generated EC prime256v1 keypair - same reasoning as
 * Sms\StatusCallbackTest's use of the real Twilio\Security\RequestValidator: this proves the
 * validator's actual openssl_verify() call site works against a genuinely correct signature, not
 * just against a hand-faked one.
 */
class SendGridSignatureValidatorTest extends TestCase
{
    private string $publicKeyBase64;
    private \OpenSSLAsymmetricKey $privateKey;

    protected function setUp(): void
    {
        $this->privateKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $details = openssl_pkey_get_details($this->privateKey);
        $publicKeyPem = $details['key'];

        // Strip the PEM armor back down to the bare base64 body - the exact form SendGrid's own
        // Event Webhook settings page gives you, and what toPem() re-wraps.
        $lines = array_filter(
            array_map('trim', explode("\n", $publicKeyPem)),
            static fn (string $line): bool => $line !== '' && !str_starts_with($line, '-----')
        );
        $this->publicKeyBase64 = implode('', $lines);
    }

    private function sign(string $payload): string
    {
        openssl_sign($payload, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    public function testValidSignatureIsAccepted(): void
    {
        $validator = new SendGridSignatureValidator();
        $signature = $this->sign('1700000000{"event":"delivered"}');

        self::assertTrue(
            $validator->isValid($this->publicKeyBase64, '1700000000', '{"event":"delivered"}', $signature)
        );
    }

    public function testTamperedBodyIsRejected(): void
    {
        $validator = new SendGridSignatureValidator();
        $signature = $this->sign('1700000000{"event":"delivered"}');

        self::assertFalse(
            $validator->isValid($this->publicKeyBase64, '1700000000', '{"event":"bounce"}', $signature)
        );
    }

    public function testForgedSignatureIsRejected(): void
    {
        $validator = new SendGridSignatureValidator();

        self::assertFalse($validator->isValid(
            $this->publicKeyBase64,
            '1700000000',
            '{"event":"delivered"}',
            base64_encode('not-a-real-signature')
        ));
    }

    public function testEmptyPublicKeyIsRejected(): void
    {
        $validator = new SendGridSignatureValidator();
        $signature = $this->sign('1700000000{"event":"delivered"}');

        self::assertFalse($validator->isValid('', '1700000000', '{"event":"delivered"}', $signature));
    }

    public function testEmptySignatureIsRejected(): void
    {
        $validator = new SendGridSignatureValidator();

        self::assertFalse($validator->isValid($this->publicKeyBase64, '1700000000', '{"event":"delivered"}', ''));
    }

    public function testGarbagePublicKeyIsRejectedWithoutError(): void
    {
        $validator = new SendGridSignatureValidator();
        $signature = $this->sign('1700000000{"event":"delivered"}');

        self::assertFalse($validator->isValid('not-a-valid-key', '1700000000', '{"event":"delivered"}', $signature));
    }
}
