<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 08.05.2018
 * Time: 13:01
 */

namespace Drupal\capdel_legacy_importer\Entity;


use Drupal\capdel_legacy_importer\LegacyDatabaseConnector;
use Drupal\taxonomy\Entity\Term;

class TaxonomyTerm
{
    /**
     * Connection to the legacy DB, injected.
     * @var \Drupal\capdel_legacy_importer\LegacyDatabaseConnector
     */
    private $legacyConnector;

    private $entityManager;

    private $term;

    public function __construct(LegacyDatabaseConnector $legacyConnector)
    {
        $this->legacyConnector = $legacyConnector;
        $this->entityManager = \Drupal::service('entity_field.manager');
    }

    public function createTerm($data, $type = 'default', $vid = 'tags') {

        if($vid != 'tax_destination') {
            $query = \Drupal::entityQuery('taxonomy_term')
              ->condition('vid',$vid)
              ->condition('field_tax_legacy_id.value',$data->id, '=');
        }
        // special case for merged destination taxonomy
        else {
            $query = \Drupal::entityQuery('taxonomy_term')
              ->condition('vid','tax_destination')
              ->condition('field_tax_legacy_id.value',$data->id, '=')
              ->condition('field_tax_destination_legacy_tax.value',$type, '=');
        }

        $nids = $query->execute();

        //updating current taxonomy term
        if(!empty($nids)) {
            $this->term = Term::load(reset($nids));
        }
        //creating new term
        else {
            $this->term = Term::create([
              'vid' => $vid,
            ]);
        }

        switch ($type) {
            case 'lang' :
                return $this->setLangTerm($data);
            case 'key_accounts' :
                return $this->setKeyAccountTerm($data);
            case 'nature_diodes' :
                return $this->setNatureDiodesTerm($data);
            case 'nature_georegions' :
            case 'nature_pays' :
            case 'nature_regions' :
                return $this->setDestinationTerm($data, $type);
            case 'default' :
                break;
            default :
                break;
        }

        return false;
    }

    private function setLangTerm($data) {
        $this->term->set('name',$data->code_langue);
        $this->term->set('field_tax_lang_comment',$data->commentaire);
        $this->term->set('field_tax_legacy_id',$data->id);
        $this->term->set('field_tax_lang_name',$data->nom);
        $this->term->save();

        return true;
    }

    private function setKeyAccountTerm($data) {
        $this->term->set('name',$data->nom);
        $this->term->set('field_tax_key_accouts_comments',$data->commentaire);
        $this->term->set('field_tax_legacy_id',$data->id);

        $query = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid','language')
          ->condition('field_tax_legacy_id.value',$data->id_langues, '=');

        $nids = $query->execute();

        if(!empty($nids)) {
            $languageRefEntity = Term::load(reset($nids));
            $this->term->set('field_tax_key_accounts_tax_lang',$languageRefEntity);
        }

        $this->term->save();

        return true;
    }

    private function setNatureDiodesTerm($data) {

        $this->term->set('field_tax_legacy_id',$data->id);
        $this->term->set('name',$data->commentaire);
        $this->term->save();


        $natureDiodesLangEntites = $this->legacyConnector->getRowFromTableByField('nature_diodes_lang','id_nature_diodes',$data->id);

        if(!empty($natureDiodesLangEntites)) {
            foreach ($natureDiodesLangEntites as $entity) {
                if($entity->id_langues != 1) {
                    $languageTerm = $this->loadTaxonomyTermByFieldValue('language','field_tax_legacy_id',$entity->id_langues);
                    if($languageTerm) {
                        if(!$this->term->hasTranslation(strtolower($languageTerm->getName()))) {
                                $this->term->addTranslation(strtolower($languageTerm->getName()),[
                                    'name' => $entity->nom,
                              ])->save();
                              //TODO add case to update translation
                        }
                    }
                }
            }
        }

        return true;
    }

    private function loadTaxonomyTermByFieldValue($vid,$field,$value) {
        $query = \Drupal::entityQuery('taxonomy_term')
          ->condition('vid',$vid)
          ->condition($field.'.value',$value, '=');

        $nids = $query->execute();

        if(!empty($nids)) {
            return Term::load(reset($nids));
        }
        return null;
    }

    private function setDestinationTerm($data, $type) {
        $this->term->set('field_tax_legacy_id',$data->id);
        $this->term->set('field_tax_destination_legacy_tax',$type);
        $this->term->set('name',$data->commentaire);
        $this->term->save();

        $destinationLangEntities = $this->legacyConnector->getRowFromTableByField($type.'_lang','id_'.$type,$data->id);

        if(!empty($destinationLangEntities)) {
            foreach ($destinationLangEntities as $entity) {
                if($entity->id_langues != 1) {
                    $languageTerm = $this->loadTaxonomyTermByFieldValue('language','field_tax_legacy_id',$entity->id_langues);
                    if($languageTerm) {
                        if(!$this->term->hasTranslation(strtolower($languageTerm->getName()))) {
                            $this->term->addTranslation(strtolower($languageTerm->getName()),[
                              'name' => $entity->nom,
                            ])->save();
                            //TODO add case to update translation
                        }
                    }
                }
            }
        }

        return true;
    }
}