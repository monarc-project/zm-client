<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2023 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Measure;
use Monarc\FrontOffice\Model\Entity\SoaScaleComment;
use Monarc\FrontOffice\Service\AnrInstanceRiskOpService;
use Monarc\FrontOffice\Service\AnrInstanceRiskService;
use Monarc\FrontOffice\Service\AnrMeasureService;
use Monarc\FrontOffice\Service\SoaScaleCommentService;
use Monarc\FrontOffice\Service\SoaService;

class ApiSoaController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

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

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $filterAnd = ['anr' => $anr->getId()];

        if ($referential) {
            if ($category !== 0) {
                $filterMeasures['category'] = [
                    'op' => 'IN',
                    'value' => (array)$category,
                ];
            } elseif ($category === -1) {
                $filterMeasures['category'] = null;
            }

            $filterMeasures['r.anr'] = $anr->getId();
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
            $filterAnd['m.anr'] = $anr->getId();
        }

        if ($order === 'measure') {
            $order = 'm.code';
        } elseif ($order === '-measure') {
            $order = '-m.code';
        }
        $soaScaleCommentsData = $this->soaScaleCommentService->getSoaScaleCommentsData($anr);
        $entities = $this->soaService->getList($page, $limit, $order, $filter, $filterAnd);
        foreach ($entities as $key => $entity) {
            $amvs = [];
            $rolfRisks = [];

            /** @var SoaScaleComment $soaScaleComment */
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
                $entity['measure']->rolfRisks = $this->anrInstanceRiskOpService->getOperationalRisks(
                    $measure->getAnr(),
                    null,
                    [
                        'rolfRisks' => $rolfRisks,
                        'limit' => -1,
                        'order' => 'cacheNetRisk',
                        'order_direction' => 'desc',
                    ]
                );
            }
            $entity['measure']->amvs = [];
            if (!empty($amvs)) {
                $entity['measure']->amvs = $this->anrInstanceRiskService->getInstanceRisks($measure->getAnr(), null, [
                    'amvs' => $amvs,
                    'limit' => -1,
                    'order' => 'maxRisk',
                    'order_direction' => 'desc',
                ]);
            }
            $entities[$key]['anr'] = [
                'id' => $measure->getAnr()->getId(),
                'label' => $measure->getAnr()->getLabel(),
            ];
            $entities[$key]['measure'] = $measure->getJsonArray();
            $entities[$key]['measure']['category'] = $measure->getCategory()->getJsonArray();
            $entities[$key]['measure']['referential'] = $measure->getReferential()->getJsonArray();
            $entities[$key]['measure']['linkedMeasures'] = [];
            foreach ($measure->getLinkedMeasures() as $linkedMeasure) {
                $entities[$key]['measure']['linkedMeasures'][] = $linkedMeasure->getUuid();
            }
            if ($soaScaleComment !== null) {
                $entities[$key]['soaScaleComment'] = $soaScaleCommentsData[$soaScaleComment->getId()];
            } else {
                $entities[$key]['soaScaleComment'] = null;
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => $this->soaService->getFilteredCount($filter, $filterAnd),
            'soaMeasures' => $entities,
        ]);
    }

    public function get($id)
    {
        $entity = $this->soaService->getEntity((int)$id);
        /** @var Measure $measure */
        $measure = $entity['measure'];
        /** @var SoaScaleComment $measure */
        $soaScaleComment = $entity['soaScaleComment'];

        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $soaScaleCommentsData = $this->soaScaleCommentService->getSoaScaleCommentsData($anr);

        $entity['anr'] = [
            'id' => $measure->getAnr()->getId(),
            'label' => $measure->getAnr()->getLabel(),
        ];
        $entity['measure'] = $measure->getJsonArray();
        $entity['measure']['category'] = $measure->getCategory()->getJsonArray();
        $entity['measure']['referential'] = $measure->getReferential()->getJsonArray();
        if ($soaScaleComment !== null) {
            $entity['soaScaleComment'] = $soaScaleCommentsData[$soaScaleComment->getId()];
        } else {
            $entity['soaScaleComment'] = null;
        }

        return $this->getPreparedJsonResponse($entity);
    }

    public function patch($id, $data)
    {
        $this->soaService->patchSoa($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function patchList($data)
    {
        /** @var Anr $anr */
        $anr = $this->getRequest()->getAttribute('anr');

        $createdObjects = [];
        foreach ($data as $newData) {
            $newData['anr'] = $anr->getId();
            $newData['measure'] = ['anr' => $anr->getId(), 'uuid' => $newData['measure']['uuid']];
            $id = $newData['id'];
            if (\is_array($newData['soaScaleComment'])) {
                $newData['soaScaleComment'] = $newData['soaScaleComment']['id'];
            }
            $this->soaService->patchSoa($id, $newData);
            $createdObjects[] = $id;
        }

        return $this->getSuccessfulJsonResponse([
            'id' => $createdObjects,
        ]);
    }
}
