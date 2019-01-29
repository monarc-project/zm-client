<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
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