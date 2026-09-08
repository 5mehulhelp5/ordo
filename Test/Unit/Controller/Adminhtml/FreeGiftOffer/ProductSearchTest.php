<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\FreeGiftOffer;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Ordo\Automation\Controller\Adminhtml\FreeGiftOffer\ProductSearch;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class ProductSearchTest extends AbstractAdminActionTestCase
{
    private function makeCollection(array $products): ProductCollection
    {
        $collection = $this->createMock(ProductCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('joinField')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($products));

        return $collection;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReturnsEmptyItemsForBlankTerm(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['term', '', '  ']]);

        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with(['items' => []])->willReturnSelf();

        $resultJsonFactory = $this->createStub(JsonFactory::class);
        $resultJsonFactory->method('create')->willReturn($result);

        $collectionFactory = $this->createMock(ProductCollectionFactory::class);
        $collectionFactory->expects(self::never())->method('create');

        $controller = new ProductSearch(
            $context,
            $resultJsonFactory,
            $collectionFactory,
            $this->createStub(ImageHelper::class)
        );

        $controller->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteMapsMatchingProductsToJsonItems(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['term', '', 'shirt']]);

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn('shirt-blue');
        $product->method('getName')->willReturn('Blue Shirt');
        $product->method('getData')->willReturnCallback(
            static fn (string $key) => ['qty' => '7', 'thumbnail' => 'no_selection'][$key] ?? null
        );

        $collectionFactory = $this->createStub(ProductCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->makeCollection([$product]));

        $result = $this->createMock(Json::class);
        $result->expects(self::once())->method('setData')->with([
            'items' => [
                [
                    'sku' => 'shirt-blue',
                    'name' => 'Blue Shirt',
                    'qty' => 7,
                    'thumbnail_url' => null,
                ],
            ],
        ])->willReturnSelf();

        $resultJsonFactory = $this->createStub(JsonFactory::class);
        $resultJsonFactory->method('create')->willReturn($result);

        $controller = new ProductSearch(
            $context,
            $resultJsonFactory,
            $collectionFactory,
            $this->createStub(ImageHelper::class)
        );

        $controller->execute();
    }
}
