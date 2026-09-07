<?php
declare(strict_types=1);

namespace Ordo\Automation\Controller\Track;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Serves push-sw.js (view/frontend/web/js/push-sw.js) from a plain, unversioned controller URL
 * instead of Magento's usual `/static/version.../frontend/...` static asset path.
 *
 * This is not a style choice - a service worker's default max scope is its own directory and
 * below, so one deployed under the deep, theme/locale-specific static path could never control
 * real storefront pages. The `Service-Worker-Allowed: /` response header this action sends is the
 * documented way around that (it widens the max scope to the whole origin regardless of where the
 * script itself is served from), but Magento's static asset pipeline has no way to attach a
 * per-file response header, hence a dedicated controller action instead.
 */
class PushServiceWorker extends Action implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly RawFactory $resultRawFactory,
        private readonly Filesystem $filesystem,
        private readonly ModuleDirReader $moduleDirReader
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $modulePath = (string) $this->moduleDirReader->getModuleDir('view', 'Ordo_Automation');
        $fileName = 'push-sw.js';

        $directory = $this->filesystem->getDirectoryReadByPath($modulePath . '/frontend/web/js');

        $result = $this->resultRawFactory->create();
        if (!$directory->isExist($fileName)) {
            // A missing file here means every visitor's serviceWorker.register() call quietly
            // "succeeds" with an empty, no-op worker that never fires push/notificationclick -
            // a real 404 at least surfaces the misconfiguration instead of an empty 200 body.
            $result->setHttpResponseCode(404);
            return $result;
        }

        $result->setHeader('Content-Type', 'application/javascript; charset=UTF-8');
        $result->setHeader('Service-Worker-Allowed', '/');
        $result->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $result->setContents((string) $directory->readFile($fileName));

        return $result;
    }
}
