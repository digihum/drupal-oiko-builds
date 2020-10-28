<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\ImportContext;
use Drupal\node\NodeInterface;

/**
 * Functional test class for content entity reference field.
 *
 * @group entity_share
 * @group entity_share_client
 */
class ContentEntityReferenceTest extends EntityShareClientFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          // Used for internal reference.
          'es_test_level_3' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
          // Content reference.
          'es_test_level_2' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_3'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
          // Content reference.
          'es_test_level_1' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_2'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
          // Content reference.
          'es_test_level_0' => $this->getCompleteNodeInfos([
            'field_es_test_content_reference' => [
              'value_callback' => function () {
                return [
                  [
                    'target_id' => $this->getEntityId('node', 'es_test_level_1'),
                  ],
                ];
              },
              'checker_callback' => 'getExpectedContentReferenceValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test that a reference entity value is still maintained.
   */
  public function testReferenceEntityValue() {
    $this->pullEveryChannels();
    $this->checkCreatedEntities();
  }

  /**
   * Test that a referenced entity is pulled even if not selected.
   */
  public function testReferencedEntityCreated() {
    // Select only the referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    $this->checkCreatedEntities();
  }

  /**
   * Test that only certain referenced entities are pulled when not selected.
   */
  public function testReferencedEntityCreatedDepthTwo() {
    // Set recursion depth or import config plugin to 2.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 2,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_1 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_2 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');

  }

  /**
   * Test that only certain referenced entities are pulled when not selected.
   */
  public function testReferencedEntityCreatedDepthOne() {
    // Set recursion depth or import config plugin to 1.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 1,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertTrue(!empty($recreated_entities), 'The node with UUID es_test_level_1 has been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_2 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');
  }

  /**
   * Test that only certain referenced entities are pulled when not selected.
   */
  public function testReferencedEntityCreatedDepthZero() {
    // Set recursion depth or import config plugin to 0.
    $new_plugin_configurations = [
      'entity_reference' => [
        'max_recursion_depth' => 0,
        'weights' => [
          'process_entity' => 10,
        ],
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // Select only the top-level referencing entity.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');

    $response = $this->remoteManager->jsonApiRequest($this->remote, 'GET', $prepared_url);
    $json = Json::decode((string) $response->getBody());
    $import_context = new ImportContext($this->remote->id(), 'node_es_test_en', $this::IMPORT_CONFIG_ID);
    $this->importService->prepareImport($import_context);
    $this->importService->importEntityListData(EntityShareUtility::prepareData($json['data']));

    $recreated_entities = $this->loadEntity('node', 'es_test_level_1');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_1 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_2');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_2 has not been recreated.');
    $recreated_entities = $this->loadEntity('node', 'es_test_level_3');
    $this->assertFalse(!empty($recreated_entities), 'The node with UUID es_test_level_3 has not been recreated.');
  }

  /**
   * Helper function.
   *
   * After the value_callback is re-evaluated, the nid will be changed. So need
   * a specific checker_callback.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The field to retrieve the value.
   *
   * @return array
   *   The expected value after import.
   */
  protected function getExpectedContentReferenceValue(ContentEntityInterface $entity, string $field_name) {
    // A little trick to dynamically get the correct value of referenced
    // entity, because our mock content UUID's respect this rule.
    // Otherwise we would need to add a new parameter to 'checker_callback'.
    $level = (int) str_replace('es_test_level_', '', $entity->uuid());
    $target_uuid = 'es_test_level_' . ($level + 1);
    return [
      [
        'target_id' => $this->getEntityId('node', $target_uuid),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function populateRequestService() {
    parent::populateRequestService();

    // Needs to make the requests when only the referencing content will be
    // required.
    $selected_entities = [
      'es_test_level_0',
    ];
    $prepared_url = $this->prepareUrlFilteredOnUuids($selected_entities, 'node_es_test_en');
    $this->discoverJsonApiEndpoints($prepared_url);
  }

}
