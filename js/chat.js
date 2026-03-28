let currentChatUser = null;
let messagePollingInterval = null;
let allUsers = [];
let currentMessages = [];
let activeMediaPreview = null;
let lastRenderedMessageSignature = '';
let lastRenderedConversationSignature = '';

document.addEventListener('DOMContentLoaded', async () => {
    if (!isAuthenticated()) {
        redirectToLogin();
        return;
    }
    bindChatUI();

    await loadConversations();

    window.addEventListener('beforeunload', stopMessagePolling);
});

function bindChatUI() {
    const searchInput = document.getElementById('searchUsers');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const refreshConversationsBtn = document.getElementById('refreshConversationsBtn');
    const refreshMessagesBtn = document.getElementById('refreshMessagesBtn');
    const sendBtn = document.getElementById('sendMessageBtn');
    const messageInput = document.getElementById('messageInput');
    const attachMediaBtn = document.getElementById('attachMediaBtn');
    const chatMediaInput = document.getElementById('chatMediaInput');
    const mobileBackBtn = document.getElementById('mobileBackBtn');
    const panelToggle = document.getElementById('panelToggle');
    const mediaViewer = document.getElementById('chatMediaViewer');
    const mediaViewerClose = document.getElementById('chatMediaViewerClose');
    const viewVaultBtn = document.getElementById('viewVaultBtn');
    const chatAvatarButton = document.getElementById('chatAvatarButton');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 250));
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', clearSearch);
    }

    if (refreshConversationsBtn) {
        refreshConversationsBtn.addEventListener('click', async () => {
            await loadConversations();
            showToast('Conversations refreshed', 'success');
        });
    }

    if (refreshMessagesBtn) {
        refreshMessagesBtn.addEventListener('click', async () => {
            if (currentChatUser) {
                await loadMessages(currentChatUser.id);
                showToast('Messages refreshed', 'success');
            }
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', sendMessage);
    }

    if (attachMediaBtn && chatMediaInput) {
        attachMediaBtn.addEventListener('click', () => chatMediaInput.click());
        chatMediaInput.addEventListener('change', async (event) => {
            const file = event.target.files?.[0];
            if (file) {
                await sendMediaAttachment(file);
            }
            chatMediaInput.value = '';
        });
    }

    if (messageInput) {
        updateMessageLength(messageInput.value.length);
        messageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 140) + 'px';
            updateMessageLength(this.value.length);
        });

        messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });
    }

    if (mobileBackBtn) {
        mobileBackBtn.addEventListener('click', closeChatOnMobile);
    }

    if (panelToggle) {
        panelToggle.addEventListener('click', () => {
            document.querySelector('.chat-shell')?.classList.toggle('chat-open');
        });
    }

    if (mediaViewer) {
        mediaViewer.addEventListener('click', (event) => {
            if (event.target.closest('[data-media-close="true"]')) {
                closeMediaViewer();
            }
        });
    }

    if (mediaViewerClose) {
        mediaViewerClose.addEventListener('click', closeMediaViewer);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeMediaPreview) {
            closeMediaViewer();
        }
    });

    if (viewVaultBtn) {
        viewVaultBtn.addEventListener('click', (event) => {
            if (!currentChatUser) {
                return;
            }

            window.location.href = `dashboard.php?user_id=${encodeURIComponent(currentChatUser.id)}`;
        });
    }

    if (chatAvatarButton) {
        chatAvatarButton.addEventListener('click', () => {
            if (!currentChatUser?.avatar) {
                return;
            }

            openMediaViewer(currentChatUser.avatar, 'image', `${currentChatUser.username}'s profile picture`);
        });
    }
}

async function loadConversations() {
    const usersList = document.getElementById('usersList');
    const isInitialLoad = allUsers.length === 0;
    if (isInitialLoad) {
        usersList.innerHTML = createSkeletonLoader(5);
        updateSearchStatus('Loading conversations...');
    }

    try {
        const response = await API.getConversations();
        allUsers = response.conversations || [];
        displayUsers(allUsers, false);
        updateConversationSummary(allUsers);
        updateSearchStatus(allUsers.length ? 'Recent conversations' : 'No conversations yet');

        if (currentChatUser) {
            const refreshedUser = allUsers.find((user) => Number(user.id) === Number(currentChatUser.id));
            if (refreshedUser) {
                currentChatUser = {
                    ...currentChatUser,
                    avatar: refreshedUser.avatar || currentChatUser.avatar || null,
                    email_verified: typeof refreshedUser.email_verified === 'boolean' ? refreshedUser.email_verified : !!currentChatUser.email_verified,
                    is_online: Boolean(refreshedUser.is_online),
                    last_seen: refreshedUser.last_seen || currentChatUser.last_seen || null,
                    last_seen_ago: refreshedUser.last_seen_ago || currentChatUser.last_seen_ago || null
                };
            }
            syncActiveConversation();
        }
    } catch (error) {
        console.error('Error loading conversations:', error);
        usersList.innerHTML = `
            <div class="empty-users">
                <i class="fas fa-triangle-exclamation"></i>
                <p>Failed to load conversations</p>
                <p class="hint">Please refresh and try again.</p>
            </div>
        `;
        showToast('Failed to load conversations', 'error');
        updateSearchStatus('Unable to load conversations');
    }
}

function displayUsers(users, isSearchResults) {
    const usersList = document.getElementById('usersList');
    const conversationSignature = JSON.stringify(
        (users || []).map((user) => ({
            id: Number(user.id),
            username: user.username,
            avatar: user.avatar || '',
            is_online: Boolean(user.is_online),
            last_seen: user.last_seen || '',
            last_seen_ago: user.last_seen_ago || '',
            last_message: user.last_message || '',
            last_message_time: user.last_message_time || '',
            unread_count: Number(user.unread_count) || 0
        }))
    );

    if (!users || users.length === 0) {
        lastRenderedConversationSignature = '';
        usersList.innerHTML = `
            <div class="empty-users">
                <i class="fas fa-users"></i>
                <p>${isSearchResults ? 'No users matched your search' : 'No conversations yet'}</p>
                <p class="hint">${isSearchResults ? 'Try a username or email keyword.' : 'Search for users to start chatting.'}</p>
            </div>
        `;
        return;
    }

    if (!isSearchResults && conversationSignature === lastRenderedConversationSignature) {
        document.querySelectorAll('.user-item').forEach((item) => {
            item.classList.toggle('active', Number(item.dataset.userId) === currentChatUser?.id);
        });
        return;
    }

    lastRenderedConversationSignature = conversationSignature;

    usersList.innerHTML = users.map((user) => {
        const lastMessage = user.last_message ? escapeHtml(user.last_message) : 'Start a secure conversation';
        const presenceMeta = user.is_online ? 'Online now' : (user.last_seen_ago ? `Last seen ${user.last_seen_ago}` : 'Offline');
        const userMeta = user.email ? `${escapeHtml(user.email)} · ${escapeHtml(presenceMeta)}` : presenceMeta;
        const activeClass = currentChatUser && currentChatUser.id === user.id ? 'active' : '';
        const avatarMarkup = user.avatar
            ? `<img src="${escapeAttribute(user.avatar)}" alt="${escapeAttribute(user.username)}">`
            : escapeHtml(user.username.charAt(0).toUpperCase());
        const presenceClass = user.is_online ? 'online' : 'offline';

        return `
            <button class="user-item ${activeClass}" type="button" data-user-id="${user.id}" data-username="${escapeAttribute(user.username)}" data-avatar="${escapeAttribute(user.avatar || '')}" data-online="${user.is_online ? '1' : '0'}" data-last-seen="${escapeAttribute(user.last_seen || '')}">
                <div class="user-avatar" style="background: ${user.avatar_color || getAvatarColor(user.username)}">
                    ${avatarMarkup}
                    <span class="presence-dot ${presenceClass}" aria-hidden="true"></span>
                </div>
                <div class="user-info">
                    <div class="user-line">
                        <h4>${renderVerifiedChatName(user.username, user.email_verified)}</h4>
                        <span class="user-time">${user.last_message_time ? formatMessageTime(user.last_message_time) : ''}</span>
                    </div>
                    <div class="user-snippet">${lastMessage}</div>
                    <div class="user-extra">${escapeHtml(userMeta)}</div>
                </div>
                ${user.unread_count > 0 ? `<span class="unread-badge">${user.unread_count}</span>` : ''}
            </button>
        `;
    }).join('');

    document.querySelectorAll('.user-item').forEach((item) => {
        item.addEventListener('click', () => {
            openChat(
                Number(item.dataset.userId),
                item.dataset.username,
                item.dataset.avatar || null,
                item.dataset.online === '1',
                item.dataset.lastSeen || null
            );
        });
    });
}

async function handleSearch(event) {
    const query = event.target.value.trim();

    if (query.length < 2) {
        displayUsers(allUsers, false);
        updateConversationSummary(allUsers);
        updateSearchStatus(allUsers.length ? 'Recent conversations' : 'No conversations yet');
        return;
    }

    updateSearchStatus(`Searching for "${query}"...`);

    try {
        const response = await API.searchUsers(query);
        const users = response.users || [];
        displayUsers(users, true);
        updateConversationSummary(users);
        updateSearchStatus(users.length ? `${users.length} match${users.length === 1 ? '' : 'es'} found` : 'No matching users');
    } catch (error) {
        console.error('Error searching users:', error);
        showToast('Search failed', 'error');
        updateSearchStatus('Search failed');
    }
}

function clearSearch() {
    const searchInput = document.getElementById('searchUsers');
    if (!searchInput) {
        return;
    }

    searchInput.value = '';
    displayUsers(allUsers, false);
    updateConversationSummary(allUsers);
    updateSearchStatus(allUsers.length ? 'Recent conversations' : 'No conversations yet');
    searchInput.focus();
}

async function openChat(userId, username, avatar = null, isOnline = false, lastSeen = null) {
    const matchedUser = allUsers.find((user) => Number(user.id) === Number(userId));
    currentChatUser = {
        id: userId,
        username,
        avatar: avatar || matchedUser?.avatar || null,
        email_verified: matchedUser?.email_verified ?? false,
        is_online: matchedUser?.is_online ?? isOnline,
        last_seen: matchedUser?.last_seen || lastSeen || null,
        last_seen_ago: matchedUser?.last_seen_ago || null
    };
    sessionStorage.setItem('selected_chat_user', JSON.stringify(currentChatUser));
    syncActiveConversation();
    openChatOnMobile();
    await loadMessages(userId);
    startMessagePolling(userId);
}

function syncActiveConversation() {
    document.querySelectorAll('.user-item').forEach((item) => {
        item.classList.toggle('active', Number(item.dataset.userId) === currentChatUser?.id);
    });

    const chatTitle = document.getElementById('chatTitle');
    const chatSubtitle = document.getElementById('chatSubtitle');
    const chatAvatar = document.getElementById('chatAvatar');
    const activeChatName = document.getElementById('activeChatName');
    const activeChatMeta = document.getElementById('activeChatMeta');
    const composer = document.getElementById('messageComposer');
    const chatPresencePill = document.getElementById('chatPresencePill');
    const viewVaultBtn = document.getElementById('viewVaultBtn');
    const chatAvatarButton = document.getElementById('chatAvatarButton');

    if (!currentChatUser) {
        chatTitle.textContent = 'Select a conversation';
        chatSubtitle.textContent = 'Search the directory or pick someone from the left panel.';
        chatAvatar.innerHTML = 'A';
        activeChatName.textContent = 'No active chat';
        activeChatMeta.textContent = 'Your secure thread history will appear here once a conversation is selected.';
        chatPresencePill.textContent = 'Offline';
        chatPresencePill.className = 'chat-presence-pill offline';
        composer.style.display = 'none';
        if (chatAvatarButton) {
            chatAvatarButton.disabled = true;
            chatAvatarButton.setAttribute('aria-label', 'View profile picture');
        }
        if (viewVaultBtn) {
            viewVaultBtn.setAttribute('aria-disabled', 'true');
        }
        return;
    }

    chatTitle.innerHTML = renderVerifiedChatName(currentChatUser.username, currentChatUser.email_verified);
    chatSubtitle.textContent = currentChatUser.is_online
        ? 'Online now'
        : `Last seen ${currentChatUser.last_seen_ago || formatPresenceTime(currentChatUser.last_seen)}`;
    chatAvatar.style.background = `linear-gradient(135deg, ${getAvatarColor(currentChatUser.username)}, #14665f)`;
    chatAvatar.innerHTML = currentChatUser.avatar
        ? `<img src="${escapeAttribute(currentChatUser.avatar)}" alt="${escapeAttribute(currentChatUser.username)}">`
        : escapeHtml(currentChatUser.username.charAt(0).toUpperCase());
    activeChatName.textContent = `Chatting with ${currentChatUser.username}`;
    activeChatMeta.textContent = currentChatUser.is_online
        ? 'They are online right now.'
        : `Messages update automatically. ${currentChatUser.username} was last active ${currentChatUser.last_seen_ago || formatPresenceTime(currentChatUser.last_seen)}.`;
    chatPresencePill.textContent = currentChatUser.is_online ? 'Online' : 'Offline';
    chatPresencePill.className = `chat-presence-pill ${currentChatUser.is_online ? 'online' : 'offline'}`;
    composer.style.display = 'block';
    if (chatAvatarButton) {
        chatAvatarButton.disabled = !currentChatUser.avatar;
        chatAvatarButton.setAttribute('aria-label', currentChatUser.avatar ? `View ${currentChatUser.username}'s profile picture` : 'No profile picture available');
    }
    if (viewVaultBtn) {
        viewVaultBtn.setAttribute('aria-disabled', 'false');
    }
}

async function loadMessages(userId, isPolling = false) {
    try {
        const response = await API.getMessages(userId);
        const nextMessages = response.messages || [];
        displayMessages(nextMessages);
        currentMessages = nextMessages;
        updateMessageSummary(response.total || currentMessages.length);
        updateConversationFromMessages(response.user);

        if (!isPolling) {
            await loadConversations();
        } else {
            updateUnreadBadgeFromUsers(allUsers);
        }

        if (typeof refreshUnreadBadge === 'function') {
            refreshUnreadBadge();
        }
    } catch (error) {
        if (!isPolling) {
            console.error('Error loading messages:', error);
            showToast('Failed to load messages', 'error');
        }
    }
}

function displayMessages(messages) {
    const container = document.getElementById('messagesContainer');
    const currentUser = getCurrentUser();
    const shouldStickToBottom = isNearMessagesBottom(container);
    const messageSignature = JSON.stringify(
        (messages || []).map((message) => ({
            id: Number(message.id),
            sender_id: Number(message.sender_id),
            message: message.message || '',
            attachment_url: message.attachment_url || '',
            attachment_type: message.attachment_type || '',
            attachment_name: message.attachment_name || '',
            is_read: Boolean(message.is_read),
            is_delivered: Boolean(message.is_delivered),
            is_deleted: Boolean(message.is_deleted),
            edited_at: message.edited_at || '',
            created_at: message.created_at || ''
        }))
    );

    if (!messages || messages.length === 0) {
        lastRenderedMessageSignature = '';
        container.innerHTML = `
            <div class="no-messages">
                <div class="empty-orb">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <p>No messages yet</p>
                <p class="hint">Say hello to begin this secure thread.</p>
            </div>
        `;
        return;
    }

    if (messageSignature === lastRenderedMessageSignature) {
        return;
    }

    lastRenderedMessageSignature = messageSignature;

    container.innerHTML = `
        <div class="message-list">
            ${messages.map((message) => `
                <div class="message ${message.sender_id === currentUser.id ? 'sent' : 'received'}">
                    ${renderMessageAvatar(message, currentUser.id)}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(message.message)}</div>
                        ${renderAttachment(message)}
                        <div class="message-meta">
                            <span>${message.sender_id === currentUser.id ? 'You' : escapeHtml(message.sender_name || currentChatUser?.username || 'User')}</span>
                            <span>${formatMessageTime(message.created_at)}${message.edited_at ? ' (edited)' : ''}</span>
                        </div>
                        ${renderMessageState(message, currentUser.id)}
                        ${renderMessageActions(message, currentUser.id)}
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    initMessageAttachmentSkeletons(container);

    if (shouldStickToBottom) {
        container.scrollTop = container.scrollHeight;
    }

    container.querySelectorAll('[data-message-edit]').forEach((button) => {
        button.addEventListener('click', async () => {
            const messageId = Number(button.dataset.messageEdit);
            const targetMessage = currentMessages.find((item) => Number(item.id) === messageId);
            if (!targetMessage || targetMessage.is_deleted) {
                return;
            }

            const dialogResult = await showPrompt({
                title: 'Edit message',
                message: 'Update your message below and save when you are ready.',
                type: 'info',
                inputValue: targetMessage.message,
                inputPlaceholder: 'Type your updated message',
                confirmText: 'Save changes',
                cancelText: 'Cancel'
            });
            if (!dialogResult.isConfirmed) {
                return;
            }

            const trimmedMessage = String(dialogResult.value || '').trim();
            if (!trimmedMessage) {
                showToast('Message cannot be empty', 'error');
                return;
            }

            try {
                await API.editMessage(messageId, trimmedMessage);
                await loadMessages(currentChatUser.id);
                showToast('Message updated', 'success');
            } catch (error) {
                showToast(error.message || 'Failed to update message', 'error');
            }
        });
    });

    container.querySelectorAll('[data-message-delete]').forEach((button) => {
        button.addEventListener('click', async () => {
            const messageId = Number(button.dataset.messageDelete);
            const confirmed = await showConfirm({
                title: 'Delete message',
                message: 'This message will be removed from the conversation for you.',
                type: 'warning',
                confirmText: 'Delete',
                cancelText: 'Keep it'
            });
            if (!confirmed) {
                return;
            }

            try {
                await API.deleteMessage(messageId);
                await loadMessages(currentChatUser.id);
                showToast('Message deleted', 'success');
            } catch (error) {
                showToast(error.message || 'Failed to delete message', 'error');
            }
        });
    });

    container.querySelectorAll('[data-media-preview]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openMediaViewer(
                trigger.dataset.mediaUrl,
                trigger.dataset.mediaType,
                trigger.dataset.mediaName || 'Attachment'
            );
        });
    });

    container.querySelectorAll('[data-profile-preview]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            if (!currentChatUser?.avatar) {
                return;
            }

            openMediaViewer(currentChatUser.avatar, 'image', `${currentChatUser.username}'s profile picture`);
        });
    });
}

async function sendMessage() {
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendMessageBtn');

    if (!currentChatUser || !messageInput || !sendBtn) {
        return;
    }

    const message = messageInput.value.trim();
    if (!message) {
        return;
    }

    sendBtn.disabled = true;

    try {
        await API.sendMessage(currentChatUser.id, message);
        messageInput.value = '';
        messageInput.style.height = 'auto';
        updateMessageLength(0);
        await loadMessages(currentChatUser.id);
    } catch (error) {
        console.error('Error sending message:', error);
        showToast('Failed to send message', 'error');
    } finally {
        sendBtn.disabled = false;
        messageInput.focus();
    }
}

async function sendMediaAttachment(file) {
    if (!currentChatUser) {
        showToast('Open a conversation first', 'error');
        return;
    }

    const isSupportedMedia = file.type.startsWith('image/') || file.type.startsWith('video/');
    if (!isSupportedMedia) {
        showToast('Only image and video attachments are supported', 'error');
        return;
    }

    const captionResult = await showPrompt({
        title: 'Send media',
        message: 'Add an optional caption before sending this attachment.',
        type: 'info',
        inputValue: '',
        inputPlaceholder: 'Write a caption (optional)',
        confirmText: 'Send attachment',
        cancelText: 'Cancel'
    });
    if (!captionResult.isConfirmed) {
        return;
    }

    const caption = captionResult.value ?? '';

    try {
        await API.uploadChatMedia(currentChatUser.id, file, caption);
        await loadMessages(currentChatUser.id);
        showToast('Attachment sent', 'success');
    } catch (error) {
        console.error('Attachment upload error:', error);
        showToast(error.message || 'Failed to send attachment', 'error');
    }
}

function updateConversationSummary(users) {
    const conversationCount = document.getElementById('conversationCount');
    const panelUnreadCount = document.getElementById('panelUnreadCount');
    const totalUnread = users.reduce((sum, user) => sum + (Number(user.unread_count) || 0), 0);

    conversationCount.textContent = users.length;
    panelUnreadCount.textContent = totalUnread;
    updateUnreadBadgeFromUsers(users);
}

function updateUnreadBadgeFromUsers(users) {
    const totalUnread = users.reduce((sum, user) => sum + (Number(user.unread_count) || 0), 0);
    const badge = document.getElementById('unreadBadge');

    if (badge) {
        badge.textContent = totalUnread;
        badge.style.display = totalUnread > 0 ? 'inline-flex' : 'none';
    }

    const panelUnreadCount = document.getElementById('panelUnreadCount');
    if (panelUnreadCount) {
        panelUnreadCount.textContent = totalUnread;
    }
}

function updateSearchStatus(text) {
    const searchStatus = document.getElementById('searchStatus');
    if (searchStatus) {
        searchStatus.textContent = text;
    }
}

function updateMessageSummary(count) {
    const pill = document.getElementById('messageCountPill');
    if (pill) {
        pill.textContent = `${count} message${count === 1 ? '' : 's'}`;
    }
}

function updateConversationFromMessages(user) {
    if (!user || !currentChatUser) {
        return;
    }

    currentChatUser = {
        id: user.id,
        username: user.username,
        avatar: user.avatar || currentChatUser.avatar || null,
        email_verified: typeof user.email_verified === 'boolean' ? user.email_verified : !!currentChatUser.email_verified,
        is_online: Boolean(user.is_online),
        last_seen: user.last_seen || currentChatUser.last_seen || null,
        last_seen_ago: user.last_seen_ago || currentChatUser.last_seen_ago || null
    };
    syncActiveConversation();
}

function updateMessageLength(length) {
    const counter = document.getElementById('messageLength');
    if (counter) {
        counter.textContent = `${length} / 1000`;
    }
}

function startMessagePolling(userId) {
    stopMessagePolling();
    messagePollingInterval = setInterval(() => {
        loadMessages(userId, true);
    }, 4000);
}

function stopMessagePolling() {
    if (messagePollingInterval) {
        clearInterval(messagePollingInterval);
        messagePollingInterval = null;
    }
}

function openChatOnMobile() {
    document.querySelector('.chat-shell')?.classList.add('chat-open');
}

function closeChatOnMobile() {
    document.querySelector('.chat-shell')?.classList.remove('chat-open');
}

function isNearMessagesBottom(container) {
    if (!container) {
        return true;
    }

    const threshold = 120;
    return container.scrollHeight - container.scrollTop - container.clientHeight <= threshold;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function escapeAttribute(text) {
    return String(text ?? '').replace(/"/g, '&quot;');
}

function renderVerifiedChatName(username, isVerified) {
    const safeName = escapeHtml(username || 'User');
    return `${safeName}${isVerified ? ' <span class="verified-badge" title="Verified email" aria-label="Verified email"><i class="fas fa-circle-check"></i></span>' : ''}`;
}

function renderAttachment(message) {
    if (!message.attachment_url || message.is_deleted) {
        return '';
    }

    const attachmentName = message.attachment_name || 'Attachment';
    const mediaTag = message.attachment_type === 'image'
        ? `<img class="message-attachment-asset" src="${escapeAttribute(message.attachment_url)}" alt="${escapeAttribute(attachmentName)}" loading="lazy">`
        : `<video class="message-attachment-asset" src="${escapeAttribute(message.attachment_url)}" controls preload="metadata" playsinline></video>`;
    const actionText = message.attachment_type === 'image' ? 'View full image' : 'View full video';

    return `
        <div class="message-attachment" data-attachment-type="${escapeAttribute(message.attachment_type)}">
            <button
                class="message-attachment-media"
                type="button"
                data-media-preview="true"
                data-media-url="${escapeAttribute(message.attachment_url)}"
                data-media-type="${escapeAttribute(message.attachment_type)}"
                data-media-name="${escapeAttribute(attachmentName)}"
                aria-label="Preview ${escapeAttribute(attachmentName)}"
            >
                <div class="message-attachment-skeleton skeleton shimmer" aria-hidden="true"></div>
                ${mediaTag}
            </button>
            <div class="message-attachment-footer">
                <span class="message-attachment-label">${escapeHtml(attachmentName)}</span>
                <button
                    class="message-attachment-link"
                    type="button"
                    data-media-preview="true"
                    data-media-url="${escapeAttribute(message.attachment_url)}"
                    data-media-type="${escapeAttribute(message.attachment_type)}"
                    data-media-name="${escapeAttribute(attachmentName)}"
                >${actionText}</button>
            </div>
        </div>
    `;
}

function initMessageAttachmentSkeletons(scope) {
    scope.querySelectorAll('.message-attachment-media').forEach((attachment) => {
        const asset = attachment.querySelector('.message-attachment-asset');
        if (!asset) {
            return;
        }

        const markLoaded = () => attachment.classList.add('loaded');
        const markError = () => attachment.classList.add('loaded', 'load-error');

        if (asset.tagName === 'IMG') {
            if (asset.complete && asset.naturalWidth > 0) {
                markLoaded();
            } else {
                asset.addEventListener('load', markLoaded, { once: true });
                asset.addEventListener('error', markError, { once: true });
            }
            return;
        }

        if (asset.readyState >= 2) {
            markLoaded();
        } else {
            asset.addEventListener('loadeddata', markLoaded, { once: true });
            asset.addEventListener('loadedmetadata', markLoaded, { once: true });
            asset.addEventListener('error', markError, { once: true });
        }
    });
}

function renderMessageAvatar(message, currentUserId) {
    if (message.sender_id === currentUserId) {
        return '';
    }

    const senderName = message.sender_name || currentChatUser?.username || 'User';
    const avatarMarkup = currentChatUser?.avatar
        ? `<img src="${escapeAttribute(currentChatUser.avatar)}" alt="${escapeAttribute(senderName)}">`
        : escapeHtml(senderName.charAt(0).toUpperCase());

    const previewAttributes = currentChatUser?.avatar
        ? `data-profile-preview="true" role="button" tabindex="0" aria-label="View ${escapeAttribute(senderName)}'s profile picture"`
        : '';

    return `<div class="message-avatar ${currentChatUser?.avatar ? 'clickable' : ''}" ${previewAttributes}>${avatarMarkup}</div>`;
}

function renderMessageState(message, currentUserId) {
    if (message.sender_id !== currentUserId) {
        return '';
    }

    let label = 'Sent';
    let stateClass = 'sent';
    let icon = 'fa-check';

    if (message.is_read) {
        label = 'Seen';
        stateClass = 'seen';
        icon = 'fa-eye';
    } else if (message.is_delivered || currentChatUser?.is_online) {
        label = 'Delivered';
        stateClass = 'delivered';
        icon = 'fa-check-double';
    }

    return `
        <div class="message-state ${stateClass}">
            <i class="fas ${icon}"></i>
            <span>${label}</span>
        </div>
    `;
}

function formatPresenceTime(value) {
    if (!value) {
        return 'recently';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'recently';
    }

    const diffMs = Date.now() - date.getTime();
    if (diffMs < 60000) {
        return 'just now';
    }
    if (diffMs < 3600000) {
        return `${Math.floor(diffMs / 60000)}m ago`;
    }
    if (diffMs < 86400000) {
        return `${Math.floor(diffMs / 3600000)}h ago`;
    }

    return date.toLocaleDateString();
}

function renderMessageActions(message, currentUserId) {
    if (message.sender_id !== currentUserId || message.is_deleted) {
        return '';
    }

    return `
        <div class="message-actions">
            <button class="message-action-btn" type="button" data-message-edit="${message.id}">Edit</button>
            <button class="message-action-btn" type="button" data-message-delete="${message.id}">Delete</button>
        </div>
    `;
}

function openMediaViewer(url, type, name) {
    const viewer = document.getElementById('chatMediaViewer');
    const stage = document.getElementById('chatMediaViewerStage');
    const title = document.getElementById('chatMediaViewerTitle');

    if (!viewer || !stage || !title || !url) {
        return;
    }

    closeMediaViewer();

    title.textContent = name || 'Attachment preview';

    const asset = type === 'video'
        ? `<video class="chat-media-viewer-asset" src="${escapeAttribute(url)}" controls autoplay playsinline></video>`
        : `<img class="chat-media-viewer-asset" src="${escapeAttribute(url)}" alt="${escapeAttribute(name || 'Attachment preview')}">`;

    stage.innerHTML = asset;
    viewer.hidden = false;
    viewer.classList.add('show');
    document.body.classList.add('chat-media-open');
    activeMediaPreview = { url, type };
}

function closeMediaViewer() {
    const viewer = document.getElementById('chatMediaViewer');
    const stage = document.getElementById('chatMediaViewerStage');

    if (!viewer || !stage) {
        return;
    }

    stage.querySelectorAll('video').forEach((video) => {
        video.pause();
        video.removeAttribute('src');
        video.load();
    });
    stage.innerHTML = '';
    activeMediaPreview = null;

    viewer.classList.remove('show');
    viewer.hidden = true;
    document.body.classList.remove('chat-media-open');
}
