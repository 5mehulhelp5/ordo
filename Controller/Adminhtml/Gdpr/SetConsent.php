<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Gdpr;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Ordo\Automation\Model\ConsentManager;

/**
 * Toggles one channel's consent for one customer — the only admin-UI path to
 * ConsentManager::setConsent(), always recorded with source="admin" so an export later shows
 * this wasn't the customer's own action.
 */
class SetConsent extends AbstractGdprAction implements HttpPostActionInterface
{
    private const array VALID_CHANNELS = [
        ConsentManager::CHANNEL_EMAIL,
        ConsentManager::CHANNEL_SMS,
        ConsentManager::CHANNEL_PUSH,
    ];

    public function __construct(
        Context $context,
        private readonly ConsentManager $consentManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $channel = (string) $this->getRequest()->getParam('channel');
        $consented = (bool) (int) $this->getRequest()->getParam('consented');
        $email = (string) $this->getRequest()->getParam('email');

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index', ['email' => $email]);

        if ($customerId <= 0 || !in_array($channel, self::VALID_CHANNELS, true)) {
            $this->messageManager->addErrorMessage(__('Invalid consent update request.'));
            return $resultRedirect;
        }

        $this->consentManager->setConsent($customerId, $channel, $consented, 'admin');
        $this->messageManager->addSuccessMessage(__('Consent updated.'));

        return $resultRedirect;
    }
}
