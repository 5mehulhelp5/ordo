<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Plugin\Email;

use Magento\Framework\Mail\EmailMessage;
use Magento\Framework\Mail\EmailMessageInterface;
use Magento\Framework\Mail\EmailMessageInterfaceFactory;
use Ordo\Automation\Model\Email\PendingMessageIdHolder;
use Ordo\Automation\Plugin\Email\EmailMessageMessageIdPlugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message as SymfonyMessage;

class EmailMessageMessageIdPluginTest extends TestCase
{
    public function testDoesNothingWhenNoPendingIdIsQueued(): void
    {
        $holder = new PendingMessageIdHolder();
        $result = $this->createMock(EmailMessage::class);
        $result->expects(self::never())->method('getSymfonyMessage');

        $plugin = new EmailMessageMessageIdPlugin($holder);
        $returned = $plugin->afterCreate($this->createStub(EmailMessageInterfaceFactory::class), $result);

        self::assertSame($result, $returned);
    }

    public function testDoesNothingWhenResultIsNotAnEmailMessage(): void
    {
        $holder = new PendingMessageIdHolder();
        $holder->set('abc@example.com');
        $result = $this->createStub(EmailMessageInterface::class);

        $plugin = new EmailMessageMessageIdPlugin($holder);
        $returned = $plugin->afterCreate($this->createStub(EmailMessageInterfaceFactory::class), $result);

        self::assertSame($result, $returned);
        // The queued id must still be consumed even when it can't be applied here - otherwise it
        // would leak onto whichever unrelated EmailMessage gets built next.
        self::assertNull($holder->consume());
    }

    public function testAddsTheMessageIdHeaderWhenAnIdIsQueued(): void
    {
        $holder = new PendingMessageIdHolder();
        $holder->set('abc@example.com');

        $symfonyMessage = new SymfonyMessage(new Headers());
        $result = $this->createStub(EmailMessage::class);
        $result->method('getSymfonyMessage')->willReturn($symfonyMessage);

        $plugin = new EmailMessageMessageIdPlugin($holder);
        $returned = $plugin->afterCreate($this->createStub(EmailMessageInterfaceFactory::class), $result);

        self::assertSame($result, $returned);
        self::assertSame('<abc@example.com>', $symfonyMessage->getHeaders()->get('Message-ID')?->getBodyAsString());
    }
}
