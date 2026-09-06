<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\Gdpr;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\Gdpr\SetConsent;
use Ordo\Automation\Model\ConsentManager;
use Ordo\Automation\Test\Unit\Controller\AbstractAdminActionTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

class SetConsentTest extends AbstractAdminActionTestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenCustomerIdMissing(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([
            ['customer_id', null],
            ['channel', 'email'],
            ['consented', '1'],
            ['email', ''],
        ]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $consentManager = $this->createMock(ConsentManager::class);
        $consentManager->expects(self::never())->method('setConsent');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $controller = new SetConsent($context, $consentManager);
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteRedirectsWithErrorWhenChannelInvalid(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([
            ['customer_id', 42],
            ['channel', 'carrier_pigeon'],
            ['consented', '1'],
            ['email', ''],
        ]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $consentManager = $this->createMock(ConsentManager::class);
        $consentManager->expects(self::never())->method('setConsent');
        $this->messageManager->expects(self::once())->method('addErrorMessage');

        $controller = new SetConsent($context, $consentManager);
        self::assertSame($redirect, $controller->execute());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteSetsConsentAndRedirectsOnSuccess(): void
    {
        $context = $this->makeContext();
        $this->request->method('getParam')->willReturnMap([
            ['customer_id', 42],
            ['channel', ConsentManager::CHANNEL_EMAIL],
            ['consented', '0'],
            ['email', 'jan@example.com'],
        ]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')
            ->with('*/*/index', ['email' => 'jan@example.com'])->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $consentManager = $this->createMock(ConsentManager::class);
        $consentManager->expects(self::once())->method('setConsent')
            ->with(42, ConsentManager::CHANNEL_EMAIL, false, 'admin');
        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        $controller = new SetConsent($context, $consentManager);
        self::assertSame($redirect, $controller->execute());
    }
}
