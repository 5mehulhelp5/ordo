<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Gdpr;

use Magento\Backend\App\Action;

abstract class AbstractGdprAction extends Action
{
    public const ADMIN_RESOURCE = 'Ordo_Automation::gdpr';
}
