<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcFO\Model\Entity\Scale;
use MonarcFO\Model\Entity\ScaleComment;
use MonarcFO\Model\Table\AnrTable;

/**
 * This class is the service that handles comments on scales within an ANR. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrScaleCommentService extends \MonarcCore\Service\AbstractService
{
    protected $filterColumns = [];
    protected $anrTable;
    protected $userAnrTable;
    protected $scaleTable;
    protected $scaleImpactTypeTable;
    protected $dependencies = ['anr', 'scale', 'scaleImpactType'];

    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        $class = $this->get('entity');

        /** @var ScaleComment $entity */
        $entity = new $class();
        $entity->setLanguage($this->getLanguage());
        $entity->setDbAdapter($this->get('table')->getDb());

        // If a scale is set, ensure we retrieve the proper scale object
        if (isset($data['scale'])) {
            $scale = $this->get('scaleTable')->getEntity($data['scale']);
            $entity->setScale($scale);

            // If this is not an IMPACT scale, remove the impact type as we won't need it
            if ($scale->type != Scale::TYPE_IMPACT) {
                unset($data['scaleImpactType']);
            }
        }
        $entity->exchangeArray($data);

        $dependencies = (property_exists($this, 'dependencies')) ? $this->dependencies : [];
        $this->setDependencies($entity, $dependencies);

        /** @var AnrTable $table */
        $table = $this->get('table');

        return $table->save($entity, $last);
    }
}
