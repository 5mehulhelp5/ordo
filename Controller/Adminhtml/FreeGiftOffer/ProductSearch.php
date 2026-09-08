<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Adminhtml\FreeGiftOffer;

use Magento\Backend\App\Action\Context;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Backs the Free Gift Offer edit page's product picker (autocomplete-with-thumbnail and the
 * "Choose from Catalog" bulk modal — ROADMAP-adjacent UX work, reported directly: a bare SKU
 * text field with no visual confirmation of what was typed is easy to mistype into a real,
 * silent misconfiguration). Read-only, admin-only search over the product grid by SKU or name;
 * no core Magento controller returns this shape as plain JSON (the closest real examples,
 * Bundle's Selection\Grid and Catalog's Widget\Chooser, both render a whole HTML grid instead),
 * so this is a small purpose-built endpoint rather than an adaptation of an existing one.
 */
class ProductSearch extends AbstractFreeGiftOfferAction implements HttpGetActionInterface
{
    /** Matches the picker's own page size - enough to be useful, small enough to stay fast. */
    private const MAX_RESULTS = 20;

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly ImageHelper $imageHelper
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
        $collection->addAttributeToSelect(['name', 'sku', 'thumbnail']);
        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->addAttributeToFilter([
            ['attribute' => 'sku', 'like' => '%' . $term . '%'],
            ['attribute' => 'name', 'like' => '%' . $term . '%'],
        ]);
        $collection->setPageSize(self::MAX_RESULTS);
        $collection->setCurPage(1);

        $items = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $qty = $product->getData('qty');
            $items[] = [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'qty' => is_numeric($qty) ? (int) $qty : null,
                'thumbnail_url' => $this->buildThumbnailUrl($product),
            ];
        }

        return $result->setData(['items' => $items]);
    }

    private function buildThumbnailUrl(Product $product): ?string
    {
        // getData('thumbnail'), not the magic getThumbnail() accessor - same reasoning as qty
        // above: a plain getData() call is directly mockable in a unit test, where a magic
        // __call-based accessor is not.
        $thumbnail = $product->getData('thumbnail');

        if (!is_string($thumbnail) || $thumbnail === '' || $thumbnail === 'no_selection') {
            return null;
        }

        return $this->imageHelper
            ->init($product, 'product_thumbnail_image')
            ->setImageFile($thumbnail)
            ->resize(40, 40)
            ->getUrl();
    }
}
