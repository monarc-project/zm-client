<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Service\AnrRecordService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Record Duplicate Controller
 *
 * Class ApiAnrRecordDuplicateController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRecordDuplicateController extends \Monarc\Core\Controller\AbstractController
{
    protected $name = 'records';

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var AnrRecordService $service */
        $service = $this->getService();
        if (!isset($data['record'])) {
            throw new \Monarc\Core\Exception\Exception('Record missing', 412);
        }

        $id = $service->duplicateRecord(intval($data['record']), $data['label']);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}
