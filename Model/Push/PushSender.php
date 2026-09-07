<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Push;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\Push\Exception\SubscriptionGoneException;
use Ordo\Automation\Model\PushSubscription;
use RuntimeException;

/**
 * Sends a single Web Push message to one subscription - real HTTP to whatever push service that
 * subscription's endpoint points at (FCM, Mozilla autopush, etc.), no vendor SDK, same choice as
 * Model\WhatsApp\WhatsAppSender/Model\Sms\TwilioSmsSender.
 */
class PushSender
{
    private const int TIMEOUT_SECONDS = 30;
    private const int TTL_SECONDS = 4 * 3600;

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly VapidTokenBuilder $vapidTokenBuilder,
        private readonly WebPushCrypto $webPushCrypto
    ) {
    }

    /**
     * @param string $payloadJson The notification payload, plaintext JSON - push-sw.js's own
     *   `push` event handler is what actually reads title/body/url out of this.
     * @throws SubscriptionGoneException the push service reports this subscription no longer
     *   exists (HTTP 404/410) - caller should delete it.
     */
    public function send(PushSubscription $subscription, string $payloadJson): void
    {
        $body = $this->webPushCrypto->encrypt(
            $payloadJson,
            $subscription->getP256dhKey(),
            $subscription->getAuthKey()
        );

        $endpoint = $subscription->getEndpoint();
        $authorization = $this->vapidTokenBuilder->buildAuthorizationHeader(
            $endpoint,
            $this->config->getVapidPublicKey(),
            $this->config->getVapidPrivateKey(),
            $this->config->getVapidSubject()
        );

        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $this->curl->addHeader('Content-Type', 'application/octet-stream');
        $this->curl->addHeader('Content-Encoding', 'aes128gcm');
        $this->curl->addHeader('TTL', (string) self::TTL_SECONDS);
        $this->curl->addHeader('Authorization', $authorization);
        $this->curl->post($endpoint, $body);

        $status = $this->curl->getStatus();
        if ($status === 404 || $status === 410) {
            throw new SubscriptionGoneException(
                sprintf('Push subscription #%d is gone (HTTP %d).', (int) $subscription->getEntityId(), $status)
            );
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                sprintf('Push service request failed (HTTP %d): %s', $status, (string) $this->curl->getBody())
            );
        }
    }
}
