<?php

namespace Drupal\site_settings\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the loading of Site Settings.
 *
 * @group SiteSettings
 */
class SiteSettingsLoaderTest extends WebTestBase {

  public static $modules = [
    'site_settings',
    'site_settings_sample_data',
    'field_ui',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the user and login.
    $this->adminUser = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test site settings loader format.
   *
   * The site settings sample data controller compares arrays and outputs
   * statements to the browser if the arrays match our expections. In this way
   * we can catch if any changes to the code are modifying the output of the
   * array as that would result in a breaking change for users of this module.
   */
  public function testSiteSettingsLoaderFormat() {
    // Open the site settings sample data controller.
    $this->drupalGet('site_settings_sample_data/test_site_settings_loader');

    // Make sure the fieldsets match.
    $this->assertText('Fieldsets match expectations', 'Fieldsets match expectations');

    // Make sure the test plain text is as expected.
    $this->assertText('Test plain text value is as expected', 'Test plain text value is as expected');

    // Make sure the test textarea is as expected.
    $this->assertText('Test textarea value is as expected', 'Test textarea value is as expected');

    // Make sure the test multiple entries contents are as expected.
    $this->assertText('Test multiple entries content 1 is as expected', 'Test multiple entries content 1 is as expected');
    $this->assertText('Test multiple entries content 2 is as expected', 'Test multiple entries content 2 is as expected');

    // Make sure the test multiple entries and fields contents are as expected.
    $this->assertText('Test multiple entries and fields content 1 field 1 is as expected', 'Test multiple entries and fields content 1 field 1 is as expected');
    $this->assertText('Test multiple entries and fields content 1 field 2 is as expected', 'Test multiple entries and fields content 1 field 2 is as expected');
    $this->assertText('Test multiple entries and fields content 2 field 1 is as expected', 'Test multiple entries and fields content 2 field 1 is as expected');
    $this->assertText('Test multiple entries and fields content 2 field 2 is as expected', 'Test multiple entries and fields content 2 field 2 is as expected');

    // Make sure the test multiple fields contents are as expected.
    $this->assertText('Test multiple fields field 1 is as expected', 'Test multiple fields field 1 is as expected');
    $this->assertText('Test multiple fields field 2 is as expected', 'Test multiple fields field 2 is as expected');

    // Make sure the test image is as expected.
    $this->assertText('Test image target id is as expected', 'Test image target id is as expected');
    $this->assertText('Test image alt is as expected', 'Test image alt is as expected');
    $this->assertText('Test image uri is as expected', 'Test image uri is as expected');

    // Make sure the test images is as expected.
    $this->assertText('Test images image 1 target id is as expected', 'Test images image 1 target id is as expected');
    $this->assertText('Test images image 1 alt is as expected', 'Test images image 1 alt is as expected');
    $this->assertText('Test images image 1 uri is as expected', 'Test images image 1 uri is as expected');
    $this->assertText('Test images image 2 target id is as expected', 'Test images image 2 target id is as expected');
    $this->assertText('Test images image 2 alt is as expected', 'Test images image 2 alt is as expected');
    $this->assertText('Test images image 2 uri is as expected', 'Test images image 2 uri is as expected');

    // Make sure the test file is as expected.
    $this->assertText('Test file target id is as expected', 'Test file target id is as expected');
  }

}
