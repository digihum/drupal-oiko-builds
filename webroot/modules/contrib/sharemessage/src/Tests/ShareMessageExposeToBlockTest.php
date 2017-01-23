<?php

namespace Drupal\sharemessage\Tests;

/**
 * Check if Share Message is exposed as block.
 *
 * @group sharemessage
 */
class ShareMessageExposeToBlockTest extends ShareMessageTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['contextual'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->adminPermissions[] = 'access contextual links';
    parent::setUp();
  }

  /**
   * Test case that check if Share Message is exposed as block.
   */
  public function testShareMessageExposeToBlock() {
    // First enable the bartik theme to place the Share Message block afterwards.
    $theme = 'bartik';
    \Drupal::service('theme_handler')->install(array($theme));
    $this->config('system.theme')->set('default', $theme)->save();

    // Create another Share Message.
    $sharemessage = array(
      'label' => 'Share Message Test Label',
      'id' => 'sharemessage_test_label',
      'message_short' => 'AddThis sharemessage short description.',
    );
    $this->drupalPostForm('admin/config/services/sharemessage/add', $sharemessage, t('Save'));
    // Check for confirmation message and listing of the Share Message entity.
    $this->assertText(t('Share Message @label has been added.', array('@label' => $sharemessage['label'])));
    $this->assertText($sharemessage['label']);

    // Enable twitter and tweet services for AddThis.
    $this->drupalGet('admin/config/services/sharemessage/addthis-settings');
    $edit = ['default_services[]' => ['twitter', 'tweet']];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // Add a block that will contain the created Share Message.
    $block = array(
      'settings[label]' => 'Share Message test block',
      'settings[sharemessage]' => $sharemessage['id'],
      'region' => 'content',
    );
    $this->drupalPostForm('admin/structure/block/add/sharemessage_block/' . $theme, $block, t('Save block'));
    // Verify that the block is in the submitted region of the bartik theme.
    $this->drupalGet('admin/structure/block/list/' . $theme);
    $this->assertText($block['settings[label]']);

    // Go to front page and check whether Share Message is displayed.
    $this->drupalGet('');
    $this->assertShareButtons($sharemessage, 'addthis_16x16_style', TRUE);

    // Check the twitter template placeholder.
    $twitter_template = '<!--//--><![CDATA[//><!-- var addthis_share = { templates: { twitter: "AddThis sharemessage short description." } } //--><!]]>';
    $this->assertRaw($twitter_template);

    // Check for the contextual links presence.
    $sharemessage_contextual_id = 'data-contextual-id="block:block=sharemessage:langcode=en|sharemessage:sharemessage=sharemessage_test_label:langcode=en"';
    $this->assertRaw($sharemessage_contextual_id);

    // Logout the user and check for the Share Message block.
    $this->drupalLogout();
    $this->drupalGet('filter/tips');
    $this->assertShareButtons($sharemessage, 'addthis_16x16_style', TRUE);

    // Create an authenticated user.
    $permissions = array();
    $admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($admin_user);
    $this->drupalGet('');
    $this->assertShareButtons($sharemessage, 'addthis_16x16_style', TRUE);

    // A normal user must not see contextual links.
    $this->assertNoRaw($sharemessage_contextual_id);
  }

}
