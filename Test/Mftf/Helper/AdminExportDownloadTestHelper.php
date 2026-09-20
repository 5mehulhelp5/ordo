<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Fetches an admin-only "Export" download (Controller\Adminhtml\Campaign\Export,
 * Controller\Adminhtml\Segment\Export - both a raw JSON body with a
 * "Content-Disposition: attachment" header) directly via an authenticated HTTP GET, reusing the
 * browser's own real admin session cookie (grabbed via MFTF's grabCookie action). A forced
 * "attachment" download can't be read back from the page - Selenium hands it to the OS/browser's
 * own download handling, not the DOM - so this drives the exact same authenticated request a
 * real download click makes and reads its real response body directly, rather than trying to
 * inspect a file that landed outside the browser entirely.
 */
class AdminExportDownloadTestHelper extends Helper
{
    public function fetchExportBody(string $url, string $adminCookieValue): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Cookie: admin=' . $adminCookieValue],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Could not reach export endpoint at {$url}: {$error}");
        }

        curl_close($ch);

        return (string) $body;
    }
}
