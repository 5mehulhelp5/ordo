<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Email;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\Email\SendGridSignatureValidator;
use Ordo\Automation\Model\MessageLog;
use Ordo\Automation\Model\ResourceModel\MessageLog as MessageLogResource;
use Ordo\Automation\Model\ResourceModel\MessageLog\CollectionFactory as MessageLogCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Public, unauthenticated endpoint registered once, by URL, in the SendGrid account's own Event
 * Webhook settings (not per-message like Twilio's statusCallback param — SendGrid pushes every
 * event for the whole account here) — same trust model as Controller\Sms\StatusCallback:
 * X-Twilio-Email-Event-Webhook-Signature (SendGrid's Event Webhook product is, confusingly,
 * "Twilio SendGrid" branded, hence the header name) is the mandatory trust boundary, checked
 * before anything else.
 *
 * Only "delivered", "bounce", and "dropped" are handled — SendGrid's other event types (open,
 * click, processed, deferred, unsubscribe, spamreport, group_unsubscribe/resubscribe) are either
 * not terminal delivery outcomes or not something ordo_message_log's status enum has a slot for;
 * unhandled event types are silently skipped, not an error.
 */
class StatusCallback extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const string SIGNATURE_HEADER = 'X-Twilio-Email-Event-Webhook-Signature';
    private const string TIMESTAMP_HEADER = 'X-Twilio-Email-Event-Webhook-Timestamp';

    /**
     * @var array<string, string> SendGrid event type => MessageLog::STATUS_* constant.
     */
    private const array EVENT_TO_STATUS = [
        'delivered' => MessageLog::STATUS_DELIVERED,
        'bounce' => MessageLog::STATUS_UNDELIVERED,
        'dropped' => MessageLog::STATUS_FAILED,
    ];

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Config $config,
        private readonly SendGridSignatureValidator $signatureValidator,
        private readonly MessageLogCollectionFactory $messageLogCollectionFactory,
        private readonly MessageLogResource $messageLogResource,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        $signature = (string) $this->getRequest()->getHeader(self::SIGNATURE_HEADER);
        $timestamp = (string) $this->getRequest()->getHeader(self::TIMESTAMP_HEADER);
        $rawBody = (string) $this->getRequest()->getContent();

        $isValid = $signature !== '' && $timestamp !== '' && $this->signatureValidator->isValid(
            $this->config->getSendGridWebhookVerificationKey(),
            $timestamp,
            $rawBody,
            $signature
        );
        if (!$isValid) {
            $this->logger->error(
                'Ordo_Automation: rejected a SendGrid event webhook call with an invalid signature.'
            );

            return $result->setHttpResponseCode(403)->setData(['ok' => false]);
        }

        $events = json_decode($rawBody, true);
        if (!is_array($events)) {
            return $result->setData(['ok' => false, 'reason' => 'invalid_payload']);
        }

        foreach ($events as $event) {
            if (is_array($event)) {
                $this->processEvent($event);
            }
        }

        return $result->setData(['ok' => true]);
    }

    /**
     * @param array<array-key, mixed> $event
     */
    private function processEvent(array $event): void
    {
        $status = self::EVENT_TO_STATUS[(string) ($event['event'] ?? '')] ?? null;
        $messageId = (string) ($event['smtp-id'] ?? '');
        if ($status === null || $messageId === '') {
            return;
        }

        $collection = $this->messageLogCollectionFactory->create();
        $collection->addFieldToFilter('provider_message_id', $messageId);
        $log = $collection->getFirstItem();

        if (!$log->getId()) {
            // SendGrid also retries on a non-2xx response for the whole batch - an unrecognized
            // smtp-id just means this one event is skipped, same reasoning as
            // Controller\Sms\StatusCallback's own unknown-MessageSid branch.
            $this->logger->info(sprintf(
                'Ordo_Automation: SendGrid event webhook for unknown smtp-id "%s" (event=%s).',
                $messageId,
                (string) ($event['event'] ?? '')
            ));

            return;
        }

        $log->setStatus($status);
        $reason = $event['reason'] ?? $event['type'] ?? null;
        $log->setErrorCode($reason !== null ? (string) $reason : null);
        $this->messageLogResource->save($log);
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
