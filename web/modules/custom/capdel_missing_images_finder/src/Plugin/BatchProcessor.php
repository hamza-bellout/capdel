<?php

namespace Drupal\capdel_missing_images_finder\Plugin;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\Entity\Media;

/**
 * Class BatchProcessor
 *
 */
class BatchProcessor
{
    use StringTranslationTrait;
    use DependencySerializationTrait;

    protected $itemscount;

    public function __construct()
    {
        $batch = [
          'title' => $this->t('Missing images'),
          'operations' => [
            [[$this, 'bundleFind'], []],
          ],
          'finished' => [$this, 'importEventsFinished'],
        ];
        batch_set($batch);
        if (PHP_SAPI == 'cli') {
            drush_backend_batch_process();
        }
    }

    public function bundleFind(&$context) {
        if (!isset($context['results']['imported'])) {
            $context['results']['imported'] = [];
        }

        $query = \Drupal::entityQuery('media')
          ->condition('bundle','image');

        $nids = $query->execute();

        $sandbox = &$context['sandbox'];
        if (!$sandbox) {
            $sandbox['progress'] = 0;
            $sandbox['max'] = count($nids);
            $sandbox['images'] = $nids;
        }

        $slice = array_splice($sandbox['images'], 0, 10);
        foreach ($slice as $imageId) {

            $mediaImage = Media::load($imageId);
            $imageUri = $mediaImage->get('field_media_image')->referencedEntities()[0]->get('uri')->value;

            if(!file_exists($imageUri)) {
                $query = \Drupal::entityQuery('node')
                  ->condition('type','event')
                  ->condition('field_event_image',$imageId,'IN')
                  ->condition('field_event_datastatus','online','=');

                $nids = $query->execute();

                if(!empty($nids)) {
                    $arr = [
                      'id' => $imageId,
                      'imageUri' => $imageUri,
                      'nids' => $nids
                    ];

                    $context['results']['imported'][] = $arr;
                }



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

        foreach ($results['imported'] as $row) {
            if(array_key_exists('nids',$row) && $row['nids']) {
                $nidsString = "Connected events id: " .implode(',',$row['nids']);
            }
            else {
                $nidsString = "No connected events";
            }

            drupal_set_message("*File does not exist for media /media/". $row['id'] ."/edit --- "
              .$row['imageUri']
              ." --- "
              .$nidsString
              ." E*"
            );
        }
    }
}