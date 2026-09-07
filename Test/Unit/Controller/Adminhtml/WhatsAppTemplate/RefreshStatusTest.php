<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\WhatsAppTemplate;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\WhatsAppTemplate\RefreshStatus;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate as WhatsAppTemplateResource;
use Ordo\Automation\Model\WhatsApp\WhatsAppTemplateClient;
use Ordo\Automation\Model\WhatsAppTemplate;
use Ordo\Automation\Model\WhatsAppTemplateFactory;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class RefreshStatusTest extends AbstractAdminActionTestCase
{
    private WhatsAppTemplateFactory $whatsAppTemplateFactory;
    private WhatsAppTemplateResource $whatsAppTemplateResource;
    private WhatsAppTemplateClient $whatsAppTemplateClient;

    protected function setUp(): void
    {
        $this->whatsAppTemplateFactory = $this->createMock(WhatsAppTemplateFactory::class);
        $this->whatsAppTemplateResource = $this->createMock(WhatsAppTemplateResource::class);
        $this->whatsAppTemplateClient = $this->createMock(WhatsAppTemplateClient::class);
    }

    private function makeController(): RefreshStatus
    {
        return new RefreshStatus(
            $this->makeContext(),
            $this->whatsAppTemplateFactory,
            $this->whatsAppTemplateResource,
            $this->whatsAppTemplateClient
        );
    }

    private function redirect(): Redirect
    {
        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        return $redirect;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenTemplateNotFound(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['entity_id', null, 5]]);
        $redirect = $this->redirect();

        $template = $this->createStub(WhatsAppTemplate::class);
        $this->whatsAppTemplateFactory->method('create')->willReturn($template);

        $this->whatsAppTemplateClient->expects(self::never())->method('getTemplateStatus');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenNotYetSubmittedToMeta(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['entity_id', null, 5]]);
        $redirect = $this->redirect();

        $template = $this->createStub(WhatsAppTemplate::class);
        $template->method('getEntityId')->willReturn(5);
        $template->method('getMetaTemplateId')->willReturn(null);
        $this->whatsAppTemplateFactory->method('create')->willReturn($template);

        $this->whatsAppTemplateClient->expects(self::never())->method('getTemplateStatus');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteUpdatesStatusFromMetaOnSuccess(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['entity_id', null, 5]]);
        $redirect = $this->redirect();

        $template = $this->createMock(WhatsAppTemplate::class);
        $template->method('getEntityId')->willReturn(5);
        $template->method('getMetaTemplateId')->willReturn('meta-template-123');
        $template->method('getStatus')->willReturn(WhatsAppTemplate::STATUS_APPROVED);
        $this->whatsAppTemplateFactory->method('create')->willReturn($template);

        $this->whatsAppTemplateClient->expects(self::once())->method('getTemplateStatus')
            ->with('meta-template-123')
            ->willReturn(['status' => 'APPROVED', 'rejectionReason' => null]);

        $template->expects(self::once())->method('setStatus')->with(WhatsAppTemplate::STATUS_APPROVED);
        $template->expects(self::once())->method('setRejectionReason')->with(null);
        $this->whatsAppTemplateResource->expects(self::once())->method('save')->with($template);
        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteKeepsExistingStatusWhenMetaReturnsUnknownStatus(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['entity_id', null, 5]]);
        $redirect = $this->redirect();

        $template = $this->createMock(WhatsAppTemplate::class);
        $template->method('getEntityId')->willReturn(5);
        $template->method('getMetaTemplateId')->willReturn('meta-template-123');
        $template->method('getStatus')->willReturn(WhatsAppTemplate::STATUS_PENDING);
        $this->whatsAppTemplateFactory->method('create')->willReturn($template);

        $this->whatsAppTemplateClient->method('getTemplateStatus')
            ->willReturn(['status' => 'IN_APPEAL', 'rejectionReason' => null]);

        $template->expects(self::once())->method('setStatus')->with(WhatsAppTemplate::STATUS_PENDING);
        $this->whatsAppTemplateResource->expects(self::once())->method('save')->with($template);

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenMetaCallThrows(): void
    {
        $controller = $this->makeController();
        $this->request->method('getParam')->willReturnMap([['entity_id', null, 5]]);
        $redirect = $this->redirect();

        $template = $this->createMock(WhatsAppTemplate::class);
        $template->method('getEntityId')->willReturn(5);
        $template->method('getMetaTemplateId')->willReturn('meta-template-123');
        $this->whatsAppTemplateFactory->method('create')->willReturn($template);

        $this->whatsAppTemplateClient->method('getTemplateStatus')
            ->willThrowException(new \RuntimeException('Meta API down'));

        $this->whatsAppTemplateResource->expects(self::never())->method('save');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        self::assertSame($redirect, $controller->execute());
    }
}
