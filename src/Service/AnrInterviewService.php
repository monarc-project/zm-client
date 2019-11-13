<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;

/**
 * This class is the service that handles interviews made on an ANR. This is a basic CRUD service.
 * @package Monarc\Core\Service
 */
class AnrInterviewService extends AbstractService
{
    protected $anrTable;
    protected $userAnrTable;
    protected $dependencies = ['anr'];
}
