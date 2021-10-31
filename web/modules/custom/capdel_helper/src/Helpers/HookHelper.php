<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 23.07.2018
 * Time: 12:16
 */

namespace Drupal\capdel_helper\Helpers;


use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Cache\Cache;

class HookHelper
{

    /**
     * entity presave hook
     *
     * This is triggered before saving of each entity. Anything is entity in Drupal - be carefull
     * with great power comes great responsibility
     * @param \Drupal\Core\Entity\EntityInterface $entity
     */
    static function capdel_helper_entity_presave(\Drupal\Core\Entity\EntityInterface $entity) {

      // IMPORTANT: There was an issue with data stored in cache_entity table. Cached data could contain old value.
      //            Here we implicitly invalidate certain tags to override that issue.
      $tags = $entity->getCacheTags();
      array_push($tags, 'entity_field_info');
      array_push($tags, 'node_values');
      array_push($tags, 'workspace_1');
      Cache::invalidateTags($tags);

      switch ($entity->bundle()) {
            case 'landing_page':
                self::setLandingPageUrlAlias($entity);
                break;
        }
    }

    private static function setLandingPageUrlAlias(Node $node) {
        $orderNo = 1;
        $referencedTaxonomyId = false;

        $nodePath = $node->toArray()['path'][0];

        //if admin wants to set custom URL then rely on core Drupal alias management
        // just delete 'undefined' langcode alias version created by us
        if($node->get('field_lp_custom_url') != null && $node->get('field_lp_custom_url')->getValue()[0]['value'] != false){
          \Drupal::service('path.alias_storage')->delete(array('source' => "/node/" . $node->id(), 'langcode' => 'und'));
          //TODO: handle 'undefined' custom langcode alias
          return;
        }

        if($node->get('field_lp_order') != null) {
            $orderNo = $node->get('field_lp_order')->getValue()[0]['value'];
        }

        if($node->get('field_lp_tax_ref') != null) {
            $referencedTaxonomyId = $node->get('field_lp_tax_ref')->getValue()[0]['target_id'];
        }

        if($referencedTaxonomyId) {
            $term = Term::load($referencedTaxonomyId);
            if($term) {
                $name = self::slugify($term->getName());
                if($name) {
                    if($orderNo > 1) {
                        $currentUrl = $name . '-' . $orderNo;
                    }
                    else {
                        $currentUrl = $name;
                    }

                    if($node->nid->value != null) {
                        if(isset($nodePath['alias']) && $nodePath['pid'] != null && $nodePath['alias'] != $currentUrl) {
                            \Drupal::service('path.alias_storage')->delete(array('pid' => $nodePath['pid']));
                            if(! \Drupal::service('path.alias_storage')->aliasExists($currentUrl,'fr') &&
                              ! \Drupal::service('path.alias_storage')->aliasExists($currentUrl,'und')
                            ) {
                                \Drupal::service('path.alias_storage')->save("/node/" . $node->nid->value, "/".$currentUrl, "und");
                                \Drupal::service('path.alias_storage')->save("/node/" . $node->nid->value, "/".$currentUrl, "fr");
                            }
                        }

                        if(!isset($nodePath['alias']) || (isset($nodePath['alias']) && $nodePath['alias'] == null)) {
                            if(! \Drupal::service('path.alias_storage')->aliasExists($currentUrl,'fr') &&
                              ! \Drupal::service('path.alias_storage')->aliasExists($currentUrl,'und')
                            ) {
                                \Drupal::service('path.alias_storage')->save("/node/" . $node->nid->value, "/".$currentUrl, "und");
                                \Drupal::service('path.alias_storage')->save("/node/" . $node->nid->value, "/".$currentUrl, "fr");
                            }
                        }
                    }
                }

            }
        }
    }

    private static function slugify($string, $replace = array(), $delimiter = '-')
    {
        if(!$string) {
            return false;
        }
        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        if (!empty($replace)) {
            $clean = str_replace((array) $replace, ' ', $clean);
        }
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
        $clean = strtolower($clean);
        $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
        $clean = trim($clean, $delimiter);
        // Revert back to the old locale
        setlocale(LC_ALL, $oldLocale);
        return $clean;
    }

    /**
     * Helper function to get values from paragraph entity.
     * If the paragraph is saved we only get target_id and rev_target_id, so
     * there is a need to load the paragraph entity before pulling out
     * the fields and their values.
     *
     * @param $paragraphsArray
     * @param bool $loadEntity
     *
     * @return array
     */
    private static function getMenuValuesFromParagraphType($paragraphsArray, $loadEntity=false) {
        $valuesArray = [];
        foreach($paragraphsArray as $paragraph) {
            //if we need to load the paragraph entity first to get saved values..
            if($loadEntity) {
                $parEntity = entity_revision_load('paragraph',$paragraph['target_revision_id']);
                if($parEntity->field_par_ref_statics) {
                    if($parEntity->field_par_ref_statics->getValue()) {
                        $valuesArray[] = $parEntity->field_par_ref_statics->getValue()[0];
                    }
                }
            }else {
                if(isset($paragraph['subform'])) {
                    $valuesArray[] =
                      $paragraph['subform']['field_par_ref_statics'][0];
                }
            }
        }

        if(\count($valuesArray) > 0) {
            return $valuesArray;
        }
        return false;
    }

    /**
     * Helper function to get the values from entity and previous ones (before save)
     * - from entity->original. If no original property - the node is created not updated
     * @param \Drupal\node\Entity\Node $entity
     * @param $fieldName
     *
     * @return array
     */
    private static function buildUpdateArrayResponse (Node $entity, $fieldName) :array {
        $post = self::getMenuValuesFromParagraphType($entity->{$fieldName}->getValue());
        // property_exist does not get the values set by __get methods. Lame
        if(isset($entity->original)) {
            $pre = $entity->original->{$fieldName};
            if($pre !== null) {
                $pre = self::getMenuValuesFromParagraphType($pre->getValue(),true);
            }
        }
        else {
            $pre = false;
        }
        return self::setUpdateArray($pre,$post);
    }

    /**
     * Helper function to get the values from entity and previous ones (before save)
     * - from entity->original. If no original property - the node is created not updated
     * @param \Drupal\node\Entity\Node $entity
     * @param $fieldName
     *
     * @return array
     */
    private static function buildUpdateArrayResponseTB (Node $entity, $fieldName) :array {
        if($entity->{$fieldName} != null) {
            $post = $entity->{$fieldName}->getValue();
        }
        else {
            $post = false;
        }
        // property_exist does not get the values set by __get methods. Lame
        if(isset($entity->original) && $entity->original->{$fieldName} != null) {
            $pre = $entity->original->{$fieldName}->getValue();
        }
        else {
            $pre = false;
        }
        return self::setUpdateArray($pre,$post);
    }

    /**
     * Build update array used in updateNodeReference function
     * @param $pre
     * @param $post
     *
     * @return array
     */
    private static function setUpdateArray($pre, $post) : array {
        $updateArray = [];

        $pre = self::normalizeRefArray($pre);
        $post = self::normalizeRefArray($post);

        // handle complete remove
        if(empty($post)) {
            return [
              'add' => [],
              'remove' => $pre,
            ];
        }
        // handle empty previous state
        if(empty($pre)) {
            return [
              'add' => $post,
              'remove' => [],
            ];
        }

        // handle new nodes to add
        $updateArray['add'] = $post;

        // handle obsolete nodes
        $updateArray['remove'] = array_diff($pre , $post);

        return $updateArray;
    }

    /**
     * Helper function to build update array processed in the next steps
     * @param $entityId
     * @param $updateList
     * @param $referenceField
     */
    private static function handleNodesUpdate($entityId, $updateList, $referenceField) {
        foreach ($updateList['add'] as $nid) {
            self::updateNodeReference($entityId,$nid,$referenceField,false);
        }
        foreach ($updateList['remove'] as $nid) {
            self::updateNodeReference($entityId,$nid,$referenceField,true);

        }
    }

    /**
     * Helper function that loads the dependent entities and sets them the reference in referenceField
     * @param $entityId
     * @param $nid
     * @param $referenceField
     * @param bool $remove
     */
    private static function updateNodeReference($entityId, $nid, $referenceField, $remove=false) {
        $node = Node::load($nid);
        if($node && $node->{$referenceField} !== null) {
            $refArray = $node->{$referenceField}->getValue();

            $refArray = self::normalizeRefArray($refArray);
            if($remove) {
                if(false !== $key = array_search($entityId, $refArray)) {
                    unset($refArray[$key]);
                }
            }
            else {
                if(!in_array($entityId,$refArray)) {
                    $refArray[] = $entityId;
                }
            }

            $refArray = self::deNormalizeRefArray($refArray);
            $node->set($referenceField,$refArray);
            $node->save();
        }
    }

    /**
     * Helper function to parse the array from [0][target_id] format (Drupal entity Reference) to normal array
     * @param $array
     *
     * @return array
     */
    public static function normalizeRefArray ($array) {
        if(!$array || \count($array) == 0) {
            return [];
        }
        $returnArray = [];
        foreach($array as $row) {
            if(key_exists('target_id',$row)) {
                $returnArray[] = $row['target_id'];
            }
        }
        return $returnArray;
    }

    /**
     * Helper function to parse normal array and transform it into Drupal entity Reference format
     * @param $array
     *
     * @return array
     */
    private static function deNormalizeRefArray ($array) {
        $returnArray = [];
        foreach($array as $row) {
            $returnArray[]['target_id'] = $row;
        }
        return $returnArray;
    }
}
