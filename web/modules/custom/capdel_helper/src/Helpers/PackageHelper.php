<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class PackageHelper
{
  public static function getPackageInfo(Node $package)
  {
    $referencedEntities = $package->field_package_entities->referencedEntities();
    if (count($referencedEntities) > 0) {
      if (isset($referencedEntities[0]) && $referencedEntities[0]->bundle() == 'event') {
        return self::getPackageInfoFromEvent($package);
      } else {
        return self::getPackageInfoFromConfiguration($package);
      }
    }
  }

  private static function getPackageInfoFromEvent(Node $package) {
    $additionalInfo = unserialize($package->field_package_info_json->getValue()[0]['value']);

    $event = $package->field_package_entities->referencedEntities()[0];
    $eventInfo = EventHelper::getEventInfo($event);

    return [
      'source' => 'event',
      'url' => $eventInfo['url'],
      'images' => $eventInfo['images'],
      'location' => $eventInfo['location'],
      'menu_type' => $eventInfo['menu_type'],
      'participants' => $additionalInfo['participants number'],
      'date' => $additionalInfo['date'],
      'resume' => []
    ];
  }

  private static function getPackageInfoFromConfiguration(Node $package)
  {
    $additionalInfo = unserialize($package->field_package_info_json->getValue()[0]['value']);

    $packageInfo = [
      'source' => 'configurator',
      'url' => '#',
      'images' => [],
      'location' => [],
      'id' => $package->id(),
    ];

    $packageInfo['participants'] = self::getAttributeFromSelectedValue($additionalInfo['selected values'], 'participant');
    $packageInfo['price'] = self::getAttributeFromSelectedValue($additionalInfo['selected values'], 'prix');
    $packageInfo['date'] = urldecode(self::getAttributeFromSelectedValue($additionalInfo['selected values'], 'format_date'));

    $place = self::getPlaceFromEntities($package);
    if ($place) {
      $packageInfo['images'] = ImagesHelper::getImages($place, 'field_conf_lieu_image');
      $packageInfo['locations'] = self::getLocationsFromPlace($place);
      $packageInfo['menu_type'] = TaxonomieHelper::getTopTaxonomieName($place, 'field_conf_lieu_menu_type');
    }
    return $packageInfo;
  }

  private static function getPlaceFromEntities(Node $package)
  {
    $referencedEntities = $package->field_package_entities->referencedEntities();
    foreach ($referencedEntities as $entity) {
      if ($entity->bundle() == 'conf_lieu') {
        return $entity;
      }
    }
  }

  private static function getLocationsFromPlace($node)
  {
    $locations = [];
    foreach($node->field_conf_lieu_dest->referencedEntities() as $destination) {
      $locations[] = $destination->getName();
    }
    return $locations;
  }

  private static function getAttributeFromSelectedValue($selectedValues, $attribute){
    foreach ($selectedValues as $selectedValue) {
      list($key, $value) = explode('=', $selectedValue);
      if ($key == $attribute) {
        return $value;
      }
    }
  }

}
