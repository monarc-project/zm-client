<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Provider;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsDeletionException;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsGetClientException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;
use Monarc\FrontOffice\Stats\Exception\StatsUpdateClientException;
use Monarc\FrontOffice\Stats\Exception\WrongResponseFormatException;

class StatsApiProvider
{
    private const BASE_URI = '/api/v1';

    private const DEFAULT_TIMEOUT = 30;

    /** @var Client $guzzleClient */
    private $guzzleClient;

    /** @var string */
    private $apiKey;

    /** @var string */
    private $appVersion;

    /**
     * @throws InvalidConfigurationException
     */
    public function __construct(array $config, callable $handler = null)
    {
        if ($handler === null) {
            $this->validateConfig($config);

            $this->guzzleClient = new Client([
                'base_uri' => $config['statsApi']['baseUrl'] . self::BASE_URI,
                'timeout' => $config['statsApi']['timeout'] ?? self::DEFAULT_TIMEOUT,
            ]);
        } else {
            $handlerStack = HandlerStack::create($handler);
            $this->guzzleClient = new Client(['handler' => $handlerStack]);
        }

        $this->apiKey = $config['statsApi']['apiKey'];
        $this->appVersion = $config['appVersion'];
    }

    /**
     * @param array $params
     *
     * @return StatsDataObject[]
     *
     * @throws StatsFetchingException
     * @throws WrongResponseFormatException
     */
    public function getStatsData(array $params): array
    {
        $response = $this->guzzleClient->get('stats/', [
            'headers' => $this->getHeaders(),
            'json' => $params,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new StatsFetchingException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return $this->buildFormattedResponse($response->getBody()->getContents());
    }

    /**
     * @param array $params
     *
     * @return StatsDataObject[]
     *
     * @throws StatsFetchingException
     * @throws WrongResponseFormatException
     */
    public function getProcessedStatsData(array $params): array
    {
        $response = $this->guzzleClient->get('stats/processed/', [
            'headers' => $this->getHeaders(),
            'json' => $params,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new StatsFetchingException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return $this->buildFormattedResponse($response->getBody()->getContents());
    }

    /**
     * @param StatsDataObject[] $data
     *
     * @throws StatsSendingException
     */
    public function sendStatsDataInBatch(array $data): void
    {
        $response = $this->guzzleClient->post('stats/', [
            'headers' => $this->getHeaders(),
            'json' => $data,
        ]);

        if (!\in_array($response->getStatusCode(), [200, 201, 204], true)) {
            // TODO: send a notification or email.
            throw new StatsSendingException($response->getBody()->getContents(), $response->getStatusCode());
        }
    }

    /**
     * @param string $anrUuid
     *
     * @throws StatsDeletionException
     */
    public function deleteStatsForAnr(string $anrUuid): void
    {
        $response = $this->guzzleClient->delete('stats/' . $anrUuid, [
            'headers' => $this->getHeaders(),
        ]);

        if ($response->getStatusCode() !== 204) {
            // TODO: send a notification or email.
            throw new StatsDeletionException($response->getBody()->getContents(), $response->getStatusCode());
        }
    }

    public function getClient(): array
    {
        $response = $this->guzzleClient->get('client/me', [
            'headers' => $this->getHeaders(),
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new StatsGetClientException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    public function updateClient(array $data): array
    {
        $response = $this->guzzleClient->patch('client/me', [
            'headers' => $this->getHeaders(),
            'json' => $data,
        ]);

        if (!\in_array($response->getStatusCode(), [200, 201, 204], true)) {
            throw new StatsUpdateClientException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getHeaders(): array
    {
        return [
            'X-API-KEY' => $this->apiKey,
            'User-Agent' => 'MONARC/' . $this->appVersion,
        ];
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function validateConfig(array $config): void
    {
        if (empty($config['statsApi']['baseUrl'])) {
            throw new InvalidConfigurationException(['statsApi.baseUrl']);
        }
        if (empty($config['appVersion'])) {
            throw new InvalidConfigurationException(['appVersion']);
        }
    }

    /**
     * @throws WrongResponseFormatException
     */
    private function buildFormattedResponse(string $responseContents): array
    {
        $formattedResponse = [];
        $response = json_decode($responseContents, true);
        if (!isset($response['data'])) {
            throw new WrongResponseFormatException(['"data"']);
        }

        if (!empty($response['data'])) {
            // Return the processed data response as received.
            if (isset($response['processor'])) {
                return $response['data'];
            }

            foreach ($response['data'] as $itemNum => $responseData) {
                if (!isset($responseData['type'], $responseData['data'])) {
                    throw new WrongResponseFormatException(
                        ['"data.' . $itemNum . '.type"', '"data.' . $itemNum . '.data"']
                    );
                }

                $formattedResponse[] = new StatsDataObject($responseData);
            }
        }

        return $formattedResponse;
    }
}
