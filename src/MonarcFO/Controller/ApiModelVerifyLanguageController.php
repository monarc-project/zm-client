<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractController;
use Zend\View\Model\JsonModel;

/**
 * Api Model Verify Language Controller
 *
 * Class ApiModelVerifyLanguageController
 * @package MonarcFO\Controller
 */
class ApiModelVerifyLanguageController extends AbstractController
{
    /**
     * Get
     *
     * @param mixed $id
     * @return JsonModel
     */
    public function get($id)
    {
        $result = $this->getService()->verifyLanguage($id);

        return new JsonModel($result);
    }

    public function getList()
    {
        return $this->methodNotAllowed();
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