<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Gdpr;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Ordo\Automation\Model\Gdpr\CustomerDataEraser;
use Psr\Log\LoggerInterface;

/**
 * The "right to erasure" half of this module's GDPR tooling — hard-deletes every row
 * CustomerDataEraser::erase() covers for one customer. Deliberately requires the customer_id to
 * still be posted from the same GDPR search page (not a bare "delete by id" endpoint reachable
 * without first looking the customer up), same reasoning as every other destructive admin
 * action in this module requiring a real form round trip rather than a raw URL parameter.
 */
class Erase extends AbstractGdprAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly CustomerDataEraser $customerDataEraser,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $customerId = (int) $this->getRequest()->getParam('customer_id');
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('*/*/index');

        if ($customerId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid customer.'));
            return $resultRedirect;
        }

        $deleted = $this->customerDataEraser->erase($customerId);
        $totalDeleted = array_sum($deleted);

        $this->logger->info(sprintf(
            'Ordo_Automation: GDPR erasure for customer #%d deleted %d rows across %d tables.',
            $customerId,
            $totalDeleted,
            count($deleted)
        ));
        $this->messageManager->addSuccessMessage(__(
            'Erased %1 Ordo Automation row(s) for customer #%2.',
            $totalDeleted,
            $customerId
        ));

        return $resultRedirect;
    }
}
