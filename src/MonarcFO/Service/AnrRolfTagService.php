<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * This class is the service that handles ROLF tags within an ANR. This is a simple CRUD service.
 * @package MonarcFO\Service
 */
class AnrRolfTagService extends \MonarcCore\Service\AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $filterColumns = ['code', 'label1', 'label2', 'label3', 'label4'];
    protected $dependencies = ['anr'];
}
