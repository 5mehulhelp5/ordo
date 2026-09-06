<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Ordo\Automation\Model\Config\Source\SegmentOptions;
use Ordo\Automation\Model\ResourceModel\Segment\CollectionFactory as SegmentCollectionFactory;
use Ordo\Automation\Model\Segment;
use PHPUnit\Framework\TestCase;

class SegmentOptionsTest extends TestCase
{
    public function testToOptionArrayListsEverySegmentByIdAndName(): void
    {
        $segmentA = $this->createStub(Segment::class);
        $segmentA->method('getEntityId')->willReturn(1);
        $segmentA->method('getName')->willReturn('VIP Customers');

        $segmentB = $this->createStub(Segment::class);
        $segmentB->method('getEntityId')->willReturn(2);
        $segmentB->method('getName')->willReturn('At Risk');

        $collectionFactory = $this->createStub(SegmentCollectionFactory::class);
        $collection = new \ArrayIterator([$segmentA, $segmentB]);
        $collectionFactory->method('create')->willReturn($collection);

        $options = (new SegmentOptions($collectionFactory))->toOptionArray();

        self::assertSame([
            ['value' => 1, 'label' => 'VIP Customers'],
            ['value' => 2, 'label' => 'At Risk'],
        ], $options);
    }
}
