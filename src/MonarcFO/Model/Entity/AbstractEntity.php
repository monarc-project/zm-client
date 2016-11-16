<?php

namespace MonarcFO\Model\Entity;

use Zend\Http\Response;
use Zend\InputFilter\InputFilter;
use Zend\InputFilter\InputFilterAwareInterface;
use Zend\InputFilter\InputFilterInterface;

abstract class AbstractEntity implements InputFilterAwareInterface
{
    use \MonarcFO\Model\GetAndSet;

    protected $inputFilter;
    protected $language;
    protected $dbadapter;
    protected $parameters = array();

    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    const MODE_GENERIC = 0;
    const MODE_SPECIFIC = 1;

    const BACK_OFFICE = 'back';
    const FRONT_OFFICE = 'front';

    const CONTEXT_BDC = 'bdc';
    const CONTEXT_ANR = 'anr';

    const SOURCE_COMMON = 'common';
    const SOURCE_CLIENT = 'cli';

    public function getArrayCopy()
    {
        return get_object_vars($this);
    }

    public function getJsonArray($fields = array())
    {
        if (empty($fields)) {
            $array = get_object_vars($this);
            unset($array['inputFilter']);
            unset($array['language']);
            unset($array['dbadapter']);
            unset($array['parameters']);
            return $array;
        } else {
            return array_intersect_key(get_object_vars($this), array_flip($fields));
        }
    }

    public function setDbAdapter($dbadapter){
        if ($dbadapter == null) {
            throw new \Exception("Trying to call setDbAdapter with a null adapter");
        }

        $this->dbadapter = $dbadapter;

        return $this;
    }
    public function getDbAdapter(){
        return $this->dbadapter;
    }

    public function getLanguage()
    {
        return empty($this->language)?1:$this->language;
    }

    public function setLanguage($language)
    {
        $this->language = $language;
    }

    public function exchangeArray(array $options, $partial = false)
    {
        $keys = array_keys($options);
        $keys = array_combine($keys,$keys);
        $filter = $this->getInputFilter($partial)
            ->setData($options)
            ->setValidationGroup(InputFilterInterface::VALIDATE_ALL);

        $isValid = $filter->isValid();
        if(!$isValid){
            $field_errors = array();

            foreach ($filter->getInvalidInput() as $field => $error) {
                foreach ($error->getMessages() as $message) {
                    if ($message != 'Value is required and can\'t be empty') {
                        $field_errors[] = $message;
                        break;
                    }
                }

                if (!count($field_errors)) {
                    if (!empty($field)) {
                        $field = strtr($field, ['1' => '', '2' => '', '3' => '', '4' => '']);
                        $field_errors[] = ucfirst($field) . ' is required';
                        break;
                    }
                }
            }
            throw new \Exception(implode(", ", $field_errors), '412');
        }

        $options = $filter->getValues();

        foreach($options as $k => $v){
            if ($this->__isset($k) && isset($keys[$k])) {
                $this->set($k, $v);
            }
        }

        return $this;
    }

    public function toArray()
    {
        return $this->getJsonArray();
        //return get_object_vars($this);
    }

    public function getInputFilter($partial = false){
        if (!$this->inputFilter) {
            $inputFilter = new InputFilter();
            $attributes = get_object_vars($this);
            foreach($attributes as $k => $v){
                switch($k){
                    case 'id':
                        $inputFilter->add(array(
                            'name' => 'id',
                            'required' => false,
                            'filters' => array(
                                array('name' => 'ToInt',),
                            ),
                            'validators' => array(),
                        ));
                        break;
                    case 'updatedAt':
                    case 'updater':
                    case 'createdAt':
                    case 'creator':
                    case 'inputFilter':
                    case 'dbadapter':
                    case 'parameters':
                        break;
                    default:
                        $inputFilter->add(array(
                            'name' => $k,
                            'required' => false,
                            'allow_empty' => true,
                            'continue_if_empty' => true,
                            'filters' => array(),
                            'validators' => array(),
                        ));
                        break;
                }
            }
            $this->inputFilter = $inputFilter;
        }
        return $this->inputFilter;
    }

    public function setInputFilter(InputFilterInterface $inputFilter){
        $this->inputFilter = $inputFilter;
        return $this;
    }
}
