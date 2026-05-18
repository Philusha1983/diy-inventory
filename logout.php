<?php
/**
 * logout.php — Session termination endpoint.
 * Destroys the PHP session and redirects to the login gate.
 * All sidebar logout links point here.
 */
session_start();
session_unset();
session_destroy();

// Expire the session cookie immediately in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

header('Location: index.php');
exit;
