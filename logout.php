<?php
/**
 * MediQueue - Logout Script
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been safely signed out. Thank you for using MediQueue.');
header('Location: ' . BASE_URL . '/login.php');
exit;
