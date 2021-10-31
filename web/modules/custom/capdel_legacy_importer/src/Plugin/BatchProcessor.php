<?php

namespace Drupal\capdel_legacy_importer\Plugin;

use Drupal\capdel_legacy_importer\Entity\Event;
use Drupal\capdel_legacy_importer\Entity\TaxonomyTerm;
use Drupal\capdel_legacy_importer\LegacyDatabaseConnector;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BatchProcessor
 *
 * @package Drupal\capdel_legacy_importer
 */
class BatchProcessor implements ContainerInjectionInterface
{

    use StringTranslationTrait;
    use DependencySerializationTrait;

    protected $legacyConnector;

    protected $itemscount;

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container) {
        return new static(
          $container->get('capdel_legacy_importer.database_connector')
        );
    }

    /**
     * ImporterBatchProcessor constructor.
     *
     * @param \Drupal\capdel_legacy_importer\LegacyDatabaseConnector $legacyConnector
     */
    public function __construct(LegacyDatabaseConnector $legacyConnector)
    {
        $this->legacyConnector = $legacyConnector;

        // get items / ids needed to perform in the batch operation (count)
        $events = $this->legacyConnector->getPackagesIds();
        $subpackages = $this->legacyConnector->getTable('subpackages',['id']);


        $languages = $this->legacyConnector->getTable('langues');
        $keyAccounts = $this->legacyConnector->getTable('keys_accounts');
        $natureDiodes = $this->legacyConnector->getTable('nature_diodes');

        $natureGeoregions = $this->legacyConnector->getTable('nature_georegions');
        $natureRegions = $this->legacyConnector->getTable('nature_regions');
        $naturePays = $this->legacyConnector->getTable('nature_pays');

        $this->itemscount = count($events) +
          count($subpackages) +
          count($languages) +
          count($keyAccounts) +
          count($natureDiodes) +
          count($natureGeoregions) +
          count($natureRegions) +
          count($naturePays);

        $batch = [
          'title' => $this->t('Importing events and taxonomies'),
          'operations' => [
            [[$this, 'importTaxonomy'], [$languages, 'lang', 'language']],
            [[$this, 'importTaxonomy'], [$keyAccounts, 'key_accounts', 'key_accounts']],
            [[$this, 'importTaxonomy'], [$natureDiodes, 'nature_diodes', 'nature_diodes']],
            [[$this, 'importTaxonomy'], [$natureGeoregions, 'nature_georegions', 'tax_destination']],
            [[$this, 'importTaxonomy'], [$natureRegions, 'nature_regions', 'tax_destination']],
            [[$this, 'importTaxonomy'], [$naturePays, 'nature_pays', 'tax_destination']],
            [[$this, 'importContentType'], [$events, 'Event']],
            [[$this, 'importContentType'], [$subpackages,'Subpackage']],
          ],
          'finished' => [$this, 'importEventsFinished'],
        ];
        batch_set($batch);
        if (PHP_SAPI == 'cli') {
            drush_backend_batch_process();
        }
    }

    /**
     * Batch operation to import the products from the legacy DB.
     *
     * @param $legacyEntities
     * @param $context
     */
    public function importContentType($legacyEntities,$entity, &$context) {
        if (!isset($context['results']['imported'])) {
            $context['results']['imported'] = [];
        }

        if (!$legacyEntities) {
            return;
        }

        $sandbox = &$context['sandbox'];
        if (!$sandbox) {
            $sandbox['progress'] = 0;
            $sandbox['max'] = count($legacyEntities);
            $sandbox['events'] = $legacyEntities;
        }

        $slice = array_splice($sandbox['events'], 0, 3);
        foreach ($slice as $legacyEntity) {
            $context['message'] = $this->t('Importing Content Type @type @number', ['@type' => $entity, '@number' => $sandbox['progress']]);

            $entityClass = "\Drupal\\capdel_legacy_importer\\Entity\\".$entity;
            $contentType = new $entityClass($this->legacyConnector, $legacyEntity->id);

            if($contentType) {
                $contentType->buildNode();
                $context['results']['imported'][] = $legacyEntity->id;
                drupal_set_message($this->t('Content Type @type imported: @event', ['@type' => $entity,'@event' => $legacyEntity->id]));
            }
            else {
                drupal_set_message($this->t('Content Type @type not imported: @event', ['@type' => $entity,'@event' => $legacyEntity->id]));
            }

            $sandbox['progress']++;
        }

        $context['finished'] = $sandbox['progress'] / $sandbox['max'];
    }

    /**
     * Batch operation to import the generic taxonomy from the legacy DB.
     *
     * @param $languages
     * @param $context
     */
    public function importTaxonomy($data, $type, $vid, &$context ) {
        if (!isset($context['results']['imported'])) {
            $context['results']['imported'] = [];
        }

        if (!$data) {
            return;
        }

        $sandbox = &$context['sandbox'];
        if (!$sandbox) {
            $sandbox['progress'] = 0;
            $sandbox['max'] = count($data);
            $sandbox['languages'] = $data;
        }

        foreach ($data as $item) {
            $context['message'] = $this->t('Importing taxonomy @type @number', [
              '@number' => $sandbox['progress'],
              '@type' => $type,
            ]);

            $taxonomyTerm = new TaxonomyTerm($this->legacyConnector);
            $result = $taxonomyTerm->createTerm($item, $type, $vid);


            if($result) {
                $context['results']['imported'][] = $item->id;
                drupal_set_message($this->t('Taxonomy term imported: @event', ['@event' => $item->id]));
            }
            else {
                drupal_set_message($this->t('Taxonomy term not imported: @event', ['@event' => $item->id]));
            }
            $sandbox['progress']++;
        }

        $context['finished'] = $sandbox['progress'] / $sandbox['max'];
    }

    /**
     * Callback for when the batch processing completes.
     *
     * @param $success
     * @param $results
     * @param $operations
     */
    public function importEventsFinished($success, $results, $operations) {
        if (!$success) {
            drupal_set_message($this->t('There was a problem with the batch'), 'error');
            return;
        }

        $imported = count($results['imported']);
        if ($imported == 0) {
            drupal_set_message($this->t('No events found to be imported.'));
        }
        else {
            drupal_set_message($this->formatPlural($imported, '1 event imported.', '@count events imported'));
        }
    }

}