<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use CURLFile;
use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * A real authenticated multipart POST straight to Controller\Adminhtml\{Campaign,Segment}\Import -
 * two things rule out driving this through the real admin UI form instead: a JS-built hidden form
 * (the technique AdminDeleteOrdoEntityByFormPostActionGroup already uses for a plain POST) can't
 * populate a real <input type="file">'s FileList (no legitimate way to fake one from script, by
 * design - the same reason Web Push registration in this suite has to go through a real fetch()
 * call rather than a constructed one), and Selenium's own <attachFile> action requires the
 * uploaded file to already exist under dev/tests/acceptance/tests/_data/, a path this module (a
 * separate Composer package, not part of core Magento's own monorepo) has no way to ship a
 * fixture file into. A raw curl multipart POST, carrying the real admin session cookie and
 * form_key MFTF's own browser session already holds, is indistinguishable from a real browser
 * upload from the server's own point of view - the same reasoning as AdminExportDownloadTestHelper
 * for the read side of this same feature.
 */
class AdminImportUploadTestHelper extends Helper
{
    /**
     * Returns the controller's own real post-import redirect URL (the edit page on success, the
     * import form again on a handled error) rather than following it here - the caller then does
     * a real <amOnPage> to it, so the Selenium browser's own current URL/DOM reflect this for
     * real (grabFromCurrentUrl for the newly-imported entity's id, <see> for the real flash
     * message), instead of a side-channel curl response the browser itself never navigated to.
     *
     * @throws \RuntimeException if the POST didn't redirect at all (a real failure to reach the
     *     controller, distinct from the controller's own handled error paths)
     */
    public function uploadImportFile(
        string $importUrl,
        string $jsonContent,
        string $adminCookieValue,
        string $formKey
    ): string {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ordo_import_');
        file_put_contents($tmpFile, $jsonContent);

        try {
            $ch = curl_init($importUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => [
                    'form_key' => $formKey,
                    'import_file' => new CURLFile($tmpFile, 'application/json', 'import.json'),
                ],
                CURLOPT_HTTPHEADER => ['Cookie: admin=' . $adminCookieValue],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } finally {
            unlink($tmpFile);
        }

        if ($response === false || !preg_match('/^Location:\s*(.+)$/mi', (string) $response, $matches)) {
            throw new \RuntimeException("Import POST to {$importUrl} did not redirect (status {$status}).");
        }

        return trim($matches[1]);
    }

    /**
     * Rewrites the first action row's "type" in a real campaign export's own JSON to an unknown/
     * unregistered value - used to prove the importer's real fail-soft drop behavior
     * (CampaignImporter::saveActions() silently skips a row it can't resolve via ActionPool::get()
     * rather than rejecting the whole import) against a genuine export body, not a hand-authored
     * fixture that only approximates the real shape.
     */
    public function corruptFirstActionType(string $exportJson, string $unknownType): string
    {
        $decoded = json_decode($exportJson, true, flags: JSON_THROW_ON_ERROR);
        if (isset($decoded['actions'][0]['type'])) {
            $decoded['actions'][0]['type'] = $unknownType;
        }

        return (string) json_encode($decoded);
    }
}
