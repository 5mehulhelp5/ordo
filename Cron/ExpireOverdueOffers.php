<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Ordo\Automation\Api\Data\OfferInterface;
use Ordo\Automation\Model\Cron\CronRunLogger;
use Ordo\Automation\Model\Offer;
use Ordo\Automation\Model\ResourceModel\Offer as OfferResource;
use Ordo\Automation\Model\ResourceModel\Offer\CollectionFactory as OfferCollectionFactory;

/**
 * Marks offers past their expiry date as "expired" (they stay "sent" until then, even if the
 * customer never responded). This is what feeds the "expired without a response" figure that
 * a rep can review, instead of offers silently rotting in a "sent" state forever.
 */
class ExpireOverdueOffers
{
    public function __construct(
        private readonly OfferCollectionFactory $offerCollectionFactory,
        private readonly OfferResource $offerResource,
        private readonly CronRunLogger $cronRunLogger
    ) {
    }

    public function execute(): void
    {
        $collection = $this->offerCollectionFactory->create();
        $collection->addPastExpiryFilter(date('Y-m-d'));

        $expired = 0;
        foreach ($collection as $offer) {
            /** @var Offer $offer */
            $offer->setStatus(OfferInterface::STATUS_EXPIRED);
            $this->offerResource->save($offer);
            $expired++;
        }

        $this->cronRunLogger->logSummary(sprintf('marked %d offers as expired', $expired));
    }
}
