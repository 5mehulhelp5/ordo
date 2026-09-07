<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\AdAudience;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Api\AdAudience\SyncClientInterface;
use Ordo\Automation\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Syncs a hashed-email list to Meta as a Custom Audience: creates the audience the first time
 * (no external_audience_id yet), then POSTs a "usersreplace" call — the documented way to
 * overwrite an audience's member list wholesale on every sync, rather than accumulating stale
 * members across syncs the way a plain "add" would. Real HTTP calls to the documented Marketing
 * API v19 REST endpoints, same "plain HTTP, no new SDK" choice as GoogleAdsSyncClient.
 *
 * Unlike Google Ads, Meta's long-lived system-user access token needs no separate refresh flow
 * here — it's used directly from config.
 *
 * @see https://developers.facebook.com/docs/marketing-api/audiences/guides/custom-audiences/
 * @see https://developers.facebook.com/docs/marketing-api/reference/custom-audience/users/
 */
class MetaSyncClient implements SyncClientInterface
{
    private const string API_VERSION = 'v19.0';
    private const int TIMEOUT_SECONDS = 30;
    private const string SCHEMA = 'EMAIL_SHA256';

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sync(?string $externalAudienceId, array $hashedEmails): ?string
    {
        $accessToken = $this->config->getMetaAccessToken();
        $createdAudienceId = null;

        if ($externalAudienceId === null || $externalAudienceId === '') {
            $externalAudienceId = $this->createAudience($accessToken);
            $createdAudienceId = $externalAudienceId;
        }

        $this->replaceUsers($externalAudienceId, $hashedEmails, $accessToken);

        return $createdAudienceId;
    }

    private function createAudience(string $accessToken): string
    {
        $adAccountId = $this->config->getMetaAdAccountId();

        $body = $this->request(
            sprintf('https://graph.facebook.com/%s/act_%s/customaudiences', self::API_VERSION, $adAccountId),
            [
                'name' => 'Ordo Automation Segment Audience',
                'subtype' => 'CUSTOM',
                'description' => 'Synced by Ordo Automation (Model\\AdAudience\\MetaSyncClient)',
                'customer_file_source' => 'USER_PROVIDED_ONLY',
                'access_token' => $accessToken,
            ]
        );

        $audienceId = $body['id'] ?? null;
        if (!is_string($audienceId) || $audienceId === '') {
            throw new \RuntimeException('Meta customaudiences create response had no id.');
        }

        return $audienceId;
    }

    /**
     * @param array<int, string> $hashedEmails
     */
    private function replaceUsers(string $audienceId, array $hashedEmails, string $accessToken): void
    {
        $body = $this->request(
            sprintf('https://graph.facebook.com/%s/%s/usersreplace', self::API_VERSION, $audienceId),
            [
                'payload' => [
                    'schema' => [self::SCHEMA],
                    'data' => array_map(static fn (string $hash): array => [$hash], $hashedEmails),
                ],
                'access_token' => $accessToken,
            ]
        );

        // Meta returns HTTP 200 here even when it silently rejected some of the batch (malformed
        // hashes, mostly) - num_invalid_entries is the only place that shows up. Without this,
        // Cron\SyncAdAudiences records a full success (based on count($hashedEmails), the batch
        // size sent, not the batch size accepted) even when Meta accepted none of it.
        $rawNumInvalid = $body['num_invalid_entries'] ?? 0;
        $numInvalid = is_numeric($rawNumInvalid) ? (int) $rawNumInvalid : 0;
        if ($numInvalid > 0) {
            $this->logger->warning(sprintf(
                'Ordo_Automation: Meta rejected %d of %d entries as invalid for audience %s.',
                $numInvalid,
                count($hashedEmails),
                $audienceId
            ));
        }
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
        $this->curl->post($url, (string) json_encode($payload));

        $status = $this->curl->getStatus();
        $responseBody = (string) $this->curl->getBody();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                sprintf('Meta Marketing API request to %s failed (HTTP %d): %s', $url, $status, $responseBody)
            );
        }

        $decoded = json_decode($responseBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}
