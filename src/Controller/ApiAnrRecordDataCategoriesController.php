<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Controller;

use Monarc\FrontOffice\Service\AnrRecordDataCategoryService;

class ApiAnrRecordDataCategoriesController extends ApiAnrAbstractController
{
    protected $name = 'record-data-categories';
    protected $dependencies = ['anr'];

    public function __construct(AnrRecordDataCategoryService $anrRecordDataCategoryService)
    {
        parent::__construct($anrRecordDataCategoryService);
    }

    public function create($data)
    {
        return $this->methodNotAllowed();
    }

    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function patch($id, $data)
    {
        return $this->methodNotAllowed();
    }

    public function delete($id)
    {
        return $this->methodNotAllowed();
    }
}
