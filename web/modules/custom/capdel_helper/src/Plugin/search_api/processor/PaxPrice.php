<?php
/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 08.08.18
 * Time: 14:17
 */

namespace Drupal\capdel_helper\Plugin\search_api\processor;
use Drupal\capdel_helper\Helpers\EventHelper;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;

/**
 * @SearchApiProcessor(
 *   id = "pax_price",
 *   label = @Translation("PAX price"),
 *   description = @Translation("Splits PAX Price to separate indexes"),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = false,
 * )
 */
class PaxPrice extends ProcessorPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(DatasourceInterface $datasource = NULL) {
    $properties = array();

    if (!$datasource) {
      $definition = array(
        'label' => $this->t('PaxPrice'),
        'description' => $this->t('Separate indexes for Pax price'),
        'type' => 'decimal',
        'processor_id' => $this->getPluginId(),
      );
      $properties['pax_price'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    $entity = $item->getOriginalObject()->getValue();

    $title = NULL;
    if ($entity->bundle() == 'event') {
      $variants = $entity->field_event_ref_variant->referencedEntities();
      $uniqueVariantLocations = EventHelper::getUniqueVariantLocations($variants);
      if(count($uniqueVariantLocations) > 0) {
        $fields = $this->getFieldsHelper()
          ->filterForPropertyPath($item->getFields(), NULL, 'pax_price');

        //properly sort fields pax_price_1, pax_price_2, ... pax_price_10, pax_price_11, ...
        uksort($fields, function($a, $b){
          return strnatcmp($a, $b);
        });

        $pax_counter = 0;
        foreach ($fields as $fieldId => $field) {
          foreach ($uniqueVariantLocations as $variant) {
            $pax_prices = $variant->field_event_sub_price_ppmin->getValue();
            if (array_key_exists($pax_counter, $pax_prices) && $pax_prices[$pax_counter]['value'] != 0) {
              $field->addValue($pax_prices[$pax_counter]['value']);
            }
          }
          $pax_counter++;
        }
      }
    }
  }
}
