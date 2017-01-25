<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Anr Assets Import Controller Factory
 *
 * Class ApiAnrAssetsImportControllerFactory
 * @package MonarcFO\Controller
 */
class ApiAnrAssetsImportControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\AnrAssetService';
}