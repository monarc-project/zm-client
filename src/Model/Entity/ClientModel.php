<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2019  SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Model\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Model\Entity\AbstractEntity;
use Monarc\Core\Model\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Model\Entity\Traits\UpdateEntityTrait;

/**
 * ClientModel link between clients and models
 *
 * @ORM\Table(name="clients_models")
 * @ORM\Entity
 */
class ClientModel extends AbstractEntity
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="model_id", type="integer", nullable=true)
     */
    protected $modelId;

    /**
     * @ORM\ManyToOne(targetEntity="Monarc\FrontOffice\Model\Entity\Client")
     * @ORM\JoinColumns({
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id")
     * })
     */
    protected $client;

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function setModelId(int $modelId): self
    {
        $this->modelId = $modelId;
        return $this;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }
}
