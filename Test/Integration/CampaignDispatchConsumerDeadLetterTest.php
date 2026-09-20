<?php
declare(strict_types=1);

namespace Ordo\Automation\Test\Integration;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Ordo\Automation\Model\Queue\CampaignDispatchConsumer;
use PHPUnit\Framework\TestCase;

/**
 * Closes SCENARIOS.md §1d's dead-letter gap. Real DI/DB throughout - CampaignDispatchConsumer is
 * resolved from the real object manager and its execute() is called directly with a genuinely
 * undecodable message, exactly the branch its own class doc describes ("an undecodable message"),
 * rather than driving it through a real queue transport: this environment's DB-backed queue
 * driver has no MFTF-reachable way to inject a malformed raw message onto the
 * ordo.automation.campaign.dispatch topic (a real publish always produces well-formed JSON), so
 * exercising this specific failure mode means calling the consumer directly, the same
 * "call the class the queue would have called" approach CampaignQueueWiringTest already
 * establishes for this module's queue wiring in general.
 *
 * Run from the Magento root: vendor/bin/phpunit --bootstrap app/bootstrap.php
 * vendor/michalper/ordo/Test/Integration/CampaignDispatchConsumerDeadLetterTest.php
 */
class CampaignDispatchConsumerDeadLetterTest extends TestCase
{
    private static ObjectManagerInterface $objectManager;

    public static function setUpBeforeClass(): void
    {
        require_once BP . '/app/bootstrap.php';
        $bootstrap = Bootstrap::create(BP, $_SERVER);
        self::$objectManager = $bootstrap->getObjectManager();
        self::$objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
    }

    protected function tearDown(): void
    {
        self::$objectManager->get(ResourceConnection::class)->getConnection()->delete(
            self::$objectManager->get(ResourceConnection::class)
                ->getTableName('ordo_campaign_dispatch_dead_letter'),
            ['message = ?' => $this->malformedMessage()]
        );
    }

    public function testUndecodableMessageIsDeadLetteredNotLost(): void
    {
        $connection = self::$objectManager->get(ResourceConnection::class)->getConnection();
        $table = self::$objectManager->get(ResourceConnection::class)
            ->getTableName('ordo_campaign_dispatch_dead_letter');

        $countBefore = (int) $connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])->where('message = ?', $this->malformedMessage())
        );
        self::assertSame(0, $countBefore, 'Test fixture message should not already exist.');

        /** @var CampaignDispatchConsumer $consumer */
        $consumer = self::$objectManager->create(CampaignDispatchConsumer::class);
        $consumer->execute($this->malformedMessage());

        $row = $connection->fetchRow(
            $connection->select()->from($table)->where('message = ?', $this->malformedMessage())
        );

        self::assertIsArray($row, 'An undecodable message must be dead-lettered, not silently lost.');
        self::assertNull($row['trigger_event'], 'A message that failed to decode at all has no readable trigger_event.');
        self::assertNotSame('', trim((string) $row['error']), 'The caught exception message must be persisted.');
    }

    private function malformedMessage(): string
    {
        return '{"this is not valid JSON';
    }
}
