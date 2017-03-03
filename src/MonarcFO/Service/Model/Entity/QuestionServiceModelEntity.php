<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
 */

namespace MonarcFO\Service\Model\Entity;

/**
 * Question Service Model Entity
 *
 * Class QuestionServiceModelEntity
 * @package MonarcFO\Service\Model\Entity
 */
class QuestionServiceModelEntity extends AbstractServiceModelEntity
{
    protected $ressources = [
        'setDbAdapter' => '\MonarcCli\Model\Db',
    ];
}
