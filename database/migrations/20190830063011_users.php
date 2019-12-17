<?php

use think\migration\Migrator;
use think\migration\db\Column;

class Users extends Migrator
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(){
        $table = $this->table('user');
        $table->addColumn('username' , 'string' , ['limit' => 30])
           ->addColumn('passwork' , 'string' , ['limit' => 32])
           ->addColumn('email' , 'string' , ['limit' => 25])
           ->addColumn('lastlogin_ip' , 'string' , ['limit' => 15])
           ->addTimestamps('create_time' , 'lastlogin_time')
           ->addColumn('status' , 'integer' , ['limit' => 1 , 'default' => 1])
           ->setId('user_id')
           ->save();
    }
}
