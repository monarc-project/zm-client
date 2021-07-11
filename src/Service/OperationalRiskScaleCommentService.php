<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2021 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\OperationalRiskScaleCommentService as CoreOperationalRiskScaleCommentService;
use Monarc\FrontOffice\Model\Table\AnrTable;
use Monarc\FrontOffice\Model\Table\OperationalRiskScaleCommentTable;
use Monarc\FrontOffice\Model\Table\TranslationTable;

class OperationalRiskScaleCommentService extends CoreOperationalRiskScaleCommentService
{
    public function __construct(
        AnrTable $anrTable,
        OperationalRiskScaleCommentTable $operationalRiskScaleCommentTable,
        TranslationTable $translationTable,
        ConfigService $configService
    ) {
        parent::__construct($anrTable, $operationalRiskScaleCommentTable, $translationTable, $configService);
    }
}
