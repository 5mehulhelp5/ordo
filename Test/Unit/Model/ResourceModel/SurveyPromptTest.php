<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ResourceModel;

use Ordo\Automation\Model\ResourceModel\SurveyPrompt;

class SurveyPromptTest extends AbstractDbTestCase
{
    public function testInitializesWithSurveyPromptTableAndEntityIdField(): void
    {
        $resource = new SurveyPrompt($this->makeDbContext());

        self::assertSame('ordo_survey_prompt', $resource->getMainTable());
        self::assertSame('entity_id', $resource->getIdFieldName());
    }
}
