@echo off
php -l models\Application.php > verify_syntax_log.txt 2>&1
php -l models\ApplicationQuestion.php >> verify_syntax_log.txt 2>&1
php -l models\ApplicationResponse.php >> verify_syntax_log.txt 2>&1
php -l controllers\CandidateApplicationsController.php >> verify_syntax_log.txt 2>&1
php -l controllers\ApplicationFormController.php >> verify_syntax_log.txt 2>&1
php -l candidate\application_actions.php >> verify_syntax_log.txt 2>&1
php -l candidate\application_form.php >> verify_syntax_log.txt 2>&1
php -l candidate\application_start.php >> verify_syntax_log.txt 2>&1
php -l candidate\opportunity_detail.php >> verify_syntax_log.txt 2>&1
php -l candidate\applications.php >> verify_syntax_log.txt 2>&1
php -l candidate\application_detail.php >> verify_syntax_log.txt 2>&1
php -l candidate\dashboard.php >> verify_syntax_log.txt 2>&1
php -l middleware\auth.php >> verify_syntax_log.txt 2>&1
php -l auth\login.php >> verify_syntax_log.txt 2>&1
php -l smoke_application_test.php >> verify_syntax_log.txt 2>&1
echo DONE >> verify_syntax_log.txt