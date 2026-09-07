<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Frontend;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Ordo\Automation\Helper\Config;

class TrackerViewModel implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function isTrackingEnabled(): bool
    {
        return $this->config->isTrackingEnabled();
    }

    public function isPopupEnabled(): bool
    {
        return $this->config->isPopupEnabled();
    }

    public function getPopupPollIntervalSeconds(): int
    {
        return $this->config->getPopupPollIntervalSeconds();
    }

    public function isNotificationEnabled(): bool
    {
        return $this->config->isNotificationEnabled();
    }

    public function getNotificationPollIntervalSeconds(): int
    {
        return $this->config->getNotificationPollIntervalSeconds();
    }

    public function isNpsSurveyEnabled(): bool
    {
        return $this->config->isNpsSurveyEnabled();
    }

    public function getNpsSurveyPollIntervalSeconds(): int
    {
        return $this->config->getNpsSurveyPollIntervalSeconds();
    }

    public function isPushEnabled(): bool
    {
        return $this->config->isPushEnabled();
    }

    /**
     * Not a secret - safe to render straight into the page, same as any other applicationServerKey
     * example in the Web Push spec itself.
     */
    public function getVapidPublicKey(): string
    {
        return $this->config->getVapidPublicKey();
    }
}
