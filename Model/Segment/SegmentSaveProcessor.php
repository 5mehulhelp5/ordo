<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\Segment;

use Ordo\Automation\Model\ResourceModel\Segment as SegmentResource;
use Ordo\Automation\Model\ResourceModel\Segment\Condition as SegmentConditionResource;
use Ordo\Automation\Model\ResourceModel\Segment\Condition\CollectionFactory as SegmentConditionCollectionFactory;
use Ordo\Automation\Model\Segment;
use Ordo\Automation\Model\SegmentConditionFactory;
use Ordo\Automation\Model\SegmentFactory;

/**
 * Persists the segment row plus its condition child rows in one request — same
 * delete-and-reinsert approach as Model\Campaign\CampaignSaveProcessor for the same reason (a
 * segment realistically has a handful of conditions, not hundreds).
 *
 * Extracted from Controller\Adminhtml\Segment\Save so the persistence logic can be unit tested
 * and reused without a controller in the loop; the controller still owns the
 * HTTP/session/redirect concerns.
 */
class SegmentSaveProcessor
{
    /**
     * The condition dynamicRows' own dedicated fields (see ordo_segment_form.xml's
     * switcherConfig, mirrored from the campaign form's own) - a subset of Campaign
     * CampaignSaveProcessor's full list, since a segment condition only ever needs the ones
     * ConditionPool's condition types actually read.
     */
    private const array DEDICATED_PARAM_FIELDS = ['tag', 'amount', 'threshold', 'days', 'count', 'percentile'];

    public function __construct(
        private readonly SegmentFactory $segmentFactory,
        private readonly SegmentResource $segmentResource,
        private readonly SegmentConditionFactory $segmentConditionFactory,
        private readonly SegmentConditionResource $segmentConditionResource,
        private readonly SegmentConditionCollectionFactory $segmentConditionCollectionFactory
    ) {
    }

    /**
     * Loads the segment (when an entity_id was posted), applies the posted fields, saves it,
     * and rebuilds its condition child rows. Returns the saved segment so the caller can read
     * back the entity_id for redirects/messages.
     *
     * @param array<string, mixed> $data
     */
    public function process(array $data): Segment
    {
        $entityId = (int) ($data['entity_id'] ?? 0);
        $segment = $this->segmentFactory->create();

        if ($entityId) {
            $this->segmentResource->load($segment, $entityId);
        }

        $segment->setName((string) ($data['name'] ?? ''));
        $segment->setEnabled(!empty($data['enabled']));

        /** @var array<int, array<string, mixed>> $conditionRows */
        $conditionRows = (array) ($data['conditions']['conditions'] ?? []);

        $this->segmentResource->save($segment);
        $this->saveConditions((int) $segment->getEntityId(), $conditionRows);

        return $segment;
    }

    /**
     * @param array<int, array<string, mixed>> $conditionRows
     */
    private function saveConditions(int $segmentId, array $conditionRows): void
    {
        $existing = $this->segmentConditionCollectionFactory->create();
        $existing->addSegmentFilter($segmentId);
        foreach ($existing as $row) {
            $this->segmentConditionResource->delete($row);
        }

        $sortOrder = 0;
        foreach ($conditionRows as $row) {
            if (empty($row['type'])) {
                continue;
            }

            $condition = $this->segmentConditionFactory->create();
            $condition->setData([
                'segment_id' => $segmentId,
                'type' => (string) $row['type'],
                'params' => $this->normalizeRowParams($row),
                'sort_order' => $sortOrder++,
            ]);
            $this->segmentConditionResource->save($condition);
        }
    }

    /**
     * Starts from whatever was typed in the JSON textarea (if valid), then overlays any
     * dedicated field present on the row — same merge Model\Campaign\CampaignSaveProcessor
     * already uses for its own conditions/actions. Without this, the dedicated fields added to
     * the form were cosmetic only: a value typed into "Minimum order total" would never reach
     * the saved params, since this method previously read params_json alone.
     *
     * @param array<string, mixed> $row
     */
    private function normalizeRowParams(array $row): string
    {
        $params = $this->parseJson((string) ($row['params_json'] ?? ''));

        foreach (self::DEDICATED_PARAM_FIELDS as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $params[$field] = $value;
            }
        }

        // json_encode([]) is the JSON array literal "[]", not the empty JSON OBJECT "{}" a
        // condition's params conceptually are (a key-value map, not a list) - forced explicitly
        // rather than relying on json_encode's own empty-array behavior, which flips to "[]"
        // the moment $params has zero keys.
        return $params === [] ? '{}' : (json_encode($params) ?: '{}');
    }

    /**
     * Falls back to an empty object rather than rejecting the save outright — a condition with
     * unparsable params simply won't find the keys it expects when matched, fails closed rather
     * than crashing.
     *
     * @return array<string, mixed>
     */
    private function parseJson(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }
}
