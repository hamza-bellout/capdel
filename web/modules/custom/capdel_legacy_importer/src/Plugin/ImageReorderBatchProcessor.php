<?php

namespace Drupal\capdel_legacy_importer\Plugin;

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
class ImageReorderBatchProcessor implements ContainerInjectionInterface
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

        $this->itemscount = count($events);

        $batch = [
          'title' => $this->t('Adding missing ones & Reordering images'),
          'operations' => [
            [[$this, 'reorderImages'], [$events, 'Event']],
          ],
          'finished' => [$this, 'reorderFinished'],
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
    public function reorderImages($legacyEntities, $entity, &$context) {
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
            $context['message'] = $this->t('Reordering images @type @number', ['@type' => $entity, '@number' => $sandbox['progress']]);

            $entityClass = "\Drupal\\capdel_legacy_importer\\Entity\\".$entity;
            $contentType = new $entityClass($this->legacyConnector, $legacyEntity->id);

            if($contentType) {
                $contentType->reorderImages();
                $context['results']['imported'][] = $legacyEntity->id;
                drupal_set_message($this->t('@type images reordered: @event', ['@type' => $entity,'@event' => $legacyEntity->id]));
            }
            else {
                drupal_set_message($this->t('@type images not reordered: @event', ['@type' => $entity,'@event' => $legacyEntity->id]));
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
    public function reorderFinished($success, $results, $operations) {
        if (!$success) {
            drupal_set_message($this->t('There was a problem with the batch'), 'error');
            return;
        }

        $imported = count($results['imported']);
        if ($imported == 0) {
            drupal_set_message($this->t('No events found to be reorder images.'));
        }
        else {
            drupal_set_message($this->formatPlural($imported, '1 event images reordered.', '@count events images reordered'));
        }
    }

}
