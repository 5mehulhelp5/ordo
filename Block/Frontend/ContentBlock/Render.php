<?php
declare(strict_types=1);

namespace Ordo\Automation\Block\Frontend\ContentBlock;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Ordo\Automation\Model\ContentBlock;
use Ordo\Automation\Model\ContentBlock\Producer\ProducerInterface;
use Ordo\Automation\Model\ContentBlock\ProducerPool;
use Ordo\Automation\Model\ContentBlockRepository;

/**
 * On-site rendering of an admin-authored content block — everywhere else in this module,
 * ContentBlock\ProducerPool output only ever reaches a campaign email via AddDynamicContent's
 * {{var ...|raw}}. This is the same producer registry, the same "resolve by type, degrade to
 * empty on any failure" contract, just embedded directly into a storefront page instead.
 *
 * Referenced by "identifier" (the human-authored machine name from the content block form, not
 * the numeric entity_id) - either via the real Magento {{widget}} CMS directive (registered as
 * widget id "ordo_content_block" in etc/widget.xml, so it also appears in the CMS WYSIWYG's own
 * "Insert Widget" dialog — there is no generic "{{block class=...}}" directive in this Magento
 * version, {{widget}} is the actual mechanism):
 *   {{widget type="Ordo\Automation\Block\Frontend\ContentBlock\Render" identifier="homepage_recommendations"}}
 * or from any layout XML:
 *   <block class="Ordo\Automation\Block\Frontend\ContentBlock\Render">
 *       <arguments><argument name="identifier" xsi:type="string">homepage_recommendations</argument></arguments>
 *   </block>
 * No new admin UI beyond the widget.xml registration, no new layout update handle needed — a
 * content block already authored for campaign email (e.g. a "recommendations" or "snippet"
 * type) can be dropped onto the storefront this way with zero extra configuration on the
 * content-block side.
 */
class Render extends Template
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ContentBlockRepository $contentBlockRepository,
        private readonly ProducerPool $producerPool,
        private readonly CustomerSession $customerSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Bypasses the normal .phtml template pipeline entirely - producers already return
     * ready-to-embed HTML (the same fragment a campaign email's {{var ...|raw}} gets), so there
     * is no template-level markup of this block's own to wrap it in.
     */
    protected function _toHtml(): string
    {
        return $this->getContentHtml();
    }

    /**
     * Public (not just the _toHtml() override above) so this resolution logic is unit-testable
     * directly, without going through AbstractBlock::toHtml()'s full pipeline (event manager,
     * scope config, block cache) that a plain unit test has no reason to also wire up.
     */
    public function getContentHtml(): string
    {
        $identifier = (string) $this->getData('identifier');
        if ($identifier === '') {
            return 'ORDO_DEBUG:EMPTY_IDENTIFIER';
        }

        $block = $this->contentBlockRepository->getByIdentifier($identifier);
        if (!$block instanceof ContentBlock) {
            return 'ORDO_DEBUG:BLOCK_NOT_FOUND:' . $identifier;
        }
        if (!$block->isEnabled()) {
            return 'ORDO_DEBUG:BLOCK_DISABLED:' . $identifier;
        }

        $producer = $this->producerPool->get($block->getType());
        if (!$producer instanceof ProducerInterface) {
            return 'ORDO_DEBUG:NO_PRODUCER:' . $block->getType();
        }

        $context = [];
        if ($this->customerSession->isLoggedIn()) {
            $context['customer_id'] = (int) $this->customerSession->getCustomerId();
        }

        $rendered = $producer->render($block, $context);

        return $rendered === '' ? 'ORDO_DEBUG:PRODUCER_RETURNED_EMPTY' : $rendered;
    }
}
