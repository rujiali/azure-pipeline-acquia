@api @lightning_workflow @javascript
Feature: Scheduled updates to content

  @0e5b60fd
  Scenario: Scheduling a moderation state change on an unmoderated content type
    And article content:
      | title     | path       |
      | Jucketron | /jucketron |
    And I am logged in as a user with the administrator role
    When I visit "/jucketron"
    And I visit the edit form
    Then I should not see the link "Schedule a transition"
