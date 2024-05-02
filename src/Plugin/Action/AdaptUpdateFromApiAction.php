<?php

namespace Drupal\adapt\Plugin\Action;

use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\key\KeyRepositoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;


/**
 * Defines a bulk operation to update nodes from an external API.
 *
 * @Action(
 *   id = "adapt_update_from_api",
 *   label = @Translation("Update Adapt ID"),
 *   type = "node",
 *   confirm = TRUE,
 * )
 */
class AdaptUpdateFromApiAction extends ViewsBulkOperationsActionBase {
  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    if ($entity && $entity->id()) {
      // Fetch authentication token.
      $token = $this->fetchToken();

      if ($token) {
        // Construct API URL with node ID as a parameter.
        //$h5p_field_items = $entity->get('field_h5p')->getValue();
        $node = \Drupal::entityTypeManager()->getStorage('node')->load($entity->id());
        $h5p_id = $node->field_h5p->getValue()[0]['h5p_content_id'];

        $api_url = 'https://adapt.libretexts.org/api/h5p-collections/get-adapt-question-id-by-h5p-id/' . $h5p_id;

        // Make API request to fetch data.
        try {
          $response = \Drupal::httpClient()->get($api_url, [
            'headers' => [
              'Authorization' => 'Bearer ' . $token,
            ],
          ]);

          // Process API response and update nodes.
          $data = json_decode($response->getBody(), TRUE);
          if ($data) {
            // Process data and update nodes.
            // Update node field values.
            if ($entity instanceof NodeInterface) { // Ensure to reference NodeInterface
              // Update node fields based on data from the API response.
              $entity->set('field_adapt_id', $data['adapt_question_id']);
              $entity->save();
            }
          }

        } catch (\Exception $e) {
          // Log any errors.
          \Drupal::logger('adapt')->error('Error occured while processing node with ID @nid: @message', [
            '@message' => $e->getMessage(),
          ]);
        }
      }
      else {
        // Log error: Token not found.
        \Drupal::logger('adapt')->error('Authentication token not found');
      }
    }
  }

  /**
   * Fetches authentication token from the Key module.
   *
   * @return string|null
   *   The authentication token, or NULL if not found.
   */
  protected function fetchToken() {
    try {
      // Load the key.
      $key_id = 'adapt_id';
      $key = \Drupal::service('key.repository')->getKey($key_id);

      // Check if the key exists and is valid.
      if ($key && $key->getKeyValue()) {
        return $key->getKeyValue();
      }
      else {
        return NULL;
      }
    } catch (\Exception $e) {
      // Log error: Unable to load key.
      \Drupal::logger('adapt')->error('Error loading key: @message', [
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    // If certain fields are updated, access should be checked against them as well.
    // @see Drupal\Core\Field\FieldUpdateActionBase::access().
    return $object->access('update', $account, $return_as_object);
  }
}