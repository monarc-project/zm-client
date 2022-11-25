<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2022 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Model\Entity\UserSuperClass;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\FrontOffice\Model\Entity\Anr;
use Monarc\FrontOffice\Model\Entity\Theme;
use Monarc\FrontOffice\Table\ThemeTable;

class AnrThemeService
{
    private ThemeTable $themeTable;

    private UserSuperClass $connectedUser;

    public function __construct(ThemeTable $themeTable, ConnectedUserService $connectedUserService)
    {
        $this->themeTable = $themeTable;
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    public function getList(FormattedInputParams $params): array
    {
        $result = [];

        /** @var Theme[] $themes */
        $themes = $this->themeTable->findByParams($params);
        foreach ($themes as $theme) {
            $result[] = $this->prepareThemeDataResult($theme);
        }

        return $result;
    }

    public function getThemeData(Anr $anr, int $id): array
    {
        /** @var Theme $theme */
        $theme = $this->themeTable->findByIdAndAnr($id, $anr);

        return $this->prepareThemeDataResult($theme);
    }

    public function create(Anr $anr, array $data, bool $saveInDb = true): Theme
    {
        $theme = (new Theme())
            ->setAnr($anr)
            ->setLabels($data)
            ->setCreator($this->connectedUser->getEmail());

        $this->themeTable->save($theme, $saveInDb);

        return $theme;
    }

    public function update(Anr $anr, int $id, array $data): void
    {
        /** @var Theme $theme */
        $theme = $this->themeTable->findByIdAndAnr($id, $anr);

        $theme->setLabels($data)
            ->setUpdater($this->connectedUser->getEmail());

        $this->themeTable->save($theme);
    }

    public function delete(Anr $anr, int $id): void
    {
        /** @var Theme $theme */
        $theme = $this->themeTable->findByIdAndAnr($id, $anr);

        $this->themeTable->remove($theme);
    }

    private function prepareThemeDataResult(Theme $theme): array
    {
        return [
            'id' => $theme->getId(),
            'label1' => $theme->getLabel(1),
            'label2' => $theme->getLabel(2),
            'label3' => $theme->getLabel(3),
            'label4' => $theme->getLabel(4),
        ];
    }
}
