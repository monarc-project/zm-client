<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Exception\Exception;
use Monarc\Core\Entity\AbstractEntity;
use Monarc\FrontOffice\Entity\RecordPersonalData;
use Monarc\FrontOffice\Entity\RecordProcessor;
use Monarc\FrontOffice\Service\AnrRecordService;
use Doctrine\ORM\PersistentCollection;

/**
 * TODO: Refactor me.
 *  - remove the inheritance from ApiAnrAbstractController (not needed).
 *  - list endpoints results with normalizer (or temporary directly here) to get rid of formatDependencies magic.
 * TODO: Refactor the controller and related Frontend:
 *  - multiple requests
 *  - call backend only when filed is edited (recipients, processors, etc).
 */
class ApiAnrRecordsController extends ApiAnrAbstractController
{
    protected $name = 'records';
    protected $dependencies = [
        'anr',
        'controller',
        'representative',
        'dpo',
        'jointControllers',
        'personalData',
        'internationalTransfers',
        'processors',
        'recipients',
    ];

    public function __construct(AnrRecordService $anrRecordService)
    {
        parent::__construct($anrRecordService);
    }

    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $page = $this->params()->fromQuery('page');
        $limit = $this->params()->fromQuery('limit');
        $order = $this->params()->fromQuery('order');
        $filter = $this->params()->fromQuery('filter');
        $filterAnd = ['anr' => $anrId];

        $service = $this->getService();

        $entities = $service->getList($page, $limit, $order, $filter, $filterAnd);
        foreach ($entities as $key => $entity) {
            $this->formatDependencies($entities[$key], $this->dependencies);
        }

        return $this->getPreparedJsonResponse([
            'count' => $service->getFilteredCount($filter, $filterAnd),
            $this->name => $entities,
        ]);
    }

    public function get($id)
    {
        $entity = $this->getService()->getEntity(['id' => $id]);

        $this->formatDependencies($entity, $this->dependencies);

        return $this->getPreparedJsonResponse($entity);
    }

    public function formatDependencies(&$entity, $dependencies, $entityDependency = "", $subField = [])
    {
        foreach ($dependencies as $dependency) {
            if (!empty($entity[$dependency])) {
                if (\is_object($entity[$dependency])) {
                    if ($entity[$dependency] instanceof AbstractEntity) {
                        if ($entity[$dependency] instanceof $entityDependency) {
                            $entity[$dependency] = $entity[$dependency]->getJsonArray();
                            if (!empty($subField)) {
                                foreach ($subField as $value) {
                                    $entity[$dependency][$value] = $entity[$dependency][$value]
                                        ? $entity[$dependency][$value]->getJsonArray()
                                        : [];
                                    unset(
                                        $entity[$dependency][$value]['__initializer__'],
                                        $entity[$dependency][$value]['__cloner__'],
                                        $entity[$dependency][$value]['__isInitialized__']
                                    );
                                }
                            }
                        } else {
                            $entity[$dependency] = $entity[$dependency]->getJsonArray();
                        }
                    } elseif ($entity[$dependency] instanceof PersistentCollection) {
                        $entity[$dependency]->initialize();
                        if ($entity[$dependency]->count()) {
                            $dependencySnapshot = $entity[$dependency]->getSnapshot();
                            $temp = [];
                            foreach ($dependencySnapshot as $d) {
                                if ($d instanceof RecordProcessor) {
                                    $d = $d->getJsonArray();
                                    if ($d['representative']) {
                                        $d['representative'] = $d['representative']->getJsonArray();
                                    }
                                    if ($d['dpo']) {
                                        $d['dpo'] = $d['dpo']->getJsonArray();
                                    }
                                    $temp[] = $d;
                                } elseif ($d instanceof RecordPersonalData) {
                                    $d = $d->getJsonArray();
                                    $d['dataCategories']->initialize();
                                    if ($d['dataCategories']->count()) {
                                        $dataCategories = $d['dataCategories']->getSnapshot();
                                        $d['dataCategories'] = [];
                                        foreach ($dataCategories as $dc) {
                                            $tempDataCategory = $dc->toArray();
                                            $d['dataCategories'][] = $tempDataCategory;
                                        }
                                    }
                                    if ($d['record']) {
                                        $d['record'] = $d['record']->getJsonArray();
                                    }

                                    $temp[] = $d;
                                } else {
                                    if ($d instanceof AbstractEntity) {
                                        $temp[] = $d->getJsonArray();
                                    } else {
                                        $temp[] = $d;
                                    }
                                }
                            }
                            $entity[$dependency] = $temp;
                        }
                    } else {
                        if (\is_array($entity[$dependency])) {
                            foreach ($entity[$dependency] as $key => $value) {
                                if ($entity[$dependency][$key] instanceof AbstractEntity) {
                                    $entity[$dependency][$key] = $entity[$dependency][$key]->getJsonArray();
                                    unset(
                                        $entity[$dependency][$key]['__initializer__'],
                                        $entity[$dependency][$key]['__cloner__'],
                                        $entity[$dependency][$key]['__isInitialized__']
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function update($id, $data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $this->getService()->updateRecord($id, $data);

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        $this->getService()->deleteRecord($id);

        return $this->getSuccessfulJsonResponse();
    }

    public function create($data)
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new Exception('Anr id missing', 412);
        }
        $data['anr'] = $anrId;

        $id = $this->getService()->create($data);

        return $this->getSuccessfulJsonResponse(['id' => $id]);
    }
}
