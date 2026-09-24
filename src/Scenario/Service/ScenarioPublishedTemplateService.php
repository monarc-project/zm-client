<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Service;

use Monarc\Core\Scenario\Table\CatalogTable;

/** Provides the deliberately restricted FrontOffice template chooser projection. */
final class ScenarioPublishedTemplateService
{
    public function __construct(private CatalogTable $catalogTable)
    {
    }

    /** @return array{items: array<int, array<string, mixed>>, page: int, pageSize: int, total: int} */
    public function list(string $locale, string $search, int $page, int $pageSize): array
    {
        $result = $this->catalogTable->listPublishedTemplates($locale, $search, $page, $pageSize);
        $result['items'] = array_map(static function (array $template): array {
            $translation = $template['translation'] ?? [];
            $release = $template['release'] ?? [];

            return [
                'stableUuid' => $template['stable_uuid'],
                'version' => (int) $template['version'],
                'title' => $translation['title'] ?? '',
                'description' => $translation['description'] ?? '',
                'locale' => $translation['locale'] ?? 'en',
                'provenance' => $template['provenance'],
                'releaseUuid' => $release['uuid'] ?? null,
                'releasedAt' => $release['released_at'] ?? null,
            ];
        }, $result['items']);

        return $result;
    }
}
