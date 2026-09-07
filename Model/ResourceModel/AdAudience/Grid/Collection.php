<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\ResourceModel\AdAudience\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Standard admin grid collection — see Model\ResourceModel\Campaign\Grid\Collection for why
 * this is SearchResult-based rather than the plain AbstractCollection the rest of the module
 * uses. ordo_ad_audience already has a "name" column, so no aliasing is needed (see ScoreRule\
 * Grid\Collection for the entity that does need it).
 */
class Collection extends SearchResult
{
}
