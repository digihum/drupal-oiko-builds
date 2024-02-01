<?php

namespace Drupal\Tests\avatars\Functional;

/**
 * Avatar Kit admin settings test.
 *
 * @group avatars
 */
final class AvatarKitAdminSettingsTest extends AvatarKitWebTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->createUser(['administer avatars']);
    $this->drupalLogin($user);
  }

  /**
   * Test admin settings.
   */
  public function testAdminSettings(): void {
    $avatar_generator1 = $this->createAvatarGenerator(['weight' => -100, 'status' => 1]);
    $avatar_generator2 = $this->createAvatarGenerator(['weight' => 100]);

    $this->drupalGet('admin/config/people/avatars');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains(t('A list of avatar generators to try for each user in order of preference.'));

    // Generator 1 should be in first row, with checked box.
    $elements = $this->xpath('//table//tr[1]/td[1][text()=:label]', [
      ':label' => $avatar_generator1->label(),
    ]);
    $this->assertTrue(!empty($elements), 'Generator on first row.');
    $this->assertSession()->checkboxChecked('edit-avatar-generators-' . $avatar_generator1->id() . '-enabled');

    // Generator 2 should be in fourth row, with unchecked box.
    $elements = $this->xpath('//table//tr[4]/td[1][text()=:label]', [
      ':label' => $avatar_generator2->label(),
    ]);
    $this->assertTrue(!empty($elements), 'Generator on fourth row.');
    $this->assertSession()->checkboxNotChecked('edit-avatar-generators-' . $avatar_generator2->id() . '-enabled');
  }

  /**
   * Test add avatar generator config.
   */
  public function testGeneratorAdd(): void {
    $this->drupalGet('admin/config/people/avatars/generators/add');
    $this->assertSession()->statusCodeEquals(200);

    $id = mb_strtolower($this->randomMachineName());
    $label = $this->randomString();
    $edit = [
      'label' => $label,
      'id' => $id,
      'plugin' => 'avatars_test_dynamic',
    ];
    $this->drupalGet('admin/config/people/avatars/generators/add');
    $this->submitForm($edit, t('Save'));

    $t_args = ['%label' => $label];
    $this->assertSession()->responseContains(t('Created avatar generator %label', $t_args));
    $this->assertSession()->addressEquals('admin/config/people/avatars/generators/' . $id);
  }

  /**
   * Test edit avatar generator config.
   */
  public function testGeneratorEdit(): void {
    $avatar_generator = $this->createAvatarGenerator();

    $this->drupalGet($avatar_generator->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);

    $t_args = ['%label' => $avatar_generator->label()];
    $this->assertSession()->responseContains(t('Edit avatar generator %label', $t_args));

    $edit = ['label' => $avatar_generator->label()];
    $this->drupalGet($avatar_generator->toUrl('edit-form'));
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->addressEquals('admin/config/people/avatars');
    $this->assertSession()->responseContains(t('Updated avatar generator %label', $t_args));
  }

  /**
   * Test delete avatar generator config.
   */
  public function testGeneratorDelete(): void {
    $avatar_generator = $this->createAvatarGenerator();
    $this->drupalGet($avatar_generator->toUrl('delete-form'));
    $this->assertSession()->statusCodeEquals(200);

    $t_args = ['%label' => $avatar_generator->label()];
    $this->assertSession()->responseContains(t('Are you sure you want to delete avatar generator %label?', $t_args));
    $this->drupalGet($avatar_generator->toUrl('delete-form'));

    $this->submitForm([], t('Delete'));
    $this->assertSession()->addressEquals('admin/config/people/avatars');
    $this->assertSession()->responseContains(t('Avatar generator %label was deleted.', $t_args));
  }

}
