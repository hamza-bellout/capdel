<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 07.05.2018
 * Time: 13:26
 */

namespace Drupal\capdel_legacy_importer\Entity;


use Drupal\capdel_legacy_importer\LegacyDatabaseConnector;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class Subpackage
 *
 * @package Drupal\capdel_legacy_importer\Entity
 */
class Subpackage
{

    /**
     * Entity manager
     * @var mixed
     */
    private $entityManager;

    /**
     * Fields assigned to the Subpackage entity
     * @var
     */
    private $fields;

    /**
     * Container for the new Subpackage Node
     * @var
     */
    private $subpackage;

    /**
     * Connection to the legacy DB, injected.
     * @var \Drupal\capdel_legacy_importer\LegacyDatabaseConnector
     */
    private $legacyConnector;

    /**
     * Currently processed subpackageId from batch.
     * @var
     */
    private $legacySubpackageId;

    /**
     * Data from the subpackages legacy table
     * @var mixed
     */
    private $data;

    /**
     * Data from the subpackages_lang legacy table
     * @var mixed
     */
    private $langData;


    /**
     * Subpackage constructor.
     * Gets DI, gets the data from the LegacyDB using subpackageID, and initially parses data.
     *
     * @param \Drupal\capdel_legacy_importer\LegacyDatabaseConnector $legacyConnector
     * @param $subpackageId
     */
    public function __construct(LegacyDatabaseConnector $legacyConnector, $subpackageId)
    {
        $this->entityManager = \Drupal::service('entity_field.manager');
        $this->fields = $this->entityManager->getFieldDefinitions('node','event');
        $this->legacyConnector = $legacyConnector;
        $this->legacySubpackageId = $subpackageId;

        $data = $this->legacyConnector->getRowFromTableByField('subpackages','id',$subpackageId);
        $langData = $this->legacyConnector->getRowFromTableByFieldWhereLang('subpackages_lang','id_subpackages',$subpackageId);

        $this->data = reset($data);
        $this->langData = reset($langData);
        $this->prepareData();
    }

    /**
     * Helper function to change the data from n,o, to boolean values
     */
    private function prepareData(){
        foreach ($this->data as $key => $value) {

            if($value == "o") {
                $this->data->{$key} = 1;
            }
            else if($value == "n") {
                $this->data->{$key} = 0;
            }
        }
    }

    /**
     * Get fields assigned to the event entity in the BO.
     *
     * @return mixed
     */
    public function getFields()
    {
       return $this->fields;
    }

    /**
     * Main function to build the Event Node from the connection to the legacy DB.
     */
    public function buildNode() {
        //check if the event entity exist in the BO
        $query = \Drupal::entityQuery('node')
          ->condition('type','event_subpackage')
          ->condition('field_event_sub_legacy_id',$this->legacySubpackageId, '=');

        $nids = $query->execute();

        //updating current node
        if(!empty($nids)) {
            $this->subpackage = Node::load(reset($nids));
        }
        //creating new node
        else {
            $this->createNode();
            $this->setLegacyID();
        }

        $this->setTitle();

        $this->setBooleanFields();
        $this->setFields();
        $this->setLegacyLanguage();
        $this->setPackageAvailability();

        $this->setDestinationTaxonomy();

        $this->setRelationToPackage();
        $this->setRelationInPackage();

        $this->subpackage->save();
    }

    /**
     * Helper function to create new subpackage node.
     * @param string $type
     */
    private function createNode($type = 'event_subpackage') {
        $this->subpackage = Node::create([
          'type' => $type,
        ]);
        $this->subpackage->setPublished(1);
        $this->subpackage->set('moderation_state','published');
    }

    /**
     * Helper function to set legacyId in the subpackage node.
     */
    private function setLegacyID () {
        $this->subpackage->set('field_event_sub_legacy_id',$this->legacySubpackageId);
    }

    /**
     * Helper function to set simple boolean fields.
     */
    private function setBooleanFields() {
        $this->subpackage->set('field_event_sub_is_valid',$this->data->valide);
        $this->subpackage->set('field_event_sub_google_maps_visi',$this->data->googlemap_affiche);

    }

    /**
     * Helper function to set misc fields to event entity
     */
    private function setFields() {
        $this->subpackage->set('field_event_sub_origin','legacy');
        $this->subpackage->set('moderation_state','published');
        $this->subpackage->setPublished(1);

        $this->subpackage->set('field_event_sub_accomtype',$this->data->accommtype);
        $this->subpackage->set('field_event_sub_address',$this->data->address);
        $this->subpackage->set('field_event_sub_city',$this->data->city);
        $this->subpackage->set('field_event_sub_zip',$this->data->zip);
        $this->subpackage->set('field_event_sub_geo_params',$this->data->googlemap_param);
        $this->subpackage->set('field_event_sub_participants_max',$this->data->numberparticipantsmax);
        $this->subpackage->set('field_event_sub_participants_min',$this->data->numberparticipantsmin);

        $this->subpackage->set('field_event_sub_price',$this->data->price);
        $this->subpackage->set('field_event_sub_price_extra',$this->data->priceextrasr);
        $this->subpackage->set('field_event_sub_price_info',$this->data->priceinfo);
        $this->subpackage->set('field_event_sub_price_ppmin',$this->data->priceppmin);

        $this->subpackage->set('field_event_sub_price_taxes',$this->data->pricetaxes);
        $this->subpackage->set('field_event_sub_price_type',$this->data->pricetype);
        $this->subpackage->set('field_event_sub_detail_comments',$this->langData->detail);
    }

    /**
     * Helper function to get the duration type from the package, and then check if the taxonomy term is created.
     * If not, create it and assign as the durationType field.
     */
    private function setPackageAvailability() {
        if($this->data->month_list) {

            $monthsArray = explode(',',$this->data->month_list);
            $termsArray = [];

            foreach ($monthsArray as $month) {
                $query = \Drupal::entityQuery('taxonomy_term')
                  ->condition('vid','tax_availability')
                  ->condition('name',$month, '=');

                $nids = $query->execute();

                $term = null;

                //term is found
                if(!empty($nids)) {
                    $term = Term::load(reset($nids));
                }
                //creating new term
                else {
                    $term = Term::create([
                      'vid' => 'tax_availability',
                      'name' => $month,
                    ]);
                }

                $termsArray[] = $term;
            }

            $this->subpackage->set('field_event_sub_tax_availability',$termsArray);
        }
    }

    /**
     * Helper function that sets subpackage title based on headline
     */
    private function setTitle() {
        $this->subpackage->title = 'Event '.$this->data->id_packages . ' variant no ' . $this->data->id;
    }

    private function filterObjectsByFieldValue($objects,$field,$value) {
        $result = [];
        foreach ($objects as $object) {
            if(property_exists($object,$field) && $object->{$field} == $value) {
                $result[] = $object;
            }
        }
        return $result;
    }



    private function setLegacyLanguage() {
        $query = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid','language')
          ->condition('field_tax_legacy_id.value',$this->langData->id_langues, '=');

        $nids = $query->execute();

        if(!empty($nids)) {
            $languageRefEntity = Term::load(reset($nids));
            $this->subpackage->set('field_event_sub_tax_lang',$languageRefEntity);
        }
    }

    private function setRelationInPackage() {
        $query = \Drupal::entityQuery('node')
          ->condition('type','event')
          ->condition('field_event_legacy_id',$this->data->id_packages, '=');

        $nids = $query->execute();

        if(!empty($nids)) {
            $eventEntity = Node::load(reset($nids));
            $this->subpackage->set('field_event_sub_belongs_to',$eventEntity);
        }
    }

    private function setRelationToPackage() {
        $query = \Drupal::entityQuery('node')
          ->condition('type','event')
          ->condition('field_event_legacy_id',$this->data->id_packages, '=');

        $nids = $query->execute();

        if(!empty($nids)) {
            $eventEntity = Node::load((int)reset($nids));
            $this->subpackage->save();

            $oldValue = $eventEntity->get('field_event_ref_variant');
            $oldValue = $oldValue->getValue();

            $legacyVariants = [];
            $usedIds = [];
            foreach ($oldValue as $value) {
                if(isset($value['target_id'])) {
                    $legacyVariants[] = Node::load($value['target_id']);
                    $usedIds[] = $value['target_id'];
                }
            }

            if(!empty($legacyVariants)) {
                if(!in_array($this->subpackage->id,$usedIds)) {
                    $legacyVariants[] = $this->subpackage;
                }
                $eventEntity->set('field_event_ref_variant',$legacyVariants);
            }
            else {
                $eventEntity->set('field_event_ref_variant',$this->subpackage);
            }

            $eventEntity->save();
        }
    }

    private function setDestinationTaxonomy() {

        $variantsArray = [];

        if($this->data->id_nature_georegions != null) {
            $andGeo = \Drupal::entityQuery('taxonomy_term')
                ->condition('vid','tax_destination')
                ->condition('field_tax_destination_legacy_tax.value','nature_georegions', '=')
                ->condition('field_tax_legacy_id.value',$this->data->id_nature_georegions, '=');
            $andGeoResult = $andGeo->execute();
            $variantsArray = array_merge($variantsArray,$andGeoResult);
        }

        if($this->data->id_nature_regions != null) {
            $andRegion = \Drupal::entityQuery('taxonomy_term')
                ->condition('vid','tax_destination')
                ->condition('field_tax_destination_legacy_tax.value','nature_regions', '=')
                ->condition('field_tax_legacy_id.value',$this->data->id_nature_regions, '=');
            $andRegionResult = $andRegion->execute();
            $variantsArray = array_merge($variantsArray,$andRegionResult);
        }

        if($this->data->id_nature_pays != null) {
            $andPays = \Drupal::entityQuery('taxonomy_term')
                ->condition('vid','tax_destination')
                ->condition('field_tax_destination_legacy_tax.value','nature_pays', '=')
                ->condition('field_tax_legacy_id.value',$this->data->id_nature_pays, '=');

            $andPaysResult = $andPays->execute();
            $variantsArray = array_merge($variantsArray,$andPaysResult);
        }

        if (!empty($variantsArray)) {
            $termNodes = Term::loadMultiple($variantsArray);
            $this->subpackage->set('field_event_sub_tax_destination',$termNodes);
        }
    }
}