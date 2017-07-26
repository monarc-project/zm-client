<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Controller;

/**
 * Api Anr Recommandations Historics
 *
 * Class ApiAnrRecommandationsHistoricsController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsHistoricsController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-historics';
    protected $dependencies = ['anr'];
}