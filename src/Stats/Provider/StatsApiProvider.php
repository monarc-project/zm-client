<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Provider;

use Doctrine\ORM\EntityNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Model\Entity\Setting;
use Monarc\FrontOffice\Model\Table\SettingTable;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsDeletionException;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsGetClientException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;
use Monarc\FrontOffice\Stats\Exception\WrongResponseFormatException;

class StatsApiProvider
{
    private const BASE_URI = '/api/v1';

    private const DEFAULT_TIMEOUT = 30;

    /** @var Client $guzzleClient */
    private $guzzleClient;

    /** @var string */
    private $apiKey;

    /**
     * @throws EntityNotFoundException
     * @throws InvalidConfigurationException
     */
    public function __construct(SettingTable $settingTable, array $config, callable $handler = null)
    {
        if ($handler === null) {
            $this->validateConfig($config);

            $this->guzzleClient = new Client([
                'base_uri' => $config['statsApi']['baseUrl'],
                'timeout' => $config['statsApi']['timeout'] ?? self::DEFAULT_TIMEOUT,
            ]);
        } else {
            $handlerStack = HandlerStack::create($handler);
            $this->guzzleClient = new Client(['handler' => $handlerStack]);
        }

        $this->apiKey = $settingTable->findByName(Setting::SETTINGS_STATS)->getValue()[Setting::SETTING_STATS_API_KEY];
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
        $response = $this->guzzleClient->get(self::BASE_URI . '/stats/', [
            'headers' => $this->getAuthHeaders(),
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
        $response = $this->guzzleClient->get(self::BASE_URI . '/stats/processed/', [
            'headers' => $this->getAuthHeaders(),
            'json' => $params,
        ]);
        
        file_put_contents('php://stderr', print_r($params, TRUE).PHP_EOL);

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
        $response = $this->guzzleClient->post(self::BASE_URI . '/stats/', [
            'headers' => $this->getAuthHeaders(),
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
        $response = $this->guzzleClient->delete(self::BASE_URI . '/stats/' . $anrUuid, [
            'headers' => $this->getAuthHeaders(),
        ]);

        if ($response->getStatusCode() !== 204) {
            // TODO: send a notification or email.
            throw new StatsDeletionException($response->getBody()->getContents(), $response->getStatusCode());
        }
    }

    public function getClient(): array
    {
        $response = $this->guzzleClient->get(self::BASE_URI . '/client/me', [
            'headers' => $this->getAuthHeaders(),
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new StatsGetClientException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getAuthHeaders(): array
    {
        return [
            'X-API-KEY' => $this->apiKey,
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
