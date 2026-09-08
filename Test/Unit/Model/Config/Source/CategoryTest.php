<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Ordo\Automation\Model\Config\Source\Category;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    private function stubCategory(int $id, string $name, int $level): CategoryModel
    {
        $category = $this->createStub(CategoryModel::class);
        $category->method('getId')->willReturn($id);
        $category->method('getName')->willReturn($name);
        $category->method('getLevel')->willReturn($level);

        return $category;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testToOptionArrayIndentsByDepthAndExcludesTheRoot(): void
    {
        // level 1 (the synthetic root) is filtered out via addAttributeToFilter, simulated here
        // by simply never including a level-1 stub in the fixture below - level 2 is the store's
        // real top-level categories (e.g. "Root Catalog" -> "Default Category"), so it gets no
        // indent; level 3 is one level deeper.
        $collection = $this->createMock(CategoryCollection::class);
        $collection->expects(self::once())->method('addAttributeToSelect')->with(['name'])->willReturnSelf();
        $collection->expects(self::once())->method('addAttributeToFilter')
            ->with('level', ['gt' => 1])->willReturnSelf();
        $collection->expects(self::once())->method('addAttributeToSort')
            ->with('path', 'ASC')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            $this->stubCategory(2, 'Default Category', 2),
            $this->stubCategory(15, 'Gear', 3),
            $this->stubCategory(16, 'Bags', 4),
        ]));

        $collectionFactory = $this->createStub(CategoryCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $options = (new Category($collectionFactory))->toOptionArray();

        self::assertSame([
            ['value' => 2, 'label' => 'Default Category'],
            ['value' => 15, 'label' => '— Gear'],
            ['value' => 16, 'label' => '— — Bags'],
        ], $options);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testToOptionArrayReturnsEmptyArrayWhenNoCategoriesExist(): void
    {
        $collection = $this->createMock(CategoryCollection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('addAttributeToSort')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $collectionFactory = $this->createStub(CategoryCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        self::assertSame([], (new Category($collectionFactory))->toOptionArray());
    }
}
