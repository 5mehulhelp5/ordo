<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Purchase;

use Magento\Framework\App\ResourceConnection;

/**
 * "Has this customer ever purchased SKU X / anything from category Y (including subcategories)?"
 * - the two condition types Model\Campaign\Condition\PurchasedSku and \PurchasedCategory delegate
 * to, plus the set-level counterparts Model\Segment\SegmentMemberResolver's own resolveCondition()
 * uses for 'purchased_sku'/'purchased_category'. Same "derive live from sales_order_item every
 * time, no separate ledger" approach as Model\Rfm\RfmCalculator, and the same
 * single-customer-lookup + whole-customer-base-query pairing that class establishes (a condition
 * class calls the single-customer method; SegmentMemberResolver calls the bulk one).
 *
 * "Purchased" means at least one non-canceled order line item - same exclusion
 * RfmCalculator/CreditLimitCalculator already use for "counts as a real purchase".
 */
class PurchasedProductResolver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return bool true if $customerId has a non-canceled order line item for exactly this SKU
     */
    public function hasPurchasedSku(int $customerId, string $sku): bool
    {
        if ($customerId <= 0 || $sku === '') {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();

        $count = $connection->fetchOne(
            $connection->select()
                ->from(['o' => $this->resourceConnection->getTableName('sales_order')], ['count' => new \Zend_Db_Expr('COUNT(*)')])
                ->joinInner(
                    ['oi' => $this->resourceConnection->getTableName('sales_order_item')],
                    'oi.order_id = o.entity_id',
                    []
                )
                ->where('o.customer_id = ?', $customerId)
                ->where('o.state != ?', 'canceled')
                ->where('oi.sku = ?', $sku)
        );

        return (int) $count > 0;
    }

    /**
     * Every customer_id with at least one non-canceled order line item for this SKU - the
     * set-level counterpart to hasPurchasedSku(), used by SegmentMemberResolver so a "purchased
     * SKU X" segment condition resolves to a real membership list instead of falling into the
     * order_total_gte-style "always resolves to nobody at the set level" bucket.
     *
     * @return int[]
     */
    public function getCustomerIdsWhoPurchasedSku(string $sku): array
    {
        if ($sku === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        $ids = $connection->fetchCol(
            $connection->select()
                ->from(['o' => $this->resourceConnection->getTableName('sales_order')], [])
                ->joinInner(
                    ['oi' => $this->resourceConnection->getTableName('sales_order_item')],
                    'oi.order_id = o.entity_id',
                    []
                )
                ->where('o.customer_id IS NOT NULL')
                ->where('o.state != ?', 'canceled')
                ->where('oi.sku = ?', $sku)
                ->distinct(true)
                ->columns('o.customer_id')
        );

        return array_map('intval', $ids);
    }

    /**
     * @return bool true if $customerId has a non-canceled order line item for any product that
     *  belongs to $categoryId or any of its subcategories
     */
    public function hasPurchasedInCategory(int $customerId, int $categoryId): bool
    {
        if ($customerId <= 0 || $categoryId <= 0) {
            return false;
        }

        $productIds = $this->getProductIdsInCategorySubtree($categoryId);
        if ($productIds === []) {
            return false;
        }

        $connection = $this->resourceConnection->getConnection();

        $count = $connection->fetchOne(
            $connection->select()
                ->from(['o' => $this->resourceConnection->getTableName('sales_order')], ['count' => new \Zend_Db_Expr('COUNT(*)')])
                ->joinInner(
                    ['oi' => $this->resourceConnection->getTableName('sales_order_item')],
                    'oi.order_id = o.entity_id',
                    []
                )
                ->where('o.customer_id = ?', $customerId)
                ->where('o.state != ?', 'canceled')
                ->where('oi.product_id IN (?)', $productIds)
        );

        return (int) $count > 0;
    }

    /**
     * Set-level counterpart to hasPurchasedInCategory(), same role as
     * getCustomerIdsWhoPurchasedSku() above.
     *
     * @return int[]
     */
    public function getCustomerIdsWhoPurchasedInCategory(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [];
        }

        $productIds = $this->getProductIdsInCategorySubtree($categoryId);
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        $ids = $connection->fetchCol(
            $connection->select()
                ->from(['o' => $this->resourceConnection->getTableName('sales_order')], [])
                ->joinInner(
                    ['oi' => $this->resourceConnection->getTableName('sales_order_item')],
                    'oi.order_id = o.entity_id',
                    []
                )
                ->where('o.customer_id IS NOT NULL')
                ->where('o.state != ?', 'canceled')
                ->where('oi.product_id IN (?)', $productIds)
                ->distinct(true)
                ->columns('o.customer_id')
        );

        return array_map('intval', $ids);
    }

    /**
     * Every product_id directly assigned to $categoryId or to any category in its subtree -
     * "subtree" resolved via catalog_category_entity's own materialized path column (e.g.
     * "1/2/15"), the standard Magento way to find descendants without a recursive query:
     * a descendant's path either equals the target's own path (the target itself) or starts with
     * "{target path}/". A self-join in one query rather than fetching the target's path first and
     * building a second LIKE query from it - one round trip instead of two, and no risk of the
     * target's path changing between the two queries under concurrent category moves.
     *
     * @return int[]
     */
    private function getProductIdsInCategorySubtree(int $categoryId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $categoryTable = $this->resourceConnection->getTableName('catalog_category_entity');
        $categoryProductTable = $this->resourceConnection->getTableName('catalog_category_product');

        // The join condition itself references only column names and a fixed '/%' literal (no
        // user-supplied value in it - $categoryId is bound separately below via a real `?`
        // placeholder), so it's embedded directly rather than through quoteInto(), which expects
        // an actual value to escape into a placeholder, not a static SQL fragment.
        $subtreeSelect = $connection->select()
            ->from(['target' => $categoryTable], [])
            ->joinInner(
                ['descendant' => $categoryTable],
                "descendant.path = target.path OR descendant.path LIKE CONCAT(target.path, '/%')",
                ['entity_id' => 'descendant.entity_id']
            )
            ->where('target.entity_id = ?', $categoryId);

        $categoryIds = array_map('intval', $connection->fetchCol($subtreeSelect));
        if ($categoryIds === []) {
            return [];
        }

        $productIds = $connection->fetchCol(
            $connection->select()
                ->from($categoryProductTable, 'product_id')
                ->where('category_id IN (?)', $categoryIds)
                ->distinct(true)
        );

        return array_map('intval', $productIds);
    }
}
