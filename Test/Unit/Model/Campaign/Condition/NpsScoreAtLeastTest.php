<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Campaign\Condition;

use Ordo\Automation\Model\Campaign\Condition\NpsScoreAtLeast;
use Ordo\Automation\Model\ResourceModel\SurveyPrompt\Collection as SurveyPromptCollection;
use Ordo\Automation\Model\ResourceModel\SurveyPrompt\CollectionFactory as SurveyPromptCollectionFactory;
use Ordo\Automation\Model\SurveyPrompt;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class NpsScoreAtLeastTest extends TestCase
{
    private SurveyPromptCollectionFactory&\PHPUnit\Framework\MockObject\MockObject $surveyPromptCollectionFactory;
    private NpsScoreAtLeast $condition;

    protected function setUp(): void
    {
        $this->surveyPromptCollectionFactory = $this->createMock(SurveyPromptCollectionFactory::class);
        $this->condition = new NpsScoreAtLeast($this->surveyPromptCollectionFactory);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenCustomerIdIsMissing(): void
    {
        $this->surveyPromptCollectionFactory->expects(self::never())->method('create');

        self::assertFalse($this->condition->isSatisfied([], ['threshold' => 9]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenCustomerIdIsZero(): void
    {
        $this->surveyPromptCollectionFactory->expects(self::never())->method('create');

        self::assertFalse($this->condition->isSatisfied(['customer_id' => 0], ['threshold' => 9]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenThresholdParamIsMissing(): void
    {
        $this->surveyPromptCollectionFactory->expects(self::never())->method('create');

        self::assertFalse($this->condition->isSatisfied(['customer_id' => 42], []));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenThresholdParamIsNotNumeric(): void
    {
        $this->surveyPromptCollectionFactory->expects(self::never())->method('create');

        self::assertFalse($this->condition->isSatisfied(['customer_id' => 42], ['threshold' => 'high']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsTrueWhenLatestResponseMeetsThreshold(): void
    {
        $prompt = $this->createMock(SurveyPrompt::class);
        $prompt->method('getScore')->willReturn(9);

        $collection = $this->createMock(SurveyPromptCollection::class);
        $collection->expects(self::once())->method('addLatestResponseFilter')->with(42);
        $collection->method('getFirstItem')->willReturn($prompt);
        $this->surveyPromptCollectionFactory->method('create')->willReturn($collection);

        self::assertTrue($this->condition->isSatisfied(['customer_id' => 42], ['threshold' => 9]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testReturnsFalseWhenLatestResponseIsBelowThreshold(): void
    {
        $prompt = $this->createMock(SurveyPrompt::class);
        $prompt->method('getScore')->willReturn(3);

        $collection = $this->createMock(SurveyPromptCollection::class);
        $collection->method('getFirstItem')->willReturn($prompt);
        $this->surveyPromptCollectionFactory->method('create')->willReturn($collection);

        self::assertFalse($this->condition->isSatisfied(['customer_id' => 42], ['threshold' => 9]));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsClosedWhenCustomerHasNeverResponded(): void
    {
        $prompt = $this->createMock(SurveyPrompt::class);
        $prompt->method('getScore')->willReturn(null);

        $collection = $this->createMock(SurveyPromptCollection::class);
        $collection->method('getFirstItem')->willReturn($prompt);
        $this->surveyPromptCollectionFactory->method('create')->willReturn($collection);

        self::assertFalse($this->condition->isSatisfied(['customer_id' => 42], ['threshold' => 9]));
    }
}
