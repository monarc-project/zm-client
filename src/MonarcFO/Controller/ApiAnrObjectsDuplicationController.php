<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Model\Entity\AbstractEntity;
use Zend\View\Model\JsonModel;

/**
 * Api ANR Objects Duplication Controller
 *
 * Class ApiAnrObjectsDuplicationController
 * @package MonarcFO\Controller
 */
class ApiAnrObjectsDuplicationController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
        if (isset($data['id'])) {
            $id = $this->getService()->duplicate($data, AbstractEntity::FRONT_OFFICE);

            return new JsonModel([
                'status' => 'ok',
                'id' => $id,
            ]);
        } else {
            throw new \MonarcCore\Exception\Exception('Object to duplicate is required');
        }
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function getList()
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