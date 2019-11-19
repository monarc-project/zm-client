<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Model\Entity\Client;
use Monarc\FrontOffice\Model\Table\ClientTable;
use Monarc\Core\Service\AbstractService;

/**
 * This class is the service that handles clients. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class ClientService extends AbstractService
{
    protected $forbiddenFields = ['model_id'];

    /**
     * Get Filtered Count
     *
     * @param null $filter
     * @param null $filterAnd
     * @return mixed
     */
    public function getFilteredCount($filter = null, $filterAnd = null)
    {
        return $this->table->countFiltered(
            $this->parseFrontendFilter(
                $filter,
                ['name', 'address', 'postalcode', 'email', 'contact_fullname', 'contact_email']
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
    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null, $filterJoin = null)
    {
        return $this->table->fetchAllFiltered(
            ['id', 'name', 'proxy_alias', 'address', 'postalcode', 'fax', 'email', 'contactFullname',
                'employees_number', 'contact_email', 'model_id'],
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter(
                $filter,
                ['name', 'address', 'postalcode', 'email', 'contactFullname', 'contact_email']
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
