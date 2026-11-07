<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Controller;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityNotFoundException;
use Laminas\Diactoros\ServerRequest;
use Laminas\Router\RouteMatch;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\Question\GetQuestionsInputFormatter;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use Monarc\Core\Tests\Fixture\QuestionTestEnvironment;
use Monarc\FrontOffice\Controller\ApiAnrQuestionsController;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Validator\InputValidator\Question\QuestionDataInputValidator;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class QuestionControllerTest extends TestCase
{
    private QuestionTestEnvironment $env;
    private \Monarc\FrontOffice\Entity\Anr $anr;
    private \Monarc\FrontOffice\Entity\Anr $other;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/QuestionTestEnvironment.php';
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
        }
        $connectedUserService = $this->createMock(ConnectedUserService::class);
        $connectedUserService->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new QuestionTestEnvironment($connectedUserService, true);
        $this->anr = $this->env->analysis(1);
        $this->other = $this->env->analysis(2);
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testCustomQuestionsAreTextOnlyAndIgnoreDefinitionAndOrderingFields(): void
    {
        $id = $this->request('POST', null, [
            'label1' => '  Custom question  ', 'label2' => '', 'response' => '',
            'type' => 2, 'multichoice' => 1, 'mode' => 0, 'anr' => 2,
            'position' => 100, 'implicitPosition' => 3, 'previous' => 999,
            'questionChoices' => [['label1' => 'Unwanted choice']],
        ])['id'];
        $data = $this->request('GET', $id);
        self::assertSame(1, $data['type']);
        self::assertSame(0, $data['multichoice']);
        self::assertSame(1, $data['mode']);
        self::assertSame(1, $data['position']);
        self::assertSame('  Custom question  ', $data['label1']);
        self::assertSame('', $data['label2']);
        self::assertSame(1, $this->env->questions->findById($id)->getAnr()->getId());
        self::assertSame(0, $this->env->choices->countAll());
        $this->request('PATCH', $id, ['response' => '0']);
        self::assertSame('0', $this->request('GET', $id)['response']);
        self::assertSame('  Custom question  ', $this->request('GET', $id)['label1']);
        $this->request('PUT', $id, ['label3' => 'Edited', 'response' => null, 'position' => 3, 'type' => 2]);
        $data = $this->request('GET', $id);
        self::assertNull($data['response']);
        self::assertSame('Edited', $data['label3']);
        self::assertSame(1, $data['type']);
        self::assertSame(1, $data['position']);
        self::assertSame('guide@example.invalid', $data['creator']);
        self::assertSame('guide@example.invalid', $data['updater']);
        self::assertSame(1, $this->request('GET')['count']);
        self::assertSame(['status' => 'ok'], $this->request('DELETE', $id));
        self::assertSame(0, $this->request('GET')['count']);
    }

    public function testImportedChoiceQuestionsAllowAnswersWhileKeepingTheirDefinitions(): void
    {
        $question = (new Question())->setAnr($this->anr)->setType(2)->setMode(0)->setIsMultiChoice(false)
            ->setPosition(1)->setLabels(['label1' => 'Imported definition']);
        $this->env->questions->save($question);
        $choice = (new QuestionChoice())->setAnr($this->anr)->setQuestion($question)
            ->setPosition(1)->setLabels(['label1' => 'Answer']);
        $this->env->choices->save($choice);
        $this->request('PATCH', $question->getId(), [
            'response' => $choice->getId(), 'label1' => 'Changed', 'type' => 1, 'multichoice' => 1,
        ]);
        $data = $this->request('GET', $question->getId());
        self::assertSame('Imported definition', $data['label1']);
        self::assertSame(2, $data['type']);
        self::assertSame(0, $data['multichoice']);
        self::assertSame((string)$choice->getId(), $data['response']);
        self::assertSame(
            [null, $choice->getId()],
            array_column($this->request('GET')['questions'][0]['choices'], 'id')
        );
        $this->expectException(Exception::class);
        $this->expectExceptionCode(412);
        $this->request('DELETE', $question->getId());
    }

    public function testQuestionActionsKeepTheMiddlewareAnalysisBoundary(): void
    {
        $foreign = (new Question())->setAnr($this->other)->setType(1)->setMode(1)->setPosition(1);
        $this->env->questions->save($foreign);
        foreach (['GET', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                $this->request($method, $foreign->getId(), ['label1' => 'Changed', 'anr' => 2]);
                self::fail('A record from another analysis must remain inaccessible.');
            } catch (EntityNotFoundException $error) {
                self::assertStringContainsString('analysis ID 1', $error->getMessage());
            }
        }
        self::assertSame(1, $this->env->questions->countAll());
    }

    public function testOnlyTheQuestionRouteRemainsRegisteredAndAuthorized(): void
    {
        $config = require __DIR__ . '/../../../config/module.config.php';
        $router = \Laminas\Router\Http\TreeRouteStack::factory($config['router']);
        $request = (new \Laminas\Http\Request())->setUri('/api/client-anr/1/questions');
        self::assertSame('monarc_api_global_client_anr/questions', $router->match($request)->getMatchedRouteName());
        $request->setUri('/api/client-anr/1/questions-choices');
        self::assertNull($router->match($request));
        self::assertContains('monarc_api_global_client_anr/questions', $config['roles']['userfo']);
        self::assertNotContains('monarc_api_global_client_anr/questions_choices', $config['roles']['userfo']);
    }

    public function testCreateValidatesTheSimplePayloadOnce(): void
    {
        $data = ['label1' => 'Question', 'type' => 1];
        $questionDataInputValidator = $this->createMock(QuestionDataInputValidator::class);
        $questionDataInputValidator->expects(self::once())->method('validate')->with($data)
            ->willReturn(['label1' => 'Question']);
        $controller = new ApiAnrQuestionsController(
            new GetQuestionsInputFormatter(),
            $questionDataInputValidator,
            $this->env->service
        );
        $response = $controller->handle($this->serverRequest('POST', null, $data));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->env->questions->countAll());
    }

    public function testInvalidContentIsRejectedBeforePersistence(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(412);
        try {
            $this->request('POST', null, ['label1' => []]);
        } finally {
            self::assertSame(0, $this->env->questions->countAll());
        }
    }

    /** @dataProvider unsupportedMethods */
    public function testUnsupportedMethodsReturn405(string $method): void
    {
        $response = $this->controller()->handle($this->serverRequest($method));
        self::assertSame(405, $response->getStatusCode());
    }

    public static function unsupportedMethods(): array
    {
        return [['PUT'], ['PATCH'], ['HEAD'], ['OPTIONS'], ['TRACE']];
    }

    private function controller(): ApiAnrQuestionsController
    {
        return new ApiAnrQuestionsController(
            new GetQuestionsInputFormatter(),
            $this->env->validator,
            $this->env->service
        );
    }

    private function serverRequest(
        string $method,
        ?int $id = null,
        ?array $data = null,
        array $query = []
    ): ServerRequest {
        $body = (new \Laminas\Diactoros\StreamFactory())->createStream(json_encode($data ?? [], JSON_THROW_ON_ERROR));
        $params = $id === null ? ['anrid' => 1] : ['anrid' => 1, 'id' => $id];
        return (new ServerRequest([], [], '/api/client-anr/1/questions', $method))->withParsedBody($data)
            ->withHeader('Content-Type', 'application/json')->withBody($body)->withQueryParams($query)
            ->withAttribute(RouteMatch::class, new RouteMatch($params))
            ->withAttribute('anr', $this->anr);
    }

    private function request(string $method, ?int $id = null, ?array $data = null, array $query = []): array
    {
        $response = $this->controller()->handle($this->serverRequest($method, $id, $data, $query));
        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
