<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Adminhtml\WhatsAppTemplate\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Ordo\Automation\Block\Adminhtml\Shared\Edit\GenericButton;

class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Template'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'ordo_whatsapptemplate_form.ordo_whatsapptemplate_form',
                                'actionName' => 'save',
                                'params' => [false],
                            ],
                        ],
                    ],
                ],
            ],
            'sort_order' => 90,
        ];
    }
}
