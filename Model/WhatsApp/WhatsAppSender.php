<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\WhatsApp;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Helper\Config;

/**
 * Sends a single WhatsApp template message via the real Meta Graph API - real HTTP, no SDK, same
 * choice as WhatsAppTemplateClient above. Only template sends are supported (never a free-form
 * message): outside the 24h customer-service window a free-form send is rejected by Meta anyway,
 * and a campaign dispatch has no reliable way to know whether that window is currently open for
 * a given recipient, so Model\Campaign\Action\SendWhatsApp never even offers the choice.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
 */
class WhatsAppSender
{
    private const string API_VERSION = 'v20.0';
    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config
    ) {
    }

    /**
     * @param string[] $params Positional values for the template's {{1}}, {{2}}, ... body
     *   placeholders, in order - empty when the template has none.
     * @return string the provider message id (messages[0].id) - what a later delivery-status
     *   webhook correlates back to this send.
     */
    public function send(string $toPhone, string $metaTemplateName, string $language, array $params): string
    {
        $components = [];
        if ($params !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(
                    static fn (string $value): array => ['type' => 'text', 'text' => $value],
                    $params
                ),
            ];
        }

        $body = $this->request(
            sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                self::API_VERSION,
                $this->config->getWhatsAppPhoneNumberId()
            ),
            [
                'messaging_product' => 'whatsapp',
                'to' => $toPhone,
                'type' => 'template',
                'template' => [
                    'name' => $metaTemplateName,
                    'language' => ['code' => $language],
                    'components' => $components,
                ],
            ]
        );

        $messageId = $body['messages'][0]['id'] ?? null;
        if (!is_string($messageId) || $messageId === '') {
            throw new \RuntimeException('Meta messages:send response had no message id.');
        }

        return $messageId;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    private function request(string $url, array $payload): array
    {
        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Authorization', 'Bearer ' . $this->config->getWhatsAppAccessToken());
        $this->curl->post($url, (string) json_encode($payload));

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
