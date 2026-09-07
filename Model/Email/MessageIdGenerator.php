<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Email;

use Magento\Store\Model\StoreManagerInterface;

/**
 * Generates the value this module sets as the outgoing email's own Message-ID header (via
 * Plugin\Email\EmailMessageMessageIdPlugin) and stores as ordo_message_log's provider_message_id
 * — SendGrid's Event Webhook echoes back whatever Message-ID header a message was sent with in
 * each event's "smtp-id" field (RFC 5322-quoted, i.e. wrapped in angle brackets), so this is what
 * Controller\Email\StatusCallback correlates an incoming event against, the same role Twilio's
 * own message Sid plays for SMS.
 */
class MessageIdGenerator
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Returns the bare "local@domain" form — what
     * Symfony\Component\Mime\Header\IdentificationHeader::setIds() requires (it parses each id
     * as an Address, which requires the "@"). SendGrid's webhook payload echoes this back
     * angle-bracket-wrapped (the RFC 5322 "msg-id" form Symfony itself renders the header value
     * as), so callers that need to match against ordo_message_log's stored provider_message_id
     * must wrap this return value in "<...>" themselves - see SendEmail::execute() for the one
     * real caller doing exactly that.
     */
    public function generate(): string
    {
        $domain = 'localhost';
        try {
            // No Magento core alternative extracts the host from an arbitrary URL string.
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $host = parse_url((string) $this->storeManager->getStore()->getBaseUrl(), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $domain = $host;
            }
        } catch (\Throwable) {
            // Falls back to "localhost" — an unresolvable store is not a reason to block sending
            // the email itself, only this id's domain suffix is affected.
        }

        return sprintf('ordo.%s@%s', bin2hex(random_bytes(16)), $domain);
    }
}
