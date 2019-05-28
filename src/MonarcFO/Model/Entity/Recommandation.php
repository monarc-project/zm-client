<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;
use MonarcFO\Model\Entity\RecommandationSet;


/**
 * Recommandation
 *
 * @ORM\Table(name="recommandations", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id","code"}),
 *      @ORM\Index(name="anr_id_2", columns={"anr_id"},
 *      @ORM\Index(name="recommandation_set_uuid", columns={"recommandation_set_uuid", "code", "anr_id"},)
 * )
 * })
 * @ORM\Entity
 */
class Recommandation extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     * @ORM\Id
     */
    protected $uuid;

    /**
     * @var \MonarcCore\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcCore\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     * @ORM\Id
     */
    protected $anr;

    /**
     * @var \MonarcFO\Model\Entity\RecommandationSet
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\RecommandationSet", cascade={"persist"}, fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="recommandation_set_uuid", referencedColumnName="uuid", nullable=true),
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="anr_id", nullable=true)
     * })
     */
    protected $recommandationSet;

    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=100, nullable=true)
     */
    protected $code;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255, nullable=true)
     */
    protected $description;

    /**
     * @var smallint
     *
     * @ORM\Column(name="importance", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $importance = 1;

    /**
     * @var smallint
     *
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true, "default":1}, nullable=true)
     */
    protected $position = null;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="string", length=255, nullable=true)
     */
    protected $comment;

    /**
     * @var smallint
     *
     * @ORM\Column(name="status", type="smallint", options={"unsigned":true, "default":1})
     */
    protected $status = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="responsable", type="string", length=255, nullable=true)
     */
    protected $responsable;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="duedate", type="datetime", nullable=true)
     */
    protected $duedate;

    /**
     * @var smallint
     *
     * @ORM\Column(name="counter_treated", type="smallint", options={"unsigned":true, "default":0})
     */
    protected $counterTreated = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="original_code", type="string", length=100, nullable=true)
     */
    protected $originalCode;

    /**
     * @var string
     *
     * @ORM\Column(name="token_import", type="string", length=255, nullable=true)
     */
    protected $tokenImport;

    /**
     * @var string
     *
     * @ORM\Column(name="creator", type="string", length=255, nullable=true)
     */
    protected $creator;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true)
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="updater", type="string", length=255, nullable=true)
     */
    protected $updater;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true)
     */
    protected $updatedAt;

    /**
     * @return int
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param int $id
     * @return Asset
     */
    public function setUuid($id)
    {
        $this->uuid = $id;
        return $this;
    }

    /**
     * @return Anr
     */
    public function getAnr()
    {
        return $this->anr;
    }

    /**
     * @param Anr $anr
     * @return Scale
     */
    public function setAnr($anr)
    {
        $this->anr = $anr;
        return $this;
    }


    /**
     * @return Date
     */
    public function getDueDate()
    {
        return $this->duedate;
    }


    /**
     * @param DateTime date
     * @return Scale
     */
    public function setDueDate($date)
    {
        $this->duedate = $date;
        return $this;
    }

    /**
     * @return RecommandationSet
     */
    public function getRecommandationSet()
    {
        return $this->recommandationSet;
    }

    /**
     * @param RecommandationSet $recommandationSet
     * @return Recommandation
     */
    public function setRecommandationSet($recommandationSet)
    {
        $this->recommandationSet = $recommandationSet;
        return $this;
    }

    public function getFiltersForService(){
        $filterJoin = [
            [
                'as' => 'r',
                'rel' => 'recommandationSet',
            ],
        ];
        $filterLeft = [
            [
                'as' => 'r1',
                'rel' => 'recommandationSet',
            ],

        ];
        $filtersCol = [
            'r.uuid',
            'r.anr',
            'r.code',
        ];
        return [$filterJoin,$filterLeft,$filtersCol];
    }
    

    /**
     * @param bool $partial
     * @return mixed
     */
    public function getInputFilter($partial = true)
    {
        if (!$this->inputFilter) {
            parent::getInputFilter($partial);
            $this->inputFilter->add([
                'name' => 'importance',
                'required' => (!$partial) ? true : false,
                'allow_empty' => false,
                'validators' => [
                    [
                        'name' => 'InArray',
                        'options' => [
                            'haystack' => [1, 2, 3],
                        ],
                        'default' => 0,
                    ],
                ],
            ]);

            $validatorsCode = [];
            if (!$partial) {
                $validatorsCode = [
                    [
                        'name' => '\MonarcCore\Validator\UniqueCode',
                        'options' => [
                            'entity' => $this
                        ],
                    ],
                ];
            }

            $this->inputFilter->add([
                'name' => 'code',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
                'filters' => [],
                'validators' => $validatorsCode
            ]);

            $this->inputFilter->add([
                'name' => 'anr',
                'required' => ($partial) ? false : true,
                'allow_empty' => false,
            ]);

            return $this->inputFilter;
        }
    }
}
