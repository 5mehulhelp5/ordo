<?php
declare(strict_types=1);

namespace Ordo\Automation\Model;

use Magento\Framework\Model\AbstractModel;
use Ordo\Automation\Model\ResourceModel\CustomerConsent as CustomerConsentResource;

/**
 * One customer's consent state for one channel (email/sms/push). See Model\ConsentManager for
 * the actual read/write API — this class is a plain data holder, same role PendingPopup/
 * Notification play for their own tables.
 */
class CustomerConsent extends AbstractModel
{
    public const ENTITY_ID = 'entity_id';
    public const CUSTOMER_ID = 'customer_id';
    public const CHANNEL = 'channel';
    public const CONSENTED = 'consented';
    public const SOURCE = 'source';
    public const UPDATED_AT = 'updated_at';

    protected function _construct(): void
    {
        $this->_init(CustomerConsentResource::class);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    public function setCustomerId(int $customerId): self
    {
        $this->setData(self::CUSTOMER_ID, $customerId);
        return $this;
    }

    public function getChannel(): string
    {
        return (string) $this->getData(self::CHANNEL);
    }

    public function setChannel(string $channel): self
    {
        $this->setData(self::CHANNEL, $channel);
        return $this;
    }

    public function isConsented(): bool
    {
        return (bool) $this->getData(self::CONSENTED);
    }

    public function setConsented(bool $consented): self
    {
        $this->setData(self::CONSENTED, $consented);
        return $this;
    }

    public function getSource(): ?string
    {
        $value = $this->getData(self::SOURCE);
        return $value === null ? null : (string) $value;
    }

    public function setSource(?string $source): self
    {
        $this->setData(self::SOURCE, $source);
        return $this;
    }
}
