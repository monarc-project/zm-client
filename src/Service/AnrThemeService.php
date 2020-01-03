<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

/**
 * This class is the service that handles themes within an ANR. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class AnrThemeService extends \Monarc\Core\Service\AbstractService
{
    protected $filterColumns = ['label1', 'label2', 'label3', 'label4'];
    protected $dependencies = ['anr'];
    protected $anrTable;
    protected $userAnrTable;
}
