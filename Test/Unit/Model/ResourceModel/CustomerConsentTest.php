<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\CustomerConsent;

class CustomerConsentTest extends AbstractDbTestCase
{
    public function testInitializesWithCustomerConsentTableAndEntityIdField(): void
    {
        $resource = new CustomerConsent($this->makeDbContext());

        self::assertSame('ordo_customer_consent', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
