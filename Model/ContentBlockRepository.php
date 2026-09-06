<?php
declare(strict_types=1);

namespace Ordo\Automation\Model;

use Ordo\Automation\Model\ResourceModel\ContentBlock as ContentBlockResource;
use Ordo\Automation\Model\ResourceModel\ContentBlock\CollectionFactory as ContentBlockCollectionFactory;

/**
 * Simple direct resource-model persistence, same pattern the campaign engine's own controllers
 * use for admin CRUD (no WebAPI surface for content blocks, so no Api\...RepositoryInterface/
 * SearchResults ceremony is needed here — see getById()'s null-safe contract, which
 * Model\Campaign\Action\AddDynamicContent relies on to fail quiet rather than throw).
 */
class ContentBlockRepository
{
    public function __construct(
        private readonly ContentBlockFactory $contentBlockFactory,
        private readonly ContentBlockResource $contentBlockResource,
        private readonly ContentBlockCollectionFactory $contentBlockCollectionFactory
    ) {
    }

    /**
     * Null-safe: returns null for a missing/unset id instead of throwing, so callers on a hot
     * dispatch path (AddDynamicContent) can fail quiet without a try/catch.
     */
    public function getById(int $id): ?ContentBlock
    {
        if ($id <= 0) {
            return null;
        }

        $block = $this->contentBlockFactory->create();
        $this->contentBlockResource->load($block, $id);

        return $block->getId() ? $block : null;
    }

    /**
     * Null-safe, same contract as getById() — used by Block\Frontend\ContentBlock\Render, whose
     * only handle to a content block is the human-authored "identifier" (CMS block content/
     * layout XML references it by name, not the numeric entity_id nobody authoring a CMS page
     * would know or want to type). No unique-index enforcement on `identifier` at the DB level
     * (see etc/db_schema.xml), so the first match wins if two rows ever share one.
     */
    public function getByIdentifier(string $identifier): ?ContentBlock
    {
        if ($identifier === '') {
            return null;
        }

        $collection = $this->contentBlockCollectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier);
        $collection->setPageSize(1);

        /** @var ContentBlock $block getFirstItem() always returns a model instance - a fresh,
         *  id-less one when nothing matches, never false/null. */
        $block = $collection->getFirstItem();

        return $block->getId() ? $block : null;
    }

    public function save(ContentBlock $block): ContentBlock
    {
        $this->contentBlockResource->save($block);
        return $block;
    }

    public function delete(ContentBlock $block): void
    {
        $this->contentBlockResource->delete($block);
    }
}
