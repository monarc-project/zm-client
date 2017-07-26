<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;

/**
 * Proxy class to instantiate MonarcCore's ObjectCategoryService, with MonarcFO's services
 * @package MonarcFO\Service
 */
class ObjectCategoryServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\ObjectCategoryService";

    protected $ressources = [
        'table' => '\MonarcFO\Model\Table\ObjectCategoryTable',
        'entity' => '\MonarcFO\Model\Entity\ObjectCategory',
        'anrObjectCategoryTable' => '\MonarcFO\Model\Table\AnrObjectCategoryTable',
        'objectTable' => '\MonarcFO\Model\Table\ObjectTable',
        'rootTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'parentTable' => 'MonarcFO\Model\Table\ObjectCategoryTable',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
    ];
}