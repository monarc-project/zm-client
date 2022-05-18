<?php

use Phinx\Seed\AbstractSeed;

class AdminUserInit extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * http://docs.phinx.org/en/latest/seeding.html
     */
    public function run()
    {
        //create client
        $dataClient = [
            'creator' => 'System',
            'created_at' => date('Y-m-d H:i:s')
        ];
        $clientTable = $this->table('clients');
        $clientTable->insert($dataClient)
            ->save();

        //create first user
        $firstname = 'Admin';
        $lastname = 'Admin';
        $email = 'admin@admin.localhost';
        $password = 'admin';
        $dataUser = [
            'status' => 1,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'language' => 1,
            'creator' => 'System',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $userTable = $this->table('users');
        $userTable->insert($dataUser)
            ->save();

        //create user roles
        $pathFo = __DIR__."/../../config/module.config.php";
        if (file_exists($pathFo)) {
            $confFo = include $pathFo;
            if (!empty($confFo['roles'])) {
                $rows = $this->fetchRow('SELECT id FROM users where email LIKE \''.$email.'\' LIMIT 1');
                if (!empty($rows)) {
                    $posts = $this->table('users_roles');
                    foreach ($confFo['roles'] as $k => $v) {
                        $data = array(
                            'user_id' => $rows['id'],
                            'role' => $k,
                            'creator' => 'System',
                            'created_at' => date('Y-m-d H:i:s'),
                        );
                        $posts
                            ->insert($data)
                            ->save();
                    }
                }
            }
        }
    }
}
