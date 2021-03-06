<?php
/**
 * @link      https://github.com/monarc-project for the canonical source repository
 * @copyright Copyright (c) 2016-2020 SMILE GIE Securitymadein.lu - Licensed under GNU Affero GPL v3
 * @license   MONARC is licensed under GNU Affero General Public License version 3
 */

namespace Monarc\FrontOffice\Service;

use Monarc\Core\Service\AbstractService;
use Monarc\FrontOffice\Model\Table\UserAnrTable;

/**
 * This class is the service that handles the user<->anr permissions matrix. This is a simple CRUD service.
 * @package Monarc\FrontOffice\Service
 */
class UserAnrService extends AbstractService
{
    protected $anrTable;
    protected $userTable;
    protected $dependencies = ['anr', 'user'];

    /**
     * Retrieves the user <-> ANR permissions matrix
     * @return array An array with the rights for each user
     */
    public function getMatrix()
    {
        //retieve matrix of rights (users and anrs)
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('table');
        $usersAnrs = $userAnrTable->fetchAllObject();

        $rights = [];
        foreach ($usersAnrs as $userAnr) {
            $rights[] = [
                'id' => $userAnr->id,
                'userId' => $userAnr->user->id,
                'firstname' => $userAnr->user->firstname,
                'lastname' => $userAnr->user->lastname,
                'email' => $userAnr->user->email,
                'anrId' => $userAnr->anr->id,
                'label1' => $userAnr->anr->label1,
                'label2' => $userAnr->anr->label2,
                'label3' => $userAnr->anr->label3,
                'label4' => $userAnr->anr->label4,
                'rwd' => $userAnr->rwd,
            ];
        }

        return $rights;
    }


    /**
     * @inheritdoc
     */
    public function create($data, $last = true)
    {
        //verify if this right not already exist
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('table');
        $userAnr = $userAnrTable->getEntityByFields(['user' => $data['user'], 'anr' => $data['anr']]);
        if (count($userAnr)) {
            throw new \Monarc\Core\Exception\Exception('This right already exist', 412);
        }

        return parent::create($data, $last);
    }

    /**
     * @inheritdoc
     */
    public function getEntity($id)
    {
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('table');
        $userAnr = $userAnrTable->get($id);

        return [
            'id' => $userAnr['id'],
            'userId' => $userAnr['user']->id,
            'firstname' => $userAnr['user']->firstname,
            'lastname' => $userAnr['user']->lastname,
            'email' => $userAnr['user']->email,
            'anrId' => $userAnr['anr']->id,
            'label1' => $userAnr['anr']->label1,
            'label2' => $userAnr['anr']->label2,
            'label3' => $userAnr['anr']->label3,
            'label4' => $userAnr['anr']->label4,
            'rwd' => $userAnr['rwd'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function patch($id, $data)
    {
        // Check if the permission already exists to avoid duplicates
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('table');
        $userAnr = $userAnrTable->get($id);
        if (
            ((isset($data['user'])) && ($data['user'] != $userAnr['user']->id))
            ||
            ((isset($data['anr'])) && ($data['anr'] != $userAnr['anr']->id))
        ) {
            $newUser = (isset($data['user'])) ? $data['user'] : $userAnr['user']->id;
            $newAnr = (isset($data['anr'])) ? $data['anr'] : $userAnr['anr']->id;

            $existingUserAnr = $userAnrTable->getEntityByFields(['user' => $newUser, 'anr' => $newAnr]);
            if (count($existingUserAnr)) {
                throw new \Monarc\Core\Exception\Exception('This right already exist', 412);
            }
        }

        parent::patch($id, $data);
    }
}
