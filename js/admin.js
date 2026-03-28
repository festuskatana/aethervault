let adminDashboardState = {
    settings: {},
    metrics: {},
    users: [],
    logs: [],
    admin: null
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!isAuthenticated()) {
        redirectToLogin();
        return;
    }

    const currentUser = getCurrentUser();
    if (!currentUser?.is_admin) {
        window.location.href = 'dashboard.php';
        return;
    }

    bindAdminUi();
    startLiveClock();
    await loadAdminDashboard();
});

function bindAdminUi() {
    const logoInput = document.getElementById('appLogo');
    const saveBtn = document.getElementById('saveAdminSettingsBtn');
    const testSmtpBtn = document.getElementById('testSmtpBtn');
    const refreshBtn = document.getElementById('refreshAdminDashboardBtn');
    const applyConfigBtn = document.getElementById('applySysConfigBtn');
    const manualBackupBtn = document.getElementById('manualBackupBtn');
    const restorePointBtn = document.getElementById('restorePointBtn');

    if (logoInput) {
        logoInput.addEventListener('change', () => {
            const file = logoInput.files?.[0];
            if (!file) {
                setAdminLogoPreview(adminDashboardState.settings.app_logo_url || null);
                return;
            }

            const reader = new FileReader();
            reader.onload = () => setAdminLogoPreview(reader.result);
            reader.readAsDataURL(file);
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', saveAdminSettings);
    }

    if (testSmtpBtn) {
        testSmtpBtn.addEventListener('click', testSmtpConfiguration);
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadAdminDashboard);
    }

    if (applyConfigBtn) {
        applyConfigBtn.addEventListener('click', saveAdminSettings);
    }

    if (manualBackupBtn) {
        manualBackupBtn.addEventListener('click', runBackupSimulation);
    }

    if (restorePointBtn) {
        restorePointBtn.addEventListener('click', showRestoreNotice);
    }
}

async function loadAdminDashboard() {
    try {
        const response = await API.getAdminDashboard();
        adminDashboardState = {
            settings: response.settings || {},
            metrics: response.metrics || {},
            users: response.users || [],
            logs: response.logs || [],
            admin: response.admin || null
        };

        populateSettings(adminDashboardState.settings);
        renderMetrics(adminDashboardState.metrics);
        renderUserTable(adminDashboardState.users, adminDashboardState.metrics);
        renderSecurityLogs(adminDashboardState.logs);
        renderAdminSummary(adminDashboardState.admin, adminDashboardState.metrics);
        renderBackupStatus(adminDashboardState.settings);
    } catch (error) {
        showToast(error.message || 'Failed to load admin dashboard', 'error');
    }
}

function populateSettings(settings) {
    setValue('appName', settings.app_name || 'Aether Vault');
    setValue('smtpHost', settings.smtp_host || '');
    setValue('smtpPort', settings.smtp_port || 587);
    setValue('smtpUsername', settings.smtp_username || '');
    setValue('smtpSecure', settings.smtp_secure || 'tls');
    setValue('smtpFromEmail', settings.smtp_from_email || '');
    setValue('smtpFromName', settings.smtp_from_name || settings.app_name || 'Aether Vault');
    setValue('rateLimitInput', settings.rate_limit_per_minute || 120);
    setValue('logRetentionSelect', settings.log_retention_days || 60);
    setValue('maintenanceWindow', settings.maintenance_window || 'Sunday 02:00-04:00 UTC');
    setCheckboxValue('autoBackupToggle', Boolean(settings.auto_backup_enabled));
    setCheckboxValue('enforce2faToggle', Boolean(settings.enforce_admin_2fa));
    setAdminLogoPreview(settings.app_logo_url || null);

    const smtpModeBadge = document.getElementById('smtpModeBadge');
    if (smtpModeBadge) {
        smtpModeBadge.textContent = settings.smtp_host ? String(settings.smtp_secure || 'smtp').toUpperCase() : 'not set';
    }

    const smtpPasswordHint = document.getElementById('smtpPasswordHint');
    if (smtpPasswordHint) {
        smtpPasswordHint.textContent = settings.smtp_has_password
            ? 'Leave blank to keep the current password.'
            : 'No SMTP password is saved yet. Enter one to store it.';
    }
}

function renderMetrics(metrics) {
    setText('totalUsersMetric', formatNumber(metrics.total_users));
    setText('activeSessionsMetric', formatNumber(metrics.active_sessions));
    setText('apiCallsMetric', formatNumber(metrics.activity_logs_24h));
    setText('storageMetric', formatFileSize(Number(metrics.storage_used_bytes || 0)));
    setText('uptimeMetric', formatNumber(metrics.total_messages));
    setText('onlineUsersMetric', formatNumber(metrics.online_users));
    setText('adminUsersMetric', formatNumber(metrics.admin_users));
    setText('verifiedUsersMetric', formatNumber(metrics.verified_users));
}

function renderUserTable(users, metrics) {
    const tbody = document.querySelector('#userManagementTable tbody');
    if (!tbody) {
        return;
    }

    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="table-empty">No users found in the database.</td></tr>';
    } else {
        tbody.innerHTML = users.map((user) => `
            <tr>
                <td>${escapeHtml(user.email)}</td>
                <td><span class="role-badge">${escapeHtml(user.role)}</span></td>
                <td><span class="status-pill ${user.status === 'active' ? 'active' : 'inactive'}">${escapeHtml(user.status)}</span></td>
                <td>${escapeHtml(user.last_active_human || 'Never')}</td>
            </tr>
        `).join('');
    }

    const summary = document.getElementById('userDirectorySummary');
    if (summary) {
        summary.textContent = `${formatNumber(metrics.admin_users || 0)} admins, ${formatNumber(metrics.verified_users || 0)} verified users, ${formatNumber(metrics.online_users || 0)} online now`;
    }
}

function renderSecurityLogs(logs) {
    const container = document.getElementById('securityLogContainer');
    if (!container) {
        return;
    }

    if (!logs.length) {
        container.innerHTML = '<div class="log-empty">No activity logs found yet.</div>';
        return;
    }

    container.innerHTML = logs.map((log) => `
        <div class="log-item">
            <i class="fas ${getLogIcon(log.action)} log-icon-small"></i>
            <div class="log-text">
                <strong>${escapeHtml(formatLogTitle(log.action))}</strong><br>
                <span>${escapeHtml(log.details || `${log.actor} performed ${log.action}`)}</span>
            </div>
            <div class="log-time">${escapeHtml(log.time_ago || '')}</div>
        </div>
    `).join('');
}

function renderAdminSummary(admin, metrics) {
    renderAdminTitle(admin);

    const summary = document.getElementById('adminHeroSummary');
    if (!summary) {
        return;
    }

    const adminName = admin?.email || admin?.username || 'the current admin';
    summary.textContent = `${adminName} is viewing ${formatNumber(metrics.total_users || 0)} users, ${formatNumber(metrics.total_media || 0)} media items, and ${formatNumber(metrics.activity_logs_24h || 0)} activity logs from the last 24 hours.`;
}

function renderAdminTitle(admin) {
    const titleElement = document.getElementById('adminPageTitle');
    const displayName = admin?.username || admin?.email || getCurrentUser()?.username || 'Admin';

    if (titleElement) {
        titleElement.innerHTML = `<i class="fas fa-gem"></i> ${escapeHtml(displayName)}'s Admin Dashboard`;
    }

    document.title = `${displayName} - Admin`;
}

function renderBackupStatus(settings) {
    const lastBackupText = document.getElementById('lastBackupText');
    const backupBar = document.getElementById('backupProgressBar');

    if (lastBackupText) {
        lastBackupText.textContent = settings.last_backup_at
            ? `Last backup: ${formatDateTime(settings.last_backup_at)}`
            : 'Last backup: No backup timestamp stored yet';
    }

    if (backupBar) {
        backupBar.style.width = settings.last_backup_at ? '100%' : '12%';
    }
}

async function saveAdminSettings() {
    const saveBtn = document.getElementById('saveAdminSettingsBtn');
    const formData = new FormData();

    formData.set('app_name', getFieldValue('appName'));
    formData.set('smtp_host', getFieldValue('smtpHost'));
    formData.set('smtp_port', getFieldValue('smtpPort'));
    formData.set('smtp_username', getFieldValue('smtpUsername'));
    formData.set('smtp_password', document.getElementById('smtpPassword')?.value || '');
    formData.set('smtp_secure', getFieldValue('smtpSecure'));
    formData.set('smtp_from_email', getFieldValue('smtpFromEmail'));
    formData.set('smtp_from_name', getFieldValue('smtpFromName'));
    formData.set('auto_backup_enabled', document.getElementById('autoBackupToggle')?.checked ? '1' : '0');
    formData.set('rate_limit_per_minute', getFieldValue('rateLimitInput'));
    formData.set('log_retention_days', getFieldValue('logRetentionSelect'));
    formData.set('maintenance_window', getFieldValue('maintenanceWindow'));
    formData.set('enforce_admin_2fa', document.getElementById('enforce2faToggle')?.checked ? '1' : '0');

    const logoFile = document.getElementById('appLogo')?.files?.[0];
    if (logoFile) {
        formData.append('app_logo', logoFile);
    }

    if (saveBtn) {
        saveBtn.disabled = true;
    }

    try {
        await API.updateAdminSettings(formData);
        await loadAdminDashboard();
        if (typeof loadAppBranding === 'function') {
            await loadAppBranding(true);
        }
        const smtpPassword = document.getElementById('smtpPassword');
        if (smtpPassword) {
            smtpPassword.value = '';
        }
        showToast('Admin settings saved', 'success');
    } catch (error) {
        showToast(error.message || 'Failed to save admin settings', 'error');
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
        }
    }
}

async function testSmtpConfiguration() {
    const testButton = document.getElementById('testSmtpBtn');
    const testEmail = getFieldValue('smtpTestEmail');

    if (!testEmail) {
        showToast('Enter a test email address first', 'error');
        return;
    }

    if (testButton) {
        testButton.disabled = true;
    }

    try {
        const response = await API.testAdminSmtp(testEmail);
        showToast(response.message || 'Test email sent successfully', 'success');
        await loadAdminDashboard();
    } catch (error) {
        showToast(error.message || 'SMTP test failed', 'error');
    } finally {
        if (testButton) {
            testButton.disabled = false;
        }
    }
}

function runBackupSimulation() {
    const backupBar = document.getElementById('backupProgressBar');
    const lastBackupText = document.getElementById('lastBackupText');
    if (!backupBar) {
        return;
    }

    let width = 0;
    const interval = window.setInterval(() => {
        width += 20;
        backupBar.style.width = `${Math.min(width, 100)}%`;
        if (width >= 100) {
            window.clearInterval(interval);
            if (lastBackupText) {
                lastBackupText.textContent = 'Last backup: Manual run completed locally. Save settings to keep configuration changes.';
            }
            showToast('Manual backup simulation completed', 'success');
        }
    }, 180);
}

function showRestoreNotice() {
    showToast('Restore points are not stored in the current database schema yet.', 'warning');
}

function startLiveClock() {
    updateClock();
    window.setInterval(updateClock, 1000);
}

function updateClock() {
    const clock = document.getElementById('liveClock');
    if (!clock) {
        return;
    }

    const now = new Date();
    clock.innerHTML = `<i class="far fa-clock"></i> ${now.toLocaleString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    })}`;
}

function setAdminLogoPreview(src) {
    const image = document.getElementById('adminLogoPreview');
    const fallback = document.getElementById('adminLogoFallback');

    if (!image || !fallback) {
        return;
    }

    if (src) {
        image.src = src;
        image.style.display = 'block';
        fallback.style.display = 'none';
    } else {
        image.removeAttribute('src');
        image.style.display = 'none';
        fallback.style.display = 'flex';
    }
}

function getLogIcon(action) {
    const iconMap = {
        login: 'fa-key',
        logout: 'fa-arrow-right-from-bracket',
        register: 'fa-user-plus',
        upload_media: 'fa-cloud-arrow-up',
        send_message: 'fa-envelope',
        admin_update_settings: 'fa-gear',
        admin_test_smtp: 'fa-paper-plane'
    };

    return iconMap[action] || 'fa-shield-halved';
}

function formatLogTitle(action) {
    return String(action || 'activity').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString();
}

function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.value = value;
    }
}

function setCheckboxValue(id, checked) {
    const element = document.getElementById(id);
    if (element) {
        element.checked = Boolean(checked);
    }
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function getFieldValue(id) {
    return document.getElementById(id)?.value?.trim() || '';
}
