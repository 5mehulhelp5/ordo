<?php
declare(strict_types=1);

namespace Ordo\Automation\Cron;

use Ordo\Automation\Helper\Config;
use Ordo\Automation\Model\Cron\CronRunLogger;
use Ordo\Automation\Model\Cron\ReminderEmailSender;
use Ordo\Automation\Model\CustomerMapBuilder;
use Ordo\Automation\Model\CustomerTagManager;
use Ordo\Automation\Model\TriggerOutcomeLogger;

/**
 * Emails everyone TagInactiveCustomers has tagged "inactive" who hasn't already received a
 * win-back email (tracked as its own tag, so it survives independently of the inactive/active
 * flip-flopping and never sends twice).
 */
class SendWinBackEmails
{
    private const string XML_PATH_EMAIL_TEMPLATE = 'ordo_win_back_email';
    public const TAG_WIN_BACK_SENT = 'win_back_sent';

    public function __construct(
        private readonly Config $config,
        private readonly CustomerTagManager $customerTagManager,
        private readonly CustomerMapBuilder $customerMapBuilder,
        private readonly ReminderEmailSender $emailSender,
        private readonly TriggerOutcomeLogger $triggerOutcomeLogger,
        private readonly CronRunLogger $cronRunLogger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isLifecycleEmailsEnabled()) {
            return;
        }

        $customerIds = $this->customerTagManager->getCustomerIdsWithTag(TagInactiveCustomers::TAG_INACTIVE);
        $customerMap = $this->customerMapBuilder->build($customerIds);

        $sent = 0;
        foreach ($customerIds as $customerId) {
            if ($this->customerTagManager->hasTag($customerId, self::TAG_WIN_BACK_SENT)) {
                continue;
            }

            if (!isset($customerMap[$customerId])) {
                continue;
            }

            try {
                $customer = $customerMap[$customerId];
                $this->emailSender->send(
                    self::XML_PATH_EMAIL_TEMPLATE,
                    ['customer_name' => $customer->getFirstname()],
                    $customer->getEmail(),
                    $customer->getFirstname()
                );
                $this->customerTagManager->addTag($customerId, self::TAG_WIN_BACK_SENT);
                $this->triggerOutcomeLogger->logSent(TriggerOutcomeLogger::TRIGGER_WIN_BACK, $customerId);
                $sent++;
            } catch (\Throwable $e) {
                $this->cronRunLogger->logFailure(
                    sprintf('send win-back email to customer #%d', $customerId),
                    $e
                );
            }
        }

        $this->cronRunLogger->logSummary(sprintf('sent %d win-back emails', $sent));
    }
}
