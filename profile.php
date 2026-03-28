<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault - Profile</title>
    <?php
    $styleVersion = @filemtime(__DIR__ . '/css/style.css') ?: time();
    $profileStyleVersion = @filemtime(__DIR__ . '/css/profile.css') ?: time();
    $utilsScriptVersion = @filemtime(__DIR__ . '/js/utils.js') ?: time();
    $apiScriptVersion = @filemtime(__DIR__ . '/js/api.js') ?: time();
    $appSettingsScriptVersion = @filemtime(__DIR__ . '/js/app-settings.js') ?: time();
    $navScriptVersion = @filemtime(__DIR__ . '/js/nav.js') ?: time();
    $profileScriptVersion = @filemtime(__DIR__ . '/js/profile.js') ?: time();
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/profile.css?v=<?= $profileStyleVersion ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $activePage = 'profile'; include __DIR__ . '/includes/navigation.php'; ?>

    <main class="profile-shell">
        <section class="profile-hero">
            <p class="section-kicker">Account</p>
            <h1 id="profileHeroTitle">Profile settings</h1>
            <p id="profileHeroSubtitle">Update your account information, avatar, and privacy preferences from one place.</p>
            <div class="profile-viewer-banner" id="profileViewerBanner" hidden></div>
        </section>

        <section class="profile-grid">
            <aside class="profile-column">
                <section class="profile-card profile-summary loading" id="profileSummaryCard">
                    <div class="profile-summary-skeleton">
                        <div class="skeleton avatar"></div>
                        <div class="skeleton line"></div>
                        <div class="skeleton line"></div>
                        <div class="skeleton line"></div>
                    </div>
                    <div class="avatar-preview-wrap">
                        <img id="avatarPreview" class="avatar-preview" alt="Profile avatar">
                        <div id="avatarFallback" class="avatar-fallback">A</div>
                    </div>
                    <h2 id="profileDisplayName">User</h2>
                    <p id="profileDisplayEmail">email@example.com</p>
                    <div class="profile-summary-meta">
                        <span class="profile-meta-pill" id="profileMemberSince">Member since --</span>
                        <span class="profile-meta-pill" id="profileLastSeen">Last active --</span>
                    </div>
                    <div class="avatar-actions" id="avatarActions">
                        <label class="avatar-upload-btn" id="avatarUploadLabel" for="avatarInput">
                            <i class="fas fa-camera"></i>
                            <span>Change photo</span>
                        </label>
                        <button class="avatar-remove-btn" id="removeAvatarBtn" type="button">
                            <i class="fas fa-trash"></i>
                            <span>Remove photo</span>
                        </button>
                    </div>
                </section>

                <section class="profile-card profile-side-card" data-own-only>
                    <div class="profile-section-head">
                        <div>
                            <p class="section-kicker">Security</p>
                            <h2>Security essentials</h2>
                        </div>
                    </div>
                    <div class="profile-stack-list">
                        <div class="profile-list-item">
                            <div>
                                <strong>Email verification</strong>
                                <p id="profileVerificationStatus">Checking verification status...</p>
                            </div>
                            <div class="profile-inline-actions">
                                <button class="secondary-btn" id="sendVerificationOtpBtn" type="button">Send OTP</button>
                                <button class="secondary-btn" id="verifyEmailOtpBtn" type="button">Verify</button>
                            </div>
                        </div>
                        <div class="profile-list-item">
                            <div>
                                <strong>Two-factor authentication</strong>
                                <p id="profile2faStatus">Checking protection level...</p>
                            </div>
                            <button class="secondary-btn" id="enable2faBtn" type="button">Manage</button>
                        </div>
                        <div class="profile-list-item">
                            <div>
                                <strong>Password</strong>
                                <p id="profilePasswordStatus">Use a strong password and rotate it when needed.</p>
                            </div>
                            <button class="secondary-btn" id="changePasswordBtn" type="button">Update</button>
                        </div>
                        <div class="profile-list-item">
                            <div>
                                <strong>Recovery email</strong>
                                <p id="profileRecoveryEmail">Loading recovery contact...</p>
                            </div>
                            <button class="secondary-btn" id="updateRecoveryBtn" type="button">Edit</button>
                        </div>
                    </div>
                </section>

                <section class="profile-card profile-side-card" data-own-only>
                    <div class="profile-section-head">
                        <div>
                            <p class="section-kicker">Apps</p>
                            <h2>Connected apps</h2>
                        </div>
                    </div>
                    <div class="profile-empty-card" id="connectedAppsState">
                        <i class="fas fa-plug-circle-bolt"></i>
                        <p>No connected app records are stored in this version yet.</p>
                        <button class="secondary-btn" id="connectNewAppBtn" type="button">Connect new app</button>
                    </div>
                </section>
            </aside>

            <section class="profile-column">
                <section class="profile-card">
                    <form id="profileForm" class="profile-form loading">
                        <div class="profile-form-skeleton">
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton"></div>
                            <div class="skeleton large"></div>
                        </div>
                        <input type="file" id="avatarInput" name="avatar" accept="image/*" hidden>

                        <div class="profile-section-head">
                            <div>
                                <p class="section-kicker">Profile</p>
                                <h2>Personal information</h2>
                            </div>
                        </div>

                        <div class="form-grid">
                            <label>
                                <span>Full name</span>
                                <input type="text" id="fullName" name="full_name">
                            </label>
                            <label>
                                <span>Username</span>
                                <input type="text" id="username" name="username" required>
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" id="email" name="email" required>
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="text" id="phone" name="phone">
                            </label>
                            <label>
                                <span>Location</span>
                                <input type="text" id="location" name="location">
                            </label>
                            <label>
                                <span>Timezone</span>
                                <input type="text" id="timezone" name="timezone" value="UTC">
                            </label>
                            <label>
                                <span>Language</span>
                                <input type="text" id="language" name="language" value="en">
                            </label>
                            <label>
                                <span>Privacy</span>
                                <select id="privacyLevel" name="privacy_level">
                                    <option value="public">Public</option>
                                    <option value="contacts">Contacts</option>
                                    <option value="private">Private</option>
                                </select>
                            </label>
                        </div>

                        <label class="full-width">
                            <span>Bio</span>
                            <textarea id="bio" name="bio" rows="4"></textarea>
                        </label>

                        <label class="checkbox-row">
                            <input type="checkbox" id="notificationsEnabled" name="notifications_enabled" value="1">
                            <span>Enable notifications</span>
                        </label>

                        <div class="form-actions">
                            <button type="submit" class="primary-btn" id="saveProfileBtn">
                                <i class="fas fa-floppy-disk"></i>
                                <span>Save changes</span>
                            </button>
                        </div>
                    </form>
                </section>

                <section class="profile-card profile-detail-card" data-own-only>
                    <div class="profile-section-head">
                        <div>
                            <p class="section-kicker">Sessions</p>
                            <h2>Active sessions</h2>
                        </div>
                    </div>
                    <div class="profile-stack-list" id="profileSessionsList">
                        <div class="profile-empty-card compact">
                            <i class="fas fa-desktop"></i>
                            <p>Loading active sessions...</p>
                        </div>
                    </div>
                </section>

                <section class="profile-card profile-detail-card">
                    <div class="profile-section-head">
                        <div>
                            <p class="section-kicker">Activity</p>
                            <h2>Recent activity</h2>
                        </div>
                    </div>
                    <div class="profile-activity-list" id="profileActivityList">
                        <div class="profile-empty-card compact">
                            <i class="fas fa-chart-line"></i>
                            <p>Loading recent activity...</p>
                        </div>
                    </div>
                </section>
            </section>
        </section>

        <section class="profile-card profile-vault" id="profileVaultSection" hidden>
            <div class="profile-vault-head">
                <div>
                    <p class="section-kicker">Vault</p>
                    <h2 id="profileVaultTitle">User vault</h2>
                </div>
                <a id="profileVaultLink" class="primary-btn profile-vault-link" href="dashboard.php">
                    <i class="fas fa-images"></i>
                    <span>Open full vault</span>
                </a>
            </div>
            <div class="profile-vault-grid" id="profileVaultGrid">
                <div class="skeleton-loader">
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                </div>
            </div>
        </section>
    </main>

    <div class="profile-image-viewer" id="profileImageViewer" hidden>
        <div class="profile-image-viewer-backdrop" data-profile-image-close="true"></div>
        <div class="profile-image-viewer-dialog" role="dialog" aria-modal="true" aria-labelledby="profileImageViewerTitle">
            <div class="profile-image-viewer-topbar">
                <div>
                    <span class="section-kicker">Profile picture</span>
                    <h3 id="profileImageViewerTitle">Avatar preview</h3>
                </div>
                <button class="profile-image-viewer-close" id="profileImageViewerClose" type="button" aria-label="Close preview">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="profile-image-viewer-stage">
                <img id="profileImageViewerAsset" class="profile-image-viewer-asset" alt="Profile picture preview">
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="js/utils.js?v=<?= $utilsScriptVersion ?>"></script>
    <script src="js/api.js?v=<?= $apiScriptVersion ?>"></script>
    <script src="js/app-settings.js?v=<?= $appSettingsScriptVersion ?>"></script>
    <script src="js/nav.js?v=<?= $navScriptVersion ?>"></script>
    <script src="js/profile.js?v=<?= $profileScriptVersion ?>"></script>
</body>
</html>
