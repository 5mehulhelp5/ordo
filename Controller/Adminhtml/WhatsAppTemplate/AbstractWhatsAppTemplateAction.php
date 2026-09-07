<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\WhatsAppTemplate;

use Magento\Backend\App\Action;

abstract class AbstractWhatsAppTemplateAction extends Action
{
    public const ADMIN_RESOURCE = 'Ordo_Automation::whatsapp_templates';
}
