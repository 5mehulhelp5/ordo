<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Campaign\Action;

use Ordo\Automation\Model\Campaign\Action\ContextTargetResolver;
use PHPUnit\Framework\TestCase;

class ContextTargetResolverTest extends TestCase
{
    public function testResolveCustomerOrVisitorReturnsCustomerIdWhenPresent(): void
    {
        $target = (new ContextTargetResolver())->resolveCustomerOrVisitor(['customer_id' => 42]);

        self::assertSame(42, $target->customerId);
        self::assertNull($target->visitorId);
        self::assertFalse($target->isEmpty());
    }

    public function testResolveCustomerOrVisitorReturnsVisitorIdWhenPresent(): void
    {
        $target = (new ContextTargetResolver())->resolveCustomerOrVisitor(['visitor_id' => 'v1']);

        self::assertNull($target->customerId);
        self::assertSame('v1', $target->visitorId);
        self::assertFalse($target->isEmpty());
    }

    public function testResolveCustomerOrVisitorTreatsZeroOrNegativeCustomerIdAsAbsent(): void
    {
        $target = (new ContextTargetResolver())->resolveCustomerOrVisitor(['customer_id' => 0]);

        self::assertNull($target->customerId);
        self::assertTrue($target->isEmpty());
    }

    public function testResolveCustomerOrVisitorTreatsEmptyVisitorIdAsAbsent(): void
    {
        $target = (new ContextTargetResolver())->resolveCustomerOrVisitor(['visitor_id' => '']);

        self::assertNull($target->visitorId);
        self::assertTrue($target->isEmpty());
    }

    public function testResolveCustomerOrVisitorIsEmptyWhenNeitherIsPresent(): void
    {
        $target = (new ContextTargetResolver())->resolveCustomerOrVisitor([]);

        self::assertTrue($target->isEmpty());
    }

    public function testNullableStringTrimsAndReturnsNullForBlank(): void
    {
        $resolver = new ContextTargetResolver();

        self::assertSame('hello', $resolver->nullableString('  hello  '));
        self::assertNull($resolver->nullableString('   '));
        self::assertNull($resolver->nullableString(null));
    }
}
