<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Signing Out - Aether Vault</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <main class="main-content" style="min-height: 100vh; display: flex; align-items: center; justify-content: center;">
        <div style="background: var(--white); padding: 2rem 2.5rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md); text-align: center; max-width: 420px;">
            <h1 style="margin-bottom: 0.75rem; color: var(--midnight-emerald);">Signing you out</h1>
            <p style="color: #66726d;">Please wait while we close your secure session.</p>
        </div>
    </main>

    <script src="js/utils.js"></script>
    <script src="js/api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            try {
                if (getAuthToken()) {
                    await API.logout();
                }
            } catch (error) {
                console.error('Logout error:', error);
            } finally {
                clearAuthData();
                window.location.href = 'index.php';
            }
        });
    </script>
</body>
</html>
