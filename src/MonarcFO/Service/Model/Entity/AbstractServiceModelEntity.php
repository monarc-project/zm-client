<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service\Model\Entity;

use MonarcFO\Model\Table\AnrTable;
use Zend\Http\PhpEnvironment\Request;
use Zend\Mvc\Router\Http\TreeRouteStack;
use Zend\Mvc\Router\RouteMatch;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class AbstractServiceModelEntity
 * @package MonarcCore\Service\Model\Entity
 */
abstract class AbstractServiceModelEntity extends \MonarcCore\Service\Model\Entity\AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];

    public function getDefaultLanguage($sm)
    {
        /** @var Request $request */
        $request = $sm->get('Request');
        if(!$request instanceof \Zend\Console\Request){
            /** @var TreeRouteStack $router */
            $router = $sm->get('Router');
            /** @var RouteMatch $match */
            $match = $router->match($request);
            if($match){
                $anrId = $match->getParam('anrid', false);

                if ($anrId) {
                    /** @var AnrTable $anrTable */
                    $anrTable = $sm->get('\MonarcFO\Model\Table\AnrTable');
                    $anr = $anrTable->getEntity($anrId);

                    if ($anr->get('language')) {
                        return $anr->get('language');
                    }
                }
            }
        }
        return parent::getDefaultLanguage($sm);
    }
}
