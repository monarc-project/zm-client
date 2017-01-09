<?php
namespace MonarcFO\Controller;

use MonarcFO\Service\AnrService;
use Zend\View\Model\JsonModel;

class ApiModelVerifyLanguageController extends \MonarcCore\Controller\AbstractController
{
    public function getList()
    {
        return $this->methodNotAllowed();
    }

    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     */
    public function get($id)
    {
        $language = $this->params()->fromQuery('language');

        $this->getService()->verifyLanguage($id, $language);
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}

