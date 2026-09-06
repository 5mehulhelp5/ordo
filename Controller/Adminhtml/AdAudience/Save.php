<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\AdAudience;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Ordo\Automation\Model\AdAudienceFactory;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;

class Save extends AbstractAdAudienceAction implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly AdAudienceFactory $adAudienceFactory,
        private readonly AdAudienceResource $adAudienceResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var array<string, mixed> $data */
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $entityId = (int) ($data['entity_id'] ?? 0);

        try {
            $adAudience = $this->adAudienceFactory->create();
            if ($entityId) {
                $this->adAudienceResource->load($adAudience, $entityId);
            }

            $adAudience->setName((string) ($data['name'] ?? ''));
            $adAudience->setSegmentId((int) ($data['segment_id'] ?? 0));
            $adAudience->setPlatform((string) ($data['platform'] ?? ''));
            $externalAudienceId = trim((string) ($data['external_audience_id'] ?? ''));
            $adAudience->setExternalAudienceId($externalAudienceId === '' ? null : $externalAudienceId);
            $adAudience->setEnabled((bool) ($data['enabled'] ?? false));

            $this->adAudienceResource->save($adAudience);

            $this->messageManager->addSuccessMessage(__('The ad audience has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $adAudience->getEntityId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not save the ad audience: %1', $e->getMessage()));
            return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
        }
    }
}
