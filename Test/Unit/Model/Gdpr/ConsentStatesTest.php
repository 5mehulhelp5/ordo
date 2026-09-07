<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Gdpr;

use Ordo\Automation\Model\ConsentChannel;
use Ordo\Automation\Model\Gdpr\ConsentStates;
use PHPUnit\Framework\TestCase;

class ConsentStatesTest extends TestCase
{
    public function testGettersReflectTheConstructorArguments(): void
    {
        $states = new ConsentStates(email: false, sms: true, push: false, whatsapp: true, ads: false);

        self::assertFalse($states->isEmailConsented());
        self::assertTrue($states->isSmsConsented());
        self::assertFalse($states->isPushConsented());
        self::assertTrue($states->isWhatsAppConsented());
        self::assertFalse($states->isAdsConsented());
    }

    public function testIteratesAsChannelValueToConsentedMap(): void
    {
        $states = new ConsentStates(email: true, sms: false, push: true, whatsapp: false, ads: true);

        self::assertSame(
            [
                ConsentChannel::Email->value => true,
                ConsentChannel::Sms->value => false,
                ConsentChannel::Push->value => true,
                ConsentChannel::WhatsApp->value => false,
                ConsentChannel::Ads->value => true,
            ],
            iterator_to_array($states)
        );
    }
}
