<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\Notification;

class NotificationTest extends AbstractDbTestCase
{
    public function testInitializesWithNotificationTableAndEntityIdField(): void
    {
        $resource = new Notification($this->makeDbContext());

        self::assertSame('ordo_notification', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
