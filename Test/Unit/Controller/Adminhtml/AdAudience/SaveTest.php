<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\AdAudience;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\AdAudience\Save;
use Ordo\Automation\Model\AdAudience;
use Ordo\Automation\Model\AdAudienceFactory;
use Ordo\Automation\Model\ResourceModel\AdAudience as AdAudienceResource;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class SaveTest extends AbstractAdminActionTestCase
{
    private AdAudienceFactory $adAudienceFactory;
    private AdAudienceResource $adAudienceResource;

    protected function setUp(): void
    {
        $this->adAudienceFactory = $this->createMock(AdAudienceFactory::class);
        $this->adAudienceResource = $this->createMock(AdAudienceResource::class);
    }

    private function makeController(): Save
    {
        return new Save($this->makeContext(), $this->adAudienceFactory, $this->adAudienceResource);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsImmediatelyWhenNoPostData(): void
    {
        $controller = $this->makeController();
        $this->request->method('getPostValue')->willReturn(null);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $this->adAudienceFactory->expects(self::never())->method('create');

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteSavesNewAdAudienceAndRedirectsToGrid(): void
    {
        $controller = $this->makeController();
        $postData = [
            'entity_id' => 0,
            'name' => 'Test Audience',
            'segment_id' => '3',
            'platform' => 'google_ads',
            'external_audience_id' => '',
            'enabled' => '1',
        ];
        $this->request->method('getPostValue')->willReturn($postData);
        $this->request->method('getParam')->willReturnMap([['back', null]]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')->with('*/*/')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $adAudience = $this->createMock(AdAudience::class);
        $adAudience->expects(self::once())->method('setName')->with('Test Audience');
        $adAudience->expects(self::once())->method('setSegmentId')->with(3);
        $adAudience->expects(self::once())->method('setPlatform')->with('google_ads');
        $adAudience->expects(self::once())->method('setExternalAudienceId')->with(null);
        $adAudience->expects(self::once())->method('setEnabled')->with(true);
        $this->adAudienceFactory->method('create')->willReturn($adAudience);

        $this->adAudienceResource->expects(self::once())->method('save')->with($adAudience);
        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteLoadsExistingAdAudienceAndRedirectsBackWhenRequested(): void
    {
        $controller = $this->makeController();
        $postData = [
            'entity_id' => '7',
            'name' => 'Test Audience',
            'segment_id' => '3',
            'platform' => 'google_ads',
            'external_audience_id' => '',
            'enabled' => '1',
            'back' => '1',
        ];
        $this->request->method('getPostValue')->willReturn($postData);
        $this->request->method('getParam')->willReturnMap([['back', null, '1']]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')
            ->with('*/*/edit', ['entity_id' => 7])->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $adAudience = $this->createMock(AdAudience::class);
        $adAudience->method('getEntityId')->willReturn(7);
        $this->adAudienceFactory->method('create')->willReturn($adAudience);

        $this->adAudienceResource->expects(self::once())->method('load')->with($adAudience, 7);
        $this->adAudienceResource->expects(self::once())->method('save')->with($adAudience);

        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenSaveThrows(): void
    {
        $controller = $this->makeController();
        $this->request->method('getPostValue')->willReturn(['entity_id' => 0, 'name' => 'X']);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $adAudience = $this->createStub(AdAudience::class);
        $this->adAudienceFactory->method('create')->willReturn($adAudience);
        $this->adAudienceResource->method('save')->willThrowException(new \RuntimeException('db down'));

        $this->messageManager->expects(self::once())->method('addErrorMessage');

        self::assertSame($redirect, $controller->execute());
    }
}
