<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\AdAudience;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Ordo\Automation\Model\AdAudienceFactory;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;

class Edit extends AbstractAdAudienceAction implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Registry $registry,
        private readonly AdAudienceFactory $adAudienceFactory,
        private readonly AdAudienceResource $adAudienceResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $entityId = (int) $this->getRequest()->getParam('entity_id');
        $adAudience = $this->adAudienceFactory->create();

        if ($entityId) {
            $this->adAudienceResource->load($adAudience, $entityId);
            if (!$adAudience->getEntityId()) {
                $this->messageManager->addErrorMessage(__('This ad audience no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        }

        $this->registry->register('ordo_ad_audience', $adAudience);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ordo_Automation::campaigns');
        $resultPage->getConfig()->getTitle()->prepend(
            $entityId ? __('Edit Ad Audience "%1"', $adAudience->getName()) : __('New Ad Audience')
        );

        return $resultPage;
    }
}
