<?php

namespace Drupal\Tests\system\Functional\Entity\Update;

use Drupal\Core\Entity\Sql\TemporaryTableMapping;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\system\Tests\Entity\EntityDefinitionTestTrait;

/**
 * Tests updating a non-translatable entity type to be revisionable.
 *
 * @group Entity
 * @group Update
 */
class SqlContentEntityStorageSchemaConverterNotTranslatableTest extends UpdatePathTestBase {

  use EntityDefinitionTestTrait;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The entity definition update manager.
   *
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface
   */
  protected $entityDefinitionUpdateManager;

  /**
   * The last installed schema repository service.
   *
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface
   */
  protected $lastInstalledSchemaRepository;

  /**
   * The key-value collection for tracking installed storage schema.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $installedStorageSchema;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityManager = \Drupal::entityManager();
    $this->entityDefinitionUpdateManager = \Drupal::entityDefinitionUpdateManager();
    $this->lastInstalledSchemaRepository = \Drupal::service('entity.last_installed_schema.repository');
    $this->installedStorageSchema = \Drupal::keyValue('entity.storage_schema.sql');
    $this->state = \Drupal::state();
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../fixtures/update/drupal-8.0.0-rc1-filled.standard.entity_test_update.php.gz',
      __DIR__ . '/../../../../fixtures/update/drupal-8.entity-test-schema-converter-enabled.php',
    ];
  }

  /**
   * Tests the conversion of an entity type to revisionable.
   */
  public function testMakeRevisionableNotTranslatable() {
    // Check that entity type is not revisionable prior to running the update
    // process.
    $entity_test_update = $this->lastInstalledSchemaRepository->getLastInstalledDefinition('entity_test_update');
    $this->assertFalse($entity_test_update->isRevisionable());

    // Make the entity type revisionable and run the updates.
    $this->updateEntityTypeToRevisionable();
    $this->runUpdates();


    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_test_update */
    $entity_test_update = $this->lastInstalledSchemaRepository->getLastInstalledDefinition('entity_test_update');
    $this->assertTrue($entity_test_update->isRevisionable());

    $field_storage_definitions = $this->lastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions('entity_test_update');
    $this->assertFalse(isset($field_storage_definitions['revision_translation_affected']));

    /** @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage */
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_update');
    $this->assertEqual(count($storage->loadMultiple()), 102, 'All test entities were found.');

    // Check that each field value was copied correctly to the revision tables.
    for ($i = 1; $i <= 102; $i++) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $revision */
      $revision = $storage->loadRevision($i);

      $this->assertEqual($i, $revision->id());
      $this->assertEqual($i, $revision->getRevisionId());

      $this->assertEqual($i . ' - test single property', $revision->test_single_property->value);

      $this->assertEqual($i . ' - test multiple properties - value1', $revision->test_multiple_properties->value1);
      $this->assertEqual($i . ' - test multiple properties - value2', $revision->test_multiple_properties->value2);

      $this->assertEqual($i . ' - test single property multiple values 0', $revision->test_single_property_multiple_values->value);
      $this->assertEqual($i . ' - test single property multiple values 1', $revision->test_single_property_multiple_values[1]->value);

      $this->assertEqual($i . ' - test multiple properties multiple values - value1 0', $revision->test_multiple_properties_multiple_values[0]->value1);
      $this->assertEqual($i . ' - test multiple properties multiple values - value2 0', $revision->test_multiple_properties_multiple_values[0]->value2);
      $this->assertEqual($i . ' - test multiple properties multiple values - value1 1', $revision->test_multiple_properties_multiple_values[1]->value1);
      $this->assertEqual($i . ' - test multiple properties multiple values - value2 1', $revision->test_multiple_properties_multiple_values[1]->value2);

      $this->assertEqual($i . ' - field test configurable field - value1 0', $revision->field_test_configurable_field[0]->value1);
      $this->assertEqual($i . ' - field test configurable field - value2 0', $revision->field_test_configurable_field[0]->value2);
      $this->assertEqual($i . ' - field test configurable field - value1 1', $revision->field_test_configurable_field[1]->value1);
      $this->assertEqual($i . ' - field test configurable field - value2 1', $revision->field_test_configurable_field[1]->value2);

      $this->assertEqual($i . ' - test entity base field info', $revision->test_entity_base_field_info->value);
    }

    // Check that temporary tables have been removed at the end of the process.
    $schema = \Drupal::database()->schema();
    foreach ($storage->getTableMapping()->getTableNames() as $table_name) {
      $this->assertFalse($schema->tableExists(TemporaryTableMapping::getTempTableName($table_name)));
    }

    // Check that backup tables have been removed at the end of the process.
    $schema = \Drupal::database()->schema();
    foreach ($storage->getTableMapping()->getTableNames() as $table_name) {
      $this->assertFalse($schema->tableExists(TemporaryTableMapping::getTempTableName($table_name, 'old_')));
    }
  }

}
