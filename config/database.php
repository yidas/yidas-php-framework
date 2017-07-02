<?php 

/*
 * ======================================================================
 * Database Setting
 * ======================================================================
 *
 * Return the array consisted of databases data, each database consisted 
 * of array of configurations. The example data setting is below:
 *
    return array(
            'default' => array(
                'host'      => '10.1.2.1',
                'database'  => 'db_name',
                'username'  => 'db_username',
                'password'  => 'db_userpassword',
                'charset'   => 'utf8',
                'collation' => 'utf8_genera_ci',
                'prefix'    => '',
                'host_r'    => array('10.1.2.2','10.1.2.3')
                )
            );
 *
 */ 

# Development DB Environment
if( Config::get('env_dev','app')==true ) {

    return array(
        'default' => array(
            'host'      => 'localhost',
            'database'  => 'db_name',
            'username'  => 'db_username',
            'password'  => 'db_userpassword',
            'charset'   => 'utf8',
            'collation' => 'utf8_genera_ci',
            'prefix'    => '',
            'host_r'    => array()
            )
        );
} 

# Stage/Production DB Environment
else {

    return array(
        'default' => array(
            'host'      => 'localhost',
            'database'  => 'db_name',
            'username'  => 'db_username',
            'password'  => 'db_userpassword',
            'charset'   => 'utf8',
            'collation' => 'utf8_genera_ci',
            'prefix'    => '',
            'host_r'    => array()
            )
        );
}


?>