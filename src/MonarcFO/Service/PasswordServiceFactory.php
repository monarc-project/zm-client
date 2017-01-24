<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Password Service Factory
 *
 * Class PasswordServiceFactory
 * @package MonarcFO\Service
 */
class PasswordServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'entity' => 'MonarcFO\Model\Entity\PasswordToken',
        'table'=> 'MonarcFO\Model\Table\PasswordTokenTable',
        'userTable'=> 'MonarcFO\Model\Table\UserTable',
        'userService'=> 'MonarcFO\Service\UserService',
        'mailService'=> 'MonarcFO\Service\MailService'
    );

    /**
     * Create Service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return bool
     */
    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\PasswordService";

        if(class_exists($class)){
            $ressources = $this->getRessources();
            if (empty($ressources)) {
                $instance = new $class();
            } elseif (is_array($ressources)) {
                $sls = array();
                foreach ($ressources as $key => $value) {
                    $sls[$key] = $serviceLocator->get($value);
                }
                $instance = new $class($sls);
            } else {
                $instance = new $class($serviceLocator->get($ressources));
            }

            $instance->setLanguage($this->getDefaultLanguage($serviceLocator));
            $conf = $serviceLocator->get('Config');
            $instance->setMonarcConf(isset($conf['monarc'])?$conf['monarc']:array());

            return $instance;
        }else{
            return false;
        }
    }
}
