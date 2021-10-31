<?php

namespace Drupal\capdel_helper\Helpers;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;

/**
 * Created by IntelliJ IDEA.
 * User: mirek
 * Date: 28.05.18
 * Time: 09:41
 */
class ImagesHelper
{
  public static function getImages(Node $node, $field) {
    $images = [];

    $relatedImages = $node->{$field}->referencedEntities();
    foreach($relatedImages as $relatedImage) {
      $image = [];
      if(!empty($relatedImage->field_media_image_title->getValue())) {
        $image['title'] = $relatedImage->field_media_image_title->getValue()[0]['value'];
      }
      if(!empty($relatedImage->field_media_image_->getValue())) {
        $image['owner'] = $relatedImage->field_media_image_->getValue()[0]['value'];
      }
      $image['alt'] = $relatedImage->field_media_image->getValue()[0]['alt'];
      $image['urls'] = self::getImageUrls($relatedImage);

      $images[] = $image;
    }

    return $images;
  }

  public static function getImageUrls($image) {
    $urls = [];

    $targetId = $image->field_media_image->getValue()[0]['target_id'];
    $file = File::load($targetId);
    $url = file_create_url($file->getFileUri());
    $urls['full'] = file_url_transform_relative($url);

    $urls = array_merge($urls, self::getStyleImageUrls($file, ['search', 'slider', 'option']));
    return $urls;
  }

  private static function getStyleImageUrls(File $file, array $styles){
    $urls = [];
    foreach($styles as $styleName){
      $style = ImageStyle::load($styleName);
      $url = $style->buildUrl($file->getFileUri());
      $urls[$styleName] = file_url_transform_relative($url);
    }
    return $urls;
  }

  public static function fixMissingImageStyles(Media $media) {
    if($media->bundle() != 'image') {
      return;
    }

    $file = $media->get('thumbnail')->entity;
    if(empty($file)) {
      return;
    }

    $fileUri = $file->getFileUri();

    $thumbnailUri = ImageStyle::load('thumbnail')->buildUri($fileUri);
    if(!file_exists($thumbnailUri)){
      $image_style = ImageStyle::load('thumbnail');
      $image_style->createDerivative($fileUri, $thumbnailUri);
    }

    $cropThumbnailUri = ImageStyle::load('crop_thumbnail')->buildUri($fileUri);
    if(!file_exists($cropThumbnailUri)) {
      $image_style = ImageStyle::load('crop_thumbnail');
      $image_style->createDerivative($fileUri, $cropThumbnailUri);
    }
  }

}
