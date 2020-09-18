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
        $query = '';
        foreach ($params as $name => $value) {
            if (\is_array($value)) {
                foreach ($value as $v) {
                    $query .= $name . '=' . $v . '&';
                }
            } else {
                $query .= $name . '=' . $value . '&';
            }
        }
        $query = substr($query, 0, -1);

        $response = $this->guzzleClient->get(self::BASE_URI . '/stats', [
            'headers' => $this->getAuthHeaders(),
            'query' => $query,
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
        $response = $this->guzzleClient->post(self::BASE_URI . '/stats', [
            'headers' => $this->getAuthHeaders(),
            'json' => $data,
        ]);

        if (!\in_array($response->getStatusCode(), [200, 201, 204], true)) {
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

    /**
     * @throws WrongResponseFormatException
     */
    private function buildFormattedResponse(string $responseContents): array
    {
        $formattedResponse = [];
        $response = json_decode($responseContents, true);
        if (!isset($response['metadata']['count'], $response['data'])) {
            throw new WrongResponseFormatException(['"metadata.count"', '"data"']);
        }

        if ($response['metadata']['count'] > 0) {
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
