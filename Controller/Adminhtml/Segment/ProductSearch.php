<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\Segment;

use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Backs the "Purchased Product (SKU)" segment/campaign condition's SKU autocomplete field (see
 * view/adminhtml/web/js/segment-sku-autocomplete.js) - the same read-only, admin-only
 * SKU-or-name search Controller\Adminhtml\FreeGiftOffer\ProductSearch already implements, kept as
 * its own Segment-namespaced controller (own ACL resource: Ordo_Automation::segments, matching
 * every other Segment controller, rather than reaching into FreeGiftOffer's) instead of one shared
 * controller both features call cross-module. Same response shape as that controller's own
 * ("items": [{sku, name, qty, thumbnail_url}]) so a future shared picker JS module could serve
 * both without a response-shape adapter.
 */
class ProductSearch extends AbstractSegmentAction implements HttpGetActionInterface
{
    /** Matches the picker's own page size - enough to be useful, small enough to stay fast. */
    private const int MAX_RESULTS = 20;

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $rawTerm = $this->getRequest()->getParam('term', '');
        $term = trim(is_string($rawTerm) ? $rawTerm : '');
        $result = $this->resultJsonFactory->create();

        if ($term === '') {
            return $result->setData(['items' => []]);
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'sku']);
        $collection->addAttributeToFilter([
            ['attribute' => 'sku', 'like' => '%' . $term . '%'],
            ['attribute' => 'name', 'like' => '%' . $term . '%'],
        ]);
        $collection->setPageSize(self::MAX_RESULTS);
        $collection->setCurPage(1);

        $items = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $items[] = [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
            ];
        }

        return $result->setData(['items' => $items]);
    }
}
