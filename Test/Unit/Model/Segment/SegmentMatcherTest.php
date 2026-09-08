<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Segment;

use Ordo\Automation\Api\Campaign\ConditionInterface;
use Ordo\Automation\Model\Campaign\ConditionPool;
use Ordo\Automation\Model\ResourceModel\Segment\Condition\Collection as SegmentConditionCollection;
use Ordo\Automation\Model\ResourceModel\Segment\Condition\CollectionFactory as SegmentConditionCollectionFactory;
use Ordo\Automation\Model\ResourceModel\Segment as SegmentResource;
use Ordo\Automation\Model\Segment;
use Ordo\Automation\Model\Segment\SegmentMatcher;
use Ordo\Automation\Model\SegmentCondition;
use Ordo\Automation\Model\SegmentFactory;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class SegmentMatcherTest extends TestCase
{
    private SegmentConditionCollectionFactory&\PHPUnit\Framework\MockObject\MockObject $collectionFactory;
    private SegmentConditionCollection&\PHPUnit\Framework\MockObject\MockObject $collection;
    private ConditionPool&\PHPUnit\Framework\MockObject\MockObject $conditionPool;
    private SegmentFactory&\PHPUnit\Framework\MockObject\MockObject $segmentFactory;
    private SegmentResource&\PHPUnit\Framework\MockObject\MockObject $segmentResource;
    private LoggerInterface&\PHPUnit\Framework\MockObject\MockObject $logger;
    private SegmentMatcher $matcher;
    private Segment $segmentStub;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(SegmentConditionCollectionFactory::class);
        $this->collection = $this->createMock(SegmentConditionCollection::class);
        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->collection->method('addSegmentFilter')->willReturnSelf();

        $this->conditionPool = $this->createMock(ConditionPool::class);
        $this->segmentFactory = $this->createMock(SegmentFactory::class);
        $this->segmentResource = $this->createMock(SegmentResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // willReturnCallback (not willReturn) so stubSegmentConditionLogic() can change what's
        // returned later in a test — PHPUnit stacks multiple ->method('create') stubs FIFO, so a
        // second plain willReturn() call would never actually override this one.
        $this->segmentFactory->method('create')->willReturnCallback(fn () => $this->segmentStub);
        $this->stubSegmentConditionLogic('all');

        $this->matcher = new SegmentMatcher(
            $this->collectionFactory,
            $this->conditionPool,
            $this->segmentFactory,
            $this->segmentResource,
            $this->logger
        );
    }

    private function stubSegmentConditionLogic(string $logic): void
    {
        $segment = $this->createStub(Segment::class);
        $segment->method('getConditionLogic')->willReturn($logic);
        $this->segmentStub = $segment;
    }

    private function makeConditionRow(string $type, array $params): SegmentCondition
    {
        $row = $this->createMock(SegmentCondition::class);
        $row->method('getData')->willReturnMap([['type', $type]]);
        $row->method('getParams')->willReturn($params);

        return $row;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNotInSegmentWhenSegmentHasNoConditions(): void
    {
        $this->collection->method('getSize')->willReturn(0);
        $this->collection->expects(self::never())->method('getIterator');

        self::assertFalse($this->matcher->isCustomerInSegment(3, 42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInSegmentWhenAllConditionsAreSatisfied(): void
    {
        $row = $this->makeConditionRow('tag', ['tag' => 'vip']);
        $this->collection->method('getSize')->willReturn(1);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));

        $condition = $this->createMock(ConditionInterface::class);
        $condition->method('isSatisfied')
            ->willReturnMap([
                [['customer_id' => 42, '_in_segment_visited' => [3]], ['tag' => 'vip'], true],
            ]);
        $this->conditionPool->method('get')->willReturnMap([['tag', $condition]]);

        self::assertTrue($this->matcher->isCustomerInSegment(3, 42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNotInSegmentWhenAnyConditionFails(): void
    {
        $row = $this->makeConditionRow('tag', ['tag' => 'vip']);
        $this->collection->method('getSize')->willReturn(1);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));

        $condition = $this->createMock(ConditionInterface::class);
        $condition->method('isSatisfied')->willReturn(false);
        $this->conditionPool->method('get')->willReturnMap([['tag', $condition]]);

        self::assertFalse($this->matcher->isCustomerInSegment(3, 42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedOnUnknownConditionType(): void
    {
        $row = $this->makeConditionRow('this_type_does_not_exist', []);
        $this->collection->method('getSize')->willReturn(1);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));

        $this->conditionPool->method('get')->willReturn(null);
        $this->logger->expects(self::once())->method('error');

        self::assertFalse($this->matcher->isCustomerInSegment(3, 42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenSegmentIsAlreadyBeingVisited(): void
    {
        // Simulates the recursive call InSegment::isSatisfied makes when a segment's own
        // in_segment condition points back at a segment already in the call chain (segment 3
        // referencing itself, directly or via a longer cycle) — must short-circuit before even
        // querying its conditions, not recurse until the stack overflows.
        $this->collection->expects(self::never())->method('getSize');
        $this->collection->expects(self::never())->method('getIterator');

        self::assertFalse($this->matcher->isCustomerInSegment(3, 42, [3]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnyLogicMatchesWhenOnlyOneConditionIsSatisfied(): void
    {
        $this->stubSegmentConditionLogic('any');

        $satisfiedRow = $this->makeConditionRow('tag', ['tag' => 'vip']);
        $unsatisfiedRow = $this->makeConditionRow('score_at_least', ['threshold' => 100]);
        $this->collection->method('getSize')->willReturn(2);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$unsatisfiedRow, $satisfiedRow]));

        $failing = $this->createStub(ConditionInterface::class);
        $failing->method('isSatisfied')->willReturn(false);
        $passing = $this->createStub(ConditionInterface::class);
        $passing->method('isSatisfied')->willReturn(true);
        $this->conditionPool->method('get')->willReturnMap([
            ['score_at_least', $failing],
            ['tag', $passing],
        ]);

        self::assertTrue($this->matcher->isCustomerInSegment(3, 42));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnyLogicFailsWhenNoConditionIsSatisfied(): void
    {
        $this->stubSegmentConditionLogic('any');

        $row = $this->makeConditionRow('tag', ['tag' => 'vip']);
        $this->collection->method('getSize')->willReturn(1);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$row]));

        $condition = $this->createStub(ConditionInterface::class);
        $condition->method('isSatisfied')->willReturn(false);
        $this->conditionPool->method('get')->willReturnMap([['tag', $condition]]);

        self::assertFalse($this->matcher->isCustomerInSegment(3, 42));
    }
}
