<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * Recommandation
 *
 * @ORM\Table(name="recommandations", indexes={
 *      @ORM\Index(name="anr_id", columns={"anr_id","code"}),
 *      @ORM\Index(name="anr_id_2", columns={"anr_id"}),
 *      @ORM\Index(name="recommandation_set_uuid", columns={"recommandation_set_uuid", "code", "anr_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Recommandation extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="uuid", type="uuid", nullable=false)
     * @ORM\Id
     */
    protected $uuid;

    /**
     * @var Anr
     *
     * @ORM\ManyToOne(targetEntity="Anr", fetch="EAGER")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     * @ORM\Id
     */
    protected $anr;

    /**
     * @var RecommandationSet
     *
     * @ORM\ManyToOne(targetEntity="RecommandationSet", cascade={"persist"}, fetch="EAGER")
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
     * @var int
     *
     * @ORM\Column(name="importance", type="smallint", options={"unsigned":true, "default":0}, nullable=true)
     */
    protected $importance = 0;

    /**
     * @var int
     *
     * @ORM\Column(name="position", type="smallint", options={"unsigned":true, "default":1}, nullable=true)
     */
    protected $position = 1;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="string", length=255, nullable=true)
     */
    protected $comment;

    /**
     * @var int
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
     * @var DateTime
     *
     * @ORM\Column(name="duedate", type="datetime", nullable=true)
     */
    protected $duedate;

    /**
     * @var int
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
     * @return int
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    public function setUuid($id): self
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

    public function setAnr($anr): self
    {
        $this->anr = $anr;

        return $this;
    }

    public function getDueDate(): ?DateTime
    {
        return $this->duedate;
    }

    public function setDueDate(DateTime $date): self
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
     */
    public function setRecommandationSet($recommandationSet): self
    {
        $this->recommandationSet = $recommandationSet;

        return $this;
    }

    public function getFiltersForService()
    {
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
            // $this->inputFilter->add([
            //     'name' => 'importance',
            //     'required' => (!$partial) ? true : false,
            //     'allow_empty' => false,
            //     'validators' => [
            //         [
            //             'name' => 'InArray',
            //             'options' => [
            //                 'haystack' => [1, 2, 3],
            //             ],
            //             'default' => 0,
            //         ],
            //     ],
            // ]);

            $validatorsCode = [];
            if (!$partial) {
                $validatorsCode = [
                    [
                        'name' => 'Monarc\Core\Validator\UniqueCode',
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
