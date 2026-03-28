let unreadBadgeInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    if (!isAuthenticated()) {
        return;
    }

    bindNavigationInteractions();
    populateNavigationUser();
    populateNavigationAvatar();
    toggleAdminNavigation(false);
    refreshUnreadBadge();
    unreadBadgeInterval = setInterval(refreshUnreadBadge, 10000);

    window.addEventListener('beforeunload', () => {
        if (unreadBadgeInterval) {
            clearInterval(unreadBadgeInterval);
            unreadBadgeInterval = null;
        }
    });
});

function bindNavigationInteractions() {
    const userMenus = Array.from(document.querySelectorAll('.user-menu'));

    userMenus.forEach((userMenu) => {
        const userTrigger = userMenu.querySelector('.user-trigger');
        if (!userTrigger) {
            return;
        }

        userTrigger.addEventListener('click', (event) => {
            if (window.innerWidth > 768 && !userMenu.classList.contains('nav-mobile-user')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            userMenus.forEach((menu) => {
                if (menu !== userMenu) {
                    menu.classList.remove('open');
                }
            });
            userMenu.classList.toggle('open');
        });
    });

    document.addEventListener('click', (event) => {
        userMenus.forEach((userMenu) => {
            if (!userMenu.contains(event.target)) {
                userMenu.classList.remove('open');
            }
        });
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            userMenus.forEach((menu) => menu.classList.remove('open'));
        }
    });
}

function toggleAdminNavigation(isAdmin = false) {
    document.querySelectorAll('[data-admin-only]').forEach((link) => {
        link.hidden = !isAdmin;
        link.classList.toggle('admin-visible', isAdmin);
        link.setAttribute('aria-hidden', isAdmin ? 'false' : 'true');
    });
}

function populateNavigationUser() {
    const user = getCurrentUser();
    const userName = document.getElementById('userName');
    if (user && userName) {
        userName.innerHTML = `${escapeHtml(user.username || 'User')}${user.email_verified ? ' <span class="nav-verified-badge" title="Verified email" aria-label="Verified email"><i class="fas fa-circle-check"></i></span>' : ''}`;
    }
}

async function populateNavigationAvatar() {
    const currentUser = getCurrentUser();
    const cachedAvatar = currentUser?.avatar || null;
    setNavigationAvatar(cachedAvatar, currentUser?.username || 'U');

    if (!getAuthToken()) {
        return;
    }

    try {
        const pageParams = new URLSearchParams(window.location.search);
        const viewedUserId = Number(pageParams.get('user_id')) || null;
        const response = await API.getProfile();
        const profile = response.profile || {};
        const nextUser = {
            ...(currentUser || {}),
            id: profile.id || currentUser?.id,
            username: profile.username || currentUser?.username,
            email: profile.email || currentUser?.email,
            avatar: profile.avatar || null,
            is_admin: Boolean(profile.is_admin),
            email_verified: Boolean(profile.email_verified)
        };
        setCurrentUser(nextUser);
        setNavigationAvatar(nextUser.avatar, nextUser.username || 'U');
        toggleAdminNavigation(Boolean(profile.is_admin));

        // When viewing another user's page, keep the nav bound to the signed-in user only.
        if (viewedUserId && viewedUserId !== nextUser.id) {
            setNavigationAvatar(currentUser?.avatar || nextUser.avatar || null, currentUser?.username || nextUser.username || 'U');
        }
    } catch (error) {
        console.error('Navigation avatar error:', error);
        toggleAdminNavigation(false);
    }
}

function setNavigationAvatar(src, username) {
    const avatarImages = document.querySelectorAll('.nav-avatar-image');
    const avatarFallbacks = document.querySelectorAll('.nav-avatar-fallback');

    avatarImages.forEach((avatarImage) => {
        if (src) {
            avatarImage.src = src;
            avatarImage.style.display = 'block';
        } else {
            avatarImage.removeAttribute('src');
            avatarImage.style.display = 'none';
        }
    });

    avatarFallbacks.forEach((avatarFallback) => {
        if (src) {
            avatarFallback.style.display = 'none';
        } else {
            avatarFallback.style.display = 'flex';
            avatarFallback.textContent = (username || 'U').charAt(0).toUpperCase();
        }
    });
}

async function refreshUnreadBadge() {
    const badges = document.querySelectorAll('[data-unread-badge]');
    if (!badges.length || !getAuthToken()) {
        return;
    }

    try {
        const response = await API.getUnreadCount();
        const totalUnread = Number(response.unread_total) || 0;
        badges.forEach((badge) => {
            badge.textContent = totalUnread;
            badge.style.display = totalUnread > 0 ? 'inline-flex' : 'none';
        });
    } catch (error) {
        console.error('Unread badge error:', error);
    }
}
