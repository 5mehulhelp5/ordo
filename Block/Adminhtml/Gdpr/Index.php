<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Adminhtml\Gdpr;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Ordo\Automation\Model\ConsentManager;

class Index extends Template
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly ConsentManager $consentManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCustomerId(): int
    {
        return (int) $this->registry->registry('ordo_gdpr_customer_id');
    }

    public function getCustomerEmail(): string
    {
        return (string) $this->registry->registry('ordo_gdpr_customer_email');
    }

    public function isCustomerResolved(): bool
    {
        return $this->getCustomerId() > 0;
    }

    /**
     * @return array{email: bool, sms: bool, push: bool}
     */
    public function getConsentStates(): array
    {
        $states = $this->consentManager->getConsentStates($this->getCustomerId());

        return [
            ConsentManager::CHANNEL_EMAIL => $states[ConsentManager::CHANNEL_EMAIL] ?? true,
            ConsentManager::CHANNEL_SMS => $states[ConsentManager::CHANNEL_SMS] ?? true,
            ConsentManager::CHANNEL_PUSH => $states[ConsentManager::CHANNEL_PUSH] ?? true,
        ];
    }

    public function getSearchFormAction(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getSetConsentFormAction(): string
    {
        return $this->getUrl('*/*/setConsent');
    }

    public function getExportUrl(): string
    {
        return $this->getUrl('*/*/export', ['customer_id' => $this->getCustomerId()]);
    }

    public function getEraseFormAction(): string
    {
        return $this->getUrl('*/*/erase');
    }
}
