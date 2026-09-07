<?php
declare(strict_types=1);

namespace Ordo\Automation\Model\AdAudience;

use Magento\Framework\HTTP\Client\Curl;
use Ordo\Automation\Api\AdAudience\SyncClientInterface;
use Ordo\Automation\Helper\Config;

/**
 * Syncs a hashed-email list to Google Ads as a Customer Match user list via the REST-mapped
 * OfflineUserDataJobService, the officially documented flow: (1) create an offline user data
 * job against the target user list, (2) add one AddUserListOperation containing every hashed
 * email as a UserIdentifier, (3) run the job. Real HTTP calls to the documented v17 REST
 * endpoints — not the gRPC client library, to avoid this module's first heavyweight/grpc
 * dependency for a single feature (same "plain HTTP over a new SDK" choice RssFetcher already
 * made for its own external call).
 *
 * $externalAudienceId here is the target user list's resourceName (customers/{id}/userLists/{id})
 * — creating that user list itself is a one-time setup step done in the Google Ads UI/API
 * directly, out of this class's scope; this class only ever adds members to an existing list.
 *
 * @see https://developers.google.com/google-ads/api/docs/remarketing/audience-types/customer-match
 * @see https://developers.google.com/google-ads/api/rest/docs/rest-api
 */
class GoogleAdsSyncClient implements SyncClientInterface
{
    private const string API_VERSION = 'v17';
    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly GoogleOAuthTokenProvider $tokenProvider
    ) {
    }

    public function sync(?string $externalAudienceId, array $hashedEmails): ?string
    {
        if ($externalAudienceId === null || $externalAudienceId === '') {
            throw new \RuntimeException(
                'GoogleAdsSyncClient requires an existing user list resourceName as external_audience_id — '
                . 'creating the list itself is a one-time setup step done directly in Google Ads.'
            );
        }

        $customerId = $this->config->getGoogleAdsLoginCustomerId();
        $accessToken = $this->tokenProvider->getAccessToken();

        $jobResourceName = $this->createOfflineUserDataJob($customerId, $externalAudienceId, $accessToken);
        $this->addOperations($jobResourceName, $hashedEmails, $accessToken);
        $this->runJob($jobResourceName, $accessToken);

        return null;
    }

    private function createOfflineUserDataJob(
        string $customerId,
        string $userListResourceName,
        string $accessToken
    ): string {
        $body = $this->request(
            sprintf(
                'https://googleads.googleapis.com/%s/customers/%s/offlineUserDataJobs:create',
                self::API_VERSION,
                $customerId
            ),
            [
                'job' => [
                    'type' => 'CUSTOMER_MATCH_USER_LIST',
                    'customerMatchUserListMetadata' => ['userList' => $userListResourceName],
                ],
            ],
            $accessToken
        );

        $resourceName = $body['resourceName'] ?? null;
        if (!is_string($resourceName) || $resourceName === '') {
            throw new \RuntimeException('Google Ads offlineUserDataJobs:create response had no resourceName.');
        }

        return $resourceName;
    }

    /**
     * @param array<int, string> $hashedEmails
     */
    private function addOperations(string $jobResourceName, array $hashedEmails, string $accessToken): void
    {
        $body = $this->request(
            sprintf(
                'https://googleads.googleapis.com/%s/%s:addOperations',
                self::API_VERSION,
                $jobResourceName
            ),
            [
                'operations' => [[
                    'create' => [
                        'userIdentifiers' => array_map(
                            static fn (string $hash): array => ['hashedEmail' => $hash],
                            $hashedEmails
                        ),
                    ],
                ]],
            ],
            $accessToken
        );

        // Google Ads can return HTTP 200 for this call while still rejecting some or all of the
        // batch (a malformed hash, a policy violation, ...), reported via partialFailureError
        // rather than a non-2xx status - a batch that returns 200 with every identifier rejected
        // would otherwise be recorded as a full sync success by Cron\SyncAdAudiences.
        if (isset($body['partialFailureError'])) {
            $partialFailureError = $body['partialFailureError'];
            throw new \RuntimeException(sprintf(
                'Google Ads rejected part of the batch for %s: %s',
                $jobResourceName,
                is_string($partialFailureError) ? $partialFailureError : (string) json_encode($partialFailureError)
            ));
        }
    }

    private function runJob(string $jobResourceName, string $accessToken): void
    {
        $this->request(
            sprintf(
                'https://googleads.googleapis.com/%s/%s:run',
                self::API_VERSION,
                $jobResourceName
            ),
            [],
            $accessToken
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<mixed>
     */
    private function request(string $url, array $payload, string $accessToken): array
    {
        $this->curl->setTimeout(self::TIMEOUT_SECONDS);
        $this->curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('Authorization', 'Bearer ' . $accessToken);
        $this->curl->addHeader('developer-token', $this->config->getGoogleAdsDeveloperToken());
        $this->curl->post($url, (string) json_encode($payload));

        $status = $this->curl->getStatus();
        $responseBody = (string) $this->curl->getBody();

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(
                sprintf('Google Ads API request to %s failed (HTTP %d): %s', $url, $status, $responseBody)
            );
        }

        $decoded = json_decode($responseBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}
