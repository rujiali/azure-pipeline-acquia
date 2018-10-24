@api @lightning_workflow @javascript
Feature: Lightning Scheduler UI

  Background:
    Given I am logged in as a user with the "create page content, view own unpublished content, edit own page content, use editorial transition create_new_draft, schedule editorial transition publish, schedule editorial transition archive" permissions
    And I visit "/node/add/page"
    And I enter "Schedule This" for "Title"

  @a55f7706
  Scenario: Scheduling moderation state transitions
    When I click "Schedule a status change"
    And I select "Published" from "Scheduled moderation state"
    And I enter "5-4-2038" for "Scheduled transition date"
    And I enter "06:00:00PM" for "Scheduled transition time"
    And I press "Save transition"
    And I click "add another"
    And I select "Archived" from "Scheduled moderation state"
    And I enter "9-19-2038" for "Scheduled transition date"
    And I enter "08:57:00AM" for "Scheduled transition time"
    And I press "Save transition"
    And I press "Save"
    And I visit the edit form
    Then I should see "Change to Published on May 4, 2038 at 6:00 PM"
    And I should see "Change to Archived on September 19, 2038 at 8:57 AM"
    And I should see the link "add another"

  @e0c3690a
  Scenario: Removing a previously saved transition
    When I click "Schedule a status change"
    And I select "Published" from "Scheduled moderation state"
    And I enter "9-19-2038" for "Scheduled transition date"
    And I enter "08:57:00AM" for "Scheduled transition time"
    And I press "Save transition"
    And I press "Save"
    And I visit the edit form
    And I click "Remove transition to Published on September 19, 2038 at 8:57 AM"
    And I press "Save"
    And I visit the edit form
    Then I should not see "Change to Published on September 19, 2038 at 8:57 AM"
    And I should see the link "Schedule a status change"

  @769caa15
  Scenario: Canceling and removing moderation state transitions
    When I click "Schedule a status change"
    And I select "Published" from "Scheduled moderation state"
    And I enter "5-4-2038" for "Scheduled transition date"
    And I enter "06:00:00PM" for "Scheduled transition time"
    And I press "Save transition"
    And I click "add another"
    And I select "Archived" from "Scheduled moderation state"
    And I enter "9-19-2038" for "Scheduled transition date"
    And I enter "08:57:00AM" for "Scheduled transition time"
    And I press "Save transition"
    And I click "add another"
    And I select "Published" from "Scheduled moderation state"
    And I enter "10-31-2038" for "Scheduled transition date"
    And I enter "09:00:00PM" for "Scheduled transition time"
    And I click "Cancel transition"
    And I click "Remove transition to Archived on September 19, 2038 at 8:57 AM"
    And I press "Save"
    And I visit the edit form
    Then I should see "Change to Published on May 4, 2038 at 6:00 PM"
    But I should not see "Change to Archived on September 19, 2038 at 8:57 AM"
    And I should not see "Change to Published on October 31, 2038 at 9:00 PM"
    And I should not see the link "Schedule a status change"
    But I should see the link "add another"
