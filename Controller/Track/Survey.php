<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Track;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\ResourceModel\SurveyPrompt\CollectionFactory as SurveyPromptCollectionFactory;
use Ordo\Automation\Model\SurveyPrompt;

/**
 * Public, unauthenticated endpoint the frontend JS tracker polls, same trust model as
 * Controller\Track\Popup. Claims (sets delivered_at) the moment a row is handed out, not before —
 * same claim-before-use pattern as Controller\Track\Popup — so two near-simultaneous polls can
 * never both receive the same prompt.
 */
class Survey extends Action implements HttpGetActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly SurveyPromptCollectionFactory $surveyPromptCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerSession $customerSession,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isNpsSurveyEnabled()) {
            return $result->setData(['survey' => null]);
        }

        $visitorId = $this->getRequest()->getParam('visitor_id');
        $visitorId = is_string($visitorId) ? $visitorId : '';
        $customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;

        if ($visitorId === '' && $customerId === null) {
            return $result->setData(['survey' => null]);
        }

        $now = date('Y-m-d H:i:s');
        $collection = $this->surveyPromptCollectionFactory->create();
        $collection->addTargetFilter($customerId, $visitorId !== '' ? $visitorId : null, $now);
        // A handful of candidates, not just one — same claim-race reasoning as Controller\Track\
        // Popup.
        $collection->setPageSize(5);

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('ordo_survey_prompt');

        foreach ($collection as $prompt) {
            /** @var SurveyPrompt $prompt */
            $claimed = $connection->update(
                $table,
                ['delivered_at' => $now],
                ['entity_id = ?' => (int) $prompt->getId(), 'delivered_at IS NULL']
            );

            if ($claimed > 0) {
                return $result->setData([
                    'survey' => [
                        'id' => (int) $prompt->getId(),
                        'question' => $prompt->getQuestion(),
                    ],
                ]);
            }
        }

        return $result->setData(['survey' => null]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
