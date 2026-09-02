# Gradereport Quiz Analytics

## Overview

The gradebook interface for all users, showing graphical analysis of their quiz attempts
for a course.

Go to any course page which has at least one quiz, choose "Quiz Analytics" after
clicking on the "Grades" link in the course tree of the "Navigation" block.
There you can see the quiz name and the number of attempts of the logged-in user in a
table format. A "View Analytics" link is present there which, upon clicking, will
display the graphical depiction.

In the graphical analysis there are four types of graphs:

1. Attempt Summary / Last Attempt Summary
2. My Progress and Predictions
3. Question Categories' Analysis
4. Scores' & Questions' Stats

### 1. Attempt Summary / Last Attempt Summary

Attempt Summary for a single attempt, and Last Attempt Summary for multiple attempts.
It shows the number of questions attempted, right answers, and partially correct
answers in the last attempt, and also shows the accuracy rate.

### 2. My Progress and Predictions

It shows three types of graph.

- **Improvement Curve / Peer Performance**
  - For multiple attempts: Improvement Curve shows how you improved over all your
    attempts, and the dark block represents the average number of attempts required
    to reach the score set as cut-off (by the site admin).
  - For a single attempt (when attempts allowed for that quiz is one): Peer Performance
    shows how your peers have scored in comparison with you.
- **Hardest Question** - Shows the top ten hardest questions depending on how many
  times the quiz was attempted and how often that particular question was left
  unattempted or answered incorrectly. Clicking on the bar for a question shows the
  question itself along with your last attempt, explanation, and correct answer.
- **Attempt Snapshot** - A recap displaying the key figures of all your previous
  attempts.

### 3. Question Categories' Analysis

It shows three types of graph.

- **Question Per Category** - Tells you the number of questions present in the quiz
  from each category.
- **Challenging Categories (Across All Users)** - Reports, based on wrong and
  unanswered cases, the top ten categories that turned out to be most challenging
  across all the users who took the quiz.
- **Challenging Categories for me** - Shows the top ten categories that turned out to
  be most challenging for the logged-in user.

### 4. Scores' & Questions' Stats

It shows two types of graph.

- **Scores by Percentage (All Users)** - Shows the number of users in each percentage
  (score percentage) group.
- **Question Analysis** - The curves depict how users fared on each question. Clicking
  on the point for a question shows the question itself along with your last attempt,
  explanation, and correct answer.

## Settings

Go to **Site administration > Grades > Report settings > Quiz Analytics** to set the
cut-off for all the quizzes in the course, and to set the grade boundary.

## Installation

### Installing directly from the Moodle plugins directory

1. Login as an admin and go to **Site administration > Plugins > Install plugins**.
   (If you can't find this location, then plugin installation is prevented on your
   site.)
2. Click the button "Install plugins from Moodle plugins directory".
3. Search for the plugin and click its Install button, then click Continue.
4. Confirm the installation request.
5. Check the plugin validation report.

### Installing via uploaded ZIP file

1. Go to the Moodle plugins directory, select your current Moodle version, then choose
   the plugin with a Download button and download the ZIP file.
2. Login to your Moodle site as an admin and go to
   **Site administration > Plugins > Install plugins**.
3. Upload the ZIP file. You should only be prompted for extra details (in the "Show
   more" section) if your plugin is not automatically detected.
4. If your target directory is not writeable, you will see a warning message.
5. Check the plugin validation report.

### Installing manually

1. Go to the Moodle plugins directory, select your current Moodle version, then choose
   the plugin with a Download button and download the ZIP file.
2. Upload or copy it to `<your-moodle-directory>/grade/report` and unzip it.
3. In your Moodle site (as admin), go to
   **Site administration > Notifications**.
4. Complete the installation from there.
