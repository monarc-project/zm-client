<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service\Model\Entity;

/**
 * Soa Service Model Entity
 *
 * Class SoaServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class SoaServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
