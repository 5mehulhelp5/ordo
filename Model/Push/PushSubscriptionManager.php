<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

use Ordo\Automation\Model\PushSubscription;
use Ordo\Automation\Model\ResourceModel\PushSubscription as PushSubscriptionResource;
use Ordo\Automation\Model\ResourceModel\PushSubscription\CollectionFactory as PushSubscriptionCollectionFactory;

/**
 * CRUD for ordo_push_subscription — deliberately upsert-by-endpoint rather than always-insert:
 * a browser can silently rotate an existing subscription's endpoint/keys (key rotation, service
 * worker update) without ever calling unsubscribe() first, so re-registering the "same" logical
 * subscription must update the existing row, not accumulate duplicates. Endpoints are matched by
 * endpoint_hash (see db_schema.xml) since the raw endpoint is a `text` column MySQL can't
 * uniquely index directly.
 */
class PushSubscriptionManager
{
    public function __construct(
        private readonly PushSubscriptionResource $pushSubscriptionResource,
        private readonly PushSubscriptionCollectionFactory $collectionFactory
    ) {
    }

    public function register(
        string $endpoint,
        string $p256dhKey,
        string $authKey,
        ?int $customerId,
        ?string $visitorId
    ): void {
        $endpointHash = $this->hashEndpoint($endpoint);
        $now = date('Y-m-d H:i:s');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('endpoint_hash', $endpointHash);
        /** @var PushSubscription $subscription getFirstItem() always returns a model instance -
         *  a fresh, id-less one when nothing matches, never false/null. */
        $subscription = $collection->getFirstItem();

        $subscription->setEndpoint($endpoint);
        $subscription->setEndpointHash($endpointHash);
        $subscription->setP256dhKey($p256dhKey);
        $subscription->setAuthKey($authKey);
        $subscription->setLastSeenAt($now);
        if ($customerId !== null) {
            // A previously-anonymous subscription becoming identified is a one-way transition -
            // never overwrite an already-known customer_id with null just because a later
            // register() call happened to run logged out (e.g. a second tab).
            $subscription->setCustomerId($customerId);
        }
        if (!$subscription->getId()) {
            $subscription->setVisitorId($visitorId);
            $subscription->setCreatedAt($now);
        }

        $this->pushSubscriptionResource->save($subscription);
    }

    public function unregister(string $endpoint): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('endpoint_hash', $this->hashEndpoint($endpoint));
        foreach ($collection as $subscription) {
            /** @var PushSubscription $subscription */
            $this->pushSubscriptionResource->delete($subscription);
        }
    }

    /**
     * Called by StitchVisitorIdentity on login, same shape as
     * VisitorEventLogger::attributeVisitorToCustomer() - backfills customer_id onto this
     * visitor's previously-anonymous push subscriptions.
     */
    public function attributeVisitorToCustomer(string $visitorId, int $customerId): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addVisitorFilter($visitorId);
        $collection->addFieldToFilter('customer_id', ['null' => true]);
        foreach ($collection as $subscription) {
            /** @var PushSubscription $subscription */
            $subscription->setCustomerId($customerId);
            $this->pushSubscriptionResource->save($subscription);
        }
    }

    /**
     * @return PushSubscription[] every subscription (across all of this customer's devices) that
     *     a "send_push" campaign action should deliver to.
     */
    public function getForCustomer(int $customerId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addCustomerFilter($customerId);

        /** @var PushSubscription[] $items */
        $items = array_values($collection->getItems());
        return $items;
    }

    public function delete(PushSubscription $subscription): void
    {
        $this->pushSubscriptionResource->delete($subscription);
    }

    private function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
