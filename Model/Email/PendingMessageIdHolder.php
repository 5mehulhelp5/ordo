<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Email;

/**
 * The only channel available to hand a Message-ID from SendEmail (which calls
 * TransportBuilder::getTransport()) across to Plugin\Email\EmailMessageMessageIdPlugin (which
 * intercepts the EmailMessageInterfaceFactory::create() call TransportBuilder makes internally) —
 * TransportBuilder exposes no public seam of its own to reach the message it builds (no
 * getMessage(), prepareMessage() is protected, see this feature's own commit message for the
 * full investigation), so a small shared, request-scoped holder is the plugin's only way to know
 * which id (if any) belongs to the message currently being built. A plain object rather than a
 * static property so DI's normal singleton-per-request sharing does the scoping, no manual reset
 * needed beyond consume()'s own read-and-clear.
 */
class PendingMessageIdHolder
{
    private ?string $messageId = null;

    public function set(string $messageId): void
    {
        $this->messageId = $messageId;
    }

    /**
     * Read-and-clear — every SendEmail::execute() call sets a fresh id right before its own
     * getTransport() call and this consumes it immediately after, so a batch cron sending many
     * emails in one request never leaks one email's id onto the next.
     */
    public function consume(): ?string
    {
        $messageId = $this->messageId;
        $this->messageId = null;

        return $messageId;
    }
}
