<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service\Model\Entity;

/**
 * User Anr Service Model Entity
 *
 * Class UserAnrServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class UserAnrServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
