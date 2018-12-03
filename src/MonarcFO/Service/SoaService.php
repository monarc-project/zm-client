<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Service;

/**
 * Soa Service
 *
 * Class SoaService
 * @package MonarcFO\Service
 */
 class SoaService extends \MonarcCore\Service\AbstractService
 {
  protected $table;
  protected $entity;
  protected $anrTable;
  protected $userAnrTable;
  protected $measureService;
  protected $dependencies = ['anr', 'measure'];
}
