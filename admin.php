<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault - Admin</title>
    <?php
    $styleVersion = @filemtime(__DIR__ . '/css/style.css') ?: time();
    $adminStyleVersion = @filemtime(__DIR__ . '/css/admin.css') ?: time();
    $utilsScriptVersion = @filemtime(__DIR__ . '/js/utils.js') ?: time();
    $apiScriptVersion = @filemtime(__DIR__ . '/js/api.js') ?: time();
    $appSettingsScriptVersion = @filemtime(__DIR__ . '/js/app-settings.js') ?: time();
    $navScriptVersion = @filemtime(__DIR__ . '/js/nav.js') ?: time();
    $adminScriptVersion = @filemtime(__DIR__ . '/js/admin.js') ?: time();
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/admin.css?v=<?= $adminStyleVersion ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $activePage = 'admin'; include __DIR__ . '/includes/navigation.php'; ?>

    <main class="admin-dashboard">
        <div class="dashboard-header">
            <div class="title-section">
                <p class="section-kicker">Administration</p>
                <h1 id="adminPageTitle"><i class="fas fa-gem"></i> Admin Dashboard</h1>
                <p id="adminHeroSummary">Branding, SMTP, user roles, monitoring, and security insights from your live database.</p>
            </div>
            <div class="live-clock" id="liveClock"></div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-left">
                    <h4>Total users</h4>
                    <div class="kpi-number" id="totalUsersMetric">0</div>
                </div>
                <i class="fas fa-users kpi-icon"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <h4>Active sessions</h4>
                    <div class="kpi-number" id="activeSessionsMetric">0</div>
                </div>
                <i class="fas fa-desktop kpi-icon"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <h4>Activity logs (24h)</h4>
                    <div class="kpi-number" id="apiCallsMetric">0</div>
                </div>
                <i class="fas fa-chart-line kpi-icon"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <h4>Storage used</h4>
                    <div class="kpi-number" id="storageMetric">0 Bytes</div>
                </div>
                <i class="fas fa-database kpi-icon"></i>
            </div>
            <div class="kpi-card">
                <div class="kpi-left">
                    <h4>Messages</h4>
                    <div class="kpi-number" id="uptimeMetric">0</div>
                </div>
                <i class="fas fa-comments kpi-icon"></i>
            </div>
        </div>

        <div class="three-col-grid">
            <div>
                <div class="feature-card card-stack-gap">
                    <div class="card-header">
                        <h3><i class="fas fa-palette"></i> Brand identity</h3>
                        <span class="badge-new">live</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="appName">Application name</label>
                            <input type="text" id="appName" value="">
                        </div>
                        <div class="form-group">
                            <label for="appLogo">Logo upload</label>
                            <input type="file" id="appLogo" accept="image/*">
                        </div>
                        <div class="logo-preview" id="logoPreviewContainer">
                            <div class="fallback-icon" id="adminLogoFallback"><i class="fas fa-shield-alt"></i></div>
                            <img id="adminLogoPreview" class="preview-img" alt="logo preview">
                            <span class="helper-text">PNG, SVG, WEBP, GIF, or JPG recommended</span>
                        </div>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="card-header">
                        <h3><i class="fas fa-envelope-circle-check"></i> SMTP engine</h3>
                        <span class="badge-new" id="smtpModeBadge">mail relay</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="smtpHost">SMTP Host</label>
                            <input type="text" id="smtpHost" placeholder="smtp.provider.com">
                        </div>
                        <div class="form-group">
                            <label for="smtpPort">Port</label>
                            <input type="number" id="smtpPort" min="1" step="1">
                        </div>
                        <div class="form-group">
                            <label for="smtpUsername">Username</label>
                            <input type="text" id="smtpUsername" placeholder="admin@example.com">
                        </div>
                        <div class="form-group">
                            <label for="smtpPassword">Password</label>
                            <input type="password" id="smtpPassword" placeholder="Leave blank to keep current password">
                        </div>
                        <p class="helper-text" id="smtpPasswordHint">Leave blank to keep the current password.</p>
                        <div class="form-group">
                            <label for="smtpSecure">Encryption</label>
                            <select id="smtpSecure">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="smtpFromEmail">From email</label>
                            <input type="email" id="smtpFromEmail">
                        </div>
                        <div class="form-group">
                            <label for="smtpFromName">From name</label>
                            <input type="text" id="smtpFromName">
                        </div>
                        <div class="form-group">
                            <label for="smtpTestEmail">Test email</label>
                            <input type="email" id="smtpTestEmail" placeholder="admin@example.com">
                        </div>
                        <div class="action-bar">
                            <button class="btn btn-secondary" id="testSmtpBtn" type="button"><i class="fas fa-paper-plane"></i> Test connection</button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="feature-card card-stack-gap">
                    <div class="card-header">
                        <h3><i class="fas fa-users-viewfinder"></i> User directory</h3>
                        <span class="badge-new">database</span>
                    </div>
                    <div class="card-body">
                        <table class="user-table" id="userManagementTable">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="table-empty">Loading users...</td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="action-bar action-bar-split">
                            <div class="inline-summary" id="userDirectorySummary">Loading user summary...</div>
                            <button class="btn btn-primary" id="refreshAdminDashboardBtn" type="button"><i class="fas fa-arrows-rotate"></i> Refresh data</button>
                        </div>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="card-header">
                        <h3><i class="fas fa-database"></i> Backup & recovery</h3>
                        <span class="badge-new">snapshot</span>
                    </div>
                    <div class="card-body">
                        <div class="toggle-switch">
                            <span>Automatic daily backup</span>
                            <label class="toggle-label"><input type="checkbox" id="autoBackupToggle"> Enable</label>
                        </div>
                        <div class="backup-progress">
                            <div class="backup-bar" id="backupProgressBar"></div>
                        </div>
                        <p class="helper-text" id="lastBackupText">Loading backup status...</p>
                        <div class="action-bar">
                            <button class="btn btn-secondary" id="manualBackupBtn" type="button"><i class="fas fa-cloud-arrow-up"></i> Run manual backup</button>
                            <button class="btn btn-outline" id="restorePointBtn" type="button"><i class="fas fa-clock-rotate-left"></i> Restore</button>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="feature-card card-stack-gap">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-virus"></i> Security audit trail</h3>
                        <span class="badge-new">live feed</span>
                    </div>
                    <div class="card-body">
                        <div class="log-container" id="securityLogContainer">
                            <div class="log-empty">Loading security logs...</div>
                        </div>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="card-header">
                        <h3><i class="fas fa-microchip"></i> System monitoring</h3>
                        <span class="badge-new">settings</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="rateLimitInput">Rate limiting (req/min)</label>
                            <input type="number" id="rateLimitInput" min="1" step="1">
                        </div>
                        <div class="form-group">
                            <label for="logRetentionSelect">Log retention (days)</label>
                            <select id="logRetentionSelect">
                                <option value="30">30 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="maintenanceWindow">Maintenance window</label>
                            <input type="text" id="maintenanceWindow" placeholder="Sunday 02:00-04:00 UTC">
                        </div>
                        <div class="toggle-switch">
                            <span>Enforce 2FA for admins</span>
                            <input type="checkbox" id="enforce2faToggle">
                        </div>
                        <div class="system-health-panel">
                            <div class="system-health-item">
                                <span class="system-health-label">Online users</span>
                                <strong id="onlineUsersMetric">0</strong>
                            </div>
                            <div class="system-health-item">
                                <span class="system-health-label">Admins</span>
                                <strong id="adminUsersMetric">0</strong>
                            </div>
                            <div class="system-health-item">
                                <span class="system-health-label">Verified users</span>
                                <strong id="verifiedUsersMetric">0</strong>
                            </div>
                        </div>
                        <div class="action-bar">
                            <button class="btn btn-primary" id="applySysConfigBtn" type="button"><i class="fas fa-gear"></i> Apply config</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="action-bar global-save-bar">
            <button class="btn btn-primary btn-large" id="saveAdminSettingsBtn" type="button"><i class="fas fa-floppy-disk"></i> Commit all settings (Branding + SMTP + System)</button>
        </div>
    </main>

    <div id="toast" class="toast"></div>

    <script src="js/utils.js?v=<?= $utilsScriptVersion ?>"></script>
    <script src="js/api.js?v=<?= $apiScriptVersion ?>"></script>
    <script src="js/app-settings.js?v=<?= $appSettingsScriptVersion ?>"></script>
    <script src="js/nav.js?v=<?= $navScriptVersion ?>"></script>
    <script src="js/admin.js?v=<?= $adminScriptVersion ?>"></script>
</body>
</html>
