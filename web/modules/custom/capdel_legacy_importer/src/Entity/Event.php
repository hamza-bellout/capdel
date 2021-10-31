<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 07.05.2018
 * Time: 13:26
 */

namespace Drupal\capdel_legacy_importer\Entity;


use Drupal\capdel_legacy_importer\LegacyDatabaseConnector;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\Entity\Term;

/**
 * Class Event
 *
 * @package Drupal\capdel_legacy_importer\Entity
 */
class Event implements EventInterface
{

    /**
     * Entity manager
     * @var mixed
     */
    private $entityManager;

    /**
     * Fields assigned to the event entity
     * @var
     */
    private $fields;

    /**
     * Container for the new Event Node
     * @var
     */
    private $event;

    /**
     * Connection to the legacy DB, injected.
     * @var \Drupal\capdel_legacy_importer\LegacyDatabaseConnector
     */
    private $legacyConnector;

    /**
     * Currently processed packageId from batch.
     * @var
     */
    private $legacyPackageId;

    /**
     * Data from the package legacy table
     * @var mixed
     */
    private $data;

    /**
     * Data from the package_lang legacy table
     * @var mixed
     */
    private $langData;


    /**
     * Event constructor.
     * Gets DI, gets the data from the LegacyDB using packageID, and initially parses data.
     *
     * @param \Drupal\capdel_legacy_importer\LegacyDatabaseConnector $legacyConnector
     * @param $packageId
     */
    public function __construct(LegacyDatabaseConnector $legacyConnector, $packageId)
    {
        $this->entityManager = \Drupal::service('entity_field.manager');
        $this->fields = $this->entityManager->getFieldDefinitions('node','event');
        $this->legacyConnector = $legacyConnector;
        $this->legacyPackageId = $packageId;

        $data = $this->legacyConnector->getRowFromTableByField('packages','id',$packageId);
        $langData = $this->legacyConnector->getRowFromTableByFieldWhereLang('packages_lang','id_packages', $packageId);

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
          ->condition('type','event')
          ->condition('field_event_legacy_id',$this->legacyPackageId, '=');

        $nids = $query->execute();

        //updating current node
        if(!empty($nids)) {
            $this->event = Node::load(reset($nids));
        }
        //creating new node
        else {
            $this->createNode();
            $this->setLegacyID();
        }

        $this->setEventTitle();
        $this->setBooleanFields();
        $this->setEventDurationType();
        $this->setMetaFields();
        $this->setLegacyFields();
        $this->setFields();

        $this->setURLandRedirects();

        $this->setDiodesTaxonomyRelations();
        $this->setKeyAccountsTaxonomyRelations();

        $this->setImages();
        $this->event->save();
    }

    public function reorderImages() {
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'event')
        ->condition('field_event_legacy_id', $this->legacyPackageId, '=');

      $nids = $query->execute();

      //ignore new, inexisting in Drupal events
      if(empty($nids)) {
        return;
      }

      $this->event = Node::load(reset($nids));
      $this->setImages();


      $pictures = $this->legacyConnector->getRowFromTableByField('pictures', 'id_packages', $this->legacyPackageId);
      $pictures = $this->filterObjectsByFieldValue($pictures, 'valide', 'o');

      //sort legacy pictures by 'ordre' field
      usort($pictures, function($a, $b){
        return $a->ordre <=> $b->ordre;
      });

      $imageReferences = [];
      $images = $this->event->field_event_image->referencedEntities();
      foreach($images as $image) {
        $id = $image->id();
        $filename = $image->getName();
        $imageReferences[$filename] = $id;
      }

      $reorderedReferences = [];
      foreach ($pictures as $picture) {
        $filename ='pictures-nom_origin-'.$picture->id.'.jpg';
        //process only images that were already imported
        if(array_key_exists($filename, $imageReferences)){
          $reorderedReferences[] = ['target_id'=> $imageReferences[$filename]];
          unset($imageReferences[$filename]);
        }
      }

      //keep images that were added to Drupal but didn't exist in legacy
      foreach($imageReferences as $filename => $id) {
        $reorderedReferences[] = ['target_id'=> $imageReferences[$filename]];
      }

      $this->event->field_event_image->setValue($reorderedReferences);
      $this->event->save();
    }

    /**
     * Helper function to create new event node.
     * @param string $type
     */
    private function createNode($type = 'event') {
        $this->event = Node::create([
          'type' => $type,
        ]);
        $this->event->setPublished(1);
        $this->event->set('moderation_state','published');
    }

    /**
     * Helper function to set legacyId in the event node.
     */
    private function setLegacyID () {
        $this->event->set('field_event_legacy_id',$this->legacyPackageId);
    }

    /**
     * Helper function to set simple boolean fields.
     */
    private function setBooleanFields() {
        $this->event->set('field_event_activity_day',$this->data->activityDay);
        $this->event->set('field_event_activity_evening',$this->data->activityEvening);
        $this->event->set('field_event_activity_indoor',$this->data->activityIndoor);
        $this->event->set('field_event_activity_outdoor',$this->data->activityOutdoor);
        $this->event->set('field_event_view_prices',$this->data->viewprices);
        $this->event->set('field_event_is_valid',$this->data->valide);

    }

    /**
     * Helper function to set misc fields to event entity
     */
    private function setFields() {
        $this->event->set('field_event_origin','legacy');

        if($this->data->datastatus != "offline") {
            $this->event->set('moderation_state','published');
            $this->event->setPublished(1);
        }
        else {
            $this->event->setPublished(0);
        }

        $this->event->set('field_event_duration_max',$this->data->durationmax);
        $this->event->set('field_event_duration',$this->data->duration);
        $this->event->set('field_event_datastatus',$this->data->datastatus);
        $this->event->set('field_event_added_value',$this->langData->offerusp);
        $this->event->set('field_event_info_admin',$this->data->admin_commentaire);

        $eventDesc = [
            'value' => $this->langData->detaildescription,
            'summary' => $this->langData->shortdescription,
            'format' => 'full_html'
          ];

        $this->event->set('field_event_description',$eventDesc);
    }

    /**
     * Helper function to get the duration type from the package, and then check if the taxonomy term is created.
     * If not, create it and assign as the durationType field.
     */
    private function setEventDurationType() {
        if($this->data->durationtype) {
            $query = \Drupal::entityQuery('taxonomy_term')
              ->condition('vid','event_duration_type')
              ->condition('name',$this->data->durationtype, '=');

            $nids = $query->execute();

            $term = null;

            //term is found
            if(!empty($nids)) {
                $term = Term::load(reset($nids));
            }
            //creating new term
            else {
                $term = Term::create([
                  'vid' => 'event_duration_type',
                  'name' => $this->data->durationtype
                ]);
            }

            $this->event->set('field_event_tax_duration_type',$term);
        }
    }

    /**
     * Helper function that sets event title based on headline
     */
    private function setEventTitle() {
        $this->event->title = $this->langData->headline ?? 'No title header found';
    }

    /**
     * Helper function that sets meta fields
     */
    private function setMetaFields() {
        if(strlen($this->langData->metakeywords) < 255) {
            $metakeywords = $this->langData->metakeywords;
            $legacyKeywords = "CapdelKeywords:\r\n" . $this->langData->capdelkeywords;
        }
        else {
            $metakeywords = substr($this->langData->metakeywords,0,254);
            $legacyKeywords = "Metakeywords: \r\n" . $this->langData->metakeywords . "\r\nCapdelKeywords:\r\n" . $this->langData->capdelkeywords;
        }


        $this->event->set('field_event_meta',serialize([
          'title' => $this->langData->metatitle,
            'description' => $this->langData->metadescription,
            'keywords' => $metakeywords,
            'content-language' => 'fr',
            'abstract' => $this->langData->shortdescription,
        ]));

        $this->event->set('field_event_legacy_keywords',$legacyKeywords);
    }

    private function setURLandRedirects() {

        // need to save the event before setting redirect data
        $this->event->save();

        $legacyRedirectData = $this->legacyConnector->getRowFromTableByField('urw_history','id_key',$this->legacyPackageId);

        if(!empty($legacyRedirectData)) {
            $legacyRedirectData = $this->filterObjectsByFieldValue($legacyRedirectData,'valide','o');
            $legacyRedirectData = $this->filterObjectsByFieldValue($legacyRedirectData,'nature','p');

            $currentUrl = array_pop($legacyRedirectData);

            // set the path alias if it is not set
            if(!isset($this->event->toArray()['path'][0]['alias']) || isset($this->event->toArray()['path'][0]['alias']) && $this->event->toArray()['path'][0]['alias'] == null) {
                \Drupal::service('path.alias_storage')->save("/node/" . $this->event->nid->value, "/".$currentUrl->url, "und");
                \Drupal::service('path.alias_storage')->save("/node/" . $this->event->nid->value, "/".$currentUrl->url, "fr");
            }

            foreach ($legacyRedirectData as $legacyRedirect) {
                $query = \Drupal::entityQuery('taxonomy_term')
                  ->condition('vid','language')
                  ->condition('field_tax_legacy_id.value',$legacyRedirect->id_langues, '=');

                $nids = $query->execute();

                $language = 'und';

                if(!empty($nids)) {
                    $languageRefEntity = Term::load(reset($nids));
                    $language = $languageRefEntity->get('name');
                }

                // Redirect has to be searched in the Redirect table and then updated or created

                $redirectQuery = \Drupal::entityQuery('redirect')
                  ->condition('redirect_source__query','internal:/node/'.$this->event->nid->value,'=');

                $redNids = $redirectQuery->execute();

                if(!empty($redNids)) {
                    $redirectEntities = Redirect::loadMultiple($redNids);
                    foreach ($redirectEntities as $redEntity) {
                        $redEntity->set('redirect_source',$legacyRedirect->url);
                        $redEntity->save();
                    }
                }
                else {
                    Redirect::create([
                      'redirect_source' => $legacyRedirect->url,
                      'redirect_redirect' => 'internal:/node/'.$this->event->nid->value,
                      'language' => $language,
                      'status_code' => '307',
                    ])->save();
                }
            }
        }
    }

    public function setImages() {

        $pictures = $this->legacyConnector->getRowFromTableByField('pictures','id_packages',$this->legacyPackageId);
        $pictures = $this->filterObjectsByFieldValue($pictures,'valide','o');

        //sort legacy pictures by 'ordre' field
        usort($pictures, function($a, $b){
          return $a->ordre <=> $b->ordre;
        });

        $mediaFilesToAttach = [];

        foreach ($pictures as $picture) {

            //get additional information regarding picture
            $pictureLang = $this->legacyConnector->getRowFromTableByField('pictures_lang','id_pictures',$picture->id);

            if($pictureLang) {
                $pictureLang = reset($pictureLang);

                //check for the image file first, before creating media entity
                $query = \Drupal::entityQuery('file')
                  ->condition('filename', 'picture-'.$picture->id.'.jpg');

                $fileIds = $query->execute();

                //if found, load it
                if(!empty($fileIds)) {
                    $fileImage = File::load(reset($fileIds));
                }
                //else create new file with filename
                else {
                    $fileImage = File::create([
                      'filename' => 'pictures-nom_origin-'.$picture->id.'.jpg',
                      'uri' => 'public://org_im/pictures-nom_origin-'.$picture->id.'.jpg',
                      'uid' => 1,
                      'status' => 1,
                    ]);
                }

                $fileImage->setPermanent();
                $fileImage->save();

                //we have (or got) the image entity, let's find the media one
                $query = \Drupal::entityQuery('media')
                  ->condition('bundle','image')
                  ->condition('name','pictures-nom_origin-'.$picture->id.'.jpg')
                  ->condition('field_media_image_title',$pictureLang->title);

                $mediaNids = $query->execute();

                if(!empty($mediaNids)) {
                    $mediaImageEntity = Media::load(reset($mediaNids));

                    $existingFileName = $mediaImageEntity->field_media_image->referencedEntities()[0]->filename->getValue()[0]['value'];

                    if(strpos($existingFileName,'pictures-nom_origin') !== false &&
                      $existingFileName != $mediaImageEntity->getName()
                    ) {
                        \Drupal::logger('debugging-pictures')->warning(print_r([
                          'existing' => $existingFileName,
                            'media name' => $mediaImageEntity->getName()
                        ], TRUE));

                        $mediaImageEntity->set('field_media_image',[
                          'target_id' => $fileImage->id(),
                          'alt' => $pictureLang->title ?? 'Image alt not set',
                          'title' => $pictureLang->title ?? 'Image title not set',
                        ]);
                        $mediaImageEntity->save();
                    }


                }
                else {
                    //create missing media entity
                    $mediaImageEntity = Media::create([
                      'bundle' => 'image',
                    ]);

                    $mediaImageEntity->set('field_media_image_title',$pictureLang->title);
                    $mediaImageEntity->set('field_media_image_',$pictureLang->owner);
                    $mediaImageEntity->set('field_media_image_is_valid',1);
                    $mediaImageEntity->set('field_media_image_legacy_name',$picture->nom_origin);
                    $mediaImageEntity->set('field_media_image',[
                      'target_id' => $fileImage->id(),
                      'alt' => $pictureLang->title ?? 'Image alt not set',
                      'title' => $pictureLang->title ?? 'Image title not set',
                    ]);

                    $mediaImageEntity->save();
                    //and append it to the node
                    $this->event->get('field_event_image')->appendItem($mediaImageEntity);
                    $mediaFilesToAttach[] = $mediaImageEntity;
                }

            }
        }

       // $this->event->set('field_event_image',$mediaFilesToAttach);
        $this->event->save();
    }

    /**
     * Helper function to set the legacy fields from the old db into one, merged field
     * in the current BO.
     */
    private function setLegacyFields() {
        $placeholder = "<h1>Program</h1>";
        $placeholder .= $this->langData->program;

        $placeholder .= "<h1>L'Offre comprend</h1>";
        $placeholder .= $this->langData->inclusives;

        $placeholder .= "<h1>General conditions</h1>";
        $placeholder .= $this->langData->generalconditions;

        $this->event->set('field_event_legacy_content',$placeholder);
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

    /**
     * Function to set relation to Diodes taxonomy
     */
    private function setDiodesTaxonomyRelations() {
        $referenceEntries = $this->legacyConnector->getRowFromTableByField('liaisons_packages_diodes','id_packages', $this->legacyPackageId);

        // sort by order from legacy table
        usort($referenceEntries,function ($a,$b) {
            return strcmp($a->niveau, $b->niveau);
        });

        // filter only diodes ids
        $diodesIds = array_map(function($object) {
            return $object->id_nature_diodes;
        },$referenceEntries);

        if(!empty($diodesIds)) {
            // load diodes terms to put into fields
            $query = \Drupal::entityQuery('taxonomy_term')
              ->condition('vid','nature_diodes')
              ->condition('field_tax_legacy_id',$diodesIds, 'IN');


            $nids = $query->execute();

            if(!empty($nids)) {
                $diodesEntities = Term::loadMultiple($nids);
                $this->event->set('field_event_tax_diodes',$diodesEntities);
            }
        }
    }

    private function setKeyAccountsTaxonomyRelations() {
        $referenceEntries = $this->legacyConnector->getRowFromTableByField('liaisons_packages_keys_accounts','id_packages', $this->legacyPackageId);

        // filter only key_accounts ids
        $keyAccIds = array_map(function($object) {
            return $object->id_keys_accounts;
        },$referenceEntries);

        if(!empty($keyAccIds)) {
            $query = \Drupal::entityQuery('taxonomy_term')
              ->condition('vid','key_accounts')
              ->condition('field_tax_legacy_id',$keyAccIds, 'IN');


            $nids = $query->execute();

            if(!empty($nids)) {
                $keyAccEntities = Term::loadMultiple($nids);
                $this->event->set('field_event_tax_key_accounts',$keyAccEntities);
            }
        }
    }

}
