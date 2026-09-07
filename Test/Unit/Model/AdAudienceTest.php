<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model;

use Ordo\Automation\Model\AdAudience;

class AdAudienceTest extends AbstractModelTestCase
{
    private function makeModel(): AdAudience
    {
        return new AdAudience($this->makeModelContext(), $this->makeRegistry(), $this->makeModelResource());
    }

    public function testEntityIdRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getEntityId());

        $model->setData('entity_id', '5');
        self::assertSame(5, $model->getEntityId());
    }

    public function testNameRoundTrip(): void
    {
        $model = $this->makeModel();
        $model->setName('Test Audience');
        self::assertSame('Test Audience', $model->getName());
    }

    public function testSegmentIdRoundTrip(): void
    {
        $model = $this->makeModel();
        $model->setSegmentId(3);
        self::assertSame(3, $model->getSegmentId());
    }

    public function testPlatformRoundTrip(): void
    {
        $model = $this->makeModel();
        $model->setPlatform(AdAudience::PLATFORM_GOOGLE_ADS);
        self::assertSame('google_ads', $model->getPlatform());
    }

    public function testExternalAudienceIdRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getExternalAudienceId());

        $model->setExternalAudienceId('customers/1/userLists/2');
        self::assertSame('customers/1/userLists/2', $model->getExternalAudienceId());

        $model->setExternalAudienceId(null);
        self::assertNull($model->getExternalAudienceId());
    }

    public function testEnabledRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertFalse($model->isEnabled());

        $model->setEnabled(true);
        self::assertTrue($model->isEnabled());
    }

    public function testLastSyncedAtRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getLastSyncedAt());

        $model->setLastSyncedAt('2026-01-01 00:00:00');
        self::assertSame('2026-01-01 00:00:00', $model->getLastSyncedAt());
    }

    public function testLastSyncStatusRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getLastSyncStatus());

        $model->setLastSyncStatus(AdAudience::STATUS_SUCCESS);
        self::assertSame('success', $model->getLastSyncStatus());
    }

    public function testLastSyncMessageRoundTrip(): void
    {
        $model = $this->makeModel();
        self::assertNull($model->getLastSyncMessage());

        $model->setLastSyncMessage('5 member(s) synced');
        self::assertSame('5 member(s) synced', $model->getLastSyncMessage());
    }
}
