<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Table;

use Monarc\FrontOffice\Model\DbCli;
use Monarc\FrontOffice\Model\Entity\ClientModel;
use Monarc\Core\Model\Table\AbstractEntityTable;
use Monarc\Core\Service\ConnectedUserService;

/**
 * Class ClientModelTable
 *
 * @package Monarc\FrontOffice\Model\Table
 */
class ClientModelTable extends AbstractEntityTable
{
    public function __construct(
        DbCli $dbService,
        ConnectedUserService $connectedUserService
    ) {
        parent::__construct($dbService, ClientModel::class, $connectedUserService);
    }
}
