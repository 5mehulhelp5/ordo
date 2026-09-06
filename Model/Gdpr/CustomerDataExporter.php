<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Gdpr;

use Magento\Framework\App\ResourceConnection;

/**
 * Everything this module holds about one customer, as a plain array ready to be JSON-encoded —
 * Controller\Adminhtml\Gdpr\Export.php's data-subject-access-request response. Read via direct
 * SQL (ResourceConnection::fetchAll()), not the model layer, since this deliberately mirrors the
 * exact row shape stored, not a domain object — same "read-only reporting query" reasoning as
 * this codebase's dashboard/export code elsewhere.
 */
class CustomerDataExporter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();

        $fetch = function (string $table) use ($connection, $customerId): array {
            $select = $connection->select()
                ->from($this->resourceConnection->getTableName($table))
                ->where('customer_id = ?', $customerId);

            return $connection->fetchAll($select);
        };

        return [
            'customer_id' => $customerId,
            'consent' => $fetch('ordo_customer_consent'),
            'tags' => $fetch('ordo_customer_tag'),
            'score' => $fetch('ordo_customer_score'),
            'demographic_score' => $fetch('ordo_customer_demographic_score'),
            'notifications' => $fetch('ordo_notification'),
            'survey_responses' => $fetch('ordo_survey_prompt'),
            'pending_popups' => $fetch('ordo_pending_popup'),
            'message_log' => $fetch('ordo_message_log'),
        ];
    }
}
