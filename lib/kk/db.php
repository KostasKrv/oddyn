<?php
namespace kk\OddsMaster;

class DataBase {
    const DB_HOST = 'localhost';
    const DB_NAME = 'kino';
    const DB_PORT = 3306;
    const DB_USERNAME = 'kino';
    const DB_PASSWORD = 'kino';
    const DB_CHARSET = 'utf8mb4';

    static function getInstance() {
        global $DB;

        if ($DB === null) {
            $DB = DataBase::getNewConnection();
        }
                
        return $DB;
    }

    static function getNewConnection() {
        
        $dataSource = new \Delight\Db\PdoDataSource('mysql');
        $dataSource->setHostname(DataBase::DB_HOST);
        $dataSource->setPort(DataBase::DB_PORT);
        $dataSource->setDatabaseName(DataBase::DB_NAME);
        $dataSource->setCharset(DataBase::DB_CHARSET);
        $dataSource->setUsername(DataBase::DB_USERNAME);
        $dataSource->setPassword(DataBase::DB_PASSWORD);

        $db = \Delight\Db\PdoDatabase::fromDataSource($dataSource);
        return $db;
    }
}

global $DB;