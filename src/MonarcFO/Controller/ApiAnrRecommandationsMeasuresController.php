<?php
/**
 * @link      https://github.com/CASES-LU for the canonical source repository
 * @copyright Copyright (c) Cases is a registered trademark of SECURITYMADEIN.LU
 * @license   MyCases is licensed under the GNU Affero GPL v3 - See license.txt for more information
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
