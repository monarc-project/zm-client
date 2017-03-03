<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
        'setDbAdapter' => '\MonarcCore\Model\Db',
    ];

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $class = str_replace('Service\\', '', substr(get_class($this), 0, -18));
        if (class_exists($class)) {
            $ressources = $this->getRessources();
            $instance = new $class();
            if (!empty($ressources) && is_array($ressources)) {
                foreach ($ressources as $key => $value) {
                    if (method_exists($instance, $key)) {
                        $instance->$key($serviceLocator->get($value));
                    }
                }
            }

            $instance->setLanguage($this->getDefaultLanguage($serviceLocator));

            return $instance;
        } else {
            return false;
        }
    }

    public function getDefaultLanguage($sm)
    {
        /** @var Request $request */
        $request = $sm->get('Request');
        /** @var TreeRouteStack $router */
        $router = $sm->get('Router');
        /** @var RouteMatch $match */
        $match   = $router->match($request);

        $anrId = $match->getParam('anrid', false);

        if ($anrId) {
            /** @var AnrTable $anrTable */
            $anrTable = $sm->get('\MonarcFO\Model\Table\AnrTable');
            $anr = $anrTable->getEntity($anrId);

            if ($anr->get('language')) {
                return $anr->get('language');
            } else {
                parent::getDefaultLanguage($sm);
            }
        } else {
            parent::getDefaultLanguage($sm);
        }
    }
}
