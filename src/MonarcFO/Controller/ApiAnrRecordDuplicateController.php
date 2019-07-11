<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\MonarcObject;
use MonarcFO\Service\AnrRecordService;
use Zend\View\Model\JsonModel;

/**
 * Api Anr Record Duplicate Controller
 *
 * Class ApiAnrRecordDuplicateController
 * @package MonarcFO\Controller
 */
class ApiAnrRecordDuplicateController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'records';

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var AnrRecordService $service */
        $service = $this->getService();
        file_put_contents('php://stderr', print_r($data, TRUE).PHP_EOL);
        if (!isset($data['record'])) {
            throw new \MonarcCore\Exception\Exception('Record missing', 412);
        }

        $id = $service->duplicateRecord(intval($data['record']), $data['label']);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}
