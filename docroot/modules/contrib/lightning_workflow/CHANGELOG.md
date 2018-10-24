## 2.0.0-rc1
* Lightning Scheduler has been completely rewritten and has a new UI. Users
  with permission to schedule various workflow state transitions will be able
  to schedule transitions to take place at any date and time they want. They
  can also schedule several transitions at once. Transition data is now stored
  in fields called scheduled_transition_date and scheduled_transition_state,
  which replace the old scheduled_moderation_state and scheduled_publication
  fields. A UI is also provided so you can migrate scheduled transition data
  from the old fields into the new ones. You will see a link to this UI once
  you complete the update path. (Issues #2935715, #2935198, #2935105, #2936757, #2954329, and #2954348)

## 1.2.0
* If you have Lightning Roles
  (part of [Lightning Core](https://drupal.org/project/lightning_core))
  installed, the "reviewer" roles will now receive permission to view
  unpublished content and revisions.

## 1.1.0
* Behat contexts used for testing were moved into the
  `Acquia\LightningExtension\Context` namespace.

## 1.0.0
* No changes since last release.

## 1.0.0-rc2
* Remove legacy update code.

## 1.0.0-rc1
* Loosen the tight coupling between Lightning Workflow and Views.
  (Issue #2938769)
