<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Ordo\Automation\Model\WhatsAppTemplate;

/**
 * The category options for a WhatsApp template — wraps WhatsAppTemplate's own CATEGORY_*
 * constants. Meta requires every template to declare one of these; it drives both Meta's own
 * approval rules and per-message pricing (marketing is always billed outside the 24h customer
 * service window, utility/authentication have their own volume-tiered rates).
 */
class WhatsAppTemplateCategory implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => WhatsAppTemplate::CATEGORY_MARKETING, 'label' => __('Marketing')],
            ['value' => WhatsAppTemplate::CATEGORY_UTILITY, 'label' => __('Utility')],
            ['value' => WhatsAppTemplate::CATEGORY_AUTHENTICATION, 'label' => __('Authentication')],
        ];
    }
}
