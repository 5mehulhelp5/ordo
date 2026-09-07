<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\WhatsApp;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Helper\Config;

/**
 * Submits a WhatsApp message template to Meta for approval, and polls its current status - the
 * two Meta Graph API calls Controller\Adminhtml\WhatsAppTemplate\SubmitForReview and
 * RefreshStatus each make. Real HTTP calls to the documented Graph API REST endpoints, no SDK -
 * same "plain HTTP over a new SDK" choice this module already made for Google Ads/Meta ad-
 * audience sync (see Model/AdAudience/GoogleAdsSyncClient.php).
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/guides/create-templates
 */
class WhatsAppTemplateClient
{
    private const string API_VERSION = 'v20.0';
    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @return string Meta's own template id for this submission.
     */
    public function submitTemplate(
        string $metaTemplateName,
        string $category,
        string $language,
        string $bodyText
    ): string {
        $body = $this->request(
            'POST',
            sprintf(
                'https://graph.facebook.com/%s/%s/message_templates',
                self::API_VERSION,
                $this->config->getWhatsAppBusinessAccountId()
            ),
            [
                'name' => $metaTemplateName,
                'category' => strtoupper($category),
                'language' => $language,
                'components' => [
                    ['type' => 'BODY', 'text' => $bodyText],
                ],
            ]
        );

        $id = $body['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new \RuntimeException('Meta message_templates:create response had no template id.');
        }

        return $id;
    }

    /**
     * @return array{status: string, rejectionReason: ?string} status is Meta's own uppercase
     *   value (PENDING/APPROVED/REJECTED/...) - callers map it to WhatsAppTemplate::STATUS_*.
     */
    public function getTemplateStatus(string $metaTemplateId): array
    {
        $body = $this->request(
            'GET',
            sprintf(
                'https://graph.facebook.com/%s/%s?fields=status,rejected_reason',
                self::API_VERSION,
                $metaTemplateId
            ),
            null
        );

        $status = $body['status'] ?? null;
        if (!is_string($status) || $status === '') {
            throw new \RuntimeException('Meta template status response had no status.');
        }

        $rejectionReason = $body['rejected_reason'] ?? null;

        return [
            'status' => $status,
            'rejectionReason' => is_string($rejectionReason) && $rejectionReason !== '' ? $rejectionReason : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<mixed>
     */
    private function request(string $method, string $url, ?array $payload): array
    {
        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Authorization', 'Bearer ' . $this->config->getWhatsAppAccessToken());

        if ($method === 'GET') {
            $this->curl->get($url);
        } else {
            $this->curl->post($url, (string) json_encode($payload));
        }

        $status = $this->curl->getStatus();
        $responseBody = (string) $this->curl->getBody();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                sprintf('Meta Graph API request to %s failed (HTTP %d): %s', $url, $status, $responseBody)
            );
        }

        $decoded = json_decode($responseBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}
