<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2018 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Controller;

/**
 * Api Anr Recommandations Measures
 *
 * Class ApiAnrRecommandationsMeasuresController
 * @package MonarcFO\Controller
 */
class ApiAnrRecommandationsMeasuresController extends ApiAnrAbstractController
{
    protected $name = 'recommandations-measures';
    protected $dependencies = ['anr', 'recommandation', 'measure'];

    /**
     * @inheritdoc
     */
    public function update($id, $data)
    {
        return $this->methodNotAllowed();
    }

    /**
     * @inheritdoc
     */
    public function patch($token, $data)
    {
        return $this->methodNotAllowed();
    }
}
