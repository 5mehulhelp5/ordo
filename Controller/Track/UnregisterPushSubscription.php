<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Track;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Ordo\Automation\Model\Push\PushSubscriptionManager;

/**
 * Public, unauthenticated endpoint - push-sw.js's own `pushsubscriptionchange` handler (or
 * tracker.js, if the visitor explicitly revokes notification permission) posts here so a dead
 * subscription is removed proactively, rather than only ever being discovered the next time a
 * campaign tries (and fails) to send to it.
 */
class UnregisterPushSubscription extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly PushSubscriptionManager $pushSubscriptionManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $endpoint = $this->getRequest()->getParam('endpoint');
        $endpoint = is_string($endpoint) ? trim($endpoint) : '';
        if ($endpoint === '') {
            return $result->setData(['ok' => false, 'reason' => 'invalid_payload']);
        }

        $this->pushSubscriptionManager->unregister($endpoint);

        return $result->setData(['ok' => true]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
