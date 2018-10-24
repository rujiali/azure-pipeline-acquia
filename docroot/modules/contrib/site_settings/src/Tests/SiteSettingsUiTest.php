<?php

namespace Drupal\site_settings\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests the loading of Site Settings.
 *
 * @group SiteSettings
 */
class SiteSettingsUiTest extends WebTestBase {

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
   * Test site settings admin visibility.
   */
  public function testSiteSettingsAdminVisibility() {
    // Open the site settings list page.
    $this->drupalGet('admin/content/site-settings');

    // Make sure the fieldsets match.
    $this->assertRaw('<strong>Images</strong>', 'Images fieldset is visible');
    $this->assertRaw('<strong>Other</strong>', 'Other fieldset is visible');

    // Make sure the test plain text is as expected.
    $this->assertText('Test plain text', 'Test plain text value is visible');

    // Make sure the test textarea is as expected.
    $this->assertText('Test textarea name', 'Test textarea value is visible');

    // Make sure the test multiple entries contents are as expected.
    $this->assertText('Test multiple entries', 'Test multiple entries content 1 is visible');
    $this->assertText('Test multiple entries name 2', 'Test multiple entries content 2 is visible');

    // Make sure the test multiple entries and fields contents are as expected.
    $this->assertText('Test multiple entries and fields name 1', 'Test multiple entries and fields content 1 is visible');
    $this->assertText('Test multiple entries and fields name 2', 'Test multiple entries and fields content 2 is visible');

    // Make sure the test multiple fields contents are as expected.
    $this->assertText('Test multiple fields name', 'Test multiple fields is visible');

    // Make sure the test image is as expected.
    $this->assertText('Test image', 'Test image is visible');
    $this->assertText('Test images 1', 'Test images 1 is visible');
    $this->assertText('Test file', 'Test file is visible');

  }

  /**
   * Test site settings add another.
   */
  public function testSiteSettingsAddAnother() {
    // Open the site settings list page.
    $this->drupalGet('admin/content/site-settings');

    // Click add another link.
    $this->clickLink('Add another');

    // Make sure we can see the expected form.
    $this->assertText('Test multiple entries', 'The multiple entries edit title is visible');
    $this->assertText('Testing', 'The testing field label is visible');
    $params = [
      'field_testing[0][value]' => 'testSiteSettingsAddAnother',
    ];
    $this->drupalPostForm(NULL, $params, t('Save'));

    // Ensure we saved correctly.
    $this->assertText('Saved the Test multiple entries Site Setting.', 'The add another entity was saved');
    $this->assertText('testSiteSettingsAddAnother', 'The add another entity appears in the list of site settings');
  }

  /**
   * Test site settings edit existing.
   */
  public function testSiteSettingsEditExisting() {
    // Open the site settings list page.
    $this->drupalGet('admin/content/site-settings');

    // Click add another link.
    $this->clickLink('Edit', 3);

    // Make sure we can see the expected form.
    $this->assertText('Test plain text', 'The plain text edit title is visible');
    $this->assertText('Testing', 'The testing field label is visible');
    $params = [
      'field_testing[0][value]' => 'testSiteSettingsEditExisting',
    ];
    $this->drupalPostForm(NULL, $params, t('Save'));

    // Ensure we saved correctly.
    $this->assertText('Saved the Test plain text Site Setting.', 'The edit entity was saved');
    $this->assertText('testSiteSettingsEditExisting', 'The edited text appears in the list of site settings');
  }

  /**
   * Test site settings create new type and add a setting to that.
   */
  public function testSiteSettingsCreateNewTypeAndSetting() {
    // Open the site settings list page.
    $this->drupalGet('admin/structure/site_setting_entity_type/add');

    // Create the new site setting.
    $params = [
      'label' => 'testSiteSettingsCreateNewTypeAndSetting',
      'id' => 'testsitesettingscreatenew',
      'existing_fieldset' => 'Other',
    ];
    $this->drupalPostForm(NULL, $params, t('Save'));

    // Ensure we saved correctly.
    $this->assertText('Created the testSiteSettingsCreateNewTypeAndSetting Site Setting type.', 'The site settings type was created');

    // Add field.
    $this->drupalGet('admin/structure/site_setting_entity_type/testsitesettingscreatenew/edit/fields/add-field');
    $params = [
      'existing_storage_name' => 'field_testing',
      'existing_storage_label' => 'testSiteSettingsCreateNewTypeAndSettingLabel',
    ];
    $this->drupalPostForm(NULL, $params, t('Save and continue'));

    // Save field settings.
    $params = [];
    $this->drupalPostForm(NULL, $params, t('Save settings'));

    // Ensure we saved correctly.
    $this->assertText('Saved testSiteSettingsCreateNewTypeAndSettingLabel configuration.', 'The site settings type now has a field');
    $this->assertText('field_testing', 'The field machine name is as expected');

    // Open the site settings list page.
    $this->drupalGet('admin/content/site-settings');

    // Click add another link.
    $this->clickLink('Create setting');
    $this->assertText('testSiteSettingsCreateNewTypeAndSettingLabel', 'The field label is shown');
    $params = [
      'field_testing[0][value]' => 'testSiteSettingsCreateNewTypeAndSettingValue',
    ];
    $this->drupalPostForm(NULL, $params, t('Save'));

    // Ensure we saved correctly.
    $this->assertText('Saved the testSiteSettingsCreateNewTypeAndSetting Site Setting.', 'The created entity was saved');
    $this->assertText('testSiteSettingsCreateNewTypeAndSettingValue', 'The create text appears in the list of site settings');
  }

}
