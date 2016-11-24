<?php
namespace MonarcFO\Service;

use MonarcCore\Service\AbstractService;
use MonarcFO\Model\Table\UserAnrTable;

class UserAnrService extends AbstractService
{
    protected $anrTable;
    protected $userTable;

    protected $dependencies = ['anr', 'user'];

    public function getMatrix() {
        /** @var UserAnrTable $userAnrTable */
        $userAnrTable = $this->get('table');
        $usersAnrs = $userAnrTable->fetchAllObject();

        $rights = [];
        foreach($usersAnrs as $userAnr) {
            $rights[] = [
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
}