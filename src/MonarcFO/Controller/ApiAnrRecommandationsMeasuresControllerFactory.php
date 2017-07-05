<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Recommandations Measures Controller Factory
 *
 * Class ApiAnrRecommandationsMeasuresControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsMeasuresControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrRecommandationMeasureService';
}