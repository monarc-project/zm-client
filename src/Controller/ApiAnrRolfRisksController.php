<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\FrontOffice\Service\AnrRolfRiskService;
use Monarc\FrontOffice\Model\Entity\Measure;

class ApiAnrRolfRisksController extends ApiAnrAbstractController
{
    use ControllerRequestResponseHandlerTrait;

    protected $dependencies = ['tags', 'measures'];

    public function __construct(AnrRolfRiskService $anrRolfRiskService)
    {
        parent::__construct($anrRolfRiskService);
    }

    public function get($id)
    {
        $entity = $this->getService()->getEntity($id);

        $this->formatDependencies($entity, $this->dependencies, Measure::class, ['referential']);

        return $this->getPreparedJsonResponse($entity);
    }

    /**
     * @inheritdoc
     */
    public function getList()
    {
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $tag = $this->params()->fromQuery('tag');
        $anr = $this->params()->fromRoute("anrid");

        /** @var AnrRolfRiskService $service */
        $service = $this->getService();

        $rolfRisks = $service->getListSpecific($page, $limit, $order, $filter, $tag, $anr);
        foreach ($rolfRisks as $key => $rolfRisk) {
            $this->formatDependencies($rolfRisks[$key], $this->dependencies, Measure::class, ['referential']);

            $rolfRisk['tags']->initialize();
            $rolfTags = $rolfRisk['tags']->getSnapshot();
            $rolfRisks[$key]['tags'] = [];
            foreach ($rolfTags as $rolfTag) {
                $rolfRisks[$key]['tags'][] = $rolfTag->getJsonArray();
            }
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredSpecificCount($page, $limit, $order, $filter, $tag, $anr),
            'risks' => $rolfRisks,
        ]);
    }

    public function update($id, $data)
    {
        if (!empty($data['measures'])) {
            $data['measures'] = $this->addAnrId($data['measures']);
        }

        return parent::update($id, $data);
    }

    public function patch($id, $data)
    {
        if (!empty($data['measures'])) {
            $data['measures'] = $this->addAnrId($data['measures']);
        }

        return parent::patch($id, $data);
    }


    public function patchList($data)
    {
        $service = $this->getService();
        $data['toReferential'] = $this->addAnrId($data['toReferential']);
        $service->createLinkedRisks($data['fromReferential'], $data['toReferential']);

        return $this->getSuccessfulJsonResponse();
    }

    public function create($data)
    {
        if (!empty($data['measures'])) {
            $data['measures'] = $this->addAnrId($data['measures']);
        }

        return parent::create($data);
    }
}
