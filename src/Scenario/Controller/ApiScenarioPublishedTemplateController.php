<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Scenario\Controller;

use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Scenario\Feature\ScenarioCapability;
use Monarc\Core\Scenario\Table\CatalogTable;

/** The deliberately small, published-only FrontOffice catalog projection. */
final class ApiScenarioPublishedTemplateController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ScenarioCapability $capability, private CatalogTable $catalogTable)
    {
    }

    public function getList()
    {
        $this->capability->assertEnabled();
        $locale = (string) $this->params()->fromQuery('locale', 'en');
        $locale = preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) ? $locale : 'en';
        $search = trim((string) $this->params()->fromQuery('search', ''));
        $page = max(1, (int) $this->params()->fromQuery('page', 1));
        $pageSize = min(100, max(1, (int) $this->params()->fromQuery('pageSize', 25)));
        $pageData = $this->catalogTable->listPublishedTemplates($locale, mb_substr($search, 0, 255), $page, $pageSize);

        return $this->getPreparedJsonResponse([
            'items' => array_map([$this, 'project'], $pageData['items']),
            'page' => $pageData['page'],
            'pageSize' => $pageData['pageSize'],
            'total' => $pageData['total'],
        ]);
    }

    /** @param array<string, mixed> $template @return array<string, mixed> */
    private function project(array $template): array
    {
        $translation = is_array($template['translation'] ?? null) ? $template['translation'] : [];
        $release = is_array($template['release'] ?? null) ? $template['release'] : [];

        return [
            'stableUuid' => $template['stable_uuid'],
            'version' => (int) $template['version'],
            'title' => (string) ($translation['title'] ?? ''),
            'description' => (string) ($translation['description'] ?? ''),
            'locale' => (string) ($translation['locale'] ?? 'en'),
            'provenance' => (string) $template['provenance'],
            'releaseUuid' => $release['uuid'] ?? null,
            'releasedAt' => $release['released_at'] ?? null,
        ];
    }
}
