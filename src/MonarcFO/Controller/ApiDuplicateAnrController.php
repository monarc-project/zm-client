<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\MonarcObject;
use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

/**
 * Api Duplicate Anr Controller
 *
 * Class ApiDuplicateAnrController
 * @package MonarcFO\Controller
 */
class ApiDuplicateAnrController extends \MonarcCore\Controller\AbstractController
{
    protected $name = 'anrs';

    /**
     * @inheritdoc
     */
    public function create($data)
    {
        /** @var AnrService $service */
        $service = $this->getService();

        if (!isset($data['anr'])) {
            throw new \MonarcCore\Exception\Exception('Anr missing', 412);
        }

        $id = $service->duplicateAnr(intval($data['anr']), MonarcObject::SOURCE_CLIENT, null, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}
