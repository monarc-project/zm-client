<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Service\AnrObjectService;

/**
 * Api ANR Objects Import Controller
 *
 * Class ApiAnrObjectsImportController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrObjectsImportController extends ApiAnrImportAbstractController
{
    protected $name = 'objects';

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        $filter = $this->params()->fromQuery("filter");
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $objects = $this->getService()->getCommonObjects($anrId,$filter);
        return new JsonModel([
            'count' => count($objects),
            $this->name => $objects,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;
        /** @var AnrObjectService $anrObjectService */
        $anrObjectService = $this->getService();
        $monarcObject = $anrObjectService->importFromCommon($id, $data);
        if ($monarcObject === null) {
            throw new Exception('An error occurred during the import of the object.', 412);
        }

        return new JsonModel([
            'status' => 'ok',
            'id' => $monarcObject->getUuid()
        ]);
    }

    /**
     * @inheritdoc
     */
    public function get($id)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $object = $this->getService()->getCommonEntity($anrId, $id);

        $this->formatDependencies($object, ['asset', 'category', 'rolfTag']);
        unset($object['anrs']);

        return new JsonModel($object);
    }
}
