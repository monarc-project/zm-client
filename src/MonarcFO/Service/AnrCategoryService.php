<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles measures in use within an ANR. Inherits its behavior from its MonarcCore
 * parent class CategoryService
 * @see \MonarcCore\Service\CategoryService
 * @package MonarcFO\Service
 */
class AnrCategoryService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = [];
    protected $forbiddenFields = [];
}
