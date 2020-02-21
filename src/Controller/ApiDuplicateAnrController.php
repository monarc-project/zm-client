<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Model\Entity\MonarcObject;
use Monarc\FrontOffice\Service\AnrService;
use Laminas\View\Model\JsonModel;

/**
 * Api Duplicate Anr Controller
 *
 * Class ApiDuplicateAnrController
 * @package Monarc\FrontOffice\Controller
 */
class ApiDuplicateAnrController extends \Monarc\Core\Controller\AbstractController
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
            throw new \Monarc\Core\Exception\Exception('Anr missing', 412);
        }

        $newAnr = $service->duplicateAnr((int)$data['anr'], MonarcObject::SOURCE_CLIENT, null, $data);

        return new JsonModel([
            'status' => 'ok',
            'id' => $newAnr->getId(),
        ]);
    }
}
