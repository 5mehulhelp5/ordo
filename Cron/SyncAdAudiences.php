<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Ordo\Automation\Model\AdAudience;
use Ordo\Automation\Model\AdAudience\PiiHasher;
use Ordo\Automation\Model\AdAudience\SyncClientPool;
use Ordo\Automation\Model\Cron\CronRunLogger;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;
use Ordo\Automation\Model\ResourceModel\AdAudience\CollectionFactory as AdAudienceCollectionFactory;
use Ordo\Automation\Model\Segment\SegmentMemberResolver;
use Psr\Log\LoggerInterface;

/**
 * For every enabled ordo_ad_audience row: resolve its segment's CURRENT matching customers
 * (Model\Segment\SegmentMemberResolver — the same reusable resolver In Segment/segment bulk
 * actions already use), hash their emails (PiiHasher), and hand the list to the configured
 * platform's SyncClientInterface. One row's failure is caught and recorded, never allowed to
 * stop the rest of the run — same isolation discipline as every other per-row cron in this
 * module (e.g. Cron\SendReorderReminders).
 */
class SyncAdAudiences
{
    public function __construct(
        private readonly AdAudienceCollectionFactory $adAudienceCollectionFactory,
        private readonly AdAudienceResource $adAudienceResource,
        private readonly SegmentMemberResolver $segmentMemberResolver,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly PiiHasher $piiHasher,
        private readonly SyncClientPool $syncClientPool,
        private readonly CronRunLogger $cronRunLogger,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->adAudienceCollectionFactory->create();
        $collection->addEnabledFilter();

        $synced = 0;
        $failed = 0;

        foreach ($collection as $adAudience) {
            /** @var AdAudience $adAudience */
            try {
                $this->syncOne($adAudience);
                $synced++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error(sprintf(
                    'Ordo_Automation: ad audience sync failed for ordo_ad_audience #%d (%s): %s',
                    (int) $adAudience->getEntityId(),
                    $adAudience->getPlatform(),
                    $e->getMessage()
                ));
                $adAudience->setLastSyncedAt(date('Y-m-d H:i:s'));
                $adAudience->setLastSyncStatus(AdAudience::STATUS_ERROR);
                $adAudience->setLastSyncMessage(substr($e->getMessage(), 0, 255));
                $this->adAudienceResource->save($adAudience);
            }
        }

        $this->cronRunLogger->logSummary(sprintf('synced %d and failed %d ad audiences', $synced, $failed));
    }

    private function syncOne(AdAudience $adAudience): void
    {
        $client = $this->syncClientPool->get($adAudience->getPlatform());
        if ($client === null) {
            throw new \RuntimeException(sprintf('No sync client registered for platform "%s".', $adAudience->getPlatform()));
        }

        $customerIds = $this->segmentMemberResolver->getMatchingCustomerIds($adAudience->getSegmentId());
        $emails = [];
        foreach ($customerIds as $customerId) {
            try {
                $emails[] = $this->customerRepository->getById($customerId)->getEmail();
            } catch (\Throwable $e) {
                continue;
            }
        }
        $emails = array_values(array_filter($emails, static fn (string $email) => $email !== ''));

        $hashedEmails = $this->piiHasher->hashEmails($emails);
        $createdAudienceId = $client->sync($adAudience->getExternalAudienceId(), $hashedEmails);

        if ($createdAudienceId !== null) {
            $adAudience->setExternalAudienceId($createdAudienceId);
        }

        $adAudience->setLastSyncedAt(date('Y-m-d H:i:s'));
        $adAudience->setLastSyncStatus(AdAudience::STATUS_SUCCESS);
        $adAudience->setLastSyncMessage(sprintf('%d member(s) synced', count($hashedEmails)));
        $this->adAudienceResource->save($adAudience);
    }
}
