<?php

// Chargement conf global;
$pathGlobal = getcwd()."/config/autoload/global.php";
$globalConf = array();
if (file_exists($pathGlobal)) {
    $globalConf = require $pathGlobal;
}
// Chargement conf local;
$pathLocal = getcwd()."/config/autoload/local.php";
$localConf = array();
if (file_exists($pathLocal)) {
    $localConf = require $pathLocal;
}
$paramToUse = 'orm_cli';
$globalConf = array_replace_recursive($globalConf, $localConf);
if (empty($localConf['doctrine']['connection'][$paramToUse]['params'])) {
    $paramToUse = 'orm_default';
    if (empty($localConf['doctrine']['connection'][$paramToUse]['params'])) {
        die("Connection parameters not configured");
    }
}

return array(
    'paths' => array(
        'migrations' => __DIR__.'/db',
        'seeds' => __DIR__.'/seeds',
    ),
    'environments' => array(
        'default_migration_table' => 'phinxlog',
        'default_database' => 'cli',
        'cli' => array(
            'adapter' => 'mysql',
            'host' => $globalConf['doctrine']['connection'][$paramToUse]['params']['host'],
            'name' => $globalConf['doctrine']['connection'][$paramToUse]['params']['dbname'],
            'user' => $globalConf['doctrine']['connection'][$paramToUse]['params']['user'],
            'pass' => $globalConf['doctrine']['connection'][$paramToUse]['params']['password'],
            'port' => $globalConf['doctrine']['connection'][$paramToUse]['params']['port'],
            'charset' => 'utf8',
        ),
    ),
);
