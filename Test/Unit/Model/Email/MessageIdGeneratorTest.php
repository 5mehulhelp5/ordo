<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Email;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Ordo\Automation\Model\Email\MessageIdGenerator;
use PHPUnit\Framework\TestCase;

class MessageIdGeneratorTest extends TestCase
{
    public function testGenerateUsesStoreDomainAndIsUnwrapped(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $id = (new MessageIdGenerator($storeManager))->generate();

        self::assertStringEndsWith('@shop.example.com', $id);
        self::assertStringNotContainsString('<', $id);
        self::assertStringNotContainsString('>', $id);
        self::assertMatchesRegularExpression('/^ordo\.[0-9a-f]{32}@shop\.example\.com$/', $id);
    }

    public function testGenerateFallsBackToLocalhostWhenStoreIsUnresolvable(): void
    {
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));

        $id = (new MessageIdGenerator($storeManager))->generate();

        self::assertStringEndsWith('@localhost', $id);
    }

    public function testGenerateProducesDistinctIdsAcrossCalls(): void
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $generator = new MessageIdGenerator($storeManager);

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
