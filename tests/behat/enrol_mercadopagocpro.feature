@enrol @enrol_mercadopagocpro
Feature: Mercado Pago Checkout Pro enrolment method
  In order to sell access to a course
  As a manager
  I need to add and configure a Mercado Pago Checkout Pro enrolment method

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | One      | manager1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following config values are set as admin:
      | accesstoken    | TEST-ACCESS-TOKEN    | enrol_mercadopagocpro |
      | webhooksecret  | TEST-WEBHOOK-SECRET  | enrol_mercadopagocpro |
      | currency       | ARS                  | enrol_mercadopagocpro |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Mercado Pago Checkout Pro" "table_row"
    And I log out

  @javascript
  Scenario: A manager adds a Mercado Pago enrolment method to a course
    Given I log in as "manager1"
    And I am on the "Course 1" "enrolment methods" page
    When I select "Mercado Pago Checkout Pro" from the "Add method" singleselect
    And I set the following fields to these values:
      | Custom instance name          | Standard price |
      | Allow Mercado Pago enrolments | No             |
      | Enrolment fee                 | 15000          |
      | Currency                      | Argentine Peso |
    And I press "Add method"
    Then I should see "Standard price" in the "generaltable" "table"

  @javascript
  Scenario: A new instance form defaults to disabled
    Given I log in as "manager1"
    And I am on the "Course 1" "enrolment methods" page
    When I select "Mercado Pago Checkout Pro" from the "Add method" singleselect
    Then the field "Allow Mercado Pago enrolments" matches value "No"

  @javascript
  Scenario: The enrolment fee must be greater than zero to enable the method
    Given I log in as "manager1"
    And I am on the "Course 1" "enrolment methods" page
    When I select "Mercado Pago Checkout Pro" from the "Add method" singleselect
    And I set the following fields to these values:
      | Allow Mercado Pago enrolments | Yes |
      | Enrolment fee                 | 0   |
    And I press "Add method"
    Then I should see "The enrolment fee must be greater than zero"

  @javascript
  Scenario: The enrolment fee must be a number
    Given I log in as "manager1"
    And I am on the "Course 1" "enrolment methods" page
    When I select "Mercado Pago Checkout Pro" from the "Add method" singleselect
    And I set the following fields to these values:
      | Enrolment fee | free |
    And I press "Add method"
    Then I should see "The enrolment fee must be a number"

  # This scenario enables the instance, and the plugin refuses to enable one on a
  # site that is not served over HTTPS. It therefore requires $CFG->behat_wwwroot
  # to be an https URL. See docs/TESTING.md.
  Scenario: A student sees the price and the pay button on the enrolment page
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 2 | C2        | 0        | 0                |
    And I log in as "admin"
    And I am on the "Course 2" "enrolment methods" page
    And I select "Mercado Pago Checkout Pro" from the "Add method" singleselect
    And I set the following fields to these values:
      | Allow Mercado Pago enrolments | Yes   |
      | Enrolment fee                 | 15000 |
    And I press "Add method"
    And I log out
    When I log in as "student1"
    And I am on "Course 2" course homepage
    Then I should see "Pay with Mercado Pago"
