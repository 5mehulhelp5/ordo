<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Controller\Adminhtml\Gdpr;

use Magento\Backend\Model\View\Result\Redirect;
use Ordo\Automation\Controller\Adminhtml\Gdpr\SetConsent;
use Ordo\Automation\Model\ConsentChannel;
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
            ['channel', ConsentChannel::Email->value],
            ['consented', '0'],
            ['email', 'jan@example.com'],
        ]);

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects(self::once())->method('setPath')
            ->with('*/*/index', ['email' => 'jan@example.com'])->willReturnSelf();
        $this->resultRedirectFactory->method('create')->willReturn($redirect);

        $consentManager = $this->createMock(ConsentManager::class);
        $consentManager->expects(self::once())->method('setConsent')
            ->with(42, ConsentChannel::Email, false, 'admin');
        $this->messageManager->expects(self::once())->method('addSuccessMessage');

        $controller = new SetConsent($context, $consentManager);
        self::assertSame($redirect, $controller->execute());
    }

    /**
     * Regression test for a real bug a code audit found: this controller's channel allow-list
     * used to be a hand-maintained array that had quietly gone stale (missing WhatsApp entirely)
     * when that channel was added elsewhere. ConsentChannel::tryFrom() can't go stale the same
     * way - it validates against the one place every channel is actually defined.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteAcceptsWhatsAppAndAdsChannels(): void
    {
        foreach ([ConsentChannel::WhatsApp, ConsentChannel::Ads] as $channel) {
            $context = $this->makeContext();
            $this->request->method('getParam')->willReturnMap([
                ['customer_id', 42],
                ['channel', $channel->value],
                ['consented', '0'],
                ['email', 'jan@example.com'],
            ]);

            $redirect = $this->createMock(Redirect::class);
            $redirect->method('setPath')->willReturnSelf();
            $this->resultRedirectFactory->method('create')->willReturn($redirect);

            $consentManager = $this->createMock(ConsentManager::class);
            $consentManager->expects(self::once())->method('setConsent')->with(42, $channel, false, 'admin');
            $this->messageManager->expects(self::once())->method('addSuccessMessage');

            $controller = new SetConsent($context, $consentManager);
            self::assertSame($redirect, $controller->execute());
        }
    }
}
