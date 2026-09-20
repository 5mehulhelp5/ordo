<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Raw HTTP calls MFTF's browser-driven actions can't produce - a GET that reads a real response
 * header (Codeception/Selenium exposes rendered page content, not response headers, to a test -
 * see Controller\ProductFeed\Index.php's own SCENARIOS.md row for the same limitation already
 * documented elsewhere), and a POST that deliberately omits Origin/Referer while still carrying
 * a real logged-in customer's own session cookie - a real browser's fetch() can never send that
 * (Origin is a browser-controlled "forbidden header name" no page JS can override or suppress),
 * so this is the only way to drive
 * Model\Track\VisitorIdentityResolver::validateForCsrf()'s own rejection path for real.
 */
class RawHttpTestHelper extends Helper
{
    public function getResponseHeader(string $url, string $headerName): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Could not reach {$url}: {$error}");
        }
        curl_close($ch);

        foreach (explode("\r\n", (string) $response) as $line) {
            if (stripos($line, $headerName . ':') === 0) {
                return trim(substr($line, strlen($headerName) + 1));
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $formFields
     * @return int the real HTTP response status code
     */
    public function postWithCookieNoOrigin(string $url, array $formFields, string $cookieHeader): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($formFields),
            CURLOPT_HTTPHEADER => ['Cookie: ' . $cookieHeader],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Could not reach {$url}: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }
}
