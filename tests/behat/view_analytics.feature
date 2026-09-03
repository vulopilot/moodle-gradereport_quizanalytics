@gradereport @gradereport_quizanalytics
Feature: View the quiz analytics report
  In order to understand how a quiz went
  As a teacher or a student
  I need to be able to view the quiz analytics report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name       | questiontext         |
      | Test questions   | truefalse | Question A | This is question 01. |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | quiz1    |
    And quiz "Quiz 1" contains the following questions:
      | question   | page |
      | Question A | 1    |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |

  @javascript
  Scenario: A teacher sees the quiz listed with its attempt count and a student to pick
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "View > Quiz Analytics" in the course gradebook
    Then I should see "Quiz 1"
    And the "Student" select box should contain "Student 1"

  @javascript
  Scenario: A teacher can open a student's quiz analytics
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I navigate to "View > Quiz Analytics" in the course gradebook
    And I set the field "Student" to "Student 1"
    And I follow "View Analytics"
    Then I should see "Attempt Summary"

  @javascript
  Scenario: A student can view their own quiz analytics
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I navigate to "View > Quiz Analytics" in the course gradebook
    And I should see "Quiz 1"
    And I follow "View Analytics"
    Then I should see "Attempt Summary"
