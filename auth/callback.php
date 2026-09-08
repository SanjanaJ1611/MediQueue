<?php
/**
 * MediQueue - Supabase OAuth Callback Landing Page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/supabase.php';
require_once __DIR__ . '/../includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticating – <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="container text-center">
        <div class="mq-card shadow-sm p-5 bg-white mx-auto" style="max-width: 460px;">
            <div id="loadingState">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h4 class="fw-bold text-dark mb-2">Connecting Account...</h4>
                <p class="text-muted small mb-0">Verifying your Google authentication credentials with <?= APP_NAME ?>.</p>
            </div>

            <div id="errorState" class="d-none">
                <div class="text-danger mb-3" style="font-size: 3rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <h4 class="fw-bold text-dark mb-2">Authentication Failed</h4>
                <p id="errorMessage" class="text-danger small mb-4">An error occurred during authentication.</p>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary w-100 py-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <!-- Supabase JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        const SUPABASE_URL = "<?= htmlspecialchars(SUPABASE_URL) ?>";
        const SUPABASE_ANON_KEY = "<?= htmlspecialchars(SUPABASE_ANON_KEY) ?>";
        const BASE_URL = "<?= htmlspecialchars(BASE_URL) ?>";

        function showError(msg) {
            document.getElementById('loadingState').classList.add('d-none');
            document.getElementById('errorState').classList.remove('d-none');
            document.getElementById('errorMessage').textContent = msg;
        }

        async function processOAuthCallback() {
            // Check for OAuth error directly in query or hash fragment
            const urlParams = new URLSearchParams(window.location.search);
            const hashParams = new URLSearchParams(window.location.hash.substring(1));
            const oauthError = urlParams.get('error_description') || hashParams.get('error_description') || urlParams.get('error') || hashParams.get('error');
            if (oauthError) {
                showError(oauthError.replace(/\+/g, ' '));
                return;
            }

            if (!SUPABASE_URL || !SUPABASE_ANON_KEY) {
                showError("Supabase credentials are missing on this server.");
                return;
            }

            try {
                const supabase = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

                // Check for session in URL fragment or storage
                const { data: { session }, error } = await supabase.auth.getSession();

                if (error || !session) {
                    // Try auth state listener if not populated yet
                    const { data: authListener } = supabase.auth.onAuthStateChange(async (event, currentSession) => {
                        if (currentSession && currentSession.access_token) {
                            await sendTokenToBackend(currentSession.access_token);
                        } else if (event === 'SIGNED_OUT' || !currentSession) {
                            showError("No active authentication session found.");
                        }
                    });

                    setTimeout(() => {
                        if (!document.getElementById('loadingState').classList.contains('d-none')) {
                            showError("Authentication timed out or was cancelled. Please try again.");
                        }
                    }, 8000);
                    return;
                }

                await sendTokenToBackend(session.access_token);

            } catch (err) {
                console.error("Supabase OAuth callback error:", err);
                showError(err.message || "Unexpected authentication error occurred.");
            }
        }

        async function sendTokenToBackend(accessToken) {
            try {
                const res = await fetch(BASE_URL + '/ajax/supabase_auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ access_token: accessToken })
                });

                const data = await res.json();

                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showError(data.message || "Failed to complete login on the server.");
                }
            } catch (netErr) {
                console.error("Server synchronization error:", netErr);
                showError("Failed to communicate with MediQueue server.");
            }
        }

        document.addEventListener('DOMContentLoaded', processOAuthCallback);
    </script>
</body>
</html>
