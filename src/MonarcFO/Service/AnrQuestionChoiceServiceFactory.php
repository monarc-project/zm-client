<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

class AnrQuestionChoiceServiceFactory extends AbstractServiceFactory
{
    protected $ressources = array(
        'table' => 'MonarcFO\Model\Table\QuestionChoiceTable',
        'entity' => 'MonarcFO\Model\Entity\QuestionChoice',
        'anrTable' => 'MonarcFO\Model\Table\AnrTable',
        'questionTable' => 'MonarcFO\Model\Table\QuestionTable',
    );

    public function createService(ServiceLocatorInterface $serviceLocator){

        $class = "\\MonarcCore\\Service\\QuestionChoiceService";

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
