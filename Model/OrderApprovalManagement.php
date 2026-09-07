<?php
declare(strict_types=1);

namespace Ordo\Automation\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Ordo\Automation\Api\Data\OrderApprovalDecisionLinksInterface;
use Ordo\Automation\Api\Data\OrderApprovalInterface;
use Ordo\Automation\Api\OrderApprovalManagementInterface;
use Ordo\Automation\Model\ResourceModel\OrderApproval as OrderApprovalResource;

/**
 * The one place the approve/reject decision is actually made — both the email-link controllers
 * (Controller/Approval/{Approve,Reject}.php) and the REST API (webapi.xml) call into this, so
 * the business logic (token lookup, order release/cancel, approval bookkeeping) exists exactly
 * once regardless of which channel triggered it.
 */
class OrderApprovalManagement implements OrderApprovalManagementInterface
{
    public function __construct(
        private readonly OrderApprovalFactory $orderApprovalFactory,
        private readonly OrderApprovalResource $orderApprovalResource,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderResource $orderResource,
        private readonly OrderConfig $orderConfig,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly OrderApprovalDecisionLinksFactory $decisionLinksFactory
    ) {
    }

    public function approveByToken(string $token): OrderApprovalInterface
    {
        $approval = $this->loadPendingApprovalByToken($token);

        // Claim the decision atomically BEFORE touching the order - two concurrent requests for
        // the same token (double click, a forwarded email opened twice) must never both pass
        // this and both release/cancel the same order. Only the request whose conditional UPDATE
        // actually matches a row (still "pending" at that instant) may proceed.
        if (!$this->orderApprovalResource->claimPending($approval, OrderApproval::STATUS_APPROVED)) {
            throw new NoSuchEntityException(__('Invalid or already-used approval token.'));
        }

        $order = $this->loadOrder($approval->getOrderId());

        // Release the order into whatever status is normally the default for the "new" state —
        // i.e. exactly where it would have landed if it had never been held.
        $order->setStatus($this->orderConfig->getStateDefaultStatus(Order::STATE_NEW));
        $this->orderResource->save($order);

        return $approval;
    }

    public function rejectByToken(string $token): OrderApprovalInterface
    {
        $approval = $this->loadPendingApprovalByToken($token);

        // Same claim-before-acting reasoning as approveByToken() above.
        if (!$this->orderApprovalResource->claimPending($approval, OrderApproval::STATUS_REJECTED)) {
            throw new NoSuchEntityException(__('Invalid or already-used approval token.'));
        }

        $order = $this->loadOrder($approval->getOrderId());

        // cancel() also releases any reserved inventory back to stock.
        $order->cancel();
        $this->orderRepository->save($order);

        return $approval;
    }

    public function getDecisionLinksById(int $entityId): OrderApprovalDecisionLinksInterface
    {
        /** @var OrderApproval $approval */
        $approval = $this->orderApprovalFactory->create();
        $this->orderApprovalResource->load($approval, $entityId);

        if (!$approval->getId() || !$approval->isPending()) {
            throw new NoSuchEntityException(
                __('Order approval with id "%1" does not exist or is no longer pending.', $entityId)
            );
        }

        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        $token = $approval->getToken();

        /** @var OrderApprovalDecisionLinks $links */
        $links = $this->decisionLinksFactory->create();
        $links->setApproveUrl($baseUrl . '/ordo/approval/approve/token/' . $token);
        $links->setRejectUrl($baseUrl . '/ordo/approval/reject/token/' . $token);

        return $links;
    }

    /**
     * Looks up the approval by its token, only if it's still pending — an already-decided
     * token is not reusable, so a second click (or a forwarded email) can't flip the decision.
     */
    private function loadPendingApprovalByToken(string $token): OrderApproval
    {
        if ($token === '') {
            throw new NoSuchEntityException(__('Invalid or already-used approval token.'));
        }

        /** @var OrderApproval $approval */
        $approval = $this->orderApprovalFactory->create();
        $this->orderApprovalResource->loadByToken($approval, $token);

        if (!$approval->getId() || !$approval->isPending()) {
            throw new NoSuchEntityException(__('Invalid or already-used approval token.'));
        }

        return $approval;
    }

    private function loadOrder(int $orderId): Order
    {
        /** @var Order|null $order */
        $order = $this->orderCollectionFactory->create()
            ->addFieldToFilter('entity_id', $orderId)
            ->getFirstItem();

        if (!$order || !$order->getId()) {
            throw new LocalizedException(__('The order for this approval could not be found.'));
        }

        return $order;
    }
}
