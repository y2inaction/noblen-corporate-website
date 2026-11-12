<?php
// Proves the debug log is wired up, so that "no errors" is not vacuous.
trigger_error( 'noblen-canary', E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
echo "canary raised\n";
