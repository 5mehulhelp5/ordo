<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Frontend\ContentBlock;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Model\Context as ModelContext;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Ordo\Automation\Block\Frontend\ContentBlock\Render;
use Ordo\Automation\Model\ContentBlock;
use Ordo\Automation\Model\ContentBlock\Producer\ProducerInterface;
use Ordo\Automation\Model\ContentBlock\ProducerPool;
use Ordo\Automation\Model\ContentBlockRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

class RenderTest extends TestCase
{
    private ContentBlockRepository&\PHPUnit\Framework\MockObject\MockObject $contentBlockRepository;
    private ProducerPool&\PHPUnit\Framework\MockObject\MockObject $producerPool;
    private CustomerSession&\PHPUnit\Framework\MockObject\MockObject $customerSession;

    protected function setUp(): void
    {
        $this->contentBlockRepository = $this->createMock(ContentBlockRepository::class);
        $this->producerPool = $this->createMock(ProducerPool::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
    }

    private function makeBlock(array $data = []): Render
    {
        return new Render(
            $this->createStub(Context::class),
            $this->contentBlockRepository,
            $this->producerPool,
            $this->customerSession,
            $data
        );
    }

    private function makeContentBlock(string $type, bool $enabled): ContentBlock
    {
        $resource = $this->createStub(AbstractDb::class);
        $resource->method('getIdFieldName')->willReturn('entity_id');

        $contentBlock = new ContentBlock(
            $this->createStub(ModelContext::class),
            $this->createStub(Registry::class),
            $resource
        );
        $contentBlock->setType($type);
        $contentBlock->setEnabled($enabled);

        return $contentBlock;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testEmptyIdentifierRendersNothingWithoutLookup(): void
    {
        $this->contentBlockRepository->expects(self::never())->method('getByIdentifier');

        self::assertSame('', $this->makeBlock()->getContentHtml());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testUnknownIdentifierRendersNothing(): void
    {
        $this->contentBlockRepository->expects(self::once())
            ->method('getByIdentifier')->with('missing')->willReturn(null);
        $this->producerPool->expects(self::never())->method('get');

        self::assertSame('', $this->makeBlock(['identifier' => 'missing'])->getContentHtml());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDisabledBlockRendersNothing(): void
    {
        $block = $this->makeContentBlock('recommendations', false);
        $this->contentBlockRepository->method('getByIdentifier')->willReturn($block);
        $this->producerPool->expects(self::never())->method('get');

        self::assertSame('', $this->makeBlock(['identifier' => 'homepage_recs'])->getContentHtml());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testMissingProducerRendersNothing(): void
    {
        $block = $this->makeContentBlock('unregistered_type', true);
        $this->contentBlockRepository->method('getByIdentifier')->willReturn($block);
        $this->producerPool->expects(self::once())->method('get')->with('unregistered_type')->willReturn(null);

        self::assertSame('', $this->makeBlock(['identifier' => 'homepage_recs'])->getContentHtml());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnonymousVisitorRendersWithoutCustomerIdInContext(): void
    {
        $block = $this->makeContentBlock('recommendations', true);
        $this->contentBlockRepository->method('getByIdentifier')->willReturn($block);
        $this->customerSession->method('isLoggedIn')->willReturn(false);

        $producer = $this->createMock(ProducerInterface::class);
        $producer->expects(self::once())->method('render')->with($block, [])->willReturn('<p>Anon</p>');
        $this->producerPool->method('get')->willReturn($producer);

        self::assertSame('<p>Anon</p>', $this->makeBlock(['identifier' => 'homepage_recs'])->getContentHtml());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testLoggedInCustomerIdIsForwardedInContext(): void
    {
        $block = $this->makeContentBlock('recommendations', true);
        $this->contentBlockRepository->method('getByIdentifier')->willReturn($block);
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(42);

        $producer = $this->createMock(ProducerInterface::class);
        $producer->expects(self::once())->method('render')->with($block, ['customer_id' => 42])
            ->willReturn('<p>Hi 42</p>');
        $this->producerPool->method('get')->willReturn($producer);

        self::assertSame('<p>Hi 42</p>', $this->makeBlock(['identifier' => 'homepage_recs'])->getContentHtml());
    }
}
