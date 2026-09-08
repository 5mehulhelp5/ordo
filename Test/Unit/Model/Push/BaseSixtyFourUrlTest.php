<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Push;

use Ordo\Automation\Model\Push\BaseSixtyFourUrl;
use PHPUnit\Framework\TestCase;

class BaseSixtyFourUrlTest extends TestCase
{
    private BaseSixtyFourUrl $base64Url;

    protected function setUp(): void
    {
        $this->base64Url = new BaseSixtyFourUrl();
    }

    public function testRoundTripArbitraryBinary(): void
    {
        $original = random_bytes(65);

        $encoded = $this->base64Url->encode($original);
        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
        self::assertStringNotContainsString('=', $encoded);

        self::assertSame($original, $this->base64Url->decode($encoded));
    }

    /**
     * Real subscription keys from browsers are unpadded - decode() must not require the padding
     * encode() itself strips.
     */
    public function testDecodeToleratesMissingPadding(): void
    {
        // "f" -> base64 "Zg==" -> base64url unpadded "Zg"
        self::assertSame('f', $this->base64Url->decode('Zg'));
        // "fo" -> base64 "Zm8=" -> base64url unpadded "Zm8"
        self::assertSame('fo', $this->base64Url->decode('Zm8'));
    }
}
