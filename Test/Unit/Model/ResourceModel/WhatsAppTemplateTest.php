<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate;

class WhatsAppTemplateTest extends AbstractDbTestCase
{
    public function testInitializesWithWhatsAppTemplateTableAndEntityIdField(): void
    {
        $resource = new WhatsAppTemplate($this->makeDbContext());

        self::assertSame('ordo_whatsapp_template', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
