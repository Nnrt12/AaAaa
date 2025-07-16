<?php
require_once '../includes/session_config.php';

// Destroy user session securely
destroySession();

// Redirect to home page
header('Location: ../');
exit;
?>