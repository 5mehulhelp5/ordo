<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Campaign\Condition;

use Ordo\Automation\Api\Campaign\ConditionInterface;
use Ordo\Automation\Model\ResourceModel\SurveyPrompt\CollectionFactory as SurveyPromptCollectionFactory;

/**
 * Params: {"threshold": 9} — same dedicated "threshold" field ScoreAtLeast already uses, so no
 * new admin form field is needed. Context must include "customer_id" — there is no visitor_id
 * path here,
 * since an anonymous visitor's survey response can't be reliably re-matched to a later
 * customer_id-scoped campaign/segment evaluation the way a logged-in customer's can.
 *
 * Reads only the customer's single most recent *answered* ordo_survey_prompt row (Model\
 * ResourceModel\SurveyPrompt\Collection::addLatestResponseFilter()) — a customer who was never
 * asked, or hasn't answered yet, simply fails this condition rather than throwing.
 */
class NpsScoreAtLeast implements ConditionInterface
{
    public function __construct(
        private readonly SurveyPromptCollectionFactory $surveyPromptCollectionFactory
    ) {
    }

    public function isSatisfied(array $context, array $params): bool
    {
        $customerId = (int) ($context['customer_id'] ?? 0);
        $threshold = $params['threshold'] ?? null;

        if ($customerId <= 0 || !is_numeric($threshold)) {
            return false;
        }

        $collection = $this->surveyPromptCollectionFactory->create();
        $collection->addLatestResponseFilter($customerId);

        $latest = $collection->getFirstItem();
        $score = $latest->getScore();

        return $score !== null && $score >= (int) $threshold;
    }
}
