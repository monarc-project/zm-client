<?php
namespace MonarcFO\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use MonarcCore\Model\Entity\AbstractEntity;

/**
 * Recommandation
 *
 * @ORM\Table(name="recommandations")
 * @ORM\Entity
 */
class Recommandation extends AbstractEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var \MonarcFO\Model\Entity\Anr
     *
     * @ORM\ManyToOne(targetEntity="MonarcFO\Model\Entity\Anr", cascade={"persist"})
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="anr_id", referencedColumnName="id", nullable=true)
     * })
     */
    protected $anr;

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


    // protected $parameters = [
    //     'implicitPosition' => [
    //         'field' => 'anr'
    //     ]
    // ];

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Asset
     */
    public function setId($id)
    {
        $this->id = $id;
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
        }

        return $this->inputFilter;
    }
}