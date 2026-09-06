<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Unit\Model\Cron;

use Ordo\Automation\Model\Cron\CronRunLogger;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CronRunLoggerTest extends TestCase
{
    public function testLogFailureFormatsActionAndExceptionMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            'Ordo_Automation: failed to send win-back email to customer #5: send failed'
        );

        (new CronRunLogger($logger))->logFailure(
            'send win-back email to customer #5',
            new \RuntimeException('send failed')
        );
    }

    public function testLogSummaryFormatsSummaryWithPrefixAndPeriod(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Ordo_Automation: sent 3 win-back emails.');

        (new CronRunLogger($logger))->logSummary('sent 3 win-back emails');
    }
}
