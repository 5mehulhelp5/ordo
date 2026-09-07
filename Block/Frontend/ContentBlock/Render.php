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
 * the numeric entity_id), from any layout XML:
 *   <block class="Ordo\Automation\Block\Frontend\ContentBlock\Render">
 *       <arguments><argument name="identifier" xsi:type="string">homepage_recommendations</argument></arguments>
 *   </block>
 * The real Magento {{widget}} CMS directive (etc/widget.xml still registers this same block as
 * widget id "ordo_content_block", so it appears in the CMS WYSIWYG's own "Insert Widget" dialog)
 * is left in place but UNVERIFIED — two real CI failures proved that directive resolves to
 * nothing when typed by hand (no PHP error, this block never even instantiated), root cause
 * still unconfirmed. A CMS page's own admin-facing "Layout Update XML" field does NOT work for
 * this: Magento\Cms\Model\Page::beforeSave() unconditionally wipes that field to null whenever
 * layout_update_selected isn't '_existing_' (i.e. always, unless a custom layout file is already
 * registered and selected) - confirmed by reading that method directly, so it's been effectively
 * read-only since Magento introduced that dropdown, independent of any webapi/MFTF metadata gap.
 * For a specific CMS page, the real, supported way to add this via layout XML without any admin
 * text field at all is Magento_Cms's own per-page layout handle: Magento\Cms\Helper\Page::
 * prepareResultPage() always adds a handle named "cms_page_view_id_{identifier}" - a plain
 * view/frontend/layout/cms_page_view_id_{identifier}.xml file targeting that handle is all a
 * developer needs (see view/frontend/layout/cms_page_view_id_e2e-onsite-recommendations-page.xml
 * for a real, working example, verified by hand against a real Magento install before writing
 * Test/Mftf/Test/AdminContentBlockRecommendationsOnSiteTest.xml).
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
