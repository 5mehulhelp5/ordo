<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Adminhtml\Listing;

use Magento\Framework\View\Element\Template;

/**
 * A merchant with zero rows on a CRUD listing saw the exact same bare grid as one with a
 * hundred — no indication of what to do first (ROADMAP.md "Admin UX" Phase 3: "a first-time
 * user is guided to their first real action"). Magento's ui-component grid has no declarative
 * way to customize its own "We couldn't find any records." message per-listing (it's a single
 * hardcoded string in Magento_Ui's knockout template), so this renders a separate, dismissable-
 * by-having-data banner above the grid instead: visible only while the underlying collection is
 * empty, pointing straight at the "create new" action.
 *
 * One count query per page load — acceptable for module-scoped tables that are either genuinely
 * small or, once non-empty, never render this block's content again (isEmptyState() short-
 * circuits to false).
 */
abstract class AbstractEmptyStateHint extends Template
{
    private ?bool $isEmptyState = null;

    public function isEmptyState(): bool
    {
        $this->isEmptyState ??= $this->getCollectionSize() === 0;
        return $this->isEmptyState;
    }

    public function getHintText(): string
    {
        $value = $this->getData('hint_text');
        return is_string($value) ? $value : '';
    }

    public function getCtaLabel(): string
    {
        $value = $this->getData('cta_label');
        return is_string($value) ? $value : '';
    }

    public function getCtaUrl(): string
    {
        $path = $this->getData('cta_path');
        return $this->getUrl(is_string($path) ? $path : '');
    }

    abstract protected function getCollectionSize(): int;
}
