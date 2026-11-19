<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2026 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFrontOfficeTests\Fixture;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Validator\InputValidator\InputValidationTranslator;
use Monarc\FrontOffice\Entity\Anr;
use Monarc\FrontOffice\Entity\Interview;
use Monarc\FrontOffice\Service\AnrInterviewService;
use Monarc\FrontOffice\Table\InterviewTable;
use Monarc\FrontOffice\Validator\InputValidator\Interview\InterviewDataInputValidator;

class InterviewTestEnvironment
{
    public EntityManager $manager;
    public InterviewTable $interviewTable;
    public AnrInterviewService $anrInterviewService;
    public InterviewDataInputValidator $interviewDataInputValidator;
    public Anr $anr;
    public Anr $other;

    public function __construct(ConnectedUserService $connectedUserService)
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/../../src/Entity']));
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('Php84InterviewTestProxies');
        $config->setAutoGenerateProxyClasses(true);
        $this->manager = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        (new SchemaTool($this->manager))->createSchema([$this->manager->getClassMetadata(Interview::class)]);
        $this->interviewTable = new InterviewTable($this->manager);
        $this->anrInterviewService = new AnrInterviewService($this->interviewTable, $connectedUserService);
        $translator = new InputValidationTranslator($connectedUserService, []);
        $this->interviewDataInputValidator = new InterviewDataInputValidator([], $translator);
        $this->anr = $this->analysis(1);
        $this->other = $this->analysis(2);
    }

    public function analysis(int $id): Anr
    {
        $anr = new Anr();
        (new \ReflectionProperty($anr, 'id'))->setValue($anr, $id);
        $anr->setLanguage(1);
        $this->manager->getUnitOfWork()->registerManaged($anr, ['id' => $id], []);

        return $anr;
    }
}
