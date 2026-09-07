<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Gdpr;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Ordo\Automation\Model\ConsentChannel;
use Ordo\Automation\Model\ConsentManager;

/**
 * Toggles one channel's consent for one customer — the only admin-UI path to
 * ConsentManager::setConsent(), always recorded with source="admin" so an export later shows
 * this wasn't the customer's own action.
 *
 * Validates the submitted channel via ConsentChannel::tryFrom() rather than a hand-maintained
 * allow-list of strings — this controller used to keep its own separate list, and it had quietly
 * gone stale (missing WhatsApp entirely) the moment that channel was added elsewhere without
 * updating this one too. tryFrom() can't go stale the same way: it validates against the one
 * place every channel is actually defined.
 */
class SetConsent extends AbstractGdprAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly ConsentManager $consentManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $channel = ConsentChannel::tryFrom((string) $this->getRequest()->getParam('channel'));
        $consented = (bool) (int) $this->getRequest()->getParam('consented');
        $email = (string) $this->getRequest()->getParam('email');

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index', ['email' => $email]);

        if ($customerId <= 0 || $channel === null) {
            $this->messageManager->addErrorMessage(__('Invalid consent update request.'));
            return $resultRedirect;
        }

        $this->consentManager->setConsent($customerId, $channel, $consented, 'admin');
        $this->messageManager->addSuccessMessage(__('Consent updated.'));

        return $resultRedirect;
    }
}
