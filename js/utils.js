document.addEventListener('DOMContentLoaded', () => {
    ensureUiLayer();
});

function ensureUiLayer() {
    ensureToastElement();
    ensureAlertElement();
}

function ensureToastElement() {
    if (document.getElementById('toast')) {
        return;
    }

    const toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    document.body.appendChild(toast);
}

function ensureAlertElement() {
    if (document.getElementById('appAlertBackdrop')) {
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="app-alert-backdrop" id="appAlertBackdrop" hidden>
            <div class="app-alert-modal" role="dialog" aria-modal="true" aria-labelledby="appAlertTitle" aria-describedby="appAlertMessage">
                <button class="app-alert-close" id="appAlertClose" type="button" aria-label="Close dialog">
                    <i class="fas fa-times"></i>
                </button>
                <div class="app-alert-icon" id="appAlertIcon">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 id="appAlertTitle">Notice</h3>
                <p id="appAlertMessage"></p>
                <label class="app-alert-input-wrap" id="appAlertInputWrap" hidden>
                    <input type="text" id="appAlertInput">
                </label>
                <div class="app-alert-actions">
                    <button class="secondary-btn app-alert-cancel" id="appAlertCancel" type="button">Cancel</button>
                    <button class="primary-btn app-alert-confirm" id="appAlertConfirm" type="button">Continue</button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(wrapper.firstElementChild);
}

// Toast Notification
function showToast(message, type = 'info') {
    ensureToastElement();

    const toast = document.getElementById('toast');
    const iconMap = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-bell'
    };

    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${iconMap[type] || iconMap.info}"></i></div>
        <div class="toast-copy">
            <strong>${type === 'error' ? 'Action failed' : type === 'success' ? 'Success' : 'Notice'}</strong>
            <span>${escapeHtml(message)}</span>
        </div>
    `;
    toast.className = `toast ${type}`;
    toast.classList.add('show');

    window.clearTimeout(showToast.dismissTimer);
    showToast.dismissTimer = window.setTimeout(() => {
        toast.classList.remove('show');
    }, 3200);
}

async function showAlert(options = {}) {
    const result = await showDialog({
        title: options.title || 'Notice',
        message: options.message || '',
        type: options.type || 'info',
        confirmText: options.confirmText || 'OK',
        cancelText: options.cancelText || 'Cancel',
        showCancel: Boolean(options.showCancel),
        input: Boolean(options.input),
        inputValue: options.inputValue || '',
        inputPlaceholder: options.inputPlaceholder || '',
        allowClose: options.allowClose !== false
    });

    return options.input ? result : result.isConfirmed;
}

function showConfirm(options = {}) {
    return showAlert({
        ...options,
        showCancel: true
    });
}

function showPrompt(options = {}) {
    return showAlert({
        ...options,
        input: true,
        showCancel: true
    });
}

function showDialog({
    title,
    message,
    type,
    confirmText,
    cancelText,
    showCancel,
    input,
    inputValue,
    inputPlaceholder,
    allowClose
}) {
    ensureAlertElement();

    const backdrop = document.getElementById('appAlertBackdrop');
    const modal = backdrop.querySelector('.app-alert-modal');
    const titleEl = document.getElementById('appAlertTitle');
    const messageEl = document.getElementById('appAlertMessage');
    const iconEl = document.getElementById('appAlertIcon');
    const inputWrap = document.getElementById('appAlertInputWrap');
    const inputEl = document.getElementById('appAlertInput');
    const confirmBtn = document.getElementById('appAlertConfirm');
    const cancelBtn = document.getElementById('appAlertCancel');
    const closeBtn = document.getElementById('appAlertClose');

    const iconMap = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation',
        warning: 'fa-triangle-exclamation',
        info: 'fa-bell'
    };

    titleEl.textContent = title;
    messageEl.textContent = message;
    iconEl.className = `app-alert-icon ${type}`;
    iconEl.innerHTML = `<i class="fas ${iconMap[type] || iconMap.info}"></i>`;
    confirmBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;
    cancelBtn.hidden = !showCancel;
    closeBtn.hidden = !allowClose;
    inputWrap.hidden = !input;
    inputEl.value = inputValue;
    inputEl.placeholder = inputPlaceholder;
    backdrop.hidden = false;
    backdrop.classList.add('show');
    document.body.classList.add('dialog-open');

    return new Promise((resolve) => {
        const close = (result) => {
            backdrop.classList.remove('show');
            backdrop.hidden = true;
            document.body.classList.remove('dialog-open');
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            closeBtn.removeEventListener('click', onCancel);
            backdrop.removeEventListener('click', onBackdrop);
            inputEl.removeEventListener('keydown', onKeydown);
            modal.removeEventListener('keydown', onKeydown);
            resolve(result);
        };

        const onConfirm = () => close({
            isConfirmed: true,
            value: input ? inputEl.value : null
        });
        const onCancel = () => close({
            isConfirmed: false,
            value: null
        });
        const onBackdrop = (event) => {
            if (event.target === backdrop && allowClose) {
                onCancel();
            }
        };
        const onKeydown = (event) => {
            if (event.key === 'Escape' && allowClose) {
                onCancel();
            }

            if (event.key === 'Enter' && (event.target === inputEl || event.target === modal)) {
                event.preventDefault();
                onConfirm();
            }
        };

        confirmBtn.addEventListener('click', onConfirm, { once: true });
        cancelBtn.addEventListener('click', onCancel, { once: true });
        closeBtn.addEventListener('click', onCancel, { once: true });
        backdrop.addEventListener('click', onBackdrop);
        inputEl.addEventListener('keydown', onKeydown);
        modal.addEventListener('keydown', onKeydown);

        window.setTimeout(() => {
            if (input) {
                inputEl.focus();
                inputEl.select();
            } else {
                confirmBtn.focus();
            }
        }, 10);
    });
}

// Format Date
function formatMessageTime(date) {
    const now = new Date();
    const messageDate = new Date(date);
    const diff = now - messageDate;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return messageDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return messageDate.toLocaleDateString();
}

// Format File Size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Debounce Function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Get Auth Token
function getAuthToken() {
    return localStorage.getItem('auth_token');
}

// Set Auth Token
function setAuthToken(token) {
    localStorage.setItem('auth_token', token);
}

// Get Current User
function getCurrentUser() {
    const user = localStorage.getItem('current_user');
    return user ? JSON.parse(user) : null;
}

// Set Current User
function setCurrentUser(user) {
    localStorage.setItem('current_user', JSON.stringify(user));
}

// Clear Auth Data
function clearAuthData() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('current_user');
}

// Check Authentication
function isAuthenticated() {
    return !!getAuthToken();
}

// Redirect to Login
function redirectToLogin() {
    window.location.href = 'index.php';
}

async function handleLogout() {
    try {
        if (getAuthToken()) {
            await API.logout();
        }
    } catch (error) {
        console.error('Logout error:', error);
    } finally {
        clearAuthData();
        showToast('Logged out successfully', 'success');
        setTimeout(() => {
            redirectToLogin();
        }, 500);
    }
}

function initLogoutButton() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (!logoutBtn || logoutBtn.dataset.bound === 'true') {
        return;
    }

    logoutBtn.dataset.bound = 'true';
    logoutBtn.addEventListener('click', (event) => {
        event.preventDefault();
        handleLogout();
    });
}

// Generate Avatar Color
function getAvatarColor(username) {
    let hash = 0;
    for (let i = 0; i < username.length; i++) {
        hash = username.charCodeAt(i) + ((hash << 5) - hash);
    }
    const colors = ['#0A4D4C', '#D4AF37', '#E74C3C', '#3498DB', '#2ECC71', '#9B59B6'];
    return colors[Math.abs(hash) % colors.length];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function escapeAttribute(text) {
    return String(text ?? '').replace(/"/g, '&quot;');
}

// Create Skeleton Loader
function createSkeletonLoader(count = 6) {
    let html = '<div class="skeleton-loader">';
    for (let i = 0; i < count; i++) {
        html += '<div class="skeleton shimmer"></div>';
    }
    html += '</div>';
    return html;
}
