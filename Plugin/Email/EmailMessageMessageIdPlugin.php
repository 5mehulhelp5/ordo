<?php
declare(strict_types=1);

namespace Ordo\Automation\Plugin\Email;

use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Ordo\Automation\Model\Email\PendingMessageIdHolder;

/**
 * TransportBuilder::getTransport() builds its message through this factory internally with no
 * public seam of its own to reach the built message (no getMessage(), prepareMessage() is
 * protected — see this feature's own commit message for the full investigation), so this plugin
 * on the factory itself is the only reachable interception point. EmailMessage (unlike
 * TransportBuilder) DOES publicly expose the underlying Symfony message via getSymfonyMessage(),
 * which is what actually lets a Message-ID header be set at all.
 *
 * Sets nothing when PendingMessageIdHolder has no id queued (every campaign action other than
 * send_email, and any other TransportBuilder-based email in this Magento install, e.g. order
 * confirmation emails) — this plugin is a no-op for those.
 */
class EmailMessageMessageIdPlugin
{
    public function __construct(
        private readonly PendingMessageIdHolder $pendingMessageIdHolder
    ) {
    }

    public function afterCreate(
        EmailMessageInterfaceFactory $subject,
        EmailMessageInterface $result
    ): EmailMessageInterface {
        $messageId = $this->pendingMessageIdHolder->consume();
        if ($messageId === null || !$result instanceof EmailMessage) {
            return $result;
        }

        $result->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $messageId);

        return $result;
    }
}
