<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\AdAudience;

use Magento\Backend\App\Action;

abstract class AbstractAdAudienceAction extends Action
{
    public const ADMIN_RESOURCE = 'Ordo_Automation::ad_audiences';
}
