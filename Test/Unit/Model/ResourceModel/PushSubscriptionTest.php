<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\PushSubscription;

class PushSubscriptionTest extends AbstractDbTestCase
{
    public function testInitializesWithPushSubscriptionTableAndEntityIdField(): void
    {
        $resource = new PushSubscription($this->makeDbContext());

        self::assertSame('ordo_push_subscription', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
