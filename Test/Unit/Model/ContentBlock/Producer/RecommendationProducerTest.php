<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\ContentBlock\Producer;

use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Ordo\Automation\Model\ContentBlock;
use Ordo\Automation\Model\ContentBlock\Producer\RecommendationProducer;
use Ordo\Automation\Model\Recommendation\ProductRecommendationRenderer;
use Ordo\Automation\Model\Recommendation\ProductRecommender;
use PHPUnit\Framework\TestCase;

class RecommendationProducerTest extends TestCase
{
    private ProductRecommender&\PHPUnit\Framework\MockObject\MockObject $productRecommender;
    private ProductRecommendationRenderer&\PHPUnit\Framework\MockObject\MockObject $productRecommendationRenderer;
    private RecommendationProducer $producer;

    protected function setUp(): void
    {
        $this->productRecommender = $this->createMock(ProductRecommender::class);
        $this->productRecommendationRenderer = $this->createMock(ProductRecommendationRenderer::class);
        $this->producer = new RecommendationProducer($this->productRecommender, $this->productRecommendationRenderer);
    }

    private function makeBlock(array $config): ContentBlock
    {
        $resource = $this->createStub(AbstractDb::class);
        $resource->method('getIdFieldName')->willReturn('entity_id');

        $block = new ContentBlock($this->createStub(Context::class), $this->createStub(Registry::class), $resource);
        $block->setConfigArray($config);

        return $block;
    }

    public function testUsesDefaultCountOfFourWhenConfigIsMissing(): void
    {
        $this->productRecommender->expects(self::once())
            ->method('getRecommendedSkus')
            ->with(42, 4)
            ->willReturn(['SKU-1']);
        $this->productRecommendationRenderer->expects(self::once())
            ->method('renderHtml')
            ->with(['SKU-1'])
            ->willReturn('<div>rendered</div>');

        $html = $this->producer->render($this->makeBlock([]), ['customer_id' => 42]);

        self::assertSame('<div>rendered</div>', $html);
    }

    public function testHonorsCustomCountFromConfig(): void
    {
        $this->productRecommender->expects(self::once())->method('getRecommendedSkus')->with(42, 8)->willReturn([]);
        $this->productRecommendationRenderer->expects(self::once())->method('renderHtml')->willReturn('');

        $this->producer->render($this->makeBlock(['count' => 8]), ['customer_id' => 42]);
    }

    public function testNonPositiveCountFallsBackToDefault(): void
    {
        $this->productRecommender->expects(self::once())->method('getRecommendedSkus')->with(42, 4)->willReturn([]);
        $this->productRecommendationRenderer->expects(self::once())->method('renderHtml')->willReturn('');

        $this->producer->render($this->makeBlock(['count' => -5]), ['customer_id' => 42]);
    }

    /**
     * No customer_id in context (anonymous visitor, or a producer called from a context that
     * never had one) - ProductRecommender::getRecommendedSkus() already treats customerId<=0 as
     * "no purchase history" and falls back to store-wide best-sellers; this producer must pass
     * 0 through rather than skip the call entirely, unlike Campaign\Action\
     * AddProductRecommendations, which deliberately no-ops for an anonymous dispatch context.
     */
    public function testMissingCustomerIdStillCallsRecommenderWithZero(): void
    {
        $this->productRecommender->expects(self::once())->method('getRecommendedSkus')->with(0, 4)->willReturn([]);
        $this->productRecommendationRenderer->expects(self::once())->method('renderHtml')->willReturn('');

        $this->producer->render($this->makeBlock([]), []);
    }

    public function testEmptySkuListStillCallsRendererAndReturnsItsResult(): void
    {
        $this->productRecommender->expects(self::once())->method('getRecommendedSkus')->willReturn([]);
        $this->productRecommendationRenderer->expects(self::once())->method('renderHtml')->with([])->willReturn('');

        $html = $this->producer->render($this->makeBlock([]), ['customer_id' => 42]);

        self::assertSame('', $html);
    }
}
