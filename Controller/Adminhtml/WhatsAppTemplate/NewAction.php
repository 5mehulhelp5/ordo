<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\WhatsAppTemplate;

use Magento\Framework\App\Action\HttpGetActionInterface;

class NewAction extends AbstractWhatsAppTemplateAction implements HttpGetActionInterface
{
    public function execute()
    {
        return $this->_forward('edit');
    }
}
