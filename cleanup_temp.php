<?php
@unlink(__DIR__ . '/verify_syntax.bat');
@unlink(__DIR__ . '/verify_syntax.php');
@unlink(__DIR__ . '/verify_syntax_log.txt');
@unlink(__DIR__ . '/verify_syntax_output.txt');
echo file_exists(__DIR__ . '/verify_syntax.bat') ? 'bat still exists' : 'bat removed';
echo "\n";
echo file_exists(__DIR__ . '/verify_syntax.php') ? 'php still exists' : 'php removed';
echo "\n";