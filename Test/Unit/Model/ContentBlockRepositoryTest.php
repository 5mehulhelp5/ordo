<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model;

use Ordo\Automation\Model\ContentBlock;
use Ordo\Automation\Model\ContentBlockFactory;
use Ordo\Automation\Model\ContentBlockRepository;
use Ordo\Automation\Model\ResourceModel\ContentBlock as ContentBlockResource;
use Ordo\Automation\Model\ResourceModel\ContentBlock\Collection as ContentBlockCollection;
use Ordo\Automation\Model\ResourceModel\ContentBlock\CollectionFactory as ContentBlockCollectionFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class ContentBlockRepositoryTest extends TestCase
{
    private ContentBlockFactory $contentBlockFactory;
    private ContentBlockResource $contentBlockResource;
    private ContentBlockCollectionFactory $contentBlockCollectionFactory;

    protected function setUp(): void
    {
        $this->contentBlockFactory = $this->createMock(ContentBlockFactory::class);
        $this->contentBlockResource = $this->createMock(ContentBlockResource::class);
        $this->contentBlockCollectionFactory = $this->createStub(ContentBlockCollectionFactory::class);
    }

    private function makeRepository(): ContentBlockRepository
    {
        return new ContentBlockRepository(
            $this->contentBlockFactory,
            $this->contentBlockResource,
            $this->contentBlockCollectionFactory
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByIdReturnsLoadedEntityWhenFound(): void
    {
        $repository = $this->makeRepository();

        $contentBlock = $this->createStub(ContentBlock::class);
        $contentBlock->method('getId')->willReturn(5);
        $this->contentBlockFactory->method('create')->willReturn($contentBlock);

        $this->contentBlockResource->expects(self::once())->method('load')->with($contentBlock, 5);

        self::assertSame($contentBlock, $repository->getById(5));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByIdReturnsNullWhenEntityNotFound(): void
    {
        $repository = $this->makeRepository();

        $contentBlock = $this->createStub(ContentBlock::class);
        $contentBlock->method('getId')->willReturn(null);
        $this->contentBlockFactory->method('create')->willReturn($contentBlock);

        $this->contentBlockResource->expects(self::once())->method('load')->with($contentBlock, 99);

        self::assertNull($repository->getById(99));
    }

    public function testGetByIdReturnsNullWithoutFactoryCallWhenIdIsZeroOrNegative(): void
    {
        $repository = $this->makeRepository();

        $this->contentBlockFactory->expects(self::never())->method('create');
        $this->contentBlockResource->expects(self::never())->method('load');

        self::assertNull($repository->getById(0));
        self::assertNull($repository->getById(-1));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByIdentifierReturnsMatchingEntity(): void
    {
        $repository = $this->makeRepository();

        $contentBlock = $this->createStub(ContentBlock::class);
        $contentBlock->method('getId')->willReturn(7);

        $collection = $this->createMock(ContentBlockCollection::class);
        $collection->expects(self::once())->method('addFieldToFilter')->with('identifier', 'homepage_recs');
        $collection->expects(self::once())->method('setPageSize')->with(1);
        $collection->method('getFirstItem')->willReturn($contentBlock);

        $this->contentBlockCollectionFactory->method('create')->willReturn($collection);

        self::assertSame($contentBlock, $repository->getByIdentifier('homepage_recs'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByIdentifierReturnsNullWhenNoMatch(): void
    {
        $repository = $this->makeRepository();

        $emptyContentBlock = $this->createStub(ContentBlock::class);
        $emptyContentBlock->method('getId')->willReturn(null);

        $collection = $this->createStub(ContentBlockCollection::class);
        $collection->method('getFirstItem')->willReturn($emptyContentBlock);

        $this->contentBlockCollectionFactory->method('create')->willReturn($collection);

        self::assertNull($repository->getByIdentifier('nonexistent'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByIdentifierReturnsNullWithoutQueryWhenIdentifierIsEmpty(): void
    {
        $repository = $this->makeRepository();

        $factory = $this->createMock(ContentBlockCollectionFactory::class);
        $factory->expects(self::never())->method('create');

        $repository = new ContentBlockRepository($this->contentBlockFactory, $this->contentBlockResource, $factory);

        self::assertNull($repository->getByIdentifier(''));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSaveDelegatesToResourceAndReturnsSameEntity(): void
    {
        $repository = $this->makeRepository();

        $contentBlock = $this->createStub(ContentBlock::class);
        $this->contentBlockResource->expects(self::once())->method('save')->with($contentBlock);

        self::assertSame($contentBlock, $repository->save($contentBlock));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteDelegatesToResource(): void
    {
        $repository = $this->makeRepository();

        $contentBlock = $this->createStub(ContentBlock::class);
        $this->contentBlockResource->expects(self::once())->method('delete')->with($contentBlock);

        $repository->delete($contentBlock);
    }
}
