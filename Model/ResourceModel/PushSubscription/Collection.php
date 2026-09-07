<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\PushSubscription;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ordo\Automation\Model\PushSubscription as PushSubscriptionModel;
use Ordo\Automation\Model\ResourceModel\PushSubscription as PushSubscriptionResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(PushSubscriptionModel::class, PushSubscriptionResource::class);
    }

    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', ['eq' => $customerId]);
        return $this;
    }

    public function addVisitorFilter(string $visitorId): self
    {
        $this->addFieldToFilter('visitor_id', $visitorId);
        return $this;
    }
}
