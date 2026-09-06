<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AdAudience extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('ordo_ad_audience', 'entity_id');
    }
}
