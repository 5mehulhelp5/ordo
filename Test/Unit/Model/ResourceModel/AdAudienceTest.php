<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\AdAudience;

class AdAudienceTest extends AbstractDbTestCase
{
    public function testInitializesWithAdAudienceTableAndEntityIdField(): void
    {
        $resource = new AdAudience($this->makeDbContext());

        self::assertSame('ordo_ad_audience', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
