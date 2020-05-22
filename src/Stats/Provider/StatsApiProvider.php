<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Stats\Provider;

use Doctrine\ORM\EntityNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;
use Monarc\FrontOffice\Model\Entity\Setting;
use Monarc\FrontOffice\Model\Table\SettingTable;
use Monarc\FrontOffice\Stats\DataObject\StatsDataObject;
use Monarc\FrontOffice\Stats\Exception\StatsFetchingException;
use Monarc\FrontOffice\Stats\Exception\StatsSendingException;

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
     */
    public function getStatsData(array $params): array
    {
        $response = $this->guzzleClient->get(self::BASE_URI . '/stats', [
            'headers' => $this->getAuthHeaders(),
            'query' => $params,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new StatsFetchingException($response->getBody()->getContents(), $response->getStatusCode());
        }

        return $this->buildResponse($response->getBody()->getContents());
    }

    /**
     * @param StatsDataObject[] $data
     *
     * @throws StatsSendingException
     */
    public function sendStatsDataInBatch(array $data): void
    {
        $response = $this->guzzleClient->post(self::BASE_URI . '/stats', [
            'headers' => $this->getAuthHeaders(),
            'json' => $data,
        ]);

        if ($response->getStatusCode() !== 200) {
            // TODO: send a notification or email.
            throw new StatsSendingException($response->getBody()->getContents(), $response->getStatusCode());
        }
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

    private function buildResponse(string $responseContents): array
    {
        $response = [];
        foreach (json_decode($responseContents, true) as $data) {
            $response[] = new StatsDataObject($data);
        }

        return $response;
    }
}
