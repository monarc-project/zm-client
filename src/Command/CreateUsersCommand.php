<?php

namespace Monarc\FrontOffice\Command;

use Monarc\FrontOffice\Model\Entity\User;
use Monarc\FrontOffice\Model\Entity\UserRole;
use Monarc\FrontOffice\Model\Table\UserTable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateUsersCommand extends Command
{
    private const DEFAULT_PASSWORD = 'Password1234!';

    protected static $defaultName = 'monarc:create-users';

    private $userTable;

    public function __construct(UserTable $userTable)
    {
        $this->userTable = $userTable;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument('numberOfUsers', InputArgument::REQUIRED, 'Number of users')
            ->addArgument('password', InputArgument::OPTIONAL, 'Password is set to each user', self::DEFAULT_PASSWORD)
            ->addArgument('namesPrefix', InputArgument::OPTIONAL, 'Names prefix', 'user_');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($userNum = 1; $userNum <= (int)$input->getArgument('numberOfUsers'); $userNum++) {
            $userNamePostfix = $userNum < 10 ? '0' . $userNum : $userNum;
            $usernamePrefix = $input->getArgument('namesPrefix') . $userNamePostfix;

            $user = new User([
                'firstname' => 'First name ' . $userNum,
                'lastname' => 'Last name ' . $userNum,
                'email' => $usernamePrefix . '@monarc.lu',
                'password' => self::DEFAULT_PASSWORD,
                'creator' => User::CREATOR_SYSTEM,
                'language' => 2,
                'role' => [UserRole::USER_FO],
            ]);

            $this->userTable->saveEntity($user);

            $output->writeln([
                'FirstName: ' . $user->getFirstname(),
                'LastName: ' . $user->getLastname(),
                'Email: ' . $user->getEmail()
            ]);
        }

        return 0;
    }
}
