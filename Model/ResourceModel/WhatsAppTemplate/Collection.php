<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\WhatsAppTemplate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate as WhatsAppTemplateResource;
use Ordo\Automation\Model\WhatsAppTemplate as WhatsAppTemplateModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(WhatsAppTemplateModel::class, WhatsAppTemplateResource::class);
    }

    public function addApprovedFilter(): self
    {
        $this->addFieldToFilter('status', ['eq' => WhatsAppTemplateModel::STATUS_APPROVED]);
        return $this;
    }
}
