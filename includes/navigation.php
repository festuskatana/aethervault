<?php
$activePage = $activePage ?? '';
$showBiometric = $showBiometric ?? false;

if (!function_exists('appPath')) {
    function appPath($path = '') {
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $basePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');

        if ($path === '') {
            return $basePath === '' ? '/' : $basePath . '/';
        }

        return ($basePath === '' ? '' : $basePath) . '/' . ltrim($path, '/');
    }
}

$dashboardHref = appPath('dashboard');
$messagesHref = appPath('messages');
$adminHref = appPath('admin.php');
$profileHref = appPath('profile');
$logoutHref = appPath('logout.php');
?>
<nav class="navbar">
    <div class="nav-container">
        <div class="logo app-brand">
            <div class="app-logo-wrap" data-app-logo-wrap>
                <img class="app-logo-image" data-app-logo alt="App logo">
                <i class="fas fa-shield-alt app-logo-fallback" data-app-logo-fallback></i>
            </div>
            <span data-app-name>Aether Vault</span>
        </div>

        <div class="nav-mobile-actions">
            <a href="<?= htmlspecialchars($dashboardHref) ?>" class="nav-mobile-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" aria-label="Vault">
                <i class="fas fa-cloud-upload-alt"></i>
            </a>

            <a href="<?= htmlspecialchars($messagesHref) ?>" class="nav-mobile-link <?= $activePage === 'messages' ? 'active' : '' ?>" aria-label="Messages">
                <i class="fas fa-comments"></i>
                <span class="badge nav-mobile-badge" data-unread-badge style="display: none;">0</span>
            </a>

            <div class="nav-mobile-user user-menu">
                <button class="nav-mobile-profile user-trigger" type="button" aria-label="Open account menu">
                    <div class="nav-avatar-wrap">
                        <img id="navAvatarImage" class="nav-avatar-image" alt="Profile picture">
                        <div id="navAvatarFallback" class="nav-avatar-fallback">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="nav-avatar-status" aria-hidden="true"></span>
                    </div>
                </button>
                <div class="dropdown-menu">
                    <?php if ($showBiometric): ?>
                    <a href="#" id="biometricUnlockMobile"><i class="fas fa-fingerprint"></i> Biometric Unlock</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($adminHref) ?>" class="admin-only-link" data-admin-only hidden><i class="fas fa-user-shield"></i> Admin Panel</a>
                    <a href="<?= htmlspecialchars($profileHref) ?>" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><i class="fas fa-user-gear"></i> Profile Settings</a>
                    <a href="<?= htmlspecialchars($logoutHref) ?>"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

        </div>

        <div class="nav-menu" id="navMenu">
            <a href="<?= htmlspecialchars($dashboardHref) ?>" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-cloud-upload-alt"></i>
                <span>Vault</span>
            </a>
            <a href="<?= htmlspecialchars($messagesHref) ?>" class="nav-item <?= $activePage === 'messages' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
                <span class="badge" data-unread-badge style="display: none;">0</span>
            </a>
            <a href="<?= htmlspecialchars($adminHref) ?>" class="nav-item <?= $activePage === 'admin' ? 'active' : '' ?> admin-only-link" data-admin-only hidden>
                <i class="fas fa-user-shield"></i>
                <span>Admin</span>
            </a>
            <div class="nav-item user-menu nav-desktop-user">
                <div class="user-trigger">
                    <div class="nav-avatar-wrap">
                        <img class="nav-avatar-image" alt="Profile picture">
                        <div class="nav-avatar-fallback">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="nav-avatar-status" aria-hidden="true"></span>
                    </div>
                    <div class="user-menu-copy">
                        <span id="userName">User</span>
                        <span class="user-menu-caption">Account</span>
                    </div>
                </div>
                <div class="dropdown-menu">
                    <?php if ($showBiometric): ?>
                    <a href="#" id="biometricUnlock"><i class="fas fa-fingerprint"></i> Biometric Unlock</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($adminHref) ?>" class="admin-only-link" data-admin-only hidden><i class="fas fa-user-shield"></i> Admin Panel</a>
                    <a href="<?= htmlspecialchars($profileHref) ?>" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><i class="fas fa-user-gear"></i> Profile Settings</a>
                    <a href="<?= htmlspecialchars($logoutHref) ?>"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
