<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\ProductFeed;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\ProductFeed\RefreshNow;
use Ordo\Automation\Model\ProductFeed\GoogleMerchantFeedGenerator;
use Ordo\Automation\Model\ProductFeed\ProductFeedCacheWriter;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class RefreshNowTest extends AbstractAdminActionTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteGeneratesWritesSuccessAndRedirects(): void
    {
        $context = $this->makeContext();

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')->with('ordo/dashboard/index')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $generator = $this->createMock(GoogleMerchantFeedGenerator::class);
        $generator->method('generate')->willReturn(['xml' => '<rss></rss>', 'productCount' => 7]);

        $cacheWriter = $this->createMock(ProductFeedCacheWriter::class);
        $cacheWriter->expects(self::once())->method('writeSuccess')->with('<rss></rss>', 7);
        $cacheWriter->expects(self::never())->method('writeError');

        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        $controller = new RefreshNow($context, $generator, $cacheWriter);
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteWritesErrorAndRedirectsWhenGenerationThrows(): void
    {
        $context = $this->makeContext();

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $generator = $this->createMock(GoogleMerchantFeedGenerator::class);
        $generator->method('generate')->willThrowException(new \RuntimeException('catalog error'));

        $cacheWriter = $this->createMock(ProductFeedCacheWriter::class);
        $cacheWriter->expects(self::once())->method('writeError')->with('catalog error');
        $cacheWriter->expects(self::never())->method('writeSuccess');

        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $controller = new RefreshNow($context, $generator, $cacheWriter);
        self::assertSame($redirect, $controller->execute());
    }
}
