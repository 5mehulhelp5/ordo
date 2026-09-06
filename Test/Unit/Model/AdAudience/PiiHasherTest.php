<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\AdAudience;

use Ordo\Automation\Model\AdAudience\PiiHasher;
use PHPUnit\Framework\TestCase;

class PiiHasherTest extends TestCase
{
    private PiiHasher $hasher;

    protected function setUp(): void
    {
        $this->hasher = new PiiHasher();
    }

    public function testHashEmailMatchesKnownSha256Vector(): void
    {
        // sha256("test@example.com") - a known, independently-computable vector, not just
        // "whatever hash() returns" circularity.
        self::assertSame(
            '973dfe463ec85785f5f95af5ba3906eedb2d931c24e69824a89ea65dba4e813b',
            $this->hasher->hashEmail('test@example.com')
        );
    }

    public function testHashEmailNormalizesCaseAndWhitespaceBeforeHashing(): void
    {
        $canonical = $this->hasher->hashEmail('test@example.com');

        self::assertSame($canonical, $this->hasher->hashEmail('  Test@Example.com  '));
        self::assertSame($canonical, $this->hasher->hashEmail('TEST@EXAMPLE.COM'));
    }

    public function testHashEmailsMapsEveryEmailInOrder(): void
    {
        $result = $this->hasher->hashEmails(['a@example.com', 'b@example.com']);

        self::assertSame([
            $this->hasher->hashEmail('a@example.com'),
            $this->hasher->hashEmail('b@example.com'),
        ], $result);
    }

    public function testHashEmailsReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], $this->hasher->hashEmails([]));
    }
}
