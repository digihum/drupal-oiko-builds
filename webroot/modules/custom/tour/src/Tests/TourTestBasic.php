<?php

namespace Drupal\tour\Tests;

use Drupal\Core\Extension\ThemeInstaller;

/**
 * Simple tour tips test base.
 */
abstract class TourTestBasic extends TourTestBase {

  /**
   * Tour tip attributes to be tested. Keyed by the path.
   *
   * @var array
   *   An array of tip attributes, keyed by path.
   *
   * @code
   * protected $tips = array(
   *   '/foo/bar' => array(
   *     array('data-id' => 'foo'),
   *     array('data-class' => 'bar'),
   *   ),
   * );
   * @endcode
   */
  protected $tips = array();

  /**
   * An admin user with administrative permissions for tour.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The permissions required for a logged in user to test tour tips.
   *
   * @var array
   *   A list of permissions.
   */
  protected $permissions = array('access tour');

  protected static $modules = array('tour', 'tour_test');

  protected function setUp() {
    parent::setUp();

    // Make sure we are using distinct default and administrative themes for
    // the duration of these tests.
    $this->container->get('theme_handler')->theme_install(array('bartik', 'seven'));
    $this->config('system.theme')
      ->set('default', 'bartik')
      ->set('admin', 'seven')
      ->save();

    $this->permissions[] = 'view the administration theme';

    // Create an admin user to view tour tips.
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * A simple tip test.
   */
  public function testTips() {
    foreach ($this->tips as $path => $attributes) {
      $this->drupalGet($path);
      $this->assertTourTips($attributes);
    }
  }

}
