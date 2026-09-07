<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\WhatsAppTemplate;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate as WhatsAppTemplateResource;
use Ordo\Automation\Model\WhatsApp\WhatsAppTemplateClient;
use Ordo\Automation\Model\WhatsAppTemplate;
use Ordo\Automation\Model\WhatsAppTemplateFactory;

/**
 * Submits a draft (or previously rejected) template to Meta for approval - the real, external
 * step Model\WhatsAppTemplate::STATUS_DRAFT/STATUS_REJECTED can never leave on their own.
 * Approval itself happens asynchronously on Meta's side (minutes to a day or more per their own
 * docs) - Controller\Adminhtml\WhatsAppTemplate\RefreshStatus polls the result, this action only
 * ever moves a template into STATUS_PENDING.
 */
class SubmitForReview extends AbstractWhatsAppTemplateAction implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly WhatsAppTemplateFactory $whatsAppTemplateFactory,
        private readonly WhatsAppTemplateResource $whatsAppTemplateResource,
        private readonly WhatsAppTemplateClient $whatsAppTemplateClient
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $entityId = (int) $this->getRequest()->getParam('entity_id');

        $template = $this->whatsAppTemplateFactory->create();
        $this->whatsAppTemplateResource->load($template, $entityId);

        if (!$template->getEntityId()) {
            $this->messageManager->addErrorMessage(__('This WhatsApp template no longer exists.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $metaTemplateId = $this->whatsAppTemplateClient->submitTemplate(
                $template->getMetaTemplateName(),
                $template->getCategory(),
                $template->getLanguage(),
                $template->getBodyText()
            );

            $template->setMetaTemplateId($metaTemplateId);
            $template->setStatus(WhatsAppTemplate::STATUS_PENDING);
            $template->setRejectionReason(null);
            $template->setSubmittedAt(date('Y-m-d H:i:s'));
            $this->whatsAppTemplateResource->save($template);

            $this->messageManager->addSuccessMessage(__('The template has been submitted to Meta for approval.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not submit the template to Meta: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['entity_id' => $entityId]);
    }
}
