<?php
/**
* @link      https://github.com/monarc-project for the canonical source repository
* @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
* @license   MONARC is licensed under GNU Affero General Public License version 3
*/

namespace MonarcFO\Service;

/**

* @package MonarcFO\Service
*/
class SoaCategoryService extends \MonarcCore\Service\AbstractService
{
    protected $table;
    protected $entity;
    protected $anrTable;
    protected $userAnrTable;
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4', 'code', 'status'];

    protected $dependencies = ['anr', 'referential'];
    protected $forbiddenFields = ['anr'];

    /**
    * @inheritdoc
    */
    public function patch($id, $data)
    {
        // Filter unwanted fields
        $this->filterPatchFields($data);
        parent::patch($id, $data);
    }
}
