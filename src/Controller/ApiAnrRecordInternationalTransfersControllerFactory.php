<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\Core\Controller\AbstractControllerFactory;

/**
 *
 * Factory class attached to ApiAnrRecordInternationalTransfersController
 * @package Monarc\FrontOffice\Controller
 */
class ApiAnrRecordInternationalTransfersControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = 'Monarc\FrontOffice\Service\AnrRecordInternationalTransferService';
}
