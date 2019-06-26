<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api Anr Record Data Subjects Controller
 *
 * Class ApiAnrRecordDataSubjectsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecordDataSubjectsController extends ApiAnrAbstractController
{
    protected $name = 'record-data-subjects';
    protected $dependencies = ['anr'];

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
