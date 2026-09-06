<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\AdAudience;

use Magento\Framework\App\Action\HttpGetActionInterface;

class NewAction extends AbstractAdAudienceAction implements HttpGetActionInterface
{
    public function execute()
    {
        return $this->_forward('edit');
    }
}
