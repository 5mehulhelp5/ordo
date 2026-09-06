<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ContentBlock\Producer;

use Ordo\Automation\Model\ContentBlock;
use Ordo\Automation\Model\Recommendation\ProductRecommendationRenderer;
use Ordo\Automation\Model\Recommendation\ProductRecommender;

/**
 * Config: {"count": 4} — how many products to recommend. Personalizes via
 * ProductRecommender::getRecommendedSkus() the same way Model\Campaign\Action\
 * AddProductRecommendations already does for email, just wired as a content-block type instead
 * of an action — so the same "customers who bought X also bought Y" affinity (falling back to
 * store-wide best-sellers for a never-ordered or anonymous visitor) can be embedded anywhere a
 * content block is used: a campaign email via AddDynamicContent, or directly on-site via
 * Block\Frontend\ContentBlock\Render, both passing $context['customer_id'] through unchanged.
 * An anonymous visitor (no customer_id in context) still gets the best-sellers fallback -
 * ProductRecommender::getRecommendedSkus() already treats customerId<=0 as "no purchase
 * history", not an error.
 */
class RecommendationProducer implements ProducerInterface
{
    private const int DEFAULT_COUNT = 4;

    public function __construct(
        private readonly ProductRecommender $productRecommender,
        private readonly ProductRecommendationRenderer $productRecommendationRenderer
    ) {
    }

    public function render(ContentBlock $block, array $context = []): string
    {
        $config = $block->getConfigArray();
        $count = (int) ($config['count'] ?? self::DEFAULT_COUNT);
        if ($count <= 0) {
            $count = self::DEFAULT_COUNT;
        }

        $customerId = (int) ($context['customer_id'] ?? 0);
        $skus = $this->productRecommender->getRecommendedSkus($customerId, $count);

        return $this->productRecommendationRenderer->renderHtml($skus);
    }
}
