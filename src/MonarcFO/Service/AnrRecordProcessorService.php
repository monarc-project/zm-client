<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;

/**
 * AnrRecord Processor Service
 *
 * Class AnrRecordProcessorService
 * @package MonarcFO\Service
 */
class AnrRecordProcessorService extends AbstractService
{
    protected $dependencies = ['anr','controllers'];
    protected $recordControllerService;
    protected $filterColumns = ['label'];
    protected $userAnrTable;
    protected $anrTable;
    protected $controllerTable;
    /**
     * Creates a processor of processing activity
     * @param array $data The processor details fields
     * @return object The resulting created processor object (entity)
     */
    public function createProcessor($data)
    {
        $behalfControllers = array();
        foreach ($data['controllers'] as $bc) {
            if(!isset($bc['id'])) {
                $bc['anr'] = $this->anrTable->getEntity($data['anr']);
                // Create a new controller
                $bc['id'] = $this->recordControllerService->create($bc, true);
            }
            array_push($behalfControllers, $bc['id']);
        }
        $data['controllers'] = $behalfControllers;
        return $this->create($data, true);
    }
}
