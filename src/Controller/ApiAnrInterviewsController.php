<?php declare(strict_types=1);
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2024 Luxembourg House of Cybersecurity LHC.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrInterviewService;

class ApiAnrInterviewsController extends ApiAnrAbstractController
{
    protected $name = 'interviews';

    protected $dependencies = ['anr'];

    public function __construct(AnrInterviewService $anrInterviewService)
    {
        parent::__construct($anrInterviewService);
    }
}
