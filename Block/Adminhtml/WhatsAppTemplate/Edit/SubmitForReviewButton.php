<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Adminhtml\WhatsAppTemplate\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Ordo\Automation\Block\Adminhtml\Shared\Edit\GenericButton;

/**
 * Visible for any already-saved template regardless of its current status - GenericButton (this
 * class's own base, shared by every entity's edit toolbar in this module) only exposes
 * entity_id, not the entity's own status, so this doesn't try to hide itself for an
 * already-pending/approved template. Re-submitting one of those is harmless: Controller\
 * Adminhtml\WhatsAppTemplate\SubmitForReview just re-registers the same content with Meta.
 */
class SubmitForReviewButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if (!$this->getEntityId()) {
            return [];
        }

        return [
            'label' => __('Submit for Review'),
            'class' => 'action-secondary',
            'on_click' => sprintf(
                "confirmSetLocation('%s', '%s')",
                __('Submit this template to Meta for approval?'),
                $this->getUrl('*/*/submitforreview', ['entity_id' => $this->getEntityId()])
            ),
            'sort_order' => 40,
        ];
    }
}
