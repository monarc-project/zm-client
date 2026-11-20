<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Runtime;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\Tools\SchemaValidator;
use Monarc\Core\Exception\Exception;
use Monarc\FrontOffice\Entity\Question;
use Monarc\FrontOffice\Entity\QuestionChoice;
use Monarc\FrontOffice\InputFormatter\Question\GetQuestionsInputFormatter as GetAnrQuestionsInputFormatter;
use Psr\Container\ContainerInterface;

/** Analysis-specific checks use connection-local temporary tables, preserving installed questions. */
class QuestionRuntimeChecks
{
    public static function run(ContainerInterface $container, string &$phase): array
    {
        $manager = $container->get('doctrine.entitymanager.orm_cli');
        $connection = $manager->getConnection();
        $temporaryTables = [];
        $result = [];
        try {
            foreach ([Question::class, QuestionChoice::class] as $class) {
                $phase = 'analysis question metadata';
                $metadata = $manager->getClassMetadata($class);
                self::check((new SchemaValidator($manager))->validateClass($metadata) === [], 'analysis mappings');
                self::check(
                    !$metadata->associationMappings['anr']['joinColumns'][0]['nullable'],
                    'analysis question and choice require an analysis in metadata'
                );
                $columns = $connection->createSchemaManager()->listTableColumns($metadata->getTableName());
                self::check($columns['anr_id']->getNotnull(), 'installed anr_id is NOT NULL');
                if ($class === QuestionChoice::class) {
                    self::check(
                        !$metadata->associationMappings['question']['joinColumns'][0]['nullable'],
                        'question choice requires its question in metadata'
                    );
                    self::check($columns['question_id']->getNotnull(), 'installed question_id is NOT NULL');
                }
            }
            $manager->clear();
            foreach (['questions', 'questions_choices'] as $table) {
                $phase = 'analysis temporary ' . $table;
                $definition = $connection->createSchemaManager()->introspectTable($table);
                foreach ($definition->getForeignKeys() as $key) {
                    $definition->removeForeignKey($key->getName());
                }
                foreach ($connection->getDatabasePlatform()->getCreateTableSQL($definition) as $sql) {
                    $connection->executeStatement(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql));
                }
                $temporaryTables[] = $table;
            }
            $phase = 'analysis reject null question';
            try {
                $connection->executeStatement(
                    'INSERT INTO questions_choices (question_id, position) VALUES (NULL, 1)'
                );
                throw new \RuntimeException('A choice without a question was accepted.');
            } catch (\Doctrine\DBAL\Exception\NotNullConstraintViolationException $error) {
                // A missing parent must fail independently of service validation.
            }
            $phase = 'analysis question CRUD';
            $controllers = $container->get('ControllerManager');
            $controllers->get(\Monarc\FrontOffice\Controller\ApiAnrQuestionsController::class);
            self::check(
                !$controllers->has(\Monarc\FrontOffice\Controller\ApiAnrQuestionsChoicesController::class),
                'FrontOffice choice controller is absent'
            );
            $ids = $manager->getConnection()->fetchFirstColumn('SELECT id FROM anrs ORDER BY id LIMIT 2');
            self::check(count($ids) === 2, 'two local analyses required for scope validation');
            $table = $container->get(\Monarc\FrontOffice\Table\AnrTable::class);
            $anr = $table->findById((int)$ids[0]);
            $foreign = $table->findById((int)$ids[1]);
            $questions = $container->get(\Monarc\FrontOffice\Service\AnrQuestionService::class);
            $analysisQuestionDataInputValidator = $container->get(
                \Monarc\FrontOffice\Validator\InputValidator\Question\QuestionDataInputValidator::class
            );
            $analysisQuestionDataInputValidator->setDefaultLanguageIndex($anr->getLanguage());
            $snapshot = $container->get(\Monarc\FrontOffice\Service\QuestionSnapshotService::class);
            $questionImportProcessor = $container->get(
                \Monarc\FrontOffice\Import\Processor\QuestionImportProcessor::class
            );
            $questionTable = $container->get(\Monarc\FrontOffice\Table\QuestionTable::class);
            $importQuestions = static function ($anr, $data) use ($questionImportProcessor, $questionTable): void {
                $questionImportProcessor->processQuestionsData($anr, $data);
                $questionTable->flush();
                if ($questionImportProcessor->completeImport()) {
                    $questionTable->flush();
                }
            };
            $custom = $questions->create(
                $anr,
                $analysisQuestionDataInputValidator->validate(['label' . $anr->getLanguage() => 'Custom'])
            )->getId();
            $questions->update(
                $anr,
                $custom,
                $analysisQuestionDataInputValidator->validate(['response' => '0'], true)
            );
            self::check($questions->getQuestionData($anr, $custom)['response'] === '0', 'zero response');
            self::reject(fn () => $questions->getQuestionData($foreign, $custom), EntityNotFoundException::class);
            $phase = 'analysis single question deletion';
            $remaining = $questions->create($anr, [
                'label' . $anr->getLanguage() => 'Retained after deletion',
            ]);
            $questions->delete($anr, $custom);
            self::check($remaining->getPosition() === 1, 'single deletion closes the position gap');
            self::check($questionTable->findByIdAndAnr($remaining->getId(), $anr) === $remaining, 'remaining question');
            $questions->delete($anr, $remaining->getId());
            $phase = 'analysis import and cloning';
            $importQuestions($anr, ['questions' => [1 => [
                'id' => 100,
                'type' => 2,
                'isMultiChoice' => false,
                'label' => 'Question',
                'mode' => 0,
                'response' => '200',
                'questionChoices' => [['id' => 200, 'label' => 'Answer', 'position' => 1]],
            ]]]);
            $source = $container->get(\Monarc\FrontOffice\Table\QuestionTable::class)->findOrderedQuestions($anr);
            $snapshot->copy($source, $foreign);
            $manager->flush();
            $copies = $questions->getList((new GetAnrQuestionsInputFormatter())->format(['anr' => $foreign]));
            self::check(
                count($copies) === 1 && count($copies[0]['choices']) === 1
                && $copies[0]['response'] === null,
                'copied choices link to their question without retaining source selections'
            );
            $phase = 'analysis import tolerates mismatches';
            $importQuestions($anr, ['questions' => [
                [
                    'id' => 100,
                    'type' => 2,
                    'label' => 'Question',
                    'isMultiChoice' => true,
                    'response' => '[200,201,999]',
                    'questionChoices' => [
                        ['id' => 200, 'label' => 'Keep'],
                        ['id' => 200, 'label' => 'Ignore duplicate'],
                        ['id' => 201, 'question' => 101],
                    ],
                ],
                ['id' => 100, 'type' => Question::TYPE_SINGLE, 'label' => 'Ignore duplicate question'],
                ['id' => 101, 'type' => 6],
            ]]);
            $restored = $questions->getList((new GetAnrQuestionsInputFormatter())->format(['anr' => $anr]));
            self::check(
                count($restored) === 1 && count($restored[0]['choices']) === 1
                && $restored[0]['response'] === '[' . $restored[0]['choices'][0]['id'] . ']',
                'mismatches are skipped and valid multiple answers are remapped'
            );
            $phase = 'analysis import preserves wholly unusable snapshots';
            $importQuestions($anr, ['questions' => [['type' => 6]]]);
            self::check(
                json_encode($restored) === json_encode(
                    $questions->getList((new GetAnrQuestionsInputFormatter())->format(['anr' => $anr]))
                ),
                'wholly unusable snapshots retain existing questions'
            );
            $result['importMismatches'] = 'skipped; valid answers retained';
            $importQuestions($anr, ['questions' => []]);
            self::check(
                $questions->getCount((new GetAnrQuestionsInputFormatter())->format(['anr' => $anr])) === 0,
                'empty question import'
            );
            $result['analysisCrudScopeAndCopy'] = 'passed';
            return $result;
        } finally {
            $manager->clear();
            foreach (array_reverse($temporaryTables) as $table) {
                $connection->executeStatement('DROP TEMPORARY TABLE ' . $table);
            }
        }
    }

    private static function reject(callable $action, string $exceptionClass = Exception::class): void
    {
        try {
            $action();
        } catch (\Exception $error) {
            self::check($error instanceof $exceptionClass, 'rejected exception');
            if ($error instanceof Exception) {
                self::check($error->getCode() === 412, 'rejected status');
            }
            return;
        }
        throw new \RuntimeException('Question runtime check accepted invalid data.');
    }

    private static function check(bool $passed, string $name): void
    {
        if (!$passed) {
            throw new \RuntimeException('Question runtime check failed: ' . $name);
        }
    }
}
