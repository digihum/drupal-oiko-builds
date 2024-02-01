<?php

namespace Drupal\Tests\views_delimited_list\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Simple test of the views_delimited_list views display style.
 *
 * @group views_delimited_list
 */
class DelimitedStyleTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_delimited_list_test'];

  /**
   * Tne first node, of type 'page', to use in the system under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $sutPageOne;

  /**
   * Tne second node, of type 'page', to use in the system under test.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $sutPageTwo;

  /**
   * Test the views_delimited_list style, for long lists.
   */
  public function testLongList(): void {
    // Create 4 nodes with random titles.
    $page1 = $this->drupalCreateNode(['type' => 'page']);
    $page2 = $this->drupalCreateNode(['type' => 'page']);
    $page3 = $this->drupalCreateNode(['type' => 'page']);
    $page4 = $this->drupalCreateNode(['type' => 'page']);

    // Load the view from the test module.
    $this->drupalGet('views_delimited_list_test__delimitedlist');
    $this->assertSession()->statusCodeEquals(200);

    // Make sure we can find the custom views display type.
    $resultViewsContainers = $this->getSession()->getPage()->findAll('css', '.views-delimited-list');
    $this->assertCount(1, $resultViewsContainers, 'The view is present with the correct container.');

    // Make sure the custom views display type outputs what we expect.
    $expectedText = sprintf('%s , %s , %s , and %s', $page1->label(), $page2->label(), $page3->label(), $page4->label());
    $this->assertSession()->elementTextContains('css', '.views-delimited-list', $expectedText);
  }

  /**
   * Test the views_delimited_list style, for short lists.
   */
  public function testShortList(): void {
    // Create 4 nodes with random titles.
    $page1 = $this->drupalCreateNode(['type' => 'page']);
    $page2 = $this->drupalCreateNode(['type' => 'page']);

    // Load the view from the test module.
    $this->drupalGet('views_delimited_list_test__delimitedlist');
    $this->assertSession()->statusCodeEquals(200);

    // Make sure we can find the custom views display type.
    $resultViewsContainers = $this->getSession()->getPage()->findAll('css', '.views-delimited-list');
    $this->assertCount(1, $resultViewsContainers, 'The view is present with the correct container.');

    // Make sure the custom views display type outputs what we expect.
    $expectedText = sprintf('%s and %s', $page1->label(), $page2->label());
    $this->assertSession()->elementTextContains('css', '.views-delimited-list', $expectedText);
  }

}
