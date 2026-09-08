<?php
/**
 * MediQueue - Supabase Authentication Configuration
 */

// Simple lightweight .env parser if .env exists
if (!function_exists('load_mediqueue_env')) {
    function load_mediqueue_env() {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile) && is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $val) = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    if (!getenv($key)) {
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                        $_SERVER[$key] = $val;
                    }
                }
            }
        }
    }
}
load_mediqueue_env();

// Supabase Project Credentials
// Replace with your project settings from: https://supabase.com/dashboard/project/_/settings/api
$supabaseUrl = getenv('SUPABASE_URL') ?: 'https://woxwdvlndevhqqajkzaw.supabase.co';
$supabaseAnonKey = getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndveHdkdmxuZGV2aHFxYWpremF3Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODg4MDgyMzEsImV4cCI6MjEwNDM4NDIzMX0.mwTkYk3MCrIZXIc1cxf-Kt_6CpcKj4fT74_CS3iKfiE';

define('SUPABASE_URL', rtrim($supabaseUrl, '/'));
define('SUPABASE_ANON_KEY', $supabaseAnonKey);

/**
 * Check if Supabase credentials have been configured
 */
function is_supabase_configured(): bool {
    return !empty(SUPABASE_URL) && 
           !empty(SUPABASE_ANON_KEY) && 
           SUPABASE_URL !== 'https://your-project.supabase.co' && 
           SUPABASE_ANON_KEY !== 'your-anon-key';
}
