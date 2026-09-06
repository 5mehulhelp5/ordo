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
 * the numeric entity_id) - either from a CMS block/page's content via the standard
 * {{block class="..." identifier="..."}} widget directive, or from any layout XML:
 *   <block class="Ordo\Automation\Block\Frontend\ContentBlock\Render">
 *       <arguments><argument name="identifier" xsi:type="string">homepage_recommendations</argument></arguments>
 *   </block>
 * No new admin UI, no new layout update handle needed — a content block already authored for
 * campaign email (e.g. a "recommendations" or "snippet" type) can be dropped onto the storefront
 * this way with zero extra configuration on the content-block side.
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
            return '';
        }

        $block = $this->contentBlockRepository->getByIdentifier($identifier);
        if (!$block instanceof ContentBlock || !$block->isEnabled()) {
            return '';
        }

        $producer = $this->producerPool->get($block->getType());
        if (!$producer instanceof ProducerInterface) {
            return '';
        }

        $context = [];
        if ($this->customerSession->isLoggedIn()) {
            $context['customer_id'] = (int) $this->customerSession->getCustomerId();
        }

        return $producer->render($block, $context);
    }
}
