<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ContentBlock\Producer;

use Ordo\Automation\Model\ContentBlock;

/**
 * Turns a content block's config into the email-ready HTML fragment
 * Model\Campaign\Action\AddDynamicContent writes into the dispatch context. Registered by
 * string key (matching ContentBlock::getType()) in di.xml (Model\ContentBlock\ProducerPool),
 * same shape as Api\Campaign\ActionInterface/ActionPool.
 */
interface ProducerInterface
{
    /**
     * Never throws — a producer that can't resolve content (bad config, network failure,
     * missing cache row) returns '' so a broken content block degrades to no content rather
     * than breaking the whole email send.
     *
     * @param array<string, mixed> $context Whatever the caller already has to hand — a campaign
     *   dispatch's own context (customer_id, etc.) via AddDynamicContent, or the current
     *   storefront visitor's identity via Block\Frontend\ContentBlock\Render. Optional and
     *   ignored by producers that don't need it (snippet/rss/product_feed aren't
     *   customer-specific); RecommendationProducer reads context['customer_id'] to personalize.
     */
    public function render(ContentBlock $block, array $context = []): string;
}
