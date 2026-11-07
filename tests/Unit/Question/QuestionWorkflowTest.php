<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Unit\Question;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Tools\SchemaValidator;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\Question\GetQuestionsInputFormatter;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use Monarc\Core\Tests\Fixture\QuestionTestEnvironment;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Service\QuestionSnapshotService;
use Monarc\FrontOffice\Validator\InputValidator\Question\QuestionSnapshotInputValidator;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class QuestionWorkflowTest extends TestCase
{
    private QuestionTestEnvironment $env;
    private Anr $anr;
    private Anr $other;
    private QuestionSnapshotService $snapshots;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/QuestionTestEnvironment.php';
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
        }
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new QuestionTestEnvironment($identity, true);
        $this->anr = $this->env->analysis(1);
        $this->other = $this->env->analysis(2);
        $this->snapshots = new QuestionSnapshotService(
            $this->env->questions,
            $this->env->choices,
            new QuestionSnapshotInputValidator($this->env->definitionValidator, $this->env->choiceValidator),
            $identity
        );
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testModernParentSignaturesAndBothAssociationsAreValid(): void
    {
        foreach ([Question::class, QuestionChoice::class] as $class) {
            self::assertSame([], (new SchemaValidator($this->env->manager))
                ->validateClass($this->env->manager->getClassMetadata($class)));
        }
        self::assertFalse($this->env->manager->getClassMetadata(QuestionChoice::class)
            ->associationMappings['question']['joinColumns'][0]['nullable']);
        $method = new \ReflectionMethod($this->env->questions, 'findByAnr');
        self::assertSame(\Monarc\Core\Table\AbstractTable::class, $method->getDeclaringClass()->getName());
        $id = $this->env->service->create(
            $this->env->validator->validate(['label3' => 'Local']),
            $this->anr
        );
        self::assertSame([$id], array_map(fn ($q): int => $q->getId(), $this->env->questions->findByAnr($this->anr)));
    }

    public function testCustomAndDefinitionQuestionsHaveDifferentWriteRulesAndKeepResponses(): void
    {
        $id = $this->env->service->create(
            $this->env->validator->validate(['label3' => 'Local', 'type' => 2, 'multichoice' => 1]),
            $this->anr
        );
        $data = $this->env->service->getEntity($id, $this->anr);
        self::assertSame(1, $data['type']);
        self::assertSame(0, $data['multichoice']);
        self::assertSame(1, $data['mode']);
        self::assertSame('guide@example.invalid', $data['creator']);
        $this->env->service->update($id, $this->env->validator->validate(['response' => '0'], true), $this->anr);
        self::assertSame('0', $this->env->service->getEntity($id, $this->anr)['response']);
        self::assertSame('guide@example.invalid', $this->env->service->getEntity($id, $this->anr)['updater']);
        self::assertSame('Local', $this->env->service->getEntity($id, $this->anr)['label3']);
        $this->env->service->update($id, $this->env->validator->validate(['response' => null], true), $this->anr);
        self::assertNull($this->env->service->getEntity($id, $this->anr)['response']);
        $definition = $this->definition($this->anr, false);
        $this->env->service->update(
            $definition,
            $this->env->validator->validate(['label3' => 'Forbidden', 'type' => 1, 'response' => ''], true),
            $this->anr
        );
        self::assertSame('Definition', $this->env->service->getEntity($definition, $this->anr)['label3']);
        $this->reject(
            fn () => $this->env->service->deleteList($this->env->validator->validateIds([$id, $definition]), $this->anr)
        );
        self::assertSame('Local', $this->env->service->getEntity($id, $this->anr)['label3']);
        $this->env->service->delete($id, $this->anr);
        self::assertCount(1, $this->rows($this->anr));
    }

    public function testCrossAnalysisReadsWritesAndResponsesAreRejected(): void
    {
        $q = $this->definition($this->anr, true);
        $foreign = $this->definition($this->other, true);
        $choice = $this->choice(['question' => $foreign, 'label3' => 'Foreign'], $this->other);
        $this->reject(fn () => $this->env->service->getEntity($foreign, $this->anr), EntityNotFoundException::class);
        $this->reject(
            fn () => $this->env->service->update($foreign, ['response' => 'Changed'], $this->anr),
            EntityNotFoundException::class
        );
        $this->reject(fn () => $this->env->service->delete($foreign, $this->anr), EntityNotFoundException::class);
        $this->reject(fn () => $this->env->service->update($q, ['response' => '[' . $choice . ']'], $this->anr));
        self::assertCount(1, $this->rows($this->anr));
        self::assertCount(1, $this->env->choices->findChoicesOfQuestion(
            $this->env->questions->findByIdAndAnr($foreign, $this->other)
        ));
    }

    public function testPayloadAnalysisFieldsCannotChangeTheResolvedScope(): void
    {
        $id = $this->env->service->create(
            $this->env->validator->validate(['label3' => 'Local', 'anr' => 2]),
            $this->anr
        );
        $this->env->service->update(
            $id,
            $this->env->validator->validate(['label3' => 'Updated', 'anr' => 2], true),
            $this->anr
        );
        self::assertSame('Updated', $this->env->service->getEntity($id, $this->anr)['label3']);
        $this->reject(fn () => $this->env->service->getEntity($id, $this->other), EntityNotFoundException::class);
        $question = $this->definition($this->anr, false);
        $choice = $this->choice(
            $this->env->choiceValidator->validate(['question' => $question, 'anr' => 2]),
            $this->anr
        );
        self::assertSame($this->anr, $this->env->choices->findById($choice)->getAnr());
        self::assertSame([], $this->rows($this->other));
    }

    public function testMissingInternalAnalysisContextCannotReadOrReorderAllAnalyses(): void
    {
        $this->definition($this->anr, false);
        $this->definition($this->other, false);
        $params = (new GetQuestionsInputFormatter())->format([]);
        foreach ([
            fn () => $this->env->service->getList($params),
            fn () => $this->env->service->getCount($params),
            fn () => $this->env->service->deleteList($this->env->validator->validateIds([])),
            fn () => $this->env->service->create(
                $this->env->validator->validate(['label3' => 'Unscoped'])
            ),
        ] as $operation) {
            try {
                $operation();
                self::fail('An analysis is required by the internal query/entity contract.');
            } catch (\Error $error) {
                self::assertStringContainsString('null', $error->getMessage());
            }
        }
        self::assertCount(1, $this->rows($this->anr));
        self::assertCount(1, $this->rows($this->other));
        self::assertSame([1, 1], array_column($this->env->manager->getConnection()->fetchAllAssociative(
            'SELECT position FROM questions ORDER BY id'
        ), 'position'));
    }

    public function testScopedCountChoicesAndSingleChoicePlaceholder(): void
    {
        $q = $this->definition($this->anr, false);
        $this->definition($this->other, false);
        $choice = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Selected']),
            $this->anr
        );
        $this->env->service->update(
            $q,
            $this->env->validator->validate(['response' => $choice], true),
            $this->anr
        );
        $row = $this->rows($this->anr)[0];
        self::assertSame((string)$choice, $row['response']);
        self::assertSame([null, $choice], array_column($row['choices'], 'id'));
        self::assertNull($row['choices'][0]['question']);
        self::assertSame([], (array)$row['anr']);
        $params = (new GetQuestionsInputFormatter())->format(['filter' => 'Definition', 'limit' => 1]);
        self::assertSame(1, $this->env->service->getCount($params, $this->anr));
        self::assertCount(1, $this->env->service->getList($params, $this->anr));
    }

    /** @dataProvider snapshotFormats */
    public function testBothImportFormatsAndCloningRemapSingleAndMultiChoiceResponses(bool $legacy, bool $multi): void
    {
        $row = ['id' => 800, 'type' => 2, 'multichoice' => (int)$multi, 'mode' => 0,
            'response' => $multi ? '[900]' : '900', 'label3' => 'Definition', 'label4' => 'Fourth'];
        $choices = [['id' => 900, 'position' => 1, 'label3' => 'Answer', 'question' => 800]];
        if ($legacy) {
            $snapshot = ['questions' => [3 => $row], 'questionChoice' => $choices];
        } else {
            $row['label'] = 'Localized';
            $choices[0]['label'] = 'Localized answer';
            unset($choices[0]['question']);
            $row['questionChoices'] = $choices;
            $row['isMultiChoice'] = $multi;
            $snapshot = ['questions' => [3 => $row]];
        }
        $this->snapshots->replace($this->anr, $snapshot);
        $original = $this->env->questions->findOrderedQuestions($this->anr)[0];
        $originalChoice = $this->env->choices->findChoicesOfQuestion($original)[0];
        self::assertSame('guide@example.invalid', $original->getCreator());
        self::assertSame('guide@example.invalid', $originalChoice->getCreator());
        self::assertSame(
            $multi ? '[' . $originalChoice->getId() . ']' : (string)$originalChoice->getId(),
            $original->getResponse()
        );
        $this->copy([$original], $this->other);
        $copy = $this->env->questions->findOrderedQuestions($this->other)[0];
        $copyChoice = $this->env->choices->findChoicesOfQuestion($copy)[0];
        self::assertSame($original->getCreator(), $copy->getCreator());
        self::assertSame($originalChoice->getCreator(), $copyChoice->getCreator());
        self::assertNotSame($original->getId(), $copy->getId());
        self::assertNotSame($originalChoice->getId(), $copyChoice->getId());
        self::assertSame('Fourth', $copy->getLabel(4));
        $expectedResponse = $multi ? '[' . $copyChoice->getId() . ']' : (string)$copyChoice->getId();
        self::assertSame($expectedResponse, $copy->getResponse());
        self::assertSame($legacy ? 'Definition' : 'Localized', $copy->getLabel(3));
        $this->reject(fn () => $this->snapshots->replace($this->anr, ['questions' => [
            $row, array_merge($row, ['id' => 801, 'type' => 6]),
        ]]));
        self::assertCount(1, $this->rows($this->anr));
        $this->snapshots->replace($this->anr, ['questions' => []]);
        self::assertSame([], $this->rows($this->anr));
        self::assertCount(1, $this->rows($this->other));
    }

    public static function snapshotFormats(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    public function testCommonDefinitionsInitializeAnAnalysisWithExplicitProperties(): void
    {
        $q = (new \Monarc\Core\Entity\Question())->setLabels(['label3' => 'Common'])
            ->setType(2)->setIsMultiChoice(false)->setPosition(1);
        $choice = (new \Monarc\Core\Entity\QuestionChoice())->setQuestion($q)
            ->setLabels(['label3' => 'Choice'])->setPosition(1);
        (new \ReflectionProperty($q, 'id'))->setValue($q, 700);
        (new \ReflectionProperty($choice, 'id'))->setValue($choice, 710);
        $this->copy([$q], $this->anr);
        $copy = $this->rows($this->anr)[0];
        self::assertSame('Common', $copy['label3']);
        self::assertSame(0, $copy['mode']);
        self::assertSame('Choice', $copy['choices'][1]['label3']);
    }

    public function testMethodStepExportAndImportKeepPayloadFieldsAndEmptyReplacement(): void
    {
        $q = $this->definition($this->anr, false);
        $c = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Exported answer']),
            $this->anr
        );
        $this->env->service->update($q, $this->env->validator->validate(['response' => $c], true), $this->anr);
        $class = \Monarc\FrontOffice\Export\Service\AnrExportService::class;
        $exporter = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $deliveryTable = $this->createMock(\Monarc\FrontOffice\Table\DeliveryTable::class);
        $deliveryTable->method('findByAnr')->willReturn([]);
        (new \ReflectionProperty($class, 'deliveryTable'))->setValue($exporter, $deliveryTable);
        (new \ReflectionProperty($class, 'questionTable'))->setValue($exporter, $this->env->questions);
        $data = (new \ReflectionMethod($class, 'prepareMethodData'))->invoke($exporter, $this->anr, false);
        $row = reset($data['questions']);
        self::assertSame(
            ['id', 'mode', 'isMultiChoice', 'label', 'response', 'type', 'position', 'questionChoices'],
            array_keys($row)
        );
        self::assertSame('Definition', $row['label']);
        self::assertSame('Exported answer', $row['questionChoices'][0]['label']);
        $this->snapshots->replace($this->other, $data);
        $copy = $this->rows($this->other)[0];
        self::assertSame((string)$copy['choices'][1]['id'], $copy['response']);
        $class = \Monarc\FrontOffice\Import\Processor\AnrMethodStepImportProcessor::class;
        $importer = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($class, 'questionSnapshotService'))->setValue($importer, $this->snapshots);
        $importer->processAnrMethodStepsData($this->other, ['questions' => []]);
        self::assertSame([], $this->rows($this->other));
    }

    public function testCopyDefersPersistenceToTheCallersExistingFlushAndUsesTraitAuditValues(): void
    {
        $source = (new \Monarc\Core\Entity\Question())->setType(1)->setPosition(1);
        (new \ReflectionProperty($source, 'id'))->setValue($source, 800);
        $copies = $this->snapshots->copy([$source], $this->anr);
        self::assertSame(0, $this->env->questions->countAll());
        $this->env->questions->flush();
        $this->snapshots->completeCopy($copies);
        self::assertCount(1, $this->rows($this->anr));
        self::assertSame('', $this->rows($this->anr)[0]['creator']);
        self::assertSame('', $this->rows($this->anr)[0]['updater']);
    }

    public function testDocumentConsumerIncludesSingleChoiceAnswersStoredAsStrings(): void
    {
        $q = $this->definition($this->anr, false);
        $c = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Selected answer']),
            $this->anr
        );
        $this->env->service->update($q, $this->env->validator->validate(['response' => $c], true), $this->anr);
        $class = \Monarc\FrontOffice\Service\DeliverableGenerationService::class;
        $service = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        foreach (['anr' => $this->anr, 'anrQuestionService' => $this->env->service,
            'currentLangAnrIndex' => 3] as $name => $value
        ) {
            (new \ReflectionProperty($class, $name))->setValue($service, $value);
        }
        $table = (new \ReflectionMethod($class, 'generateTrendAssessmentTable'))->invoke($service);
        $text = [];
        foreach ($table->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                foreach ($cell->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text[] = $element->getText();
                    }
                }
            }
        }
        self::assertContains('Definition', $text);
        self::assertContains('Selected answer', $text);

        $templatePath = tempnam(sys_get_temp_dir(), 'question-template-');
        $documentPath = tempnam(sys_get_temp_dir(), 'question-document-');
        try {
            $template = new \PhpOffice\PhpWord\PhpWord();
            $template->addSection()->addText('${QUESTIONS}');
            \PhpOffice\PhpWord\IOFactory::createWriter($template, 'Word2007')->save($templatePath);
            $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            $processor->setComplexBlock('QUESTIONS', $table);
            $processor->saveAs($documentPath);
            $archive = new \ZipArchive();
            self::assertTrue($archive->open($documentPath));
            try {
                $xml = $archive->getFromName('word/document.xml');
                self::assertIsString($xml);
                $document = new \DOMDocument();
                self::assertTrue($document->loadXML($xml));
                self::assertStringContainsString('Definition', $document->textContent);
                self::assertStringContainsString('Selected answer', $document->textContent);
                self::assertStringNotContainsString('${QUESTIONS}', $document->textContent);
            } finally {
                $archive->close();
            }
        } finally {
            unlink($templatePath);
            unlink($documentPath);
        }
    }

    private function choice(array $data, Anr $anr): int
    {
        $question = $this->env->questions->findByIdAndAnr($data['question'], $anr);
        $choice = (new QuestionChoice())->setAnr($anr)->setQuestion($question)->setLabels($data)
            ->setPosition(count($this->env->choices->findChoicesOfQuestion($question)) + 1);
        $this->env->choices->save($choice);

        return $choice->getId();
    }

    private function copy(array $questions, Anr $target): void
    {
        $copies = $this->snapshots->copy($questions, $target);
        $this->env->questions->flush();
        $this->snapshots->completeCopy($copies);
    }

    private function definition(Anr $anr, bool $multi): int
    {
        $source = (new \Monarc\Core\Entity\Question())->setType(2)
            ->setIsMultiChoice($multi)->setLabels(['label3' => 'Definition'])->setPosition(1);
        (new \ReflectionProperty($source, 'id'))->setValue($source, 800);
        $this->copy([$source], $anr);
        $questions = $this->env->questions->findOrderedQuestions($anr);
        return end($questions)->getId();
    }

    private function rows(Anr $anr): array
    {
        return $this->env->service->getList((new GetQuestionsInputFormatter())->format([]), $anr);
    }

    private function reject(callable $action, string $exceptionClass = Exception::class): void
    {
        try {
            $action();
            self::fail('Expected validation failure');
        } catch (\Exception $error) {
            self::assertInstanceOf($exceptionClass, $error);
            if ($error instanceof Exception) {
                self::assertSame(412, $error->getCode());
            }
        }
    }
}
