<?php
namespace Drupal\configurator_rest\Plugin\rest\resource;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;
use Drupal\capdel_helper\Helpers\HookHelper;

/**
 * Provides a resource to get place entity and referenced entities
 *
 * @RestResource(
 *   id = "rest_config_place_resource_get",
 *   label = @Translation("Configurator Place GET REST"),
 *   serialization_class = "\Drupal\configurator_rest\ConfigResource",
 *   uri_paths = {
 *     "canonical" = "/api/v1.0/custom/configurator/place/{id}",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1.0/custom/configurator/place/{id}"
 *   }
 * )
 */
class GetConfiguratorPlaceDetails extends ResourceBase {
    /**
     * A current user instance.
     *
     * @var \Drupal\Core\Session\AccountProxyInterface
     */
    protected $currentUser;
    /**
     * Constructs a Drupal\rest\Plugin\ResourceBase object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param array $serializer_formats
     *   The available serialization formats.
     * @param \Psr\Log\LoggerInterface $logger
     *   A logger instance.
     * @param \Drupal\Core\Session\AccountProxyInterface $current_user
     *   A current user instance.
     */
    public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      array $serializer_formats,
      LoggerInterface $logger,
      AccountProxyInterface $current_user) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
        $this->currentUser = $current_user;
    }
    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->getParameter('serializer.formats'),
          $container->get('logger.factory')->get('example_rest'),
          $container->get('current_user')
        );
    }
    /**
     * Responds to GET requests.
     *
     * Returns a list of bundles for specified entity.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *   Throws exception expected.
     */
    public function get($id) {
        // You must to implement the logic of your REST Resource here.
        // Use current user after pass authentication to validate access.
        if (!$this->currentUser->hasPermission('access content')) {
            throw new AccessDeniedHttpException();
        }

        if ($id == null) {
            return null;
        }

        $query = \Drupal::entityQuery('node')
          ->condition('type','conf_lieu')
          ->condition('nid',$id,'=');

        $nid = $query->execute();
        if(!empty($nid)) {
            $result = Node::load(reset($nid));
            if ($result) {
                $connectedTB = $result->field_lieu_ref_tb->getValue();
                $menuPars = $this->getMenuFromParagraph($result->field_conf_lieu_ref_par_menu->getValue());

                $responseArray = [
                  'id' => $id,
                  'tb' => HookHelper::normalizeRefArray($connectedTB),
                  'menu' => $menuPars
                ];

                $response = new ResourceResponse($responseArray);
                $response->addCacheableDependency($responseArray);
                return $response;
            }
        }

        return new ResourceResponse('not found',404);

    }

    private function getMenuFromParagraph($paragraphsValue) {
        $returnArray = [];
        foreach ($paragraphsValue as $paragraphArray) {
            $par = Paragraph::load($paragraphArray['target_id']);
            if($par->field_par_ref_statics !== null) {
                if($par->field_par_menu_price !== null) {
                    $id = HookHelper::normalizeRefArray($par->field_par_ref_statics->getValue())[0];
                    $returnArray[$id] = [
                        'id' => $id,
                        'prices' => $par->field_par_menu_price->getValue()
                    ];
                }
            }
        }
        return $returnArray;
    }
}