<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\CustomerConsent;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Ordo\Automation\Model\CustomerConsent as CustomerConsentModel;
use Ordo\Automation\Model\ResourceModel\CustomerConsent as CustomerConsentResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CustomerConsentModel::class, CustomerConsentResource::class);
    }

    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', ['eq' => $customerId]);
        return $this;
    }

    public function addCustomerAndChannelFilter(int $customerId, string $channel): self
    {
        $this->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $this->addFieldToFilter('channel', ['eq' => $channel]);
        $this->setPageSize(1);
        return $this;
    }

    /**
     * @param int[] $customerIds
     */
    public function addCustomerIdsAndChannelFilter(array $customerIds, string $channel): self
    {
        $this->addFieldToFilter('customer_id', ['in' => $customerIds]);
        $this->addFieldToFilter('channel', ['eq' => $channel]);
        return $this;
    }
}
