<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractServiceFactory;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Asset Export Service Factory
 *
 * Class AssetExportServiceFactory
 * @package MonarcFO\Service
 */
class AssetExportServiceFactory extends AbstractServiceFactory
{
    protected $class = "\\MonarcCore\\Service\\AssetExportService";

    protected $ressources = [
        'table' => 'MonarcFO\Model\Table\AssetTable',
        'entity' => 'MonarcFO\Model\Entity\Asset',
        'amvService' => 'MonarcFO\Service\AmvService', // Ça devrait le faire
    ];
}
