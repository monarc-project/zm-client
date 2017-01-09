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
        $pathLocal = getcwd()."/config/autoload/local.php";
        $localConf = array();
        if(file_exists($pathLocal)){
            $localConf = require $pathLocal;
        }
        $salt = "";
        if(!empty($localConf['monarc']['salt'])){
            $salt = $localConf['monarc']['salt'];
        }

        $email = 'admin@netlor.fr';

        $data = array(
            'status' => 1,
            'firstname' => 'Admin',
            'lastname' => 'Admin',
            'email' => $email,
            'password' => password_hash($salt.$email,PASSWORD_BCRYPT),
            'language' => 1,
            'creator' => 'System',
            'created_at' => date('Y-m-d H:i:s'),
        );

        $posts = $this->table('users');
        $posts->insert($data)
              ->save();

        $pathFo = __DIR__."/../../config/module.config.php";
        if(file_exists($pathFo)){
            $confFo = include $pathFo;
            if(!empty($confFo['roles'])){
                $rows = $this->fetchRow('SELECT id FROM users where email LIKE \''.$email.'\' LIMIT 1');
                if(!empty($rows)){
                    $posts = $this->table('users_roles');
                    foreach($confFo['roles'] as $k => $v){
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
