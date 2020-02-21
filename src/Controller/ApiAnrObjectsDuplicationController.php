<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Model\Entity\AbstractEntity;
use Laminas\View\Model\JsonModel;

/**
 * Api ANR Objects Duplication Controller
 *
 * Class ApiAnrObjectsDuplicationController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrObjectsDuplicationController extends ApiAnrAbstractController
{
    /**
     * @inheritdoc
     */
    public function create($data)
    {
      $anrId = (int)$this->params()->fromRoute('anrid');
      if (!empty($anrId)) {
          $data['anr'] = $anrId;
      }
        if (isset($data['id'])) {
            $id = $this->getService()->duplicate($data, AbstractEntity::FRONT_OFFICE);

            return new JsonModel([
                'status' => 'ok',
                'id' => $id,
            ]);
        } else {
            throw new \Monarc\Core\Exception\Exception('Object to duplicate is required');
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
