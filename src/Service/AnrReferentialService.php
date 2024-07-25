<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Referential;
use Monarc\FrontOffice\Table\ReferentialTable;

class AnrReferentialService
{
    public function __construct(private ReferentialTable $referentialTable)
    {
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];
        /** @var Referential $referential */
        foreach ($this->referentialTable->findByParams($params) as $referential) {
            $result[] = $this->prepareReferentialDataResult($referential);
        }

        return $result;
    }

    public function getReferentialData(Anr $anr, string $uuid): array
    {
        /** @var Referential $referential */
        $referential = $this->referentialTable->findByUuidAndAnr($uuid, $anr);

        return $this->prepareReferentialDataResult($referential);
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): Referential
    {
        /** @var Referential $referential */
        $referential = (new Referential())->setAnr($anr)->setLabels($data);
        if (!empty($data['uuid'])) {
            $referential->setUuid($data['uuid']);
        }

        $this->referentialTable->save($referential, $saveInDb);

        return $referential;
    }

    public function update(Anr $anr, string $uuid, array $data): Referential
    {
        /** @var Referential $referential */
        $referential = $this->referentialTable->findByUuidAndAnr($uuid, $anr);

        $referential->setLabels($data);

        $this->referentialTable->save($referential);

        return $referential;
    }

    public function delete(Anr $anr, string $uuid): void
    {
        /** @var Referential $referential */
        $referential = $this->referentialTable->findByUuidAndAnr($uuid, $anr);

        $this->referentialTable->remove($referential);
    }

    private function prepareReferentialDataResult(Referential $referential): array
    {
        $measures = [];
        foreach ($referential->getMeasures() as $measure) {
            $measures[] = ['uuid' => $measure->getUuid()];
        }

        return array_merge(['uuid' => $referential->getUuid()], $referential->getLabels(), ['measures' => $measures]);
    }
}
