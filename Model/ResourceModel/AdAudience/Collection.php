<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\AdAudience;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ordo\Automation\Model\AdAudience as AdAudienceModel;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(AdAudienceModel::class, AdAudienceResource::class);
    }

    public function addEnabledFilter(): self
    {
        $this->addFieldToFilter('enabled', ['eq' => 1]);
        return $this;
    }
}
