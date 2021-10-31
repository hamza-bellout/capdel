<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 07.05.2018
 * Time: 13:24
 */

namespace Drupal\capdel_legacy_importer\Entity;

// FIXME: Rename it to ContentType interface and add missing methods implemented in the Event class
// This fix will allow to create new ContentType migration easily by implementing inteface

/**
 * Interface EventInterface
 *
 * @package Drupal\capdel_legacy_importer\Entity
 */
interface EventInterface
{

    /**
     * Get fields assigned to the event entity in the BO.
     * @return mixed
     */
    public function getFields();

    /**
     * Main function to build the Event Node from the connection to the legacy DB.
     * @return mixed
     */
    public function buildNode();

}