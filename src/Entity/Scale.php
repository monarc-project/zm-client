<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\ScaleSuperClass;

/**
 * @ORM\Table(name="scales")
 * @ORM\Entity
 */
class Scale extends ScaleSuperClass
{
    public function isScaleRangeDifferentFromData(array $data): bool
    {
        return isset($data[$this->type]['min'], $data[$this->type]['max'])
            && ($data[$this->type]['min'] !== $this->min || $data[$this->type]['max'] !== $this->max);
    }
}
