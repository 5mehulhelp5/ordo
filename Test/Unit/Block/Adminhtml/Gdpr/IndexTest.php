<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Block\Adminhtml\Gdpr;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Ordo\Automation\Block\Adminhtml\Gdpr\Index;
use Ordo\Automation\Model\ConsentChannel;
use Ordo\Automation\Model\ConsentManager;
use PHPUnit\Framework\TestCase;

/**
 * Same ObjectManager-singleton technique as Segment\BulkActionsTest - Backend\Block\Template's
 * own constructor falls back to it for jsonHelper/directoryHelper.
 */
class IndexTest extends TestCase
{
    private Registry $registry;
    private ConsentManager $consentManager;
    private UrlInterface $urlBuilder;
    private Index $block;

    protected function setUp(): void
    {
        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturn($this->createStub(\stdClass::class));
        ObjectManager::setInstance($objectManager);

        $this->registry = $this->createStub(Registry::class);
        $this->consentManager = $this->createStub(ConsentManager::class);
        $this->urlBuilder = $this->createStub(UrlInterface::class);

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($this->urlBuilder);

        $this->block = new Index($context, $this->registry, $this->consentManager);
    }

    protected function tearDown(): void
    {
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    public function testGetCustomerIdReturnsZeroWhenNothingRegistered(): void
    {
        $this->registry->method('registry')->willReturn(null);

        self::assertSame(0, $this->block->getCustomerId());
    }

    public function testGetCustomerIdReturnsRegisteredValue(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_id', 42]]);

        self::assertSame(42, $this->block->getCustomerId());
    }

    public function testGetCustomerEmailReturnsRegisteredValue(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_email', 'jan@example.com']]);

        self::assertSame('jan@example.com', $this->block->getCustomerEmail());
    }

    public function testIsCustomerResolvedIsFalseWithoutACustomerId(): void
    {
        $this->registry->method('registry')->willReturn(null);

        self::assertFalse($this->block->isCustomerResolved());
    }

    public function testIsCustomerResolvedIsTrueWithACustomerId(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_id', 42]]);

        self::assertTrue($this->block->isCustomerResolved());
    }

    public function testGetConsentStatesDefaultsToTrueForEveryChannelWhenNoneRecorded(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_id', 42]]);
        $this->consentManager->method('getConsentStates')->willReturn([]);

        $states = $this->block->getConsentStates();

        self::assertTrue($states->isEmailConsented());
        self::assertTrue($states->isSmsConsented());
        self::assertTrue($states->isPushConsented());
        self::assertTrue($states->isWhatsAppConsented());
        self::assertTrue($states->isAdsConsented());
        self::assertSame(
            [
                ConsentChannel::Email->value => true,
                ConsentChannel::Sms->value => true,
                ConsentChannel::Push->value => true,
                ConsentChannel::WhatsApp->value => true,
                ConsentChannel::Ads->value => true,
            ],
            iterator_to_array($states)
        );
    }

    public function testGetConsentStatesReflectsRecordedOptOut(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_id', 42]]);
        $this->consentManager->method('getConsentStates')->willReturn([ConsentChannel::Email->value => false]);

        $states = $this->block->getConsentStates();

        self::assertFalse($states->isEmailConsented());
        self::assertTrue($states->isSmsConsented());
    }

    public function testGetSearchFormActionBuildsIndexUrl(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://example.com/admin/ordo/gdpr/index/');

        self::assertSame('https://example.com/admin/ordo/gdpr/index/', $this->block->getSearchFormAction());
    }

    public function testGetSetConsentFormActionBuildsSetConsentUrl(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://example.com/admin/ordo/gdpr/setconsent/');

        self::assertSame('https://example.com/admin/ordo/gdpr/setconsent/', $this->block->getSetConsentFormAction());
    }

    public function testGetExportUrlIncludesCustomerId(): void
    {
        $this->registry->method('registry')->willReturnMap([['ordo_gdpr_customer_id', 42]]);
        $this->urlBuilder->method('getUrl')
            ->willReturnMap([['*/*/export', ['customer_id' => 42], 'https://example.com/admin/ordo/gdpr/export/customer_id/42/']]);

        self::assertSame(
            'https://example.com/admin/ordo/gdpr/export/customer_id/42/',
            $this->block->getExportUrl()
        );
    }

    public function testGetEraseFormActionBuildsEraseUrl(): void
    {
        $this->urlBuilder->method('getUrl')->willReturn('https://example.com/admin/ordo/gdpr/erase/');

        self::assertSame('https://example.com/admin/ordo/gdpr/erase/', $this->block->getEraseFormAction());
    }
}
