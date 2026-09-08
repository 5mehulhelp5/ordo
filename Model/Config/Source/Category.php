<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * The "purchased_category" segment/campaign condition's dedicated category_id field - every real
 * category in the store, indented by depth (same "flat list standing in for a tree" approach
 * Magento's own admin uses in a handful of places, e.g. the widget category chooser's list
 * fallback) rather than a full tree-picker modal, which would need its own AJAX data provider
 * wired into a form that otherwise has none (see AGENTS.md/ROADMAP.md for this module's general
 * "simple, purpose-built UI over reusing a heavyweight core component" bias, already followed by
 * Controller\Adminhtml\FreeGiftOffer\ProductSearch's own picker instead of Bundle's Selection\Grid
 * or Catalog's Widget\Chooser).
 */
class Category implements OptionSourceInterface
{
    /** The synthetic root category (typically "Root Catalog") never has a real product assigned
     *  to it and is never a meaningful segment target - excluded rather than shown as a
     *  confusing top-level option with no real match potential. */
    private const int ROOT_LEVEL = 1;

    public function __construct(
        private readonly CategoryCollectionFactory $categoryCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public function toOptionArray(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name']);
        $collection->addAttributeToFilter('level', ['gt' => self::ROOT_LEVEL]);
        $collection->addAttributeToSort('path', 'ASC');

        $options = [];
        foreach ($collection as $category) {
            /** @var \Magento\Catalog\Model\Category $category */
            $depth = max(0, (int) $category->getLevel() - self::ROOT_LEVEL - 1);
            $options[] = [
                'value' => (int) $category->getId(),
                'label' => str_repeat('— ', $depth) . $category->getName(),
            ];
        }

        return $options;
    }
}
