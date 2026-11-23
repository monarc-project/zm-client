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
use Monarc\FrontOffice\InputFormatter\Question\GetQuestionsInputFormatter;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Tests\Fixture\GuideTestEnvironment;
use MonarcFrontOfficeTests\Fixture\QuestionTestEnvironment;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\Service\QuestionSnapshotService;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
class QuestionWorkflowTest extends TestCase
{
    private QuestionTestEnvironment $env;
    private Anr $anr;
    private Anr $other;
    private QuestionSnapshotService $snapshots;
    private \Monarc\FrontOffice\Import\Processor\QuestionImportProcessor $questionImportProcessor;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../Fixture/QuestionTestEnvironment.php';
        require_once __DIR__ . '/../../../../zm-core/tests/Fixture/GuideTestEnvironment.php';
        if (!Type::hasType('uuid')) {
            Type::addType('uuid', \Ramsey\Uuid\Doctrine\UuidType::class);
        }
        $identity = $this->createMock(ConnectedUserService::class);
        $identity->method('getConnectedUser')->willReturn(GuideTestEnvironment::user());
        $this->env = new QuestionTestEnvironment($identity);
        $this->anr = $this->env->analysis(1);
        $this->other = $this->env->analysis(2);
        $this->snapshots = new QuestionSnapshotService(
            $this->env->questions,
            $this->env->choices
        );
        $this->questionImportProcessor = new \Monarc\FrontOffice\Import\Processor\QuestionImportProcessor(
            $this->env->questions,
            $this->env->choices,
            new \Monarc\FrontOffice\Import\Helper\ImportCacheHelper(),
            $identity
        );
    }

    protected function tearDown(): void
    {
        $this->env->manager->close();
    }

    public function testCreateReturnsThePersistedAnalysisQuestion(): void
    {
        $question = $this->env->service->create($this->anr, ['label3' => 'Created question']);
        self::assertSame($question, $this->env->questions->findByIdAndAnr($question->getId(), $this->anr));
        self::assertSame($this->anr, $question->getAnr());
        self::assertSame('Created question', $question->getLabels()['label3']);
    }

    public function testModernParentSignaturesAndBothAssociationsAreValid(): void
    {
        foreach ([Question::class, QuestionChoice::class] as $class) {
            self::assertSame([], (new SchemaValidator($this->env->manager))
                ->validateClass($this->env->manager->getClassMetadata($class)));
            self::assertFalse($this->env->manager->getClassMetadata($class)
                ->associationMappings['anr']['joinColumns'][0]['nullable']);
            self::assertFalse((new \ReflectionMethod($class, 'getAnr'))->getReturnType()->allowsNull());
        }
        self::assertFalse($this->env->manager->getClassMetadata(QuestionChoice::class)
            ->associationMappings['question']['joinColumns'][0]['nullable']);
        $method = new \ReflectionMethod($this->env->questions, 'findByAnr');
        self::assertSame(\Monarc\Core\Table\AbstractTable::class, $method->getDeclaringClass()->getName());
        $id = $this->env->service->create(
            $this->anr,
            $this->env->validator->validate(['label3' => 'Local'])
        )->getId();
        self::assertSame([$id], array_map(fn ($q): int => $q->getId(), $this->env->questions->findByAnr($this->anr)));
    }

    public function testCreationAppendsAfterTheAnalysisMaximumPosition(): void
    {
        $definitionId = $this->definition($this->anr, false);
        $definition = $this->env->questions->findByIdAndAnr($definitionId, $this->anr);
        $definition->setPosition(9);
        $this->env->questions->save($definition);
        $foreignId = $this->definition($this->other, false);
        $foreign = $this->env->questions->findByIdAndAnr($foreignId, $this->other);
        $foreign->setPosition(40);
        $this->env->questions->save($foreign);

        $question = $this->env->service->create($this->anr, ['label3' => 'Local']);
        self::assertSame(10, $question->getPosition());
        self::assertSame(Question::MODE_LOCALLY_CREATED, $question->getMode());
        self::assertSame(['anr' => $this->anr], $question->getImplicitPositionRelationsValues());
        self::assertSame(40, $foreign->getPosition());
    }

    public function testSingleDeletionShiftsRemainingPositionsOnlyWithinItsAnalysis(): void
    {
        $questions = [];
        foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
            $questions[] = $this->env->service->create($this->anr, ['label3' => $label]);
        }
        $foreign = $this->env->service->create($this->other, ['label3' => 'Foreign']);
        $this->env->service->delete($this->anr, $questions[1]->getId());
        $this->env->service->delete($this->anr, $questions[3]->getId());
        $remaining = $this->rows($this->anr);
        self::assertSame(['A', 'C', 'E'], array_column($remaining, 'label3'));
        self::assertSame([1, 2, 3], array_column($remaining, 'position'));
        self::assertSame(1, $foreign->getPosition());
        self::assertSame(1, $this->env->questions->findByIdAndAnr($foreign->getId(), $this->other)->getPosition());
        self::assertSame(4, $this->env->service->create($this->anr, ['label3' => 'F'])->getPosition());
    }

    public function testQuestionsAndChoicesRequireAnAnalysisInPersistence(): void
    {
        $question = $this->env->service->create($this->anr, ['label3' => 'Parent question'])->getId();
        $rows = [
            'questions' => [
                'anr_id' => null,
                'type' => Question::TYPE_SINGLE,
                'multichoice' => 0,
                'mode' => 1,
                'position' => 1,
            ],
            'questions_choices' => ['anr_id' => null, 'question_id' => $question, 'position' => 1],
        ];
        foreach ($rows as $table => $row) {
            try {
                $this->env->manager->getConnection()->insert($table, $row);
                self::fail('A question or choice without an analysis must be rejected.');
            } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException $error) {
                self::assertStringContainsString('anr_id', $error->getMessage());
            }
        }
    }

    public function testCustomAndDefinitionQuestionsHaveDifferentWriteRulesAndKeepResponses(): void
    {
        $id = $this->env->service->create(
            $this->anr,
            $this->env->validator->validate(['label3' => 'Local', 'type' => 2, 'multichoice' => 1])
        )->getId();
        $data = $this->env->service->getQuestionData($this->anr, $id);
        self::assertSame(Question::TYPE_SINGLE, $data['type']);
        self::assertSame(0, $data['multichoice']);
        self::assertSame(1, $data['mode']);
        self::assertSame('guide@example.invalid', $data['creator']);
        $this->env->service->update($this->anr, $id, $this->env->validator->validate(['response' => '0'], true));
        self::assertSame('0', $this->env->service->getQuestionData($this->anr, $id)['response']);
        self::assertSame('guide@example.invalid', $this->env->service->getQuestionData($this->anr, $id)['updater']);
        self::assertSame('Local', $this->env->service->getQuestionData($this->anr, $id)['label3']);
        $this->env->service->update($this->anr, $id, $this->env->validator->validate(['response' => null], true));
        self::assertNull($this->env->service->getQuestionData($this->anr, $id)['response']);
        $definition = $this->definition($this->anr, false);
        $this->env->service->update(
            $this->anr,
            $definition,
            $this->env->validator->validate([
                'label3' => 'Forbidden',
                'type' => Question::TYPE_SINGLE,
                'response' => '',
            ], true)
        );
        self::assertSame('Definition', $this->env->service->getQuestionData($this->anr, $definition)['label3']);
        $this->reject(
            fn () => $this->env->service->delete($this->anr, $definition)
        );
        self::assertSame('Local', $this->env->service->getQuestionData($this->anr, $id)['label3']);
        $this->env->service->delete($this->anr, $id);
        self::assertCount(1, $this->rows($this->anr));
    }

    public function testCrossAnalysisRecordsAreRejectedWithoutRepeatingChoiceResponseChecks(): void
    {
        $q = $this->definition($this->anr, true);
        $foreign = $this->definition($this->other, true);
        $choice = $this->choice(['question' => $foreign, 'label3' => 'Foreign'], $this->other);
        $this->reject(
            fn () => $this->env->service->getQuestionData($this->anr, $foreign),
            EntityNotFoundException::class
        );
        $this->reject(
            fn () => $this->env->service->update($this->anr, $foreign, ['response' => 'Changed']),
            EntityNotFoundException::class
        );
        $this->reject(fn () => $this->env->service->delete($this->anr, $foreign), EntityNotFoundException::class);
        $this->env->service->update($this->anr, $q, ['response' => '[' . $choice . ']']);
        self::assertSame('[' . $choice . ']', $this->env->service->getQuestionData($this->anr, $q)['response']);
        self::assertCount(1, $this->rows($this->anr));
        self::assertCount(1, $this->env->choices->findChoicesOfQuestion(
            $this->env->questions->findByIdAndAnr($foreign, $this->other)
        ));
    }

    public function testPayloadAnalysisFieldsCannotChangeTheResolvedScope(): void
    {
        $id = $this->env->service->create(
            $this->anr,
            $this->env->validator->validate(['label3' => 'Local', 'anr' => 2])
        )->getId();
        $this->env->service->update(
            $this->anr,
            $id,
            $this->env->validator->validate(['label3' => 'Updated', 'anr' => 2], true)
        );
        self::assertSame('Updated', $this->env->service->getQuestionData($this->anr, $id)['label3']);
        $this->reject(fn () => $this->env->service->getQuestionData($this->other, $id), EntityNotFoundException::class);
        $question = $this->definition($this->anr, false);
        $choice = $this->choice(
            $this->env->choiceValidator->validate(['question' => $question, 'label3' => 'Choice', 'anr' => 2]),
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
        ] as $operation) {
            $this->reject($operation, \LogicException::class);
        }
        foreach ([
            fn () => $this->env->service->delete(null, 1),
            fn () => $this->env->service->create(
                null,
                $this->env->validator->validate(['label3' => 'Unscoped'])
            )->getId(),
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

    public function testScopedCountAndOnlyPersistedChoicesWithSingleChoiceAnswerClearing(): void
    {
        $q = $this->definition($this->anr, false);
        $this->definition($this->other, false);
        $choice = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Selected']),
            $this->anr
        );
        $this->env->service->update(
            $this->anr,
            $q,
            $this->env->validator->validate(['response' => $choice], true)
        );
        $row = $this->rows($this->anr)[0];
        self::assertSame((string)$choice, $row['response']);
        self::assertSame([$choice], array_column($row['choices'], 'id'));
        $this->env->service->update(
            $this->anr,
            $q,
            $this->env->validator->validate(['response' => 'null'], true)
        );
        self::assertSame('null', $this->env->service->getQuestionData($this->anr, $q)['response']);
        self::assertSame([], (array)$row['anr']);
        $params = (new GetQuestionsInputFormatter())->format([
            'anr' => $this->anr,
            'filter' => 'Definition',
            'limit' => 1,
        ]);
        self::assertSame(1, $this->env->service->getCount($params));
        self::assertCount(1, $this->env->service->getList($params));
    }

    public function testMultichoiceBooleanFiltersRetainTheAnalysisScope(): void
    {
        $singleChoiceQuestion = $this->definition($this->anr, false);
        $multiChoiceQuestion = $this->definition($this->anr, true);
        $this->definition($this->other, false);
        $this->definition($this->other, true);
        foreach ([
            [0, false],
            [1, true],
            ['0', false],
            ['1', true],
            [false, false],
            [true, true],
            ['false', false],
            ['true', true],
        ] as [$value, $isMultiChoice]) {
            $params = (new GetQuestionsInputFormatter())->format([
                'anr' => $this->anr,
                'multichoice' => $value,
            ]);
            self::assertSame($isMultiChoice, $params->getFilterFor('multichoice')['value']);
            self::assertSame(1, $this->env->service->getCount($params));
            self::assertSame(
                [$isMultiChoice ? $multiChoiceQuestion : $singleChoiceQuestion],
                array_column($this->env->service->getList($params), 'id')
            );
        }
    }

    /** @dataProvider snapshotFormats */
    public function testImportsRestoreAnswersWhileCopiesUseFreshQuestionChoiceLinks(bool $legacy, bool $multi): void
    {
        $row = [
            'id' => 'question-a',
            'type' => 2,
            'multichoice' => (int)$multi,
            'mode' => 0,
            'response' => $multi ? '["choice-a"]' : 'choice-a',
            'label3' => 'Definition',
            'label4' => 'Fourth'
        ];
        $choices = [['id' => 'choice-a', 'position' => 1, 'label3' => 'Answer', 'question' => 'question-a']];
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
        $this->importQuestions($this->anr, $snapshot);
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
        self::assertNull($copy->getResponse());
        self::assertSame($copy, $copyChoice->getQuestion());
        self::assertSame($legacy ? 'Definition' : 'Localized', $copy->getLabel(3));
        $snapshot['questions'][] = array_merge($row, ['id' => 801, 'type' => 6]);
        $this->importQuestions($this->anr, $snapshot);
        self::assertCount(1, $this->rows($this->anr));
        $this->importQuestions($this->anr, ['questions' => []]);
        self::assertSame([], $this->rows($this->anr));
        self::assertCount(1, $this->rows($this->other));
    }

    public static function snapshotFormats(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    /** @dataProvider invalidSnapshotRows */
    public function testInvalidSnapshotRowsAreIgnoredWithoutStoppingRestore(
        array $row,
        bool $skipQuestion,
        int $choiceCount,
        ?string $response
    ): void {
        $id = $this->env->service->create($this->anr, ['label3' => 'Keep this question'])->getId();
        $this->importQuestions($this->anr, ['questions' => [$row]]);
        $questions = $this->env->questions->findOrderedQuestions($this->anr);
        self::assertCount(1, $questions);
        if ($skipQuestion) {
            self::assertSame($id, $questions[0]->getId());
            self::assertSame('Keep this question', $questions[0]->getLabel(3));
        } else {
            $choices = $this->env->choices->findChoicesOfQuestion($questions[0]);
            self::assertCount($choiceCount, $choices);
            self::assertSame($row['label3'], $questions[0]->getLabel(3));
            self::assertSame(
                $response === 'selected' ? (string)$choices[0]->getId() : $response,
                $questions[0]->getResponse()
            );
        }
        self::assertSame($choiceCount, $this->env->choices->countAll());
    }

    public function testImportedQuestionsHaveDistinctPositionsInTheirRequestedOrder(): void
    {
        foreach ([
            [['label3' => 'First'], ['label3' => 'Second'], ['label3' => 'Third']],
            [
                ['label3' => 'Third', 'position' => 5],
                ['label3' => 'First', 'position' => 1],
                ['label3' => 'Second', 'position' => 1],
            ],
        ] as $questions) {
            $this->importQuestions($this->anr, ['questions' => $questions]);
            $importedQuestions = $this->env->questions->findOrderedQuestions($this->anr);
            self::assertSame(
                [1, 2, 3],
                array_map(fn (Question $question) => $question->getPosition(), $importedQuestions)
            );
            self::assertSame(
                ['First', 'Second', 'Third'],
                array_map(fn (Question $question) => $question->getLabel(3), $importedQuestions)
            );
        }
    }

    public static function invalidSnapshotRows(): array
    {
        $choice = ['id' => 900, 'label3' => 'Answer'];
        $row = ['id' => 800, 'type' => 2, 'label3' => 'Question', 'response' => '900', 'questionChoices' => [$choice]];

        return [
            'blank label is preserved' => [array_replace($row, ['label3' => '   ']), false, 1, 'selected'],
            'unusable question reference' => [array_replace($row, ['id' => []]), true, 0, null],
            'unusable choice reference' => [
                array_replace($row, ['questionChoices' => [array_replace($choice, ['id' => []])]]), false, 0, null,
            ],
            'unsupported question type' => [array_replace($row, ['type' => 6]), true, 0, null],
            'duplicate choice identifier' => [
                array_replace($row, ['questionChoices' => [$choice, $choice]]), false, 1, 'selected',
            ],
            'foreign choice parent' => [
                array_replace($row, ['questionChoices' => [$choice + ['question' => 801]]]), false, 0, null,
            ],
            'foreign answer' => [array_replace($row, ['response' => '901']), false, 1, null],
            'invalid multiple answer' => [
                array_replace($row, ['isMultiChoice' => true, 'response' => '{"id":900}']), false, 1, '[]',
            ],
        ];
    }

    public function testRestoreSkipsMismatchedChoicesAndKeepsValidMultipleAnswers(): void
    {
        $otherId = $this->env->service->create($this->other, ['label3' => 'Other analysis'])->getId();
        $this->importQuestions($this->anr, ['questions' => [
            [
                'id' => 800,
                'type' => 2,
                'label3' => 'Question',
                'isMultiChoice' => true,
                'response' => '[900,901,999,true,[900],900]',
                'questionChoices' => [
                    ['id' => 900, 'label3' => 'First answer'],
                    ['id' => 900, 'label3' => 'Duplicate'],
                    ['id' => 901, 'question' => 801],
                    ['id' => 902, 'question' => []],
                    'invalid record',
                ],
            ],
            ['id' => 800, 'type' => Question::TYPE_SINGLE, 'label3' => 'Duplicate question'],
            'invalid question',
        ]]);
        $question = $this->env->questions->findOrderedQuestions($this->anr)[0];
        $choices = $this->env->choices->findChoicesOfQuestion($question);
        self::assertCount(1, $this->rows($this->anr));
        self::assertCount(1, $choices);
        self::assertSame('First answer', $choices[0]->getLabel(3));
        self::assertSame('[' . $choices[0]->getId() . ']', $question->getResponse());
        self::assertSame([$otherId], array_column($this->rows($this->other), 'id'));
    }

    public function testCopyPreservesSourceAuditAndClearsStaleAnswer(): void
    {
        $source = (new Question())->setType(2)->setMode(0)->setResponse('999')
            ->setLabels(['label3' => 'Keep'])->setCreator('source@example.invalid')->setUpdater('edit@example.invalid');
        (new \ReflectionProperty($source, 'id'))->setValue($source, 801);
        $choice = (new QuestionChoice())->setLabels(['label3' => 'Answer'])->setCreator('author@example.invalid');
        (new \ReflectionProperty($choice, 'id'))->setValue($choice, 900);
        $source->addQuestionChoice($choice);
        $this->copy([42 => $source], $this->anr);
        $questions = $this->env->questions->findOrderedQuestions($this->anr);
        self::assertCount(1, $questions);
        self::assertSame('Keep', $questions[0]->getLabel(3));
        self::assertSame('source@example.invalid', $questions[0]->getCreator());
        self::assertSame('edit@example.invalid', $questions[0]->getUpdater());
        self::assertNull($questions[0]->getResponse());
        self::assertSame('author@example.invalid', $questions[0]->getQuestionChoices()->first()->getCreator());
    }

    /** @dataProvider unusableSnapshots */
    public function testUnusableSnapshotsPreserveExistingQuestions(array $snapshot): void
    {
        $id = $this->env->service->create($this->anr, ['label3' => 'Keep'])->getId();
        $this->importQuestions($this->anr, $snapshot);
        self::assertSame([$id], array_column($this->rows($this->anr), 'id'));
    }

    public static function unusableSnapshots(): array
    {
        return [[[]], [['questions' => null]], [['questions' => 'invalid']], [['questions' => ['invalid']]]];
    }

    public function testLegacyFlatChoicesAreAssignedToTheirOwnQuestionsWithDefaultIdentifiers(): void
    {
        $this->importQuestions($this->anr, [
            'questions' => [
                0 => ['type' => 2, 'label' => 'First question', 'response' => '10'],
                1 => [
                    'type' => 2,
                    'label' => 'Second question',
                    'mode' => '1',
                    'multichoice' => '0',
                    'response' => '11'
                ],
            ],
            'questionChoice' => [
                10 => ['question' => 0, 'label' => 'First answer'],
                11 => ['question' => 1, 'label' => 'Second answer'],
            ],
        ]);
        foreach ($this->env->questions->findOrderedQuestions($this->anr) as $index => $question) {
            $choices = $this->env->choices->findChoicesOfQuestion($question);
            self::assertCount(1, $choices);
            self::assertSame($index === 0 ? 'First answer' : 'Second answer', $choices[0]->getLabel(3));
            self::assertSame((string)$choices[0]->getId(), $question->getResponse());
            self::assertSame($index, $question->getMode());
        }
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
        self::assertSame('Choice', $copy['choices'][0]['label3']);
    }

    public function testMethodStepExportAndImportKeepPayloadFieldsAndEmptyReplacement(): void
    {
        $q = $this->definition($this->anr, false);
        $c = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Exported answer']),
            $this->anr
        );
        $this->env->service->update($this->anr, $q, $this->env->validator->validate(['response' => $c], true));
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
        $this->importQuestions($this->other, $data);
        $copy = $this->rows($this->other)[0];
        self::assertSame((string)$copy['choices'][0]['id'], $copy['response']);
        $this->importQuestions($this->other, ['questions' => []]);
        self::assertSame([], $this->rows($this->other));
    }

    public function testCopyDefersPersistenceToTheCallersExistingFlushAndUsesTraitAuditValues(): void
    {
        $source = (new \Monarc\Core\Entity\Question())->setType(Question::TYPE_SINGLE)->setPosition(1);
        (new \ReflectionProperty($source, 'id'))->setValue($source, 800);
        $this->snapshots->copy([$source], $this->anr);
        self::assertSame(0, $this->env->questions->countAll());
        $this->env->questions->flush();
        self::assertCount(1, $this->rows($this->anr));
        self::assertSame('', $this->rows($this->anr)[0]['creator']);
        self::assertSame('', $this->rows($this->anr)[0]['updater']);
    }

    public function testChoiceCopyLinksEntitiesWithoutReadingSourceIds(): void
    {
        $sourceQuestion = (new Question())
            ->setAnr($this->anr)
            ->setLabels(['label3' => 'Source question'])
            ->setType(Question::TYPE_CHOICE)
            ->setResponse('source-choice-id')
            ->setPosition(1);
        $sourceChoice = (new QuestionChoice())
            ->setAnr($this->anr)
            ->setQuestion($sourceQuestion)
            ->setLabels(['label3' => 'Source answer'])
            ->setPosition(1);

        $this->snapshots->copy([$sourceQuestion], $this->other);
        self::assertSame(0, $this->env->questions->countAll());
        self::assertSame(0, $this->env->choices->countAll());
        $this->env->questions->flush();

        $copiedQuestion = $this->env->questions->findOrderedQuestions($this->other)[0];
        $copiedChoices = $this->env->choices->findChoicesOfQuestion($copiedQuestion);
        self::assertCount(1, $copiedChoices);
        self::assertSame($copiedQuestion, $copiedChoices[0]->getQuestion());
        self::assertSame($this->other, $copiedChoices[0]->getAnr());
        self::assertNull($copiedQuestion->getResponse());
        self::assertSame('Source answer', $copiedChoices[0]->getLabel(3));
    }

    public function testEntityCopyPreservesQuestionDataWithoutImportValidation(): void
    {
        $questionSnapshotService = new QuestionSnapshotService(
            $this->env->questions,
            $this->env->choices
        );
        $sourceQuestion = (new Question())->setAnr($this->anr)
            ->setLabels(['label1' => 'First', 'label2' => 'Second', 'label3' => 'Third', 'label4' => 'Fourth'])
            ->setType(Question::TYPE_SINGLE)->setMode(1)->setResponse('0')->setPosition(4)
            ->setCreator('source@example.invalid')->setUpdater('update@example.invalid');
        (new \ReflectionProperty($sourceQuestion, 'id'))->setValue($sourceQuestion, 800);
        $questionSnapshotService->copy([42 => $sourceQuestion], $this->other);
        self::assertSame(0, $this->env->questions->countAll());
        $this->env->questions->flush();
        $copiedQuestion = $this->env->questions->findOrderedQuestions($this->other)[0];

        self::assertNotSame($sourceQuestion, $copiedQuestion);
        self::assertNotSame($sourceQuestion->getId(), $copiedQuestion->getId());
        self::assertSame($this->other, $copiedQuestion->getAnr());
        self::assertSame($sourceQuestion->getLabels(), $copiedQuestion->getLabels());
        self::assertSame(Question::TYPE_SINGLE, $copiedQuestion->getType());
        self::assertSame(1, $copiedQuestion->getMode());
        self::assertSame('0', $copiedQuestion->getResponse());
        self::assertSame(4, $copiedQuestion->getPosition());
        self::assertSame('source@example.invalid', $copiedQuestion->getCreator());
        self::assertSame('update@example.invalid', $copiedQuestion->getUpdater());
        self::assertSame($this->anr, $sourceQuestion->getAnr());
    }

    public function testDocumentConsumerIncludesSingleChoiceAnswersStoredAsStrings(): void
    {
        $this->env->service->create(
            $this->other,
            $this->env->validator->validate(['label3' => 'Foreign question'])
        )->getId();
        $q = $this->definition($this->anr, false);
        $c = $this->choice(
            $this->env->choiceValidator->validate(['question' => $q, 'label3' => 'Selected answer']),
            $this->anr
        );
        $this->env->service->update($this->anr, $q, $this->env->validator->validate(['response' => $c], true));
        $class = \Monarc\FrontOffice\Service\DeliverableGenerationService::class;
        $service = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        foreach ([
            'anr' => $this->anr,
            'anrQuestionService' => $this->env->service,
            'currentLangAnrIndex' => 3
        ] as $name => $value
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
        self::assertNotContains('Foreign question', $text);

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
                self::assertStringNotContainsString('Foreign question', $document->textContent);
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
        $this->snapshots->copy($questions, $target);
        $this->env->questions->flush();
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

    private function importQuestions(Anr $anr, array $snapshot): void
    {
        $this->questionImportProcessor->processQuestionsData($anr, $snapshot);
        $this->env->questions->flush();
        if ($this->questionImportProcessor->completeImport()) {
            $this->env->questions->flush();
        }
    }

    private function rows(Anr $anr): array
    {
        return $this->env->service->getList((new GetQuestionsInputFormatter())->format(['anr' => $anr]));
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
