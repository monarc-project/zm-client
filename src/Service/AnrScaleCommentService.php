<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\FrontOffice\Model\Entity\Scale;
use Monarc\FrontOffice\Model\Entity\ScaleComment;
use Monarc\FrontOffice\Table\AnrTable;

/**
 * This class is the service that handles comments on scales within an ANR. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class AnrScaleCommentService extends \Monarc\Core\Service\AbstractService
{
    protected $filterColumns = [];
    protected $anrTable;
    protected $userAnrTable;
    protected $scaleTable;
    protected $scaleImpactTypeTable;
    // TODO: the Anr dependency can't be set.
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
