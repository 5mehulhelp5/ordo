<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Ordo\Automation\Model\ResourceModel\WhatsAppTemplate\CollectionFactory as WhatsAppTemplateCollectionFactory;
use Ordo\Automation\Model\WhatsAppTemplate;

/**
 * Every APPROVED template, by id/name — the send_whatsapp campaign action's own template_id
 * field picker. Deliberately excludes draft/pending/rejected/disabled templates: Meta's own API
 * rejects a send using anything but an approved template, so offering one here would just be a
 * guaranteed-to-fail choice.
 */
class WhatsAppTemplateOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly WhatsAppTemplateCollectionFactory $whatsAppTemplateCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function toOptionArray(): array
    {
        $collection = $this->whatsAppTemplateCollectionFactory->create();
        $collection->addApprovedFilter();

        $options = [];
        foreach ($collection as $template) {
            /** @var WhatsAppTemplate $template */
            $options[] = ['value' => (int) $template->getEntityId(), 'label' => $template->getName()];
        }

        return $options;
    }
}
