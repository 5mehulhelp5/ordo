<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Gdpr;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

/**
 * Search-by-email admin page — the single entry point into this module's GDPR tooling
 * (consent view/toggle, data export, erasure). Resolving by email rather than a grid keeps this
 * deliberately minimal: this is a data-subject-request tool used rarely and one customer at a
 * time, not a bulk-management screen.
 */
class Index extends AbstractGdprAction implements HttpGetActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $email = trim((string) $this->getRequest()->getParam('email'));

        if ($email !== '') {
            try {
                $customer = $this->customerRepository->get($email);
                $this->registry->register('ordo_gdpr_customer_id', (int) $customer->getId());
                $this->registry->register('ordo_gdpr_customer_email', $customer->getEmail());
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('No customer found for that email.'));
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ordo_Automation::top_level');
        $resultPage->getConfig()->getTitle()->prepend(__('GDPR / Consent'));

        return $resultPage;
    }
}
