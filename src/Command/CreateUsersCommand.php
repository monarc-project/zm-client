<?php

namespace Monarc\FrontOffice\Command;

use Monarc\FrontOffice\Entity\User;
use Monarc\FrontOffice\Entity\UserRole;
use Monarc\FrontOffice\Table\UserTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUsersCommand extends Command
{
    protected static $defaultName = 'monarc:create-users';

    private UserTable $userTable;

    public function __construct(UserTable $userTable)
    {
        $this->userTable = $userTable;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument('numberOfUsers', InputArgument::REQUIRED, 'Number of users')
            ->addArgument('password', InputArgument::REQUIRED, 'Password is set to each user')
            ->addArgument(
                'language',
                InputArgument::OPTIONAL,
                'Users language number 1(fr), 2(en)[default], 3(de), 4(dutch)',
                2
            )
            ->addArgument('namesPrefix', InputArgument::OPTIONAL, 'Names prefix', 'user_');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $usersLang = \in_array((int)$input->getArgument('language'), [1, 2, 3, 4], true)
            ? (int)$input->getArgument('language')
            : 2;

        for ($userNum = 1; $userNum <= (int)$input->getArgument('numberOfUsers'); $userNum++) {
            $userNamePostfix = $userNum < 10 ? '0' . $userNum : $userNum;
            $usernamePrefix = $input->getArgument('namesPrefix') . $userNamePostfix;

            $user = new User([
                'firstname' => 'First name ' . $userNum,
                'lastname' => 'Last name ' . $userNum,
                'email' => $usernamePrefix . '@monarc.lu',
                'password' => $input->getArgument('password'),
                'creator' => 'admin',
                'language' => $usersLang,
                'role' => [UserRole::USER_FO],
            ]);

            $this->userTable->save($user);

            $output->writeln([
                'FirstName: ' . $user->getFirstname(),
                'LastName: ' . $user->getLastname(),
                'Email: ' . $user->getEmail()
            ]);
        }

        return 0;
    }
}
