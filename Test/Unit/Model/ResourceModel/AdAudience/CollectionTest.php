<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel\AdAudience;

use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;
use Ordo\Automation\Model\ResourceModel\AdAudience\Collection;
use Ordo\Automation\Test\Unit\Model\ResourceModel\AbstractCollectionTestCase;

class CollectionTest extends AbstractCollectionTestCase
{
    /**
     * No custom filter methods beyond addEnabledFilter() - this smoke test just confirms the
     * wiring to the right model/resource pair, same as ScoreRule\CollectionTest.
     */
    public function testConstructsWithAdAudienceResourceModel(): void
    {
        [$entityFactory, $logger, $fetchStrategy, $eventManager] = $this->makeCollectionDeps();
        $resource = $this->makeResource();
        $collection = new Collection($entityFactory, $logger, $fetchStrategy, $eventManager, null, $resource);

        self::assertSame(AdAudienceResource::class, $collection->getResourceModelName());
        self::assertSame($resource, $collection->getResource());
    }

    public function testAddEnabledFilterIsFluent(): void
    {
        [$entityFactory, $logger, $fetchStrategy, $eventManager] = $this->makeCollectionDeps();
        $fetchStrategy->method('fetchAll')->willReturn([]);
        $collection = new Collection($entityFactory, $logger, $fetchStrategy, $eventManager, null, $this->makeResource());

        $result = $collection->addEnabledFilter();

        self::assertSame($collection, $result);
    }
}
