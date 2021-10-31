<?php

namespace Drupal\custom_rest\Normalizer;

use Drupal\custom_rest\ReservationResource;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class TestDenormalizer extends NormalizerBase implements DenormalizerInterface {
  /**
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = ['\Drupal\custom_rest\ReservationResource'];

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
    $transformator = new ReservationResource();

    $transformator->title = $data['title'];
    $transformator->entities = $data['entities'];
    $transformator->additional_info = $data['additionalInfo'];
    $transformator->user = $data['user'];
    $transformator->type = $data['type'];

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
