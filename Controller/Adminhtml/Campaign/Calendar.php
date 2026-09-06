<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Campaign;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Read-only overview of every enabled campaign's trigger(s) and its action chain's timing
 * (delay_minutes between steps) in one place — no new entities, this reads the same
 * ordo_campaign/ordo_campaign_trigger/ordo_campaign_action tables the edit form and dispatcher
 * already use (see Block\Adminhtml\Campaign\Calendar\CampaignCalendarViewModel).
 */
class Calendar extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Ordo_Automation::campaigns';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ordo_Automation::top_level');
        $resultPage->getConfig()->getTitle()->prepend(__('Campaign Calendar'));

        return $resultPage;
    }
}
