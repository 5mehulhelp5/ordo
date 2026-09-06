<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\Cron\CronRunLogger;
use Ordo\Automation\Model\ProductFeed\GoogleMerchantFeedGenerator;
use Ordo\Automation\Model\ProductFeed\ProductFeedCacheWriter;
use Psr\Log\LoggerInterface;

/**
 * Regenerates the cached Google Merchant Center feed XML — same "generate on a schedule, serve
 * the cache on request" split as Model\ContentBlock\RssFetcher/Cron\RefreshRssContentBlocks, for
 * the same reason: a public request should never trigger a full-catalog collection load.
 */
class RefreshProductFeed
{
    public function __construct(
        private readonly GoogleMerchantFeedGenerator $googleMerchantFeedGenerator,
        private readonly ProductFeedCacheWriter $productFeedCacheWriter,
        private readonly Config $config,
        private readonly CronRunLogger $cronRunLogger,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isShoppingFeedEnabled()) {
            $this->cronRunLogger->logSummary('skipped, shopping feed is disabled in config');
            return;
        }

        try {
            $result = $this->googleMerchantFeedGenerator->generate();
            $this->productFeedCacheWriter->writeSuccess($result['xml'], $result['productCount']);
            $this->cronRunLogger->logSummary(sprintf('generated feed with %d products', $result['productCount']));
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Ordo_Automation: shopping feed generation failed: %s', $e->getMessage()));
            $this->productFeedCacheWriter->writeError($e->getMessage());
            $this->cronRunLogger->logSummary('generation failed, see error log');
        }
    }
}
