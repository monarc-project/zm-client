<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrMeasureService;
use Monarc\FrontOffice\Service\SoaScaleCommentService;
use Monarc\FrontOffice\Service\SoaService;

/**
 * Api Anr Soa Controller
 *
 * Class ApiAnrSoaController
 * @package Monarc\FrontOffice\Controller
 */
class ApiSoaController extends AbstractRestfulController
{
    private SoaService $soaService;

    private AnrMeasureService $anrMeasureService;

    private AnrInstanceRiskService $anrInstanceRiskService;

    private AnrInstanceRiskOpService $anrInstanceRiskOpService;

    private SoaScaleCommentService $soaScaleCommentService;

    public function __construct(
        SoaService $soaService,
        AnrMeasureService $anrMeasureService,
        AnrInstanceRiskService $anrInstanceRiskService,
        AnrInstanceRiskOpService $anrInstanceRiskOpService,
        SoaScaleCommentService $soaScaleCommentService
    ) {

        $this->soaService = $soaService;
        $this->anrMeasureService = $anrMeasureService;
        $this->anrInstanceRiskService = $anrInstanceRiskService;
        $this->anrInstanceRiskOpService = $anrInstanceRiskOpService;
        $this->soaScaleCommentService = $soaScaleCommentService;
    }

    public function getList()
    {
        $page = (int)$this->params()->fromQuery('page', 1);
        $limit = (int)$this->params()->fromQuery('limit', 0);
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $category = (int)$this->params()->fromQuery('category', 0);
        $referential = $this->params()->fromQuery('referential');

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $filterAnd = ['anr' => $anrId];

        if ($referential) {
            if ($category !== 0) {
                $filterMeasures['category'] = [
                    'op' => 'IN',
                    'value' => (array)$category,
                ];
            } elseif ($category === -1) {
                $filterMeasures['category'] = null;
            }

            $filterMeasures['r.anr'] = $anrId;
            $filterMeasures['r.uuid'] = $referential;

            $measuresFiltered = $this->anrMeasureService->getList(1, 0, null, null, $filterMeasures);
            $measuresFilteredId = [];
            foreach ($measuresFiltered as $key) {
                $measuresFilteredId[] = $key['uuid'];
            }
            $filterAnd['m.uuid'] = [
                'op' => 'IN',
                'value' => $measuresFilteredId,
            ];
            $filterAnd['m.anr'] = $anrId;
        }

        if ($order === 'measure') {
            $order = 'm.code';
        } elseif ($order === '-measure') {
            $order = '-m.code';
        }
        $soaScaleCommentsData = $this->soaScaleCommentService->getSoaScaleCommentsDataById($anrId);
        $entities = $this->soaService->getList($page, $limit, $order, $filter, $filterAnd);
        foreach ($entities as $key => $entity) {
            $amvs = [];
            $rolfRisks = [];

            /** @var SoaScaleComment $measure */
            $soaScaleComment = $entity['soaScaleComment'];

            /** @var Measure $measure */
            $measure = $entity['measure'];
            foreach ($measure->getAmvs() as $amv) {
                $amvs[] = $amv->getUuid();
            }
            foreach ($measure->getRolfRisks() as $rolfRisk) {
                $rolfRisks[] = $rolfRisk->getId();
            }
            $entity['measure']->rolfRisks = [];
            if (!empty($rolfRisks)) {
                $entity['measure']->rolfRisks = $this->anrInstanceRiskOpService->getOperationalRisks($anrId, null, [
                    'rolfRisks' => $rolfRisks,
                    'limit' => -1,
                    'order' => 'cacheNetRisk',
                    'order_direction' => 'desc',
                ]);
            }
            $entity['measure']->amvs = [];
            if (!empty($amvs)) {
                $entity['measure']->amvs = $this->anrInstanceRiskService->getInstanceRisks($anrId, null, [
                    'amvs' => $amvs,
                    'limit' => -1,
                    'order' => 'maxRisk',
                    'order_direction' => 'desc',
                ]);
            }
            $entities[$key]['anr'] = $measure->getAnr()->getJsonArray();
            $entities[$key]['measure'] = $measure->getJsonArray();
            $entities[$key]['measure']['category'] = $measure->getCategory()->getJsonArray();
            $entities[$key]['measure']['referential'] = $measure->getReferential()->getJsonArray();
            $entities[$key]['measure']['measuresLinked'] = [];
            foreach ($measure->getMeasuresLinked() as $measureLinked) {
                $entities[$key]['measure']['measuresLinked'][] = $measureLinked->getUuid();
            }
            if ($soaScaleComment !== null) {
                $entities[$key]['soaScaleComment'] = $soaScaleCommentsData[$soaScaleComment->getId()];
            } else {
                $entities[$key]['soaScaleComment'] = null;
            }
        }

        return new JsonModel([
            'count' => $this->soaService->getFilteredCount($filter, $filterAnd),
            'soaMeasures' => $entities,
        ]);
    }

    public function get($id)
    {
        $entity = $this->soaService->getEntity($id);
        /** @var Measure $measure */
        $measure = $entity['measure'];
        /** @var SoaScaleComment $measure */
        $soaScaleComment = $entity['soaScaleComment'];

        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('"anrid" param is missing', 412);
        }
        if ($measure->getAnr()->getId() !== $anrId) {
            throw new Exception('Anr IDs are different', 412);
        }

        $soaScaleCommentsData = $this->soaScaleCommentService->getSoaScaleCommentsDataById($anrId);

        $entity['anr'] = $measure->getAnr()->getJsonArray();
        $entity['measure'] = $measure->getJsonArray();
        $entity['measure']['category'] = $measure->getCategory()->getJsonArray();
        $entity['measure']['referential'] = $measure->getReferential()->getJsonArray();
        if ($soaScaleComment !== null) {
            $entities['soaScaleComment'] = $soaScaleCommentsData[$soaScaleComment->getId()];
        } else {
            $entities['soaScaleComment'] = null;
        }

        return new JsonModel($entity);
    }

    public function patch($id, $data)
    {
        $this->soaService->patchSoa($id, $data);

        return new JsonModel(['status' => 'ok']);
    }

    public function patchList($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }

        $createdObjects = [];
        foreach ($data as $newData) {
            $newData['anr'] = $anrId;
            $newData['measure'] = ['anr' => $anrId, 'uuid' => $newData['measure']['uuid']];
            $id = $newData['id'];
            if (is_array($newData['soaScaleComment'])) {
                $newData['soaScaleComment'] = $newData['soaScaleComment']['id'];
            }
            $this->soaService->patchSoa($id, $newData);
            $createdObjects[] = $id;
        }

        return new JsonModel([
            'status' => 'ok',
            'id' => $createdObjects,
        ]);
    }
}
