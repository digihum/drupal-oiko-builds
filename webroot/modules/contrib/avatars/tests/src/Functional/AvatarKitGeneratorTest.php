<?php

namespace Drupal\Tests\avatars\Functional;

use Drupal\avatars\Entity\AvatarGenerator;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Drupal\avatars\AvatarPreviewInterface;

/**
 * Avatar Kit generator test.
 *
 * @group avatars
 */
final class AvatarKitGeneratorTest extends AvatarKitWebTestBase {

  /**
   * Test avatar generators.
   */
  public function testGenerator(): void {
    $this->deleteAvatarGenerators();

    $avatar_generator1 = $this->createAvatarGenerator();

    $user = $this->createUser([
      'administer avatars',
      'avatars avatar_generator user ' . $avatar_generator1->id(),
    ]);
    $this->drupalLogin($user);

    $this->setAvatarGeneratorPreferences([$avatar_generator1->id() => TRUE]);
    $this->drupalGet('admin/config/people/avatars');

    /** @var \Drupal\avatars\AvatarManagerInterface $am */
    $am = \Drupal::service('avatars.avatar_manager');
    $avatar_preview = $am->findValidAvatar($user);
    $this->assertTrue($avatar_preview instanceof AvatarPreviewInterface, 'Downloaded avatar');
  }

  /**
   * Ensure requesting avatar for anonymous does not crash the site.
   */
  public function testAnonymous(): void {
    $generator_1 = AvatarGenerator::create([
      'label' => $this->randomMachineName(),
      'id' => $this->randomMachineName(),
      'plugin' => 'avatars_test_static',
    ]);
    $generator_1
      ->setStatus(TRUE)
      ->save();

    // The anonymous role must be granted access to at least on generator
    // otherwise nothing will tested.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('avatars avatar_generator user ' . $generator_1->id())
      ->save();

    $anonymous = User::getAnonymousUser();

    /** @var \Drupal\avatars\AvatarManagerInterface $am */
    $am = \Drupal::service('avatars.avatar_manager');
    $am->syncAvatar($anonymous);
  }

  /**
   * Tests whether a file is matched with an avatar preview.
   *
   * Tests AvatarManagerInterface::getAvatarPreviewByFile()
   */
  public function testFileIsAvatarPreview(): void {
    $generator_2 = AvatarGenerator::create([
      'label' => $this->randomMachineName(),
      'id' => $this->randomMachineName(),
      'plugin' => 'avatars_test_static',
    ]);
    $generator_2
      ->setStatus(TRUE)
      ->save();

    $user = $this->createUser([
      'avatars avatar_generator user ' . $generator_2->id(),
    ]);

    /** @var \Drupal\avatars\AvatarManagerInterface $am */
    $am = \Drupal::service('avatars.avatar_manager');
    $avatar_preview = $am->findValidAvatar($user);
    $file = $avatar_preview->getAvatar();

    $this->assertEquals($avatar_preview->id(), $am->getAvatarPreviewByFile($file));
  }

  /**
   * Tests whether a file is not matched with an avatar preview.
   *
   * Tests AvatarManagerInterface::getAvatarPreviewByFile()
   */
  public function testFileNotAvatarPreview(): void {
    $generator_2 = AvatarGenerator::create([
      'label' => $this->randomMachineName(),
      'id' => $this->randomMachineName(),
      'plugin' => 'avatars_test_static',
    ]);
    $generator_2
      ->setStatus(TRUE)
      ->save();

    $user = $this->createUser([
      'avatars avatar_generator user ' . $generator_2->id(),
    ]);

    /** @var \Drupal\avatars\AvatarManagerInterface $am */
    $am = \Drupal::service('avatars.avatar_manager');

    // Create a random file.
    /** @var \Drupal\file\FileRepositoryInterface $fileRepo */
    $fileRepo = \Drupal::service('file.repository');
    $file = $fileRepo->writeData($this->randomString(), 'public://blah');

    $this->assertFalse($am->getAvatarPreviewByFile($file));
  }

}
