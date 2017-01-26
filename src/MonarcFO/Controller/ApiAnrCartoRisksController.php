<?php
namespace MonarcFO\Controller;

use Zend\View\Model\JsonModel;

/**
 * Api ANR Cartography Risks Real & Targeted Controller
 *
 * Class ApiAnrCartoRisksController
 * @package MonarcFO\Controller
 */
class ApiAnrCartoRisksController extends ApiAnrAbstractController
{
    protected $name = 'carto';
    protected $dependencies = [];

    /**
     * Get List
     *
     * @return JsonModel
     * @throws \Exception
     */
    public function getList()
    {
        $anrId = (int)$this->params()->fromRoute('anrid');
        if (empty($anrId)) {
            throw new \Exception('Anr id missing', 412);
        }
        $type = $this->params()->fromRoute('type', 'all'); // real / targeted / all
        switch ($type) {
            case 'real':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'real' => $this->getService()->getCartoReal($anrId),
                    ]
                ]);
                break;
            case 'targeted':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'targeted' => $this->getService()->getCartoTargeted($anrId),
                    ]
                ]);
                break;
            default:
            case 'all':
                return new JsonModel([
                    'status' => 'ok',
                    $this->name => [
                        'real' => $this->getService()->getCartoReal($anrId),
                        'targeted' => $this->getService()->getCartoTargeted($anrId),
                    ]
                ]);
                break;
        }
    }

    public function get($id)
    {
        $this->methodNotAllowed();
    }

    public function create($data)
    {
        $this->methodNotAllowed();
    }

    public function delete($id)
    {
        $this->methodNotAllowed();
    }

    public function deleteList($data)
    {
        $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        $this->methodNotAllowed();
    }
}
