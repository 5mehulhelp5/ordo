<?php
declare(strict_types=1);

// Namespaced function override: an unqualified openssl_pkey_new()/openssl_pkey_get_details()
// call inside Ordo\Automation\Console\Command resolves to this namespace's function first (PHP's
// own namespace-fallback rule), letting these two genuinely-defensive OpenSSL-failure branches be
// exercised without adding any test-only seam to the production class itself. Both default to
// delegating to the real global function so the happy-path test above is unaffected.
namespace Ordo\Automation\Console\Command;

/** @var bool $forcePkeyNewFailure set by the test right before invoking the command */
$GLOBALS['ordo_test_force_pkey_new_failure'] = false;
/** @var bool $forcePkeyDetailsFailure set by the test right before invoking the command */
$GLOBALS['ordo_test_force_pkey_details_failure'] = false;

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

namespace Ordo\Automation\Test\Unit\Console\Command;

use Ordo\Automation\Console\Command\GenerateVapidKeysCommand;
use Ordo\Automation\Model\Push\BaseSixtyFourUrl;
use Ordo\Automation\Model\Push\Der;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateVapidKeysCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['ordo_test_force_pkey_new_failure'] = false;
        $GLOBALS['ordo_test_force_pkey_details_failure'] = false;
    }

    public function testFailsGracefullyWhenOpensslCannotGenerateAKeyPair(): void
    {
        $GLOBALS['ordo_test_force_pkey_new_failure'] = true;

        $tester = new CommandTester(new GenerateVapidKeysCommand(new BaseSixtyFourUrl()));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Failed to generate an EC key pair', $tester->getDisplay());
    }

    public function testFailsGracefullyWhenOpensslCannotReadTheGeneratedKeyPair(): void
    {
        $GLOBALS['ordo_test_force_pkey_details_failure'] = true;

        $tester = new CommandTester(new GenerateVapidKeysCommand(new BaseSixtyFourUrl()));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Failed to read the generated key pair', $tester->getDisplay());
    }

    public function testGeneratesAUsableKeyPair(): void
    {
        $base64Url = new BaseSixtyFourUrl();
        $der = new Der();
        $tester = new CommandTester(new GenerateVapidKeysCommand($base64Url));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);

        $output = $tester->getDisplay();
        self::assertMatchesRegularExpression('/VAPID Public Key:\n([A-Za-z0-9_-]+)/', $output);
        preg_match('/VAPID Public Key:\n([A-Za-z0-9_-]+)/', $output, $publicMatches);
        preg_match('/VAPID Private Key:\n([A-Za-z0-9_-]+)/', $output, $privateMatches);

        $publicKey = $publicMatches[1];
        $privateKey = $privateMatches[1];

        $publicPoint = $base64Url->decode($publicKey);
        $privateScalar = $base64Url->decode($privateKey);
        self::assertSame(65, strlen($publicPoint));
        self::assertSame(4, ord($publicPoint[0]));
        self::assertSame(32, strlen($privateScalar));

        // The generated pair must actually work together, not just have the right byte lengths -
        // sign with the private key and verify with the public key.
        $privateKeyResource = $der->privateKeyFromRawScalar($privateScalar, $publicPoint);
        $publicKeyResource = $der->publicKeyFromRawPoint($publicPoint);
        $signature = '';
        openssl_sign('test message', $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        self::assertSame(1, openssl_verify('test message', $signature, $publicKeyResource, OPENSSL_ALGO_SHA256));
    }
}
