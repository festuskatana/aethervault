const profileViewParams = new URLSearchParams(window.location.search);
const viewedProfileUserId = Number(profileViewParams.get('user_id')) || null;
let profileIsReadOnly = false;
const cachedSelectedChatUser = getSelectedChatUser();
let currentProfile = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!isAuthenticated()) {
        redirectToLogin();
        return;
    }

    bindProfileUI();
    await loadProfile();
});

function bindProfileUI() {
    const avatarInput = document.getElementById('avatarInput');
    const profileForm = document.getElementById('profileForm');
    const avatarWrap = document.querySelector('.avatar-preview-wrap');
    const removeAvatarBtn = document.getElementById('removeAvatarBtn');
    const imageViewer = document.getElementById('profileImageViewer');
    const imageViewerClose = document.getElementById('profileImageViewerClose');
    const enable2faBtn = document.getElementById('enable2faBtn');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const updateRecoveryBtn = document.getElementById('updateRecoveryBtn');
    const connectNewAppBtn = document.getElementById('connectNewAppBtn');
    const sendVerificationOtpBtn = document.getElementById('sendVerificationOtpBtn');
    const verifyEmailOtpBtn = document.getElementById('verifyEmailOtpBtn');

    if (avatarInput) {
        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files?.[0];
            if (file) {
                previewAvatar(file);
            }
        });
    }

    if (profileForm) {
        profileForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (profileIsReadOnly) {
                return;
            }
            await saveProfile();
        });
    }

    if (avatarWrap) {
        avatarWrap.addEventListener('click', () => {
            if (currentProfile?.avatar) {
                openProfileImageViewer(currentProfile.avatar, currentProfile.full_name || currentProfile.username || 'Profile picture');
            }
        });
    }

    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', async () => {
            if (profileIsReadOnly || !currentProfile?.avatar) {
                return;
            }

            const confirmed = await showConfirm({
                title: 'Remove profile picture',
                message: 'Remove your current profile picture?',
                type: 'warning',
                confirmText: 'Remove',
                cancelText: 'Cancel'
            });

            if (!confirmed) {
                return;
            }

            await saveProfile({ removeAvatar: true });
        });
    }

    if (imageViewer) {
        imageViewer.addEventListener('click', (event) => {
            if (event.target.closest('[data-profile-image-close="true"]')) {
                closeProfileImageViewer();
            }
        });
    }

    if (imageViewerClose) {
        imageViewerClose.addEventListener('click', closeProfileImageViewer);
    }

    if (enable2faBtn) {
        enable2faBtn.addEventListener('click', () => {
            showToast('Two-factor management will be added in a follow-up update.', 'info');
        });
    }

    if (changePasswordBtn) {
        changePasswordBtn.addEventListener('click', () => {
            window.location.href = 'forgot-password.php';
        });
    }

    if (updateRecoveryBtn) {
        updateRecoveryBtn.addEventListener('click', () => {
            showToast('Recovery email uses your current account email in this version.', 'info');
        });
    }

    if (connectNewAppBtn) {
        connectNewAppBtn.addEventListener('click', () => {
            showToast('Connected app records are not stored in the current schema yet.', 'info');
        });
    }

    if (sendVerificationOtpBtn) {
        sendVerificationOtpBtn.addEventListener('click', async () => {
            if (profileIsReadOnly || !currentProfile?.email) {
                return;
            }
            await requestVerificationOtp();
        });
    }

    if (verifyEmailOtpBtn) {
        verifyEmailOtpBtn.addEventListener('click', async () => {
            if (profileIsReadOnly || !currentProfile?.email) {
                return;
            }
            await verifyEmailOtp();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeProfileImageViewer();
        }
    });
}

async function loadProfile() {
    try {
        const currentUser = getCurrentUser();
        const shouldUseViewerMode = viewedProfileUserId && Number(currentUser?.id) !== Number(viewedProfileUserId);

        if (shouldUseViewerMode && cachedSelectedChatUser && Number(cachedSelectedChatUser.id) === Number(viewedProfileUserId)) {
            populateProfilePictureOnly(cachedSelectedChatUser);
            toggleProfileReadOnly(true, cachedSelectedChatUser);
        }

        const response = await API.getProfile(viewedProfileUserId);
        if (viewedProfileUserId && Number(response.profile?.id) !== Number(viewedProfileUserId)) {
            throw new Error('Loaded the wrong user profile');
        }
        populateProfile(response.profile);
        renderProfileMeta(response.profile);
        renderSecurityPanel(response.profile);
        renderSessions(response.sessions || []);
        renderActivity(response.activity || []);
        currentProfile = response.profile || null;
        profileIsReadOnly = !response.profile?.is_own_profile;
        toggleProfileReadOnly(profileIsReadOnly, response.profile);
        if (profileIsReadOnly) {
            await loadProfileVault(response.profile);
        }
    } catch (error) {
        console.error('Profile load error:', error);
        showToast('Failed to load profile', 'error');
    } finally {
        document.getElementById('profileSummaryCard')?.classList.remove('loading');
        document.getElementById('profileForm')?.classList.remove('loading');
    }
}

function populateProfile(profile) {
    currentProfile = profile || null;
    setValue('fullName', profile.full_name || '');
    setValue('username', profile.username || '');
    setValue('email', profile.email || '');
    setValue('phone', profile.phone || '');
    setValue('location', profile.location || '');
    setValue('timezone', profile.timezone || 'UTC');
    setValue('language', profile.language || 'en');
    setValue('bio', profile.bio || '');
    setValue('privacyLevel', profile.privacy_level || 'contacts');
    document.getElementById('notificationsEnabled').checked = !!profile.notifications_enabled;

    renderProfileName(profile);
    document.getElementById('profileDisplayEmail').textContent = profile.email || '';
    setAvatarPreview(profile.avatar, profile.username);
    updateProfileTitle(profile);
}

function populateProfilePictureOnly(profile) {
    currentProfile = profile || null;
    renderProfileName(profile);
    document.getElementById('profileDisplayEmail').textContent = '';
    setAvatarPreview(profile.avatar, profile.username);
    updateProfileTitle(profile);
}

function toggleProfileReadOnly(isReadOnly, profile) {
    const form = document.getElementById('profileForm');
    const avatarInput = document.getElementById('avatarInput');
    const avatarUploadLabel = document.getElementById('avatarUploadLabel');
    const saveBtn = document.getElementById('saveProfileBtn');
    const heroTitle = document.getElementById('profileHeroTitle');
    const heroSubtitle = document.getElementById('profileHeroSubtitle');
    const viewerBanner = document.getElementById('profileViewerBanner');
    const profileDisplayName = document.getElementById('profileDisplayName');
    const displayEmail = document.getElementById('profileDisplayEmail');
    const vaultSection = document.getElementById('profileVaultSection');
    const vaultTitle = document.getElementById('profileVaultTitle');
    const vaultLink = document.getElementById('profileVaultLink');
    const removeAvatarBtn = document.getElementById('removeAvatarBtn');
    const ownOnlySections = document.querySelectorAll('[data-own-only]');

    if (!form) {
        return;
    }

    form.querySelectorAll('input, textarea, select').forEach((field) => {
        if (field.id === 'avatarInput') {
            return;
        }
        field.disabled = isReadOnly;
        field.readOnly = isReadOnly && field.tagName !== 'SELECT';
    });

    if (avatarInput) {
        avatarInput.disabled = isReadOnly;
    }

    if (avatarUploadLabel) {
        avatarUploadLabel.hidden = isReadOnly;
    }

    if (removeAvatarBtn) {
        removeAvatarBtn.hidden = isReadOnly || !profile?.avatar;
    }

    if (saveBtn) {
        saveBtn.hidden = isReadOnly;
    }

    ownOnlySections.forEach((section) => {
        section.hidden = isReadOnly;
    });

    if (isReadOnly) {
        const displayName = profile?.full_name || profile?.username || 'This user';
        heroTitle.textContent = 'User profile';
        heroSubtitle.textContent = 'View the profile picture and vault files for this user.';
        document.title = `${displayName} - Profile`;
        if (viewerBanner) {
            viewerBanner.hidden = false;
            viewerBanner.textContent = `You are viewing ${displayName}'s profile.`;
        }

        form.hidden = true;
        if (profileDisplayName) {
            profileDisplayName.hidden = true;
        }
        if (displayEmail) {
            displayEmail.hidden = true;
        }
        if (vaultSection) {
            vaultSection.hidden = false;
        }
        if (vaultTitle) {
            vaultTitle.textContent = `${displayName}'s vault`;
        }
        if (vaultLink && profile?.id) {
            vaultLink.href = `dashboard.php?user_id=${encodeURIComponent(profile.id)}`;
        }
    } else {
        heroTitle.textContent = `${getProfileDisplayName(profile)}'s Profile Dashboard`;
        heroSubtitle.textContent = 'Update your account information, avatar, and privacy preferences from one place.';
        document.title = `${getProfileDisplayName(profile)} - Profile`;
        if (viewerBanner) {
            viewerBanner.hidden = true;
            viewerBanner.textContent = '';
        }

        form.hidden = false;
        if (profileDisplayName) {
            profileDisplayName.hidden = false;
        }
        if (displayEmail) {
            displayEmail.hidden = false;
        }
        if (vaultSection) {
            vaultSection.hidden = true;
        }
        if (vaultLink) {
            vaultLink.href = 'dashboard.php';
        }
    }
}

function updateProfileTitle(profile) {
    if (profileIsReadOnly) {
        return;
    }

    const heroTitle = document.getElementById('profileHeroTitle');
    const displayName = getProfileDisplayName(profile);

    if (heroTitle) {
        heroTitle.textContent = `${displayName}'s Profile Dashboard`;
    }

    document.title = `${displayName} - Profile`;
}

function getProfileDisplayName(profile) {
    return profile?.full_name || profile?.username || profile?.email || 'Profile';
}

function renderProfileMeta(profile) {
    const memberSince = document.getElementById('profileMemberSince');
    const lastSeen = document.getElementById('profileLastSeen');

    if (memberSince) {
        memberSince.textContent = profile?.created_at_human ? `Member since ${profile.created_at_human}` : 'Member since recently';
    }

    if (lastSeen) {
        lastSeen.textContent = profile?.last_active_human ? `Last active ${profile.last_active_human}` : 'Last active unknown';
    }
}

function renderSecurityPanel(profile) {
    const twoFactor = document.getElementById('profile2faStatus');
    const recoveryEmail = document.getElementById('profileRecoveryEmail');
    const verificationStatus = document.getElementById('profileVerificationStatus');
    const sendVerificationOtpBtn = document.getElementById('sendVerificationOtpBtn');
    const verifyEmailOtpBtn = document.getElementById('verifyEmailOtpBtn');

    if (twoFactor) {
        twoFactor.textContent = profile?.two_factor_enabled
            ? 'Enabled for this account.'
            : 'Not enabled yet for this account.';
    }

    if (verificationStatus) {
        verificationStatus.textContent = profile?.email_verified
            ? `Verified with ${profile.email || 'your email address'}.`
            : `Not verified yet. Send an OTP to ${profile.email || 'your email'} to verify this account.`;
    }

    if (sendVerificationOtpBtn) {
        sendVerificationOtpBtn.disabled = !!profile?.email_verified;
    }

    if (verifyEmailOtpBtn) {
        verifyEmailOtpBtn.disabled = !!profile?.email_verified;
    }

    if (recoveryEmail) {
        recoveryEmail.textContent = profile?.email || 'No recovery email found';
    }
}

function renderSessions(sessions) {
    const sessionsList = document.getElementById('profileSessionsList');
    if (!sessionsList) {
        return;
    }

    if (!sessions.length) {
        sessionsList.innerHTML = `
            <div class="profile-empty-card compact">
                <i class="fas fa-desktop"></i>
                <p>No active sessions found.</p>
            </div>
        `;
        return;
    }

    sessionsList.innerHTML = sessions.map((session) => `
        <div class="profile-list-item">
            <div>
                <strong>${escapeHtml(session.label || 'Active session')}</strong>
                <p>${escapeHtml(session.ip_address || 'Unknown IP')}</p>
                <div class="profile-session-meta">
                    <span>Started ${escapeHtml(session.created_at_human || 'recently')}</span>
                    <span>Last seen ${escapeHtml(session.last_activity_human || 'recently')}</span>
                </div>
            </div>
            <span class="profile-session-badge">${session.is_current ? 'Current session' : 'Active'}</span>
        </div>
    `).join('');
}

function renderActivity(activity) {
    const activityList = document.getElementById('profileActivityList');
    if (!activityList) {
        return;
    }

    if (!activity.length) {
        activityList.innerHTML = `
            <div class="profile-empty-card compact">
                <i class="fas fa-chart-line"></i>
                <p>No recent activity recorded yet.</p>
            </div>
        `;
        return;
    }

    activityList.innerHTML = activity.map((item) => `
        <div class="profile-activity-item">
            <div class="profile-activity-icon">
                <i class="fas ${getProfileActivityIcon(item.action)}"></i>
            </div>
            <div class="profile-activity-content">
                <strong>${escapeHtml(formatProfileAction(item.action))}</strong>
                <p>${escapeHtml(item.details || 'Account activity recorded.')}</p>
            </div>
            <div class="profile-activity-time">${escapeHtml(item.created_at_human || '')}</div>
        </div>
    `).join('');
}

function getProfileActivityIcon(action) {
    const iconMap = {
        login: 'fa-right-to-bracket',
        logout: 'fa-right-from-bracket',
        upload_media: 'fa-cloud-arrow-up',
        send_message: 'fa-envelope',
        admin_update_settings: 'fa-gear',
        update_profile: 'fa-user-pen',
        register: 'fa-user-plus'
    };

    return iconMap[action] || 'fa-clock-rotate-left';
}

function formatProfileAction(action) {
    return String(action || 'activity')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

async function loadProfileVault(profile) {
    const vaultGrid = document.getElementById('profileVaultGrid');
    if (!vaultGrid || !profile?.id) {
        return;
    }

    vaultGrid.innerHTML = createSkeletonLoader(3);

    try {
        const response = await API.getMedia(profile.id);
        if (Number(response.vault_owner?.id) !== Number(profile.id)) {
            throw new Error('Loaded the wrong vault');
        }

        const mediaItems = response.media || [];
        if (!mediaItems.length) {
            vaultGrid.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-photo-film"></i>
                    <p>No files in this vault yet.</p>
                </div>
            `;
            return;
        }

        vaultGrid.innerHTML = mediaItems.map((item) => {
            const mediaType = item.file_type || item.type;
            const mediaName = item.original_filename || item.filename || 'Untitled media';
            const mediaUrl = item.url || item.file_path || '';
            const thumb = mediaType === 'image'
                ? `<img class="profile-vault-asset" src="${escapeAttribute(mediaUrl)}" alt="${escapeAttribute(mediaName)}" loading="lazy">`
                : `<video class="profile-vault-asset" src="${escapeAttribute(mediaUrl)}" preload="metadata" muted playsinline loop autoplay></video>`;

            return `
                <article class="profile-vault-item">
                    <a class="profile-vault-thumb" href="dashboard.php?user_id=${encodeURIComponent(profile.id)}" aria-label="Open ${escapeAttribute(mediaName)} in vault">
                        ${thumb}
                        <span class="profile-vault-badge">${escapeHtml(mediaType)}</span>
                    </a>
                </article>
            `;
        }).join('');
    } catch (error) {
        console.error('Profile vault load error:', error);
        vaultGrid.innerHTML = `
            <div class="error-state">
                <i class="fas fa-triangle-exclamation"></i>
                <p>Failed to load this vault.</p>
            </div>
        `;
    }
}

async function saveProfile(options = {}) {
    const form = document.getElementById('profileForm');
    const saveBtn = document.getElementById('saveProfileBtn');
    const formData = new FormData(form);

    if (!document.getElementById('notificationsEnabled').checked) {
        formData.set('notifications_enabled', '0');
    }

    if (options.removeAvatar) {
        formData.set('remove_avatar', '1');
    }

    saveBtn.disabled = true;

    try {
        const response = await fetch(`${API_BASE_URL}update_profile.php`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`
            },
            body: formData
        });

        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Failed to update profile');
        }

        const currentUser = getCurrentUser() || {};
        currentUser.username = formData.get('username');
        currentUser.email = formData.get('email');
        currentUser.email_verified = !!data.email_verified;
        setCurrentUser(currentUser);
        if (typeof populateNavigationUser === 'function') {
            populateNavigationUser();
        }

        showToast('Profile updated successfully', 'success');
        await loadProfile();
    } catch (error) {
        console.error('Profile save error:', error);
        showToast(error.message || 'Failed to update profile', 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

function openProfileImageViewer(src, title) {
    const viewer = document.getElementById('profileImageViewer');
    const asset = document.getElementById('profileImageViewerAsset');
    const heading = document.getElementById('profileImageViewerTitle');

    if (!viewer || !asset || !src) {
        return;
    }

    asset.src = src;
    asset.alt = title || 'Profile picture preview';
    if (heading) {
        heading.textContent = title || 'Avatar preview';
    }
    viewer.hidden = false;
    document.body.classList.add('dialog-open');
}

function closeProfileImageViewer() {
    const viewer = document.getElementById('profileImageViewer');
    const asset = document.getElementById('profileImageViewerAsset');

    if (!viewer || viewer.hidden) {
        return;
    }

    viewer.hidden = true;
    if (asset) {
        asset.removeAttribute('src');
    }
    document.body.classList.remove('dialog-open');
}

function previewAvatar(file) {
    const reader = new FileReader();
    reader.onload = () => {
        setAvatarPreview(reader.result, document.getElementById('username').value || 'U');
    };
    reader.readAsDataURL(file);
}

function setAvatarPreview(src, username) {
    const avatarPreview = document.getElementById('avatarPreview');
    const avatarFallback = document.getElementById('avatarFallback');
    const initial = (username || 'U').charAt(0).toUpperCase();

    if (src) {
        avatarPreview.src = src;
        avatarPreview.style.display = 'block';
        avatarFallback.style.display = 'none';
    } else {
        avatarPreview.removeAttribute('src');
        avatarPreview.style.display = 'none';
        avatarFallback.style.display = 'flex';
        avatarFallback.textContent = initial;
    }
}

function setValue(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.value = value;
    }
}

function getSelectedChatUser() {
    try {
        const stored = sessionStorage.getItem('selected_chat_user');
        return stored ? JSON.parse(stored) : null;
    } catch (error) {
        return null;
    }
}

function renderProfileName(profile) {
    const profileDisplayName = document.getElementById('profileDisplayName');
    if (!profileDisplayName) {
        return;
    }

    const displayName = escapeHtml(profile?.full_name || profile?.username || 'User');
    profileDisplayName.innerHTML = `${displayName}${profile?.email_verified ? ' <span class="verified-badge" title="Verified email" aria-label="Verified email"><i class="fas fa-circle-check"></i></span>' : ''}`;
}

async function requestVerificationOtp() {
    try {
        const response = await API.requestEmailVerification(currentProfile.email);
        showToast(response.message || 'Verification OTP sent', 'success');
    } catch (error) {
        console.error('Verification OTP request error:', error);
        showToast(error.message || 'Failed to send verification OTP', 'error');
    }
}

async function verifyEmailOtp() {
    const result = await showPrompt({
        title: 'Verify email',
        message: `Enter the OTP sent to ${currentProfile.email}.`,
        inputPlaceholder: '6-digit OTP',
        confirmText: 'Verify'
    });

    if (!result.isConfirmed) {
        return;
    }

    const otp = String(result.value || '').trim();
    if (!otp) {
        showToast('OTP is required', 'error');
        return;
    }

    try {
        const response = await API.verifyEmailVerification(currentProfile.email, otp);
        currentProfile = {
            ...currentProfile,
            email_verified: !!response.email_verified
        };

        const currentUser = getCurrentUser() || {};
        currentUser.email_verified = !!response.email_verified;
        setCurrentUser(currentUser);

        renderProfileName(currentProfile);
        renderSecurityPanel(currentProfile);
        if (typeof populateNavigationUser === 'function') {
            populateNavigationUser();
        }

        showToast(response.message || 'Email verified', 'success');
    } catch (error) {
        console.error('Email verification error:', error);
        showToast(error.message || 'Failed to verify email', 'error');
    }
}
