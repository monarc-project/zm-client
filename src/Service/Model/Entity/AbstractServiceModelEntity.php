<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service\Model\Entity;

use Monarc\Core\Service\Model\Entity\AbstractServiceModelEntity as CoreAbstractServiceModelEntity;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Table\AnrTable;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteMatch;

/**
 * TODO: drop this class as soon as all the AbstractEntity is removed.
 */
abstract class AbstractServiceModelEntity extends CoreAbstractServiceModelEntity
{
    private static ?int $anrLanguage = null;

    protected $ressources = [
        'setDbAdapter' => DbCli::class,
    ];

    public function getDefaultLanguage($sm)
    {
        if (self::$anrLanguage !== null) {
            return self::$anrLanguage;
        }

        /** @var Request $request */
        $request = $sm->get('Request');
        if (!$request instanceof \Laminas\Console\Request) {
            /** @var TreeRouteStack $router */
            $router = $sm->get('Router');
            /** @var RouteMatch $match */
            $match = $router->match($request);
            if ($match) {
                $anrId = $match->getParam('anrid');
                if ($anrId !== null) {
                    /** @var AnrTable $anrTable */
                    $anrTable = $sm->get(AnrTable::class);
                    /** @var Anr $anr */
                    $anr = $anrTable->findById((int)$anrId);
                    self::$anrLanguage = $anr->getLanguage();

                    return self::$anrLanguage;
                }
            }
        }

        return parent::getDefaultLanguage($sm);
    }
}
