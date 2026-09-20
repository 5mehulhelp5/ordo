<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel\Rfm\Grid;

use Ordo\Automation\Model\ResourceModel\Rfm\Grid\CustomerIdResource;
use Ordo\Automation\Test\Unit\Model\ResourceModel\AbstractDbTestCase;

class CustomerIdResourceTest extends AbstractDbTestCase
{
    public function testInitializesWithCustomerEntityTableAndEntityIdField(): void
    {
        $resource = new CustomerIdResource($this->makeDbContext());

        self::assertSame('customer_entity', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
