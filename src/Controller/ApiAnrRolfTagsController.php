<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRolfTagService;

class ApiAnrRolfTagsController extends ApiAnrAbstractController
{
    protected $name = 'tags';

    public function __construct(AnrRolfTagService $anrRolfTagService)
    {
        parent::__construct($anrRolfTagService);
    }
}
