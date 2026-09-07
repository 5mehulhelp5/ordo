<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Email;

use Ordo\Automation\Model\Email\PendingMessageIdHolder;
use PHPUnit\Framework\TestCase;

class PendingMessageIdHolderTest extends TestCase
{
    public function testConsumeReturnsNullWhenNothingSet(): void
    {
        self::assertNull((new PendingMessageIdHolder())->consume());
    }

    public function testConsumeReturnsAndClearsTheSetValue(): void
    {
        $holder = new PendingMessageIdHolder();
        $holder->set('abc@example.com');

        self::assertSame('abc@example.com', $holder->consume());
        self::assertNull($holder->consume());
    }
}
