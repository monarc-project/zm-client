<?php
namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Client;
use MonarcFO\Model\Table\ClientTable;
use MonarcCore\Service\AbstractService;

class ClientService extends AbstractService
{
    protected $countryTable;
    protected $countryEntity;
    protected $cityTable;
    protected $cityEntity;
    protected $forbiddenFields = ['model_id'];

    public function getTotalCount()
    {
        return $this->table->count();
    }

    public function getFilteredCount($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        return $this->table->countFiltered($page, $limit, $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, array('name', 'address', 'postalcode', 'phone', 'email',
                'contact_fullname', 'contact_email', 'contact_phone')));
    }

    public function getList($page = 1, $limit = 25, $order = null, $filter = null, $filterAnd = null)
    {
        return $this->table->fetchAllFiltered(
            array('id', 'name', 'proxy_alias', 'address', 'postalcode', 'phone', 'fax', 'email', 'contactFullname',
                'employees_number', 'contact_email', 'contact_phone', 'model_id'),
            $page,
            $limit,
            $this->parseFrontendOrder($order),
            $this->parseFrontendFilter($filter, array('name', 'address', 'postalcode', 'phone', 'email',
                'contactFullname', 'contact_email', 'contact_phone'))
        );
    }

    public function getEntity($id)
    {
        $client = $this->table->get($id);

        if(!empty($client['country_id'])){
            $country = $this->get('countryTable')->get($client['country_id']);
            $client['country'] = $country;
        }
        if(!empty($client['city_id'])){
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

        $this->table->save($entity);
    }

    public function update($id, $data) {

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
            $entity->exchangeArray($data,true);
            $clientTable->save($entity);
            return true;
        } else {
            return false;
        }
    }

    public function delete($id)
    {
        /** @var ClientTable $clientTable */
        $clientTable = $this->table;

        $entity = $clientTable->getEntity($id);

        $clientTable->delete($id);
    }

    public function getJsonData() {
        $var = get_object_vars($this);
        foreach ($var as &$value) {
            if (is_object($value) && method_exists($value,'getJsonData')) {
                $value = $value->getJsonData();
            }
        }
        return $var;
    }
}
