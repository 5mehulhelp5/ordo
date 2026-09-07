<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Config\Source;

use Ordo\Automation\Model\Config\Source\WhatsAppTemplateCategory;
use Ordo\Automation\Model\WhatsAppTemplate;
use PHPUnit\Framework\TestCase;

class WhatsAppTemplateCategoryTest extends TestCase
{
    public function testToOptionArrayListsAllThreeCategories(): void
    {
        $options = (new WhatsAppTemplateCategory())->toOptionArray();

        $values = array_column($options, 'value');
        self::assertContains(WhatsAppTemplate::CATEGORY_MARKETING, $values);
        self::assertContains(WhatsAppTemplate::CATEGORY_UTILITY, $values);
        self::assertContains(WhatsAppTemplate::CATEGORY_AUTHENTICATION, $values);
        self::assertCount(3, $options);
    }
}
