<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Adminhtml\WhatsAppTemplate\Edit;

use Magento\Backend\Block\Widget\Context;
use Ordo\Automation\Block\Adminhtml\WhatsAppTemplate\Edit\SaveButton;
use PHPUnit\Framework\TestCase;

class SaveButtonTest extends TestCase
{
    public function testGetButtonDataReturnsSaveConfig(): void
    {
        $context = $this->createStub(Context::class);

        $data = (new SaveButton($context))->getButtonData();

        self::assertSame('Save Template', (string) $data['label']);
        self::assertSame(90, $data['sort_order']);
        self::assertSame(
            'ordo_whatsapptemplate_form.ordo_whatsapptemplate_form',
            $data['data_attribute']['mage-init']['buttonAdapter']['actions'][0]['targetName']
        );
        self::assertSame(
            [false],
            $data['data_attribute']['mage-init']['buttonAdapter']['actions'][0]['params']
        );
    }
}
