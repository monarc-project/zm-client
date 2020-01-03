<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Model\Entity;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Zend\Http\PhpEnvironment\Request;
use Zend\Router\Http\TreeRouteStack;
use Zend\Router\RouteMatch;

/**
 * Class AbstractServiceModelEntity
 * @package Monarc\Core\Service\Model\Entity
 */
abstract class AbstractServiceModelEntity extends \Monarc\Core\Service\Model\Entity\AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => DbCli::class,
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
                    $anrTable = $sm->get(AnrTable::class);
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
