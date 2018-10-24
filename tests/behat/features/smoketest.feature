Feature: Smoketest

  Check the login page is up

  @api
  Scenario: Anonymous user visits loginpage
    Given I am on "/user/login"
    Then I should see the text "Log in"
