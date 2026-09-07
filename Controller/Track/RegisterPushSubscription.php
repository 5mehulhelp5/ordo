<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Track;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\Push\PushSubscriptionManager;

/**
 * Public, unauthenticated endpoint tracker.js posts to once the visitor grants notification
 * permission and the browser returns a real PushSubscription - same trust model as
 * Controller\Track\Event (no CSRF token, callable by anonymous visitors with no session yet).
 *
 * visitor_id is read from the ordo_visitor_id cookie server-side, not trusted as a request param -
 * this is also what makes push-sw.js's own `pushsubscriptionchange` re-registration (fired from a
 * service worker, which has no document.cookie access to read the cookie itself and pass it
 * along) work correctly: the cookie still rides along automatically on the same-origin fetch.
 */
class RegisterPushSubscription extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const string VISITOR_ID_COOKIE = 'ordo_visitor_id';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly PushSubscriptionManager $pushSubscriptionManager,
        private readonly CustomerSession $customerSession,
        private readonly CookieManagerInterface $cookieManager,
        private readonly Config $config
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->config->isPushEnabled()) {
            return $result->setData(['ok' => false, 'reason' => 'push_disabled']);
        }

        $endpoint = $this->getRequest()->getParam('endpoint');
        $endpoint = is_string($endpoint) ? trim($endpoint) : '';
        $p256dh = $this->getRequest()->getParam('p256dh');
        $p256dh = is_string($p256dh) ? trim($p256dh) : '';
        $auth = $this->getRequest()->getParam('auth');
        $auth = is_string($auth) ? trim($auth) : '';
        $visitorId = (string) ($this->cookieManager->getCookie(self::VISITOR_ID_COOKIE) ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '' || ($visitorId === '' && !$this->customerSession->isLoggedIn())) {
            return $result->setData(['ok' => false, 'reason' => 'invalid_payload']);
        }

        $customerId = $this->customerSession->isLoggedIn() ? (int) $this->customerSession->getCustomerId() : null;

        $this->pushSubscriptionManager->register($endpoint, $p256dh, $auth, $customerId, $visitorId ?: null);

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
