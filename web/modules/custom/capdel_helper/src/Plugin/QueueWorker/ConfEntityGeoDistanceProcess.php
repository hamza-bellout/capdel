<?php
/**
 * Created by PhpStorm.
 * User: pku
 * Date: 21.09.2018
 * Time: 17:29
 */

namespace Drupal\capdel_helper\Plugin\QueueWorker;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides base functionality for the Geo Distance between Place and TB.  Queue Workers.
 *
 * @QueueWorker(
 *   id = "cron_geo_dist_place_tb",
 *   title = @Translation("Geo Distance for TB and Place"),
 *   cron = {"time" = 60}
 * )
 */
class ConfEntityGeoDistanceProcess extends QueueWorkerBase implements ContainerFactoryPluginInterface {
    /**
     * The node storage.
     *
     * @var \Drupal\Core\Entity\EntityStorageInterface
     */
    protected $nodeStorage;
    /**
     *
     * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
     *   The node storage.
     */
    public function __construct(EntityStorageInterface $node_storage) {
        $this->nodeStorage = $node_storage;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
          $container->get('entity.manager')->getStorage('node')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function processItem($data) {
        \Drupal::logger('cron:menu:worker:process')->notice('Processing Place item (ID: @id) in the queue worker',['@id' => $data->id]);

        $loadedNode = Node::load($data->id);
        if($loadedNode) {
            $referencedTBs = $loadedNode->get('field_lieu_ref_tb')->getValue();
            if($referencedTBs && \count($referencedTBs) > 0) {
                $referencedTBs = $this->normalizeRefArray($referencedTBs);
            }

            foreach ($referencedTBs as $tb) {

                //check if we have the par entity with tb and data->id
                $parQuery = \Drupal::entityQuery('node');
                $parQuery->condition('type','conf_distance');
                $parQuery->condition('field_par_lieu_tb_geo_lieu',$data->id,'IN');
                $parQuery->condition('field_par_lieu_tb_geo_tb',$tb,'IN');
                $distanceNodesIds = $parQuery->execute();

                $tb = Node::load($tb);
                $distance = $this->calculateDistance($loadedNode, $tb);

                if(\count($distanceNodesIds) > 0 ) {
                    // update the distance
                    $distanceNode = Node::load(reset($distanceNodesIds));

                    $distanceValue = $distanceNode->get('field_par_lieu_tb_geo_dist');

                    if($distanceValue) {
                        $value = $distanceValue->getValue();
                        if(($value
                            && isset($value[0]['value'])
                            && round($value[0]['value'],6,PHP_ROUND_HALF_DOWN) !== $distance)
                          || empty($value)) {
                            $distanceNode->field_par_lieu_tb_geo_dist->value = $distance;
                            $distanceNode->save(false);
                            \Drupal::logger('geo-dist')->notice('NodeDistance: The field was saved.');
                        }
                        else{
                            \Drupal::logger('geo-dist')->notice('NodeDistance: The field value is up to date.');
                        }
                    }

                    $this->saveParagraphInPlaceAndTB($loadedNode, $tb);
                }
                else {
                    //if not, create it and attach to place and TB
                    $name = $tb->getTitle() . ' - ' . $loadedNode->getTitle();

                    $distanceNode = Node::create([
                      'type' => 'conf_distance',
                      'title' => $name,
                      'field_par_lieu_tb_geo_dist' => $distance,
                      'field_par_lieu_tb_geo_lieu' => $data->id,
                      'field_par_lieu_tb_geo_tb' => $tb
                    ]);
                    $distanceNode->save();

                    $this->saveParagraphInPlaceAndTB($loadedNode, $tb);
                }
            }
        }

        return true;
    }

    protected function saveParagraphInPlaceAndTB($place, $tb) {

        //get all the distance nodes that have the place in them and attach to the place
        $query = \Drupal::entityQuery('node');
        $query->condition('field_par_lieu_tb_geo_lieu',$place->id());
        $entity_ids = $query->execute();
        \Drupal::logger('geo-dist')->notice('attached (for place) entities found: @ent',['@ent' => implode(",",$entity_ids)]);

        $referencedDistanceNodes = $place->get('field_conf_lieu_par_geo_dist')->getValue();
        if($referencedDistanceNodes && \count($referencedDistanceNodes) > 0) {
            $referencedDistanceNodes = $this->normalizeRefArray($referencedDistanceNodes);
        }

        if(!$this->arrayCompare($referencedDistanceNodes,$entity_ids)) {
            $place->set('field_conf_lieu_par_geo_dist',$entity_ids);
            $place->save();
            \Drupal::logger('geo-dist')->notice('Place Item (ID: @id) saved',['@id' => $place->id()]);
        }
        else {
            \Drupal::logger('geo-dist')->notice('Place: The field value is up to date.');
            \Drupal::logger('geo-dist')->notice('Place Item (ID: @id) not saved',['@id' => $place->id()]);
        }

        // Save the TB
        $query = \Drupal::entityQuery('node');
        $query->condition('field_par_lieu_tb_geo_tb',$tb->id());
        $entity_ids = $query->execute();

        \Drupal::logger('geo-dist')->notice('attached (for tb) entities found: @ent',['@ent' => implode(",",$entity_ids)]);

        $referencedDistanceNodes = $tb->get('field_tb_par_geo_dist')->getValue();
        if($referencedDistanceNodes && \count($referencedDistanceNodes) > 0) {
            $referencedDistanceNodes = $this->normalizeRefArray($referencedDistanceNodes);
        }

        if(!$this->arrayCompare($referencedDistanceNodes,$entity_ids)) {
            $tb->set('field_tb_par_geo_dist',$entity_ids);
            $tb->save();
            \Drupal::logger('geo-dist')->notice('TB Item (ID: @id) saved',['@id' => $tb->id()]);
        }
        else {
            \Drupal::logger('geo-dist')->notice('TB: The field value is up to date.');
            \Drupal::logger('geo-dist')->notice('TB Item (ID: @id) not saved',['@id' => $tb->id()]);
        }
    }

    /**
     * Helper function to parse the array from [0][target_id] format (Drupal entity Reference) to normal array
     * @param $array
     *
     * @return array
     */
    public function normalizeRefArray ($array) {
        if(!$array || \count($array) == 0) {
            return [];
        }
        $returnArray = [];
        foreach($array as $row) {
            if(key_exists('target_id',$row)) {
                $returnArray[] = $row['target_id'];
            }
        }
        return $returnArray;
    }

    /**
     * Compare two array items
     * @param $arrayA
     * @param $arrayB
     *
     * @return bool
     */
    protected function arrayCompare($arrayA, $arrayB) {
        return (
          is_array($arrayA)
          && is_array($arrayB)
          && count($arrayA) == count($arrayB)
          && array_diff($arrayA, $arrayB) === array_diff($arrayB, $arrayA)
        );
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    protected function haversineGreatCircleDistance(
      $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }

    protected function calculateDistance($place, $tb) {
        $distance = 0;
        $geoParamsTb = $tb->field_conf_tb_geo_params->getValue();
        $geoParamsPlace = $place->field_conf_lieu_geo_params->getValue();

        if($geoParamsPlace !== null &&
          isset($geoParamsPlace[0]) &&
          isset($geoParamsPlace[0]['value']) &&
          $geoParamsTb !== null &&
          isset($geoParamsTb[0]) &&
          isset($geoParamsTb[0]['value'])
        ) {
            $geoParamsPlace = $geoParamsPlace[0]['value'];
            $geoParamsTb = $geoParamsTb[0]['value'];

            //both tb and place have the GEO filled
            if(substr_count($geoParamsPlace, ',') > 1 &&
              substr_count($geoParamsTb, ',') > 1
            ) {
                $geoParamsTbSplit = explode(',',$geoParamsTb);
                $geoParamsPlaceSplit = explode(',',$geoParamsPlace);

                $distance = $this->haversineGreatCircleDistance(
                  $geoParamsPlaceSplit[0],
                  $geoParamsPlaceSplit[1],
                  $geoParamsTbSplit[0],
                  $geoParamsTbSplit[1]
                );
            }
        }

        return round($distance,6,PHP_ROUND_HALF_DOWN);

    }
}