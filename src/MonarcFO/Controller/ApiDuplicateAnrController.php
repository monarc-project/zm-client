<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcFO\Model\Entity\Object;
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

        $id = $service->duplicateAnr(intval($data['anr']), Object::SOURCE_CLIENT, null, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $id,
        ]);
    }
}