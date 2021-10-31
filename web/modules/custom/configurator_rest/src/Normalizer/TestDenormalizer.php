<?php

namespace Drupal\configurator_rest\Normalizer;

use Drupal\configurator_rest\ConfigResource;
use Drupal\custom_rest\ReservationResource;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class TestDenormalizer extends NormalizerBase implements DenormalizerInterface {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = ['\Drupal\configurator_rest\ConfigResource'];

  /**
   * Denormalizes data back into an object of the given class.
   *
   * @param mixed $data data to restore
   * @param string $class the expected class to instantiate
   * @param string $format format the given data was extracted from
   * @param array $context options available to the denormalizer
   *
   * @return object
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {
    $transformator = new ConfigResource();

    $transformator->title = $data['title'];
    return $transformator;
  }

  /**
   * Normalizes an object into a set of arrays/scalars.
   *
   * @param object $object object to normalize
   * @param string $format format the normalization result will be encoded as
   * @param array $context Context options for the normalizer
   *
   * @return array
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    // TODO: Implement normalize() method.
  }
}
