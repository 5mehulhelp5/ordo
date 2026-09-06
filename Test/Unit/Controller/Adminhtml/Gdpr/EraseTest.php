<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\Gdpr;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\Gdpr\Erase;
use Ordo\Automation\Model\Gdpr\CustomerDataEraser;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

class EraseTest extends AbstractAdminActionTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenCustomerIdInvalid(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['customer_id', 0]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $customerDataEraser = $this->createMock(CustomerDataEraser::class);
        $customerDataEraser->expects(self::never())->method('erase');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $controller = new Erase($context, $customerDataEraser, $this->createStub(LoggerInterface::class));
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteErasesAndRedirectsOnSuccess(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['customer_id', 42]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')->with('*/*/index')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $customerDataEraser = $this->createMock(CustomerDataEraser::class);
        $customerDataEraser->expects(self::once())->method('erase')->with(42)
            ->willReturn(['ordo_customer_tag' => 2, 'ordo_customer_score' => 1]);
        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $controller = new Erase($context, $customerDataEraser, $logger);
        self::assertSame($redirect, $controller->execute());
    }
}
