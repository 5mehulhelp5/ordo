<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\WhatsAppTemplate;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\WhatsAppTemplate\Delete;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate as WhatsAppTemplateResource;
use Ordo\Automation\Model\WhatsAppTemplate;
use Ordo\Automation\Model\WhatsAppTemplateFactory;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class DeleteTest extends AbstractAdminActionTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenEntityIdMissing(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['entity_id', null]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $whatsAppTemplateFactory = $this->createMock(WhatsAppTemplateFactory::class);
        $whatsAppTemplateFactory->expects(self::never())->method('create');
        $whatsAppTemplateResource = $this->createStub(WhatsAppTemplateResource::class);

        $controller = new Delete($context, $whatsAppTemplateFactory, $whatsAppTemplateResource);
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteDeletesAndRedirectsOnSuccess(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['entity_id', 5]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        $template = $this->createStub(WhatsAppTemplate::class);
        $whatsAppTemplateFactory = $this->createMock(WhatsAppTemplateFactory::class);
        $whatsAppTemplateFactory->method('create')->willReturn($template);

        $whatsAppTemplateResource = $this->createMock(WhatsAppTemplateResource::class);
        $whatsAppTemplateResource->expects(self::once())->method('load')->with($template, 5);
        $whatsAppTemplateResource->expects(self::once())->method('delete')->with($template);

        $controller = new Delete($context, $whatsAppTemplateFactory, $whatsAppTemplateResource);
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenDeleteThrows(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([['entity_id', 5]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $template = $this->createStub(WhatsAppTemplate::class);
        $whatsAppTemplateFactory = $this->createMock(WhatsAppTemplateFactory::class);
        $whatsAppTemplateFactory->method('create')->willReturn($template);

        $whatsAppTemplateResource = $this->createMock(WhatsAppTemplateResource::class);
        $whatsAppTemplateResource->method('delete')->willThrowException(new \RuntimeException('locked'));

        $controller = new Delete($context, $whatsAppTemplateFactory, $whatsAppTemplateResource);
        self::assertSame($redirect, $controller->execute());
    }
}
