<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\AdAudience;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\AdAudience\Delete;
use Ordo\Automation\Model\AdAudience;
use Ordo\Automation\Model\AdAudienceFactory;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;
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

        $adAudienceFactory = $this->createMock(AdAudienceFactory::class);
        $adAudienceFactory->expects(self::never())->method('create');
        $adAudienceResource = $this->createStub(AdAudienceResource::class);

        $controller = new Delete($context, $adAudienceFactory, $adAudienceResource);
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

        $adAudience = $this->createStub(AdAudience::class);
        $adAudienceFactory = $this->createMock(AdAudienceFactory::class);
        $adAudienceFactory->method('create')->willReturn($adAudience);

        $adAudienceResource = $this->createMock(AdAudienceResource::class);
        $adAudienceResource->expects(self::once())->method('load')->with($adAudience, 5);
        $adAudienceResource->expects(self::once())->method('delete')->with($adAudience);

        $controller = new Delete($context, $adAudienceFactory, $adAudienceResource);
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

        $adAudience = $this->createStub(AdAudience::class);
        $adAudienceFactory = $this->createMock(AdAudienceFactory::class);
        $adAudienceFactory->method('create')->willReturn($adAudience);

        $adAudienceResource = $this->createMock(AdAudienceResource::class);
        $adAudienceResource->method('delete')->willThrowException(new \RuntimeException('locked'));

        $controller = new Delete($context, $adAudienceFactory, $adAudienceResource);
        self::assertSame($redirect, $controller->execute());
    }
}
