<?php

namespace Drupal\capdel_helper\Helpers;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class ConfigurateurHelper
{
  public static function getEntityInfo($node, $referencedNodes=null) {
      if($node && $node->bundle() == 'conf_lieu') {
          $item = [
            'category' => [],
            'configuration' => [],
            'description' => [],
            'short_description' => [],
            'locations' => [],
            'destination' => [],
            'details' => [],
            'format' => [],
            'price' => [],
            'type' => [],
            'images' => [],
            'max_par' => [],
            'min_par' => [],
            'avail' => [],
            'coordinates' => [],
            'bundle' => $node->bundle(),
            'distances' => [],
          ];

          $item['images'] = self::getImages($node,'field_conf_lieu_image');
          $item['price'] = $node->field_conf_price->value;
          $item['description'] = new FormattableMarkup($node->field_conf_lieu_desc->value, []);
          $item['short_description'] = new FormattableMarkup($node->field_conf_lieu_short_desc->value, []);
          $item['locations'] = $node->field_conf_lieu_geo_params->value;
          $item['max_par'] = $node->field_conf_lieu_max_par->value;
          $item['min_par'] = $node->field_conf_lieu_min_par->value;
          $item['coordinates'] = $node->field_conf_lieu_geo_params->value;

          $item['category'] = self::getReferencedEntityName($node,'field_conf_lieu_category');
          $item['configuration'] = self::getReferencedEntityName($node,'field_conf_lieu_tax_conf');
          $item['destination'] = self::getReferencedEntityName($node,'field_conf_lieu_dest');
          $item['details'] = self::getReferencedEntityName($node,'field_conf_lieu_tax_details');
          $item['format'] = self::getReferencedEntityName($node,'field_conf_lieu_format');
          $item['type'] = self::getReferencedEntityName($node,'field_conf_lieu_type');
          $item['avail'] = self::getReferencedEntityName($node,'field_conf_lieu_avail');
          $item['distances'] = self::getDistancesArray($node,'field_conf_lieu_par_geo_dist','tb');

          return $item;
      }

      if($node && $node->bundle() == 'configurateur_statics') {
          $item = [
            'description' => [],
            'short_description' => [],
            'drink_type' => [],
            'menu_type' => [],
            'is_menu_item' => [],
            'is_option_item' => [],
            'is_countable' => [],
            'images' => [],
            'price' => [],
            'bundle' => $node->bundle(),
          ];

          $item['images'] = self::getImages($node,'field_conf_static_image');
          $item['description'] = new FormattableMarkup($node->field_conf_static_desc->value, []);
          $item['short_description'] = new FormattableMarkup($node->field_conf_static_short_desc->value, []);
          $item['drink_type'] = (int) $node->field_conf_static_is_drink_item->value;
          $item['menu_type'] = (int) $node->field_conf_static_is_menu_item->value;
          $item['is_menu_item'] = (int) $node->field_conf_static_is_menu->value;
          $item['is_option_item'] = (int) $node->field_conf_static_is_option->value;
          $item['is_countable'] = (int) $node->field_conf_static_is_countable->value;

          $item['price'] = false;
          if($item['is_menu_item'] && $referencedNodes !== null) {
            //get the value from the place entity for this particular menu
            $item['price'] = self::getMenuPricingFromPlace($referencedNodes,$node->id());
            $item['default_price'] = (float)$node->field_conf_static_price->value;
          }

          if ($item['price'] === false) {
              $item['price'] = (float)$node->field_conf_static_price->value;
              $item['default_price'] = (float)$node->field_conf_static_price->value;
          }
          return $item;
      }

      if($node && $node->bundle() == 'configurateur_tb_and_anim') {
          $item = [
            'added_value' => [],
              'avail' => [],
            'category' => [],
            'subcategory' => [],
            'description' => [],
            'short_description' => [],
            'duration' => [],
            'locations' => [],
            'images' => [],
              'max_par' => [],
              'min_par' => [],
            'destination' => [],
            'price' => [],
            'bundle' => $node->bundle(),
          ];


          $item['added_value'] = new FormattableMarkup($node->field_conf_tb_add_val->value, []);
          $item['avail'] = self::getReferencedEntityName($node,'field_conf_tb_avail');
            $item['category'] = self::getReferencedEntityName($node,'field_conf_tb_category');
            $item['subcategory'] = self::getReferencedEntityName($node,'field_conf_tb_cat_sub');
            $item['description'] = new FormattableMarkup($node->field_conf_tb_desc->value, []);
            $item['short_description'] = new FormattableMarkup($node->field_tb_short_desc->value, []);
            $item['duration'] = $node->field_conf_tb_duration->value;
            $item['locations'] = $node->field_conf_tb_geo_params->value;
            $item['images'] = self::getImages($node,'field_conf_tb_image');
              $item['max_par'] = $node->field_conf_tb_max_par->value;
              $item['min_par'] = $node->field_conf_tb_min_par->value;
            $item['destination'] = self::getReferencedEntityName($node,'field_conf_tb_destination');
            $item['price'] = $node->field_conf_tb_price->getValue();
          $item['distances'] = self::getDistancesArray($node,'field_tb_par_geo_dist','place');

          return $item;
      }

      if($node && $node->bundle() == 'configurateur_animations') {
          $item = [
            'added_value' => [],
            'avail' => [],
            'category' => [],
            'description' => [],
            'short_description' => [],
            'duration' => [],
            'locations' => [],
            'images' => [],
            'max_par' => [],
            'min_par' => [],
            'destination' => [],
            'price' => [],
            'bundle' => $node->bundle(),
          ];


          $item['added_value'] = new FormattableMarkup($node->field_conf_anim_add_val->value, []);
          $item['avail'] = self::getReferencedEntityName($node,'field_conf_anim_avail');
          $item['category'] = self::getReferencedEntityName($node,'field_conf_anim_category');
          $item['description'] = new FormattableMarkup($node->field_conf_anim_desc->value, []);
          $item['short_description'] = new FormattableMarkup($node->field_conf_anim_short_desc->value, []);
          $item['duration'] = $node->field_conf_anim_duration->value;
          $item['locations'] = $node->field_conf_anim_geo_params->value;
          $item['images'] = self::getImages($node,'field_conf_anim_image');
          $item['max_par'] = $node->field_conf_anim_max_par->value;
          $item['min_par'] = $node->field_conf_anim_min_par->value;
          $item['destination'] = self::getReferencedEntityName($node,'field_conf_anim_place');
          $item['price'] = $node->field_conf_anim_price->getValue();

          return $item;
      }

      return null;
  }

    /**
     * Helper method to get proper pricing for the menu item.
     * It loads up the Place entity. Unfortunatelly the place ent keeps the
     * references to the paragraph items, not the values, so there is need
     * to load each entity and check the match.
     *
     * @param $placeId
     * @param $menuId
     *
     * @return mixed
     */private static function getMenuPricingFromPlace($placeId, $menuId) {
      $placeNode = Node::load($placeId);
      $paragraphsValue = $placeNode->field_conf_lieu_ref_par_menu->getValue();
      foreach ($paragraphsValue as $paragraphArray) {
          $par = Paragraph::load($paragraphArray['target_id']);
          if($par->field_par_ref_statics !== null && $par->field_par_ref_statics->getValue()[0]['target_id'] === $menuId) {
              if($par->field_par_menu_price !== null) {
                  //break the foreach if found
                  return $par->field_par_menu_price->getValue();
              }
              //break the foreach if found and empty
              return false;
          }
      }
      return false;
  }

  private static function getReferencedEntityName($node,$field) {

      $refEntities = $node->{$field}->referencedEntities();
      if(!empty($refEntities)) {
          return $node->{$field}->referencedEntities()[0]->getName();
      }
      return null;
  }


  public static function getImages($node, $field, $style=['search', 'option']) {
    $images = [];

    $relatedImages = $node->{$field}->referencedEntities();
    foreach($relatedImages as $relatedImage) {
      $image = [];

      $relatedTitle = $relatedImage->field_media_image_title->getValue();
      $relatedAlt = $relatedImage->field_media_image->getValue();

      ($relatedTitle != null) ?
        $image['title'] = $relatedImage->field_media_image_title->getValue()[0]['value'] :
        $image['title'] = '';

      ($relatedAlt != null) ?
        $image['alt'] = $relatedImage->field_media_image->getValue()[0]['alt'] :
        $image['alt'] = '';

      $urls = self::getImageUrls($relatedImage,$style);
      $image['url'] = $urls;
      $images[] = $image;
    }

    return $images;
  }

    private static function getImageUrls($image, $style=['search', 'option']) {
        $urls = [];

        $targetId = $image->field_media_image->getValue()[0]['target_id'];
        $file = File::load($targetId);
        $url = file_create_url($file->getFileUri());
        $urls['full'] = $url;

        return array_merge($urls, self::getStyleImageUrls($file, $style));
    }

    private static function getStyleImageUrls(File $file, array $styles){
        $urls = [];
        foreach($styles as $styleName){
            $style = ImageStyle::load($styleName);
            $search = $style->buildUrl($file->getFileUri());
            $urls[$styleName] = $search;
        }
        return $urls;
    }

    public static function removeValueFromHeader($header,$counter) {
        $price = (int)filter_var(strip_tags($header['result']['#markup']), FILTER_SANITIZE_NUMBER_INT);
        $updatedValue = $price + $counter;
        $header['result']['#markup'] = str_replace($price.' ',$updatedValue.' ',$header['result']['#markup']);
        return $header;
    }

    public static function getNodeAlias($id) {
         return \Drupal::service('path.alias_manager')->getAliasByPath('/node/'.$id);
    }

    public static function getDistancesArray($node, $field, $type='place') : array {
         $fieldValue = $node->{$field};
         if($fieldValue == null) {
             return [];
         }
         $distanceNodeIds = self::normalizeRefArray($fieldValue->getValue());

         $distanceTable = [];
         $loadedNodes = Node::loadMultiple($distanceNodeIds);

        foreach ($loadedNodes as $disNode) {
            $id = $disNode->get('field_par_lieu_tb_geo_lieu')->getValue();
            if($type === 'tb') {
                $id = $disNode->get('field_par_lieu_tb_geo_tb')->getValue();
            }
            $distance = $disNode->get('field_par_lieu_tb_geo_dist')->getValue();

            if(isset($id[0]['target_id']) && isset($distance[0]['value'])) {
                $distanceTable[$id[0]['target_id']] = $distance[0]['value'];
            }
        }

        return $distanceTable;
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
}
