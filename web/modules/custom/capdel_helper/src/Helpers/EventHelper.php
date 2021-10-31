<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\capdel_helper\Controller\PriceWizardController;
use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class EventHelper
{
  public static function getEventInfo($node, $prefferedMenuType = null, $addRequestParams = false, $removeQuery = false) {

    $event = [
      'locations' => [],
      'coordinates' => [],
      'participants_range' => [],
      'availability_range' => []
    ];

    if($prefferedMenuType == null && array_key_exists('Drupal_visitor_event_type', $_COOKIE)) {
      $prefferedMenuType = $_COOKIE['Drupal_visitor_event_type'];
    }

    $variants = self::getVariants($node);
    if(!empty($variants)) {
      $event = [];
      $event['locations'] = self::getLocations($variants);
      $event['coordinates'] = self::getCoordinates($variants);
      $event['participants_range'] = self::getParticipantsRange($variants);
      $event['availability_range'] = self::getAvailabilityRange($variants);
      $event['price'] = self::getMinPrice($variants);
      $event['all_prices'] = self::getAllPricesFromVariants($variants);
      $event['pax_groups'] = self::getPaxGroups($variants);
      $event['languages'] = self::getLanguages($variants);
    }

    $event['summary'] = $node->field_event_description->summary;
    $event['url'] = $node->toUrl()->toString();
    $event['menu_type'] = TaxonomieHelper::getTopTaxonomieName($node, 'field_event_tax_menu_types', $prefferedMenuType);
    $event['menu_types'] = self::getMenuTypes($node);
    $event['search_filters'] = self::getSearchFilters($node);
    $event['duration_type'] = self::getDurationType($node);
    $event['related_events'] = self::getRelatedEvents($node);
    $event['images'] = ImagesHelper::getImages($node, 'field_event_image');
    $event['meta'] = self::getMeta($node);

    if($addRequestParams) {
      $request = \Drupal::request();
      $event['get_params'] = $request->query->all();
    }

    return $event;
  }

  //Renders the fav icon using the flag module logic
  public static function getFavLink($node) {
      $flag_link = [
          '#lazy_builder' => ['flag.link_builder:build', [
            $node->getEntityTypeId(),
            $node->id(),
            'bookmark',
          ]],
          '#create_placeholder' => false,
        ];
        return render($flag_link);
  }

  private static function getDurationType($node) {
    $durationTypes = $node->field_event_tax_duration_type->referencedEntities();
    if(!empty($durationTypes)) {
      if(!empty($node->field_event_duration_max->getValue()))
      return PluralHelper::parse($durationTypes[0]->getName(), $node->field_event_duration_max->getValue()[0]['value']);
    }
  }

  private static function getVariants($node) {
    return $node->field_event_ref_variant->referencedEntities();
  }

  public static function getUniqueVariantLocations(array $variants){
    $selected_variants = [];

    //sort variants by id in case multiple variants have the same location
    usort($variants, function($a, $b){
      return ($a->id() < $b->id()) ? -1 : 1;
    });

    if (!empty($variants)) {
      foreach ($variants as $variant) {
        $locationKey = '';
        $city = $variant->field_event_sub_city->getValue();
        if($city && isset($city[0])) {
            $city = $variant->field_event_sub_city->getValue()[0]['value'];
            if (!empty($city)) {
                $locationKey .= $city;
            }
        }

        $address = $variant->field_event_sub_address->getValue();
        if($address && isset($address[0])) {
          $address = $variant->field_event_sub_address->getValue()[0]['value'];
          if (!empty($address)) {
            $locationKey .= '_' . $address;
          }
        }

        if(!array_key_exists($locationKey, $selected_variants)) {
            $selected_variants[$locationKey] = $variant;
        }
      }
    }

    return $selected_variants;
  }

  private static function getMinPrice(array $variants) {
    $request = \Drupal::request();

    $uniqueVariantLocations = self::getUniqueVariantLocations($variants);

    if(!empty($uniqueVariantLocations)) {
      $min_price = null;
      foreach($uniqueVariantLocations as $variant) {
        $pax_prices = $variant->field_event_sub_price_ppmin->getValue();

        if(!empty($request->get('participant')) &&
          $request->get('participant') != 'All' &&
          array_key_exists($request->get('participant') - 1, $pax_prices)) {
          $price = $pax_prices[$request->get('participant') - 1]['value'];
          if (!empty($price) && (empty($min_price) || $price < $min_price)) {
            $min_price = $price;
          }
        } else {
          foreach($pax_prices as $price) {
            if(!empty($price['value']) && (empty($min_price) || $price['value'] < $min_price))
              $min_price = $price['value'];
          }
        }
      }
      return $min_price;
    }
  }

    private static function getAllPricesFromVariants(array $variants) {
        $uniqueVariantLocations = self::getUniqueVariantLocations($variants);

        if(!empty($uniqueVariantLocations)) {
            $min_price = [];
            foreach($uniqueVariantLocations as $variant) {
              $pax_prices = $variant->field_event_sub_price_ppmin->getValue();
              if(count($pax_prices) == 1) {
                for($i = 0; $i < PriceWizardController::VARIANT_PRICES_COUNT; $i++){
                  $min_price[$i] = $pax_prices[0]['value'];
                }
              } else {
                foreach ($pax_prices as $key => $price) {
                  if (!empty($price['value']) && (empty($min_price[$key]) || $price['value'] < $min_price[$key]))
                    $min_price[$key] = $price['value'];
                }
              }
            }

            return $min_price;
        }
    }

  private static function getPaxGroups(array $variants)
  {
    $prices = [];

    $uniqueVariantLocations = self::getUniqueVariantLocations($variants);
    if(!empty($uniqueVariantLocations)) {
      foreach ($uniqueVariantLocations as $variant) {
        $pax_prices = $variant->field_event_sub_price_ppmin->getValue();

        if(count($pax_prices) <= 1) {
          for($i = 0; $i < PriceWizardController::VARIANT_PRICES_COUNT; $i++){
            $prices[$i] = true;
          }
        } else {
          foreach ($pax_prices as $idx => $price) {
            if (empty($prices[$idx])) {
              $prices[$idx] = !empty($price['value']);
            }
          }
        }
      }
    }
    return $prices;
  }

  private static function getLocations(array $variants) {
    $locations = [];

    if(!empty($variants)) {
      foreach($variants as $variant) {
        $location = $variant->field_event_sub_city->getValue()[0]['value'];
        if(!empty($location))
          $locations[] = $location;
      }
    }

    return array_unique($locations);
  }

  private static function getCoordinates(array $variants) {
    $coords = [];

    if(!empty($variants)) {
      foreach($variants as $variant) {
        $displayLocation = $variant->field_event_sub_google_maps_visi->getValue()[0]['value'];
        if(!empty($variant->field_event_sub_geo_params->getValue()) && $displayLocation == "1") {
          $coord = $variant->field_event_sub_geo_params->getValue()[0]['value'];
          if (!empty($coord))
            $coords[] = $coord;
        }
      }
    }

    return array_unique($coords);
  }

  private static function getLanguages(array $variants) {
    $languages = [];

    if(!empty($variants)) {
      foreach($variants as $variant) {
        $languageTaxonomies = $variant->field_event_sub_tax_lang->referencedEntities();
        if(!empty($languageTaxonomies)) {
          $language = $languageTaxonomies[0]->getName();
          if(!empty($language)) {
            $languages[] = $language;
          }
        }
      }
    }

    return array_unique($languages);
  }

  /**
   * Get unique paricipant ranges array
   * @param array $variants
   * @return array
   */
  private static function getParticipantsRange(array $variants) {
    $participants_range = [];
    $participants_min = $participants_max = null;

    $range_variants = [];
    if(!empty($variants)) {
      foreach ($variants as $variant) {
        $min = $variant->field_event_sub_participants_min->getValue()[0]['value'];
        $max = $variant->field_event_sub_participants_max->getValue()[0]['value'];
        $range_variants["$min:$max"] = $variant->id();
      }

      //get unique min-max values and sort them in ascending order
      $unique_ranges = array_unique(array_keys($range_variants));
      usort($unique_ranges, function($a, $b){
        list($min_a, $max_a) = explode(':', $a);
        list($min_b, $max_b) = explode(':', $a);

        if($min_a != $min_b) {
          return ($min_a < $min_b) ? -1 : 1;
        }
        return ($max_a < $max_b) ? -1 : 1;
      });

      $participants_range['variants'] = [];
      foreach($unique_ranges as $unique_range) {
        list($min, $max) = explode(':', $unique_range);
        $participants_range['variants'][] = [
          'min' => $min,
          'max' => $max,
          'id' => $range_variants[$unique_range]
        ];

        if (!empty($min) && (empty($participants_min) || $min < $participants_min)) {
          $participants_min = $min;
        }

        if (!empty($max) && (empty($participants_max) || $max > $participants_max)) {
          $participants_max = $max;
        }
      }
    }

    $participants_range['min'] = $participants_min;
    $participants_range['max'] = $participants_max;

    return $participants_range;
  }

  private static function getAvailabilityRange(array $variants) {
    $availability = [];
    $all_year = false;

    if(!empty($variants)) {
      foreach ($variants as $variant) {
        $availability_terms = $variant->field_event_sub_tax_availability->referencedEntities();
        foreach ($availability_terms as $availability_term) {
          $month = null;
          //get translated name if present
          if(!empty($availability_term->field_tax_avail_name->getValue())){
            $month = $availability_term->field_tax_avail_name->getValue()[0]['value'];
          } else {
            $month = $availability_term->getName();
          }
          $availability[$availability_term->getWeight()] = $month;
        }
      }
    }

    if(count($availability) == 12) {
      $all_year = true;
    } else {
      //sort from jan to dec
      ksort($availability);

      //if availability is at begining and end of the month then sort in order from end to beginning of the year
      //i.e. 0,1,2,11,12 -> 11,12,13,14,15
      for ($i = 0; $i < count($availability); $i++){
        if(array_key_exists($i, $availability) && array_key_exists($i+11, $availability)) {
          $val = $availability[$i];
          unset($availability[$i]);
          $availability[$i + 12] = $val;
        }
      }
    }

    $availability = array_values($availability);

    return [
      'all' => $all_year,
      'min' => $availability[0],
      'max' => $availability[count($availability) - 1]
    ];
  }

  private static function getMenuTypes($node)
  {
    $names = [];
    $menu_types = $node->field_event_tax_menu_types->referencedEntities();
    foreach($menu_types as $menu_type) {
      $names[] = $menu_type->getName();
    }
    return $names;
  }

  private static function getSearchFilters($node)
  {
    $search_filters = [];
    $filters = $node->field_field_event_tax_filters->referencedEntities();
    foreach ($filters as $filter) {
      $top = TaxonomieHelper::getTopTerm($filter);
      if (!array_key_exists($top->tid->value, $search_filters)) {
        $search_filters[$top->tid->value] = [
          'name' => ucfirst(strtolower($top->getName())),
          'icon' => $top->field_tax_filters_icon->value,
          'children' => [],
        ];
      }

      if ($filter->tid->value != $top->tid->value) {
        $search_filters[$top->tid->value]['children'][] = $filter->getName();
      }
    }
    return $search_filters;
  }

  private static function getRelatedEvents($node) {
    $related_events = [];

    $referencedEvents = $node->field_event_related_events->referencedEntities();
    foreach($referencedEvents as $relatedEvent) {
      $event = [];
      $event['id'] = $relatedEvent->id();
      $event['title'] = $relatedEvent->title->value;
      $event['url'] = $relatedEvent->toUrl()->toString();
      $event['menu_type'] = TaxonomieHelper::getTopTaxonomieName($relatedEvent, 'field_event_tax_menu_types');

      $relatedImages = $relatedEvent->field_event_image->referencedEntities();
      if (!empty($relatedImages)) {
        $urls = ImagesHelper::getImageUrls($relatedImages[0]);
        $event['image_url'] = $urls['search'];
      }

      $related_events[] = $event;
    }

    return $related_events;
  }

  public static function getSimilarEvents($node) {
    return null;
    //get ids of top menu term child taxonomies
    $topMenuTypeTerm = TaxonomieHelper::getTopTaxonomieTerm($node, 'field_event_tax_menu_types');
    if (empty($topMenuTypeTerm)) {
      return null;
    }

    $taxonomies = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('tax_menu_types', $topMenuTypeTerm->tid->value);

    $tids = [];
    foreach($taxonomies as $taxonomy){
      $tids[] = $taxonomy->tid;
    }

    //get events sample from the same parent taxonomy
    if (!empty($tids)) {
      $query = \Drupal::entityQuery('node')
        ->condition('field_event_tax_menu_types', $tids, 'IN')
        ->condition('nid', $node->nid->value, '!=')
        ->range(0, 3);

      $nids = $query->execute();

      if(!empty($nids)) {
        return Node::loadMultiple($nids);
      }
    }
  }

  private static function getMeta(Node $node) {
    return unserialize($node->field_event_meta->getValue()[0]['value']);
  }

}
