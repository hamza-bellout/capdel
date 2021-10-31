<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 07.09.2018
 * Time: 09:44
 */

namespace Drupal\capdel_helper\Helpers;


use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class ConfigurateurPlaceHelper
{
    public static function getConfPlaceInfo(Node $node) {
        $request = \Drupal::request();
        $place =  [
            'name' => $node->getTitle(),
            'description' => $node->field_conf_lieu_desc->value,
            'images' => ImagesHelper::getImages($node, 'field_conf_lieu_image'),
            'destination' => self::getValuesFromTaxonomy($node->field_conf_lieu_dest),
            'category' => self::getConfFilters($node),
            'coordinates' => $node->field_conf_lieu_geo_params->value,
            'par_min' => $node->field_conf_lieu_min_par->value,
            'par_max' => $node->field_conf_lieu_max_par->value,
            'availability' => self::getValuesFromTaxonomy($node->field_conf_lieu_avail),
            'rooms' => self::getRooms($node),
            'get_params' => $request->query->all(),
        ];

        return $place;
    }

    private static function getValuesFromTaxonomy($taxonomyField) {
        $items = $taxonomyField->referencedEntities();
        $values = [];

        foreach($items as $item) {
            $values[] = $item->name->value;
        }
        return $values;
    }

    private static function getConfFilters($node)
    {
        $conf_filters = [];
        $filters = $node->field_conf_lieu_category->referencedEntities();
        foreach ($filters as $filter) {
            $top = TaxonomieHelper::getTopTerm($filter);
            if (!array_key_exists($top->tid->value, $conf_filters)) {
                $conf_filters[$top->tid->value] = [
                  'name' => ucfirst(strtolower($top->getName())),
                  'icon' => $top->field_tax_conf_lieu_cat_icon->value,
                  'children' => [],
                ];
            }

            if ($filter->tid->value != $top->tid->value) {
                $conf_filters[$top->tid->value]['children'][] = $filter->getName();
            }
        }
        return $conf_filters;
    }

    private static function getRooms($node) {
        $rooms = [];
        $paragraphsValue = $node->field_conf_lieu_rooms->getValue();
        foreach ($paragraphsValue as $paragraphArray) {
            $par = Paragraph::load($paragraphArray['target_id']);
            if($par) {
                $room = [];
                $fields = $par->getFields(false);
                foreach ($fields as $fieldItem) {
                    if(strpos($fieldItem->getName(),'field_room_') !== false) {
                        $value = $fieldItem->getValue();
                        $name = $fieldItem->getName();
                        if(isset($value[0]) && isset($value[0]['value'])) {
                            $room[$name] = $fieldItem->getValue()[0]['value'];
                        }
                        else {
                            $room[$name] = 0;
                        }
                    }
                }
                if(\count($room) > 0) {
                    $rooms[] = $room;
                }
            }
        }

        return (\count($rooms) ? $rooms : false);
    }
}