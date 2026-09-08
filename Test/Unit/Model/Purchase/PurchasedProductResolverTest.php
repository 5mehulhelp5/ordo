<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Purchase;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Ordo\Automation\Model\Purchase\PurchasedProductResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class PurchasedProductResolverTest extends TestCase
{
    private function stubSelect(): Select
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();
        $select->method('columns')->willReturnSelf();

        return $select;
    }

    private function stubResourceConnection(AdapterInterface $connection): ResourceConnection
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnCallback(fn (string $t) => $t);

        return $resourceConnection;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasPurchasedSkuReturnsTrueWhenCountIsPositive(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        $connection->method('fetchOne')->willReturn('2');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertTrue($resolver->hasPurchasedSku(42, '24-MB01'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasPurchasedSkuReturnsFalseWhenCountIsZero(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        $connection->method('fetchOne')->willReturn('0');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertFalse($resolver->hasPurchasedSku(42, '24-MB01'));
    }

    public function testHasPurchasedSkuFailsClosedOnInvalidInputWithoutQuerying(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('select');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertFalse($resolver->hasPurchasedSku(0, '24-MB01'));
        self::assertFalse($resolver->hasPurchasedSku(42, ''));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetCustomerIdsWhoPurchasedSkuReturnsIntArray(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        $connection->method('fetchCol')->willReturn(['1', '2']);

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertSame([1, 2], $resolver->getCustomerIdsWhoPurchasedSku('24-MB01'));
    }

    public function testGetCustomerIdsWhoPurchasedSkuReturnsEmptyForBlankSkuWithoutQuerying(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('select');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertSame([], $resolver->getCustomerIdsWhoPurchasedSku(''));
    }

    public function testHasPurchasedInCategoryFailsClosedOnInvalidInputWithoutQuerying(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('select');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertFalse($resolver->hasPurchasedInCategory(0, 15));
        self::assertFalse($resolver->hasPurchasedInCategory(42, 0));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasPurchasedInCategoryReturnsFalseWhenSubtreeHasNoProducts(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        // First fetchCol is the category-subtree scan (empty here means "target category doesn't
        // even resolve itself", including via a self-match) - hasPurchasedInCategory() must bail
        // out on an empty product set rather than running a WHERE product_id IN () query, which
        // most DB adapters treat as always-false anyway but is worth short-circuiting explicitly.
        $connection->method('fetchCol')->willReturn([]);
        $connection->expects(self::never())->method('fetchOne');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertFalse($resolver->hasPurchasedInCategory(42, 15));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHasPurchasedInCategoryReturnsTrueWhenCountIsPositive(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        // Two fetchCol calls inside getProductIdsInCategorySubtree(): the subtree's own category
        // ids, then the product ids assigned to them.
        $connection->method('fetchCol')->willReturnOnConsecutiveCalls([15, 16], [100, 101]);
        $connection->method('fetchOne')->willReturn('1');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertTrue($resolver->hasPurchasedInCategory(42, 15));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetCustomerIdsWhoPurchasedInCategoryReturnsIntArray(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->stubSelect());
        $connection->method('fetchCol')->willReturnOnConsecutiveCalls([15], [100], ['3', '4']);

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertSame([3, 4], $resolver->getCustomerIdsWhoPurchasedInCategory(15));
    }

    public function testGetCustomerIdsWhoPurchasedInCategoryFailsClosedOnInvalidCategoryIdWithoutQuerying(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('select');

        $resolver = new PurchasedProductResolver($this->stubResourceConnection($connection));

        self::assertSame([], $resolver->getCustomerIdsWhoPurchasedInCategory(0));
    }
}
