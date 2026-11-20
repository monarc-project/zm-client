<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Controller;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use Laminas\Router\RouteMatch;
use Monarc\Core\Entity\User;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\DeliveryModel\GetDeliveryModelsInputFormatter;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\DeliveryModelTestEnvironment;
use Monarc\FrontOffice\Controller\ApiDeliveriesModelsController;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class DeliveryModelControllerTest extends TestCase
{
    private DeliveryModelTestEnvironment $env;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/DeliveryModelTestEnvironment.php';
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn(new User([
            'firstname' => 'Template',
            'lastname' => 'Editor',
            'email' => 'editor@example.invalid',
            'language' => 2,
            'role' => [],
            'creator' => 'test',
        ]));
        $this->env = new DeliveryModelTestEnvironment($identity);
    }

    protected function tearDown(): void
    {
        $this->env->close();
    }

    public function testMultipartCreateReplacePatchListDownloadAndDelete(): void
    {
        $created = $this->decode($this->request('POST', data: [
            'category' => '1',
            'description1' => 'Original',
            'editable' => '0',
        ], uploads: [1 => $this->env->upload('French'), 2 => $this->env->upload('English')]));
        self::assertSame('ok', $created['status']);
        $id = $created['id'];
        self::assertIsInt($id);
        $this->request('PUT', $id, ['description1' => 'Replaced'], [1 => $this->env->upload('Replacement')]);
        $this->request('PATCH', $id, ['description2' => null, 'path1' => '/untrusted']);
        $listed = $this->decode($this->request('GET', query: ['limit' => 1, 'category' => 1]));
        self::assertSame(1, $listed['count']);
        $template = $listed['deliveriesmodels'][0];
        self::assertSame($id, $template['id']);
        self::assertSame('Replaced', $template['description1']);
        self::assertNull($template['description2']);
        self::assertFalse($template['editable']);
        self::assertSame('./api/deliveriesmodels/' . $id . '?lang=1', $template['path1']);
        self::assertNotEmpty($template['filename2']);
        $download = $this->request('GET', $id, query: ['lang' => 1]);
        self::assertStringContainsString('Replacement', (string)$download->getBody());
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $download->getHeaderLine('Content-Type')
        );
        $englishDownload = $this->request('GET', $id, query: ['lang' => 2]);
        self::assertStringContainsString('English', (string)$englishDownload->getBody());
        self::assertSame(['status' => 'ok'], $this->decode($this->request('DELETE', $id)));
        self::assertSame(0, $this->decode($this->request('GET'))['count']);
    }

    public function testInvalidReplacementKeepsStoredFilesAndMetadata(): void
    {
        $id = $this->decode($this->request(
            'POST',
            data: ['category' => 1],
            uploads: [1 => $this->env->upload('Previous')]
        ))['id'];
        $before = $this->env->table->findById($id)->getPaths();
        $file = $this->env->upload();
        $file['name'] = 'malformed.txt';
        try {
            $this->request('PUT', $id, ['description1' => 'Rejected'], [1 => $file]);
            self::fail('Invalid upload accepted');
        } catch (Exception $error) {
            self::assertSame(400, $error->getCode());
        }
        self::assertSame($before, $this->env->table->findById($id)->getPaths());
        self::assertNull($this->env->table->findById($id)->getDescriptions()['description1']);
        self::assertStringContainsString('Previous', (string)$this->request('GET', $id)->getBody());
        self::assertSame(405, $this->request('DELETE')->getStatusCode());
        self::assertSame(405, $this->request('PUT')->getStatusCode());
    }

    public function testTemplateRouteRetainsRoleAuthorizationAndRejectsGuests(): void
    {
        require_once __DIR__ . '/../../../Module.php';
        $config = require __DIR__ . '/../../../config/module.config.php';
        $router = \Laminas\Router\Http\TreeRouteStack::factory($config['router']);
        foreach (['/api/deliveriesmodels', '/api/deliveriesmodels/23'] as $uri) {
            $match = $router->match((new \Laminas\Http\Request())->setUri($uri));
            self::assertSame('monarc_api_doc_models', $match->getMatchedRouteName());
        }
        $identity = $this->createMock(ConnectedUserService::class);
        $user = $this->createMock(\Monarc\Core\Entity\UserSuperClass::class);
        $connectedUser = $user;
        $identity->method('getConnectedUser')->willReturnCallback(
            static function () use (&$connectedUser): ?\Monarc\Core\Entity\UserSuperClass {
                return $connectedUser;
            }
        );
        $roles = [];
        $user->method('getRolesArray')->willReturnCallback(static function () use (&$roles): array {
            return $roles;
        });
        $services = new \Laminas\ServiceManager\ServiceManager(['services' => [
            'Config' => $config,
            ConnectedUserService::class => $identity,
        ]]);
        $application = $this->createMock(\Laminas\Mvc\Application::class);
        $application->method('getServiceManager')->willReturn($services);
        $event = (new \Laminas\Mvc\MvcEvent())->setApplication($application)
            ->setRouteMatch($match)->setResponse(new \Laminas\Http\Response())
            ->setViewModel(new \Laminas\View\Model\ViewModel());
        $module = new \Monarc\FrontOffice\Module();
        $module->initRbac($event);
        foreach (['superadminfo', 'userfo'] as $role) {
            $roles = [$role];
            self::assertNull($module->checkRbac($event));
        }
        $roles = ['guest'];
        self::assertSame(403, $module->checkRbac($event)->getStatusCode());
        $connectedUser = null;
        self::assertSame(401, $module->checkRbac($event)->getStatusCode());
    }

    private function request(
        string $method,
        ?int $id = null,
        array $data = [],
        array $uploads = [],
        array $query = []
    ): object {
        $files = [];
        foreach ($uploads as $language => $file) {
            $files[$language] = new UploadedFile($file['tmp_name'], $file['size'], $file['error'], $file['name']);
        }
        $request = (new ServerRequest([], [], '/api/deliveriesmodels', $method))
            ->withAttribute(RouteMatch::class, new RouteMatch($id === null ? [] : ['id' => $id]))
            ->withParsedBody($data)->withQueryParams($query);
        if ($files) {
            $request = $request->withUploadedFiles(['file' => $files])
                ->withHeader('Content-Type', 'multipart/form-data');
        } else {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($data, JSON_THROW_ON_ERROR)));
        }
        $controller = new ApiDeliveriesModelsController(
            new GetDeliveryModelsInputFormatter(),
            $this->env->validator,
            $this->env->service
        );

        return $controller->handle($request);
    }

    private function decode(object $response): array
    {
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
