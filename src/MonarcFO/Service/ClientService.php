<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Client;
use MonarcFO\Model\Table\ClientTable;
use MonarcCore\Service\AbstractService;

/**
 * This class is the service that handles clients. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class ClientService extends AbstractService
{
    protected $countryTable;
    protected $countryEntity;
    protected $cityTable;
    protected $cityEntity;
    protected $forbiddenFields = ['model_id'];

    /**
     * Get Total Count
     *
     * @return mixed
     */
    public function getTotalCount()
    {
        return $this->table->count();
    }

    /**
     * Get Filtered Count
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @param null $filterAnd
     * @return mixed
     */
    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        return $this->table->countFiltered(
            $this->parseFrontendFilter(
                $filter,
                ['name', 'address', 'postalcode', 'phone', 'email', 'contact_fullname', 'contact_email', 'contact_phone']
            )
        );
    }

    /**
     * Get List
     *
     * @param int $page
     * @param int $limit
     * @param null $order
     * @param null $filter
     * @param null $filterAnd
     * @return mixed
     */
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        return $this->table->fetchAllFiltered(
            ['id', 'name', 'proxy_alias', 'address', 'postalcode', 'phone', 'fax', 'email', 'contactFullname',
                'employees_number', 'contact_email', 'contact_phone', 'model_id'],
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter(
                $filter,
                ['name', 'address', 'postalcode', 'phone', 'email', 'contactFullname', 'contact_email', 'contact_phone']
            )
        );
    }

    /**
     * Get Entity
     *
     * @param $id
     * @return mixed
     */
    public function getEntity($id)
    {
        $client = $this->table->get($id);

        if (!empty($client['country_id'])) {
            $country = $this->get('countryTable')->get($client['country_id']);
            $client['country'] = $country;
        }
        if (!empty($client['city_id'])) {
            $city = $this->get('cityTable')->get($client['city_id']);
            $client['city'] = $city;
        }

        return $client;
    }

    /**
     * Create
     *
     * @param $data
     * @param bool $last
     */
    public function create($data, $last = true)
    {
        $entity = $this->get('clientEntity');
        $entity->exchangeArray($data);

        $this->table->save($entity, $last);
    }

    /**
     * Update
     *
     * @param $id
     * @param $data
     * @return bool
     */
    public function update($id, $data)
    {
        //security
        $this->filterPatchFields($data);

        /** @var ClientTable $clientTable */
        $clientTable = $this->table;

        /** @var Client $entity */
        $entity = $clientTable->getEntity($id);

        if (isset($data['proxy_alias'])) {
            // Don't allow changing the proxy_alias once set
            unset($data['proxy_alias']);
        }

        if ($entity != null) {
            $entity->exchangeArray($data, true);
            $clientTable->save($entity);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Delete
     *
     * @param $id
     */
    public function delete($id)
    {
        /** @var ClientTable $clientTable */
        $clientTable = $this->table;

        $clientTable->delete($id);
    }

    /**
     * Get Json Data
     *
     * @return array
     */
    public function getJsonData()
    {
        $var = get_object_vars($this);
        foreach ($var as &$value) {
            if (is_object($value) && method_exists($value, 'getJsonData')) {
                $value = $value->getJsonData();
            }
        }
        return $var;
    }
}
