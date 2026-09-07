<?php
declare(strict_types=1);

namespace Ordo\Automation\Model;

use Magento\Framework\Model\AbstractModel;
use Ordo\Automation\Model\ResourceModel\PushSubscription as PushSubscriptionResource;

/**
 * One browser/device's Web Push subscription. Plain data holder, same role CustomerConsent/
 * PendingPopup play for their own tables - real getters/setters here instead of relying purely
 * on AbstractModel's magic __call, matching this codebase's own convention (see CustomerConsent).
 */
class PushSubscription extends AbstractModel
{
    public const ENTITY_ID = 'entity_id';
    public const CUSTOMER_ID = 'customer_id';
    public const VISITOR_ID = 'visitor_id';
    public const ENDPOINT = 'endpoint';
    public const ENDPOINT_HASH = 'endpoint_hash';
    public const P256DH_KEY = 'p256dh_key';
    public const AUTH_KEY = 'auth_key';
    public const CREATED_AT = 'created_at';
    public const LAST_SEEN_AT = 'last_seen_at';

    protected function _construct(): void
    {
        $this->_init(PushSubscriptionResource::class);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData(self::CUSTOMER_ID);
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(int $customerId): self
    {
        $this->setData(self::CUSTOMER_ID, $customerId);
        return $this;
    }

    public function getVisitorId(): ?string
    {
        $value = $this->getData(self::VISITOR_ID);
        return $value === null ? null : (string) $value;
    }

    public function setVisitorId(?string $visitorId): self
    {
        $this->setData(self::VISITOR_ID, $visitorId);
        return $this;
    }

    public function getEndpoint(): string
    {
        return (string) $this->getData(self::ENDPOINT);
    }

    public function setEndpoint(string $endpoint): self
    {
        $this->setData(self::ENDPOINT, $endpoint);
        return $this;
    }

    public function getEndpointHash(): string
    {
        return (string) $this->getData(self::ENDPOINT_HASH);
    }

    public function setEndpointHash(string $endpointHash): self
    {
        $this->setData(self::ENDPOINT_HASH, $endpointHash);
        return $this;
    }

    public function getP256dhKey(): string
    {
        return (string) $this->getData(self::P256DH_KEY);
    }

    public function setP256dhKey(string $p256dhKey): self
    {
        $this->setData(self::P256DH_KEY, $p256dhKey);
        return $this;
    }

    public function getAuthKey(): string
    {
        return (string) $this->getData(self::AUTH_KEY);
    }

    public function setAuthKey(string $authKey): self
    {
        $this->setData(self::AUTH_KEY, $authKey);
        return $this;
    }

    public function setCreatedAt(string $createdAt): self
    {
        $this->setData(self::CREATED_AT, $createdAt);
        return $this;
    }

    public function setLastSeenAt(string $lastSeenAt): self
    {
        $this->setData(self::LAST_SEEN_AT, $lastSeenAt);
        return $this;
    }
}
