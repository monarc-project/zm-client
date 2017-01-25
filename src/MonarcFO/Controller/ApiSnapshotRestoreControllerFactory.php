<?php
namespace MonarcFO\Controller;

use MonarcCore\Controller\AbstractControllerFactory;

/**
 * Api Snapshots Restore Controller Factory
 *
 * Class ApiSnapshotRestoreControllerFactory
 * @package MonarcFO\Controller
 */
class ApiSnapshotRestoreControllerFactory extends AbstractControllerFactory
{
    protected $serviceName = '\MonarcFO\Service\SnapshotService';
}