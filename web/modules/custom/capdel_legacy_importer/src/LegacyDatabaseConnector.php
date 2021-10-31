<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 07.05.2018
 * Time: 08:21
 */

namespace Drupal\capdel_legacy_importer;

/*
 *  Manages the connection to the external, legacy database to import entities from.
 */
use Drupal\Core\Database\Database;

/**
 * Class LegacyDatabaseConnector
 *
 * @package Drupal\capdel_legacy_importer
 */
class LegacyDatabaseConnector
{

    /**
     * @var \Drupal\Core\Database\Connection
     */
    private $db;
    /**
     * LegacyDatabaseConnector constructor.
     * Sets the db connection to legacy DB using .env params
     */
    public function __construct()
    {
        $legacyDbSettings = [
          'database' => getenv('LEGACY_DB'),
          'driver' => 'mysql',
          'host' => getenv('LEGACY_HOSTNAME') ?? 'localhost',
          'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
          'password' => getenv('LEGACY_PASSWORD'),
          'port' => getenv('LEGACY_PORT') ?? 3306,
          'prefix' => '',
          'username' => getenv('LEGACY_USER')
        ];

        Database::addConnectionInfo('legacy_db', 'default', $legacyDbSettings);
        $this->db = Database::getConnection('default','legacy_db');
    }

    /**
     * LegacyDatabaseConnector destructor.
     * Sets the db connection back to default one.
     *
     */
    public function __destruct()
    {
        //set db back to default during destruct.
        db_set_active();
    }

    /**
     * Helper function to get sample categories from the legacy DB
     * @return mixed
     */
    public function getCategories() {

        $query = $this->db->select('categories','cat')
          ->fields('cat')
          ->execute();

        return $query->fetchAll();
    }

    public function getPackagesIds() {
        $query = $this->db->select('packages','pkg')
          ->fields('pkg',['id'])
          ->execute();

        return $query->fetchAll();
    }

    public function getLanguages() {
        $query = $this->db->select('langues','l')
          ->fields('l')
          ->execute();

        return $query->fetchAll();
    }

    public function getTable($name,$fields=null) {
        $query = $this->db->select($name,'table_name')
          ->fields('table_name');

        if($fields) {
            $query->fields('table_name',$fields);
        }

        $query = $query->execute();
        return $query->fetchAll();
    }

    public function getRowFromTableByField($name,$fieldName, $value) {
        $query = $this->db->select($name,'table_name')
          ->fields('table_name')
          ->condition('table_name.'.$fieldName, $value,'=');

        $query = $query->execute();
        return $query->fetchAll();
    }

    public function getRowFromTableByFieldWhereLang($name,$fieldName, $value) {
        $query = $this->db->select($name,'table_name')
          ->fields('table_name')
          ->condition('table_name.'.$fieldName, $value,'=')
          ->condition('table_name.'.'id_langues', 1,'=');

        $query = $query->execute();
        return $query->fetchAll();
    }








}