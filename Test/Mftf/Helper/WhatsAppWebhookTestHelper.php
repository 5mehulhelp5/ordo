<?php

declare(strict_types=1);

namespace Ordo\Automation\Test\Mftf\Helper;

use Magento\FunctionalTestingFramework\Helper\Helper;

/**
 * Posts a real, HMAC-SHA256-signed WhatsApp Cloud API webhook payload directly to
 * Controller\WhatsApp\Webhook - the same "messages" array shape and "X-Hub-Signature-256"
 * header Meta's own webhook delivery uses (see that controller's own docblock/@see links).
 * MFTF's browser-driven actions have no way to send a raw, custom-signed HTTP POST with a
 * non-form body, so this drives it directly the way Meta itself would, rather than skipping the
 * signature check by calling the controller/processor in-process.
 */
class WhatsAppWebhookTestHelper extends Helper
{
    /**
     * @return int the real HTTP response status code
     */
    public function postSignedInboundMessage(
        string $appSecret,
        string $fromAddress,
        string $body,
        string $baseUrl,
        string $providerMessageId = 'wamid.test0000000000000000000000'
    ): int {
        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => $fromAddress,
                                        'id' => $providerMessageId,
                                        'text' => ['body' => $body],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $rawBody = (string) json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        $url = rtrim($baseUrl, '/') . '/ordo/whatsapp/webhook';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $rawBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Hub-Signature-256: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Could not reach WhatsApp webhook endpoint at {$url}: {$error}");
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }
}
