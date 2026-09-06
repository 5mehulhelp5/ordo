<?php
declare(strict_types=1);

namespace Ordo\Automation\Ui\Component\Listing\Column;

class AdAudienceActions extends AbstractEntityActionsColumn
{
    protected function getEditUrlPath(): string
    {
        return 'ordo/adaudience/edit';
    }

    protected function getDeleteUrlPath(): string
    {
        return 'ordo/adaudience/delete';
    }

    protected function getEntityLabel(): string
    {
        return (string) __('ad audience');
    }
}
