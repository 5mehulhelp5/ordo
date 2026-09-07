<?php
declare(strict_types=1);

namespace Ordo\Automation\Ui\Component\Listing\Column;

class WhatsAppTemplateActions extends AbstractEntityActionsColumn
{
    protected function getEditUrlPath(): string
    {
        return 'ordo/whatsapptemplate/edit';
    }

    protected function getDeleteUrlPath(): string
    {
        return 'ordo/whatsapptemplate/delete';
    }

    protected function getEntityLabel(): string
    {
        return (string) __('WhatsApp template');
    }
}
