// ===== MESSAGES PAGE - SMOOTH UPDATES, IMPROVED REPLY INDICATOR =====

let messagesState = {
    conversations: [],
    filteredUsers: [],
    currentUser: null,
    messages: [],
    pollingTimer: null,
    lastMessageCount: 0,
    lastMessageIds: [],
    pendingFile: null,
    replyToMessage: null,
    touchStartX: 0,
    touchStartY: 0,
    longPressTimer: null,
    isLongPressing: false,
    activeMessageElement: null,
    messageElementsCache: new Map()
};

document.addEventListener('DOMContentLoaded', async () => {
    if (!isAuthenticated()) {
        redirectToLogin();
        return;
    }

    bindMessagesUi();
    await loadMessagesSidebar();
    restoreSelectedConversation();

    window.addEventListener('beforeunload', stopMessagesPolling);
});

function bindMessagesUi() {
    const searchInput = document.getElementById('messageUserSearch');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const refreshSidebarBtn = document.getElementById('refreshMessagesSidebarBtn');
    const refreshMessagesBtn = document.getElementById('refreshMessagesBtn');
    const sendBtn = document.getElementById('messagesSendBtn');
    const messageInput = document.getElementById('messagesInput');
    const attachFileBtn = document.getElementById('attachFileBtn');
    const fileInput = document.getElementById('fileInput');
    const removeAttachmentBtn = document.getElementById('removeAttachmentBtn');
    const cancelReplyBtn = document.getElementById('cancelReplyBtn');
    const mobileBackBtn = document.getElementById('mobileBackBtn');
    const panelToggle = document.getElementById('panelToggle');
    const viewVaultBtn = document.getElementById('openVaultFromMessagesBtn');
    const mediaViewer = document.getElementById('chatMediaViewer');
    const mediaViewerClose = document.getElementById('chatMediaViewerClose');
    const downloadAttachmentBtn = document.getElementById('downloadAttachmentBtn');
    const chatAvatarButton = document.getElementById('chatAvatarButton');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleMessagesSearch, 250));
    }

    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            if (searchInput) {
                searchInput.value = '';
                displayFilteredUsers(messagesState.conversations);
                updateSearchStatus(messagesState.conversations.length ? 'Recent conversations' : 'No conversations yet');
                searchInput.focus();
            }
        });
    }

    if (refreshSidebarBtn) {
        refreshSidebarBtn.addEventListener('click', async () => {
            await loadMessagesSidebar();
            if (messagesState.currentUser) {
                await loadMessages(messagesState.currentUser.id);
            }
            showToast('Conversations refreshed', 'success');
        });
    }

    if (refreshMessagesBtn) {
        refreshMessagesBtn.addEventListener('click', async () => {
            if (messagesState.currentUser) {
                await loadMessages(messagesState.currentUser.id);
                showToast('Messages refreshed', 'success');
            }
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', sendCurrentMessage);
    }

    if (messageInput) {
        updateMessageLength(messageInput.value.length);
        messageInput.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            updateMessageLength(this.value.length);
        });

        messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendCurrentMessage();
            }
        });
    }

    if (attachFileBtn && fileInput) {
        attachFileBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', handleFileSelect);
    }

    if (removeAttachmentBtn) {
        removeAttachmentBtn.addEventListener('click', clearAttachment);
    }

    if (cancelReplyBtn) {
        cancelReplyBtn.addEventListener('click', clearReply);
    }

    if (mobileBackBtn) {
        mobileBackBtn.addEventListener('click', closeChatOnMobile);
    }

    if (panelToggle) {
        panelToggle.addEventListener('click', () => {
            document.querySelector('.messages-shell')?.classList.toggle('chat-open');
        });
    }

    if (mediaViewer) {
        mediaViewer.addEventListener('click', (event) => {
            if (event.target.closest('[data-media-close="true"]') || event.target === mediaViewer) {
                closeMediaViewer();
            }
        });
    }

    if (mediaViewerClose) {
        mediaViewerClose.addEventListener('click', closeMediaViewer);
    }

    if (downloadAttachmentBtn) {
        downloadAttachmentBtn.addEventListener('click', downloadCurrentAttachment);
    }

    if (viewVaultBtn) {
        viewVaultBtn.addEventListener('click', () => {
            if (!messagesState.currentUser) return;
            window.location.href = `dashboard.php?user_id=${encodeURIComponent(messagesState.currentUser.id)}`;
        });
    }

    if (chatAvatarButton) {
        chatAvatarButton.addEventListener('click', () => {
            if (messagesState.currentUser?.avatar) {
                openMediaViewer(messagesState.currentUser.avatar, 'image', `${messagesState.currentUser.username}'s profile picture`);
            }
        });
    }

    // Close context menu on click outside
    document.addEventListener('click', (event) => {
        const menu = document.getElementById('messageContextMenu');
        if (menu && !menu.contains(event.target)) {
            menu.style.display = 'none';
        }
    });
}

async function loadMessagesSidebar() {
    const usersList = document.getElementById('messagesUserList');
    const isInitialLoad = messagesState.conversations.length === 0;
    
    if (isInitialLoad) {
        usersList.innerHTML = createSkeletonLoader(5);
        updateSearchStatus('Loading conversations...');
    }

    try {
        const response = await API.getConversations();
        messagesState.conversations = response.conversations || [];
        messagesState.filteredUsers = [...messagesState.conversations];
        displayFilteredUsers(messagesState.filteredUsers);
        updateConversationSummary(messagesState.conversations);
        updateSearchStatus(messagesState.conversations.length ? 'Recent conversations' : 'No conversations yet');

        if (messagesState.currentUser) {
            const refreshedUser = messagesState.conversations.find(
                (user) => Number(user.id) === Number(messagesState.currentUser.id)
            );
            if (refreshedUser) {
                const wasOnline = messagesState.currentUser.is_online;
                messagesState.currentUser = {
                    ...messagesState.currentUser,
                    avatar: refreshedUser.avatar || messagesState.currentUser.avatar || null,
                    email_verified: typeof refreshedUser.email_verified === 'boolean' ? refreshedUser.email_verified : !!messagesState.currentUser.email_verified,
                    is_online: Boolean(refreshedUser.is_online),
                    last_seen_ago: refreshedUser.last_seen_ago || messagesState.currentUser.last_seen_ago || null
                };
                if (wasOnline !== messagesState.currentUser.is_online) {
                    syncActiveConversation();
                }
            }
        }
    } catch (error) {
        console.error('Messages sidebar load error:', error);
        usersList.innerHTML = `
            <div class="empty-users">
                <i class="fas fa-triangle-exclamation"></i>
                <p>Failed to load conversations</p>
                <p class="hint">Please refresh and try again.</p>
            </div>
        `;
        updateSearchStatus('Unable to load conversations');
        showToast('Failed to load conversations', 'error');
    }
}

function displayFilteredUsers(users) {
    const usersList = document.getElementById('messagesUserList');

    if (!users || users.length === 0) {
        usersList.innerHTML = `
            <div class="empty-users">
                <i class="fas fa-users"></i>
                <p>No conversations yet</p>
                <p class="hint">Search for users to start chatting</p>
            </div>
        `;
        return;
    }

    // Preserve scroll position while updating
    const scrollTop = usersList.scrollTop;
    
    usersList.innerHTML = users.map((user) => {
        const lastMessage = user.last_message ? escapeHtml(user.last_message.substring(0, 50)) : 'Start a secure conversation';
        const presenceMeta = user.is_online ? 'Online now' : (user.last_seen_ago ? `Last seen ${user.last_seen_ago}` : 'Offline');
        const activeClass = messagesState.currentUser && messagesState.currentUser.id === user.id ? 'active' : '';
        const avatarMarkup = user.avatar
            ? `<img src="${escapeAttribute(user.avatar)}" alt="${escapeAttribute(user.username)}">`
            : escapeHtml((user.username || 'U').charAt(0).toUpperCase());
        const avatarColor = getAvatarColor(user.username);
        const presenceClass = user.is_online ? 'online' : 'offline';

        return `
            <button class="user-item ${activeClass}" type="button" data-user-id="${user.id}" data-username="${escapeAttribute(user.username)}" data-avatar="${escapeAttribute(user.avatar || '')}" data-online="${user.is_online ? '1' : '0'}" data-last-seen-ago="${escapeAttribute(user.last_seen_ago || '')}">
                <div class="user-avatar" style="background: linear-gradient(135deg, ${avatarColor}, #14665f)">
                    ${avatarMarkup}
                    <span class="presence-dot ${presenceClass}" aria-hidden="true"></span>
                </div>
                <div class="user-info">
                    <div class="user-line">
                        <h4>${renderVerifiedName(user.username, user.email_verified)}</h4>
                        <span class="user-time">${user.last_message_time ? formatMessageTime(user.last_message_time) : ''}</span>
                    </div>
                    <div class="user-snippet">${lastMessage}</div>
                    <div class="user-extra">${escapeHtml(presenceMeta)}</div>
                </div>
                ${Number(user.unread_count) > 0 ? `<span class="unread-badge">${user.unread_count}</span>` : ''}
            </button>
        `;
    }).join('');

    // Restore scroll position
    usersList.scrollTop = scrollTop;

    document.querySelectorAll('.user-item').forEach((item) => {
        item.addEventListener('click', () => {
            openConversation(
                Number(item.dataset.userId),
                true,
                item.dataset.username,
                item.dataset.avatar || null,
                item.dataset.online === '1',
                item.dataset.lastSeenAgo || null
            );
        });
    });
}

async function handleMessagesSearch(event) {
    const query = event.target.value.trim();

    if (query.length < 2) {
        displayFilteredUsers(messagesState.conversations);
        updateSearchStatus(messagesState.conversations.length ? 'Recent conversations' : 'No conversations yet');
        return;
    }

    updateSearchStatus(`Searching for "${query}"...`);

    try {
        const response = await API.searchUsers(query);
        const users = response.users || [];
        messagesState.filteredUsers = users;
        displayFilteredUsers(users);
        updateSearchStatus(users.length ? `${users.length} result${users.length === 1 ? '' : 's'} found` : 'No matching users');
    } catch (error) {
        console.error('Messages search error:', error);
        updateSearchStatus('Search failed');
        showToast('Search failed', 'error');
    }
}

async function openConversation(userId, persistSelection = true, username = null, avatar = null, isOnline = false, lastSeenAgo = null) {
    clearReply();
    clearAttachment();
    
    try {
        const response = await API.getMessages(userId);
        
        const matchedUser = messagesState.conversations.find((user) => Number(user.id) === Number(userId));
        messagesState.currentUser = {
            id: userId,
            username: username || matchedUser?.username || response.user?.username || 'User',
            avatar: avatar || matchedUser?.avatar || response.user?.avatar || null,
            is_online: matchedUser?.is_online ?? isOnline ?? (response.user?.is_online || false),
            email_verified: matchedUser?.email_verified ?? response.user?.email_verified ?? false,
            last_seen_ago: lastSeenAgo || matchedUser?.last_seen_ago || response.user?.last_seen_ago || null
        };
        messagesState.messages = response.messages || [];
        messagesState.lastMessageCount = messagesState.messages.length;
        messagesState.lastMessageIds = messagesState.messages.map(m => m.id);

        if (persistSelection && messagesState.currentUser) {
            sessionStorage.setItem('selected_message_user_id', String(messagesState.currentUser.id));
        }

        syncActiveConversation();
        renderMessagesThread(true); // true = initial load, scroll to bottom
        displayFilteredUsers(messagesState.filteredUsers.length ? messagesState.filteredUsers : messagesState.conversations);
        enableComposer(true);
        openChatOnMobile();
        startMessagesPolling(userId);

        if (typeof refreshUnreadBadge === 'function') {
            refreshUnreadBadge();
        }
    } catch (error) {
        console.error('Open conversation error:', error);
        showToast(error.message || 'Failed to open conversation', 'error');
    }
}

async function loadMessages(userId, isPolling = false) {
    try {
        const response = await API.getMessages(userId);
        const newMessages = response.messages || [];
        
        if (response.user) {
            messagesState.currentUser = {
                ...messagesState.currentUser,
                is_online: response.user.is_online ?? messagesState.currentUser?.is_online,
                last_seen_ago: response.user.last_seen_ago ?? messagesState.currentUser?.last_seen_ago
            };
            syncActiveConversation();
        }
        
        // Check if messages actually changed to avoid unnecessary re-renders
        const newMessageIds = newMessages.map(m => m.id).join(',');
        const oldMessageIds = messagesState.messages.map(m => m.id).join(',');
        
        if (newMessageIds !== oldMessageIds) {
            const wasAtBottom = isNearBottom();
            const oldScrollHeight = document.getElementById('messagesThread')?.scrollHeight || 0;
            
            messagesState.messages = newMessages;
            
            if (isPolling && messagesState.messages.length > messagesState.lastMessageCount) {
                // New messages arrived - append smoothly
                const newOnly = messagesState.messages.slice(messagesState.lastMessageCount);
                appendMessages(newOnly);
                messagesState.lastMessageCount = messagesState.messages.length;
                messagesState.lastMessageIds = messagesState.messages.map(m => m.id);
                
                if (wasAtBottom) {
                    scrollToBottom();
                }
            } else {
                // Full re-render (initial load or refresh)
                renderMessagesThread(!isPolling);
                messagesState.lastMessageCount = messagesState.messages.length;
                messagesState.lastMessageIds = messagesState.messages.map(m => m.id);
            }
        }
        
        if (!isPolling) {
            await loadMessagesSidebar();
        }
    } catch (error) {
        if (!isPolling) {
            console.error('Error loading messages:', error);
            showToast('Failed to load messages', 'error');
        }
    }
}

function appendMessages(newMessages) {
    const thread = document.getElementById('messagesThread');
    const currentUser = getCurrentUser();
    
    if (!thread || !newMessages.length) return;
    
    const messageList = thread.querySelector('.message-list');
    if (!messageList) {
        // If no message list exists, do full render
        renderMessagesThread(true);
        return;
    }
    
    // Append new messages without re-rendering all
    newMessages.forEach(message => {
        const messageHtml = renderMessage(message, currentUser);
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = messageHtml;
        const messageElement = tempDiv.firstElementChild;
        messageElement.classList.add('new-message');
        messageList.appendChild(messageElement);
        
        // Attach event listeners to the new message
        attachMessageEventListenersToElement(messageElement);
    });
    
    updateMessageSummary(messagesState.messages.length);
}

function renderMessagesThread(shouldScrollToBottom = false) {
    const thread = document.getElementById('messagesThread');
    const currentUser = getCurrentUser();

    if (!messagesState.currentUser) {
        thread.innerHTML = `
            <div class="no-chat-selected">
                <div class="empty-orb">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h2>No conversation selected</h2>
                <p>Pick a recent chat or search for a user to start a message.</p>
            </div>
        `;
        updateMessageSummary(0);
        return;
    }

    if (!messagesState.messages.length) {
        thread.innerHTML = `
            <div class="no-messages">
                <div class="empty-orb">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <p>No messages yet</p>
                <p class="hint">Send the first message to start this conversation.</p>
            </div>
        `;
        updateMessageSummary(0);
        return;
    }

    updateMessageSummary(messagesState.messages.length);

    thread.innerHTML = `
        <div class="message-list">
            ${messagesState.messages.map((message) => renderMessage(message, currentUser)).join('')}
        </div>
    `;

    // Attach event listeners to all messages
    attachMessageEventListeners(thread);
    
    if (shouldScrollToBottom) {
        scrollToBottom();
    }
}

function renderMessage(message, currentUser) {
    const mine = Number(message.sender_id) === Number(currentUser?.id);
    const status = getMessageStatus(message, mine);
    const timeFormatted = formatMessageTime(message.created_at);
    
    return `
        <div class="message ${mine ? 'sent' : 'received'}" data-message-id="${message.id}" data-message-text="${escapeAttribute(message.message || '')}" data-message-sender="${mine ? 'me' : 'them'}">
            ${!mine ? `
                <div class="message-avatar ${messagesState.currentUser?.avatar ? 'clickable' : ''}" data-profile-preview="true">
                    ${messagesState.currentUser?.avatar 
                        ? `<img src="${escapeAttribute(messagesState.currentUser.avatar)}" alt="${escapeAttribute(messagesState.currentUser.username)}">`
                        : escapeHtml((messagesState.currentUser?.username || 'U').charAt(0).toUpperCase())}
                </div>
            ` : ''}
            <div class="message-bubble">
                ${message.reply_to ? renderReplyPreview(message.reply_to) : ''}
                ${message.message ? `<div class="message-text">${escapeHtml(message.message)}</div>` : ''}
                ${message.attachment ? renderAttachment(message.attachment) : ''}
                <div class="message-meta">
                    <span class="message-time">${timeFormatted}</span>
                    ${mine ? `
                        <span class="message-status ${status.class}">
                            <i class="fas ${status.icon}"></i>
                            <span>${status.label}</span>
                        </span>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
}

function renderReplyPreview(replyTo) {
    return `
        <div class="message-reply-preview">
            <div class="reply-to-name">↳ Replying to ${escapeHtml(replyTo.sender_name || 'User')}</div>
            <div class="reply-to-text">${escapeHtml((replyTo.message || 'Attachment').substring(0, 80))}${(replyTo.message || '').length > 80 ? '...' : ''}</div>
        </div>
    `;
}

function renderAttachment(attachment) {
    const fileIcon = getFileIcon(attachment.type);
    
    return `
        <div class="message-attachment">
            <div class="attachment-item" data-media-url="${escapeAttribute(attachment.url)}" data-media-type="${attachment.type}" data-media-name="${escapeAttribute(attachment.name)}">
                <div class="attachment-icon"><i class="fas ${fileIcon}"></i></div>
                <div class="attachment-info">
                    <div class="attachment-name">${escapeHtml(attachment.name)}</div>
                    <div class="attachment-size">${formatFileSize(attachment.size)}</div>
                </div>
            </div>
            ${attachment.type === 'image' ? `<img class="attachment-preview-img" src="${escapeAttribute(attachment.url)}" alt="Attachment" data-media-url="${escapeAttribute(attachment.url)}" data-media-type="image" data-media-name="${escapeAttribute(attachment.name)}">` : ''}
        </div>
    `;
}

function getMessageStatus(message, isMine) {
    if (!isMine) return { class: '', icon: '', label: '' };
    
    if (message.is_read) {
        return { class: 'seen', icon: 'fa-check-double', label: 'Seen' };
    } else if (message.is_delivered) {
        return { class: 'delivered', icon: 'fa-check-double', label: 'Delivered' };
    } else {
        return { class: 'sent', icon: 'fa-check', label: 'Sent' };
    }
}

function getFileIcon(fileType) {
    const icons = {
        image: 'fa-image',
        pdf: 'fa-file-pdf',
        document: 'fa-file-word',
        default: 'fa-file'
    };
    return icons[fileType] || icons.default;
}

function formatFileSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function attachMessageEventListeners(container) {
    const messages = container.querySelectorAll('.message');
    messages.forEach(message => attachMessageEventListenersToElement(message));
}

function attachMessageEventListenersToElement(message) {
    const bubble = message.querySelector('.message-bubble');
    if (!bubble) return;
    
    // Touch start for long press and swipe
    bubble.addEventListener('touchstart', (e) => {
        messagesState.touchStartX = e.touches[0].clientX;
        messagesState.touchStartY = e.touches[0].clientY;
        messagesState.activeMessageElement = message;
        
        messagesState.longPressTimer = setTimeout(() => {
            handleLongPress(message);
            messagesState.isLongPressing = true;
        }, 500);
    });
    
    bubble.addEventListener('touchmove', (e) => {
        if (messagesState.longPressTimer) {
            const deltaX = Math.abs(e.touches[0].clientX - messagesState.touchStartX);
            const deltaY = Math.abs(e.touches[0].clientY - messagesState.touchStartY);
            if (deltaX > 10 || deltaY > 10) {
                clearTimeout(messagesState.longPressTimer);
                messagesState.longPressTimer = null;
            }
        }
    });
    
    bubble.addEventListener('touchend', (e) => {
        if (messagesState.longPressTimer) {
            clearTimeout(messagesState.longPressTimer);
            messagesState.longPressTimer = null;
        }
        
        // Check for swipe right
        const endX = e.changedTouches[0].clientX;
        const deltaX = endX - messagesState.touchStartX;
        
        if (deltaX > 50 && !messagesState.isLongPressing) {
            handleSwipeToReply(message);
        }
        
        messagesState.isLongPressing = false;
    });
    
    // Click for media preview
    bubble.querySelectorAll('[data-media-url]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            const url = el.dataset.mediaUrl;
            const type = el.dataset.mediaType;
            const name = el.dataset.mediaName;
            if (url) openMediaViewer(url, type, name);
        });
    });
    
    // Profile preview
    message.querySelectorAll('[data-profile-preview="true"]').forEach(el => {
        el.addEventListener('click', () => {
            if (messagesState.currentUser?.avatar) {
                openMediaViewer(messagesState.currentUser.avatar, 'image', `${messagesState.currentUser.username}'s profile picture`);
            }
        });
    });
}

function handleLongPress(messageElement) {
    const messageId = messageElement.dataset.messageId;
    const isMine = messageElement.dataset.messageSender === 'me';
    
    const messageData = messagesState.messages.find(m => String(m.id) === String(messageId));
    if (!messageData) return;
    
    const rect = messageElement.getBoundingClientRect();
    const menu = document.getElementById('messageContextMenu');
    
    // Position menu near the message
    menu.style.top = `${rect.top + window.scrollY - 10}px`;
    menu.style.left = `${rect.left + window.scrollX + 20}px`;
    menu.style.display = 'block';
    
    // Store active message data
    menu.activeMessage = messageData;
    menu.activeIsMine = isMine;
    
    // Update buttons based on ownership
    const editBtn = document.getElementById('contextEditBtn');
    const deleteBtn = document.getElementById('contextDeleteBtn');
    
    if (editBtn) editBtn.style.display = isMine ? 'flex' : 'none';
    if (deleteBtn) deleteBtn.style.display = isMine ? 'flex' : 'none';
}

function handleSwipeToReply(messageElement) {
    const messageId = messageElement.dataset.messageId;
    const messageData = messagesState.messages.find(m => String(m.id) === String(messageId));
    
    if (messageData) {
        setReplyToMessage(messageData);
        showToast('Replying to message', 'info');
        
        // Visual feedback
        const bubble = messageElement.querySelector('.message-bubble');
        if (bubble) {
            bubble.style.transform = 'scale(0.98)';
            setTimeout(() => {
                bubble.style.transform = '';
            }, 200);
        }
    }
}

function setReplyToMessage(message) {
    messagesState.replyToMessage = message;
    const replyIndicator = document.getElementById('replyIndicator');
    const replyPreview = document.getElementById('replyMessagePreview');
    
    if (replyPreview) {
        const previewText = message.message ? message.message.substring(0, 60) : (message.attachment ? 'Attachment' : 'Message');
        replyPreview.textContent = previewText + ((message.message?.length || 0) > 60 ? '...' : '');
    }
    
    if (replyIndicator) {
        replyIndicator.style.display = 'block';
        // Scroll reply indicator into view
        replyIndicator.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    document.getElementById('messagesInput')?.focus();
}

function clearReply() {
    messagesState.replyToMessage = null;
    const replyIndicator = document.getElementById('replyIndicator');
    if (replyIndicator) {
        replyIndicator.style.display = 'none';
    }
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file size (max 10MB for images, 20MB for PDFs)
    const maxSize = file.type === 'application/pdf' ? 20 * 1024 * 1024 : 10 * 1024 * 1024;
    if (file.size > maxSize) {
        showToast(`File too large. Max ${maxSize / (1024 * 1024)}MB`, 'error');
        event.target.value = '';
        return;
    }
    
    messagesState.pendingFile = file;
    
    const preview = document.getElementById('attachmentPreview');
    const previewName = document.getElementById('attachmentPreviewName');
    const previewIcon = document.getElementById('attachmentPreviewIcon');
    
    if (previewName) previewName.textContent = file.name;
    if (previewIcon) {
        if (file.type.startsWith('image/')) {
            previewIcon.className = 'fas fa-image';
        } else if (file.type === 'application/pdf') {
            previewIcon.className = 'fas fa-file-pdf';
        } else {
            previewIcon.className = 'fas fa-file';
        }
    }
    
    if (preview) preview.style.display = 'block';
}

function clearAttachment() {
    messagesState.pendingFile = null;
    const fileInput = document.getElementById('fileInput');
    const preview = document.getElementById('attachmentPreview');
    
    if (fileInput) fileInput.value = '';
    if (preview) preview.style.display = 'none';
}

async function sendCurrentMessage() {
    const input = document.getElementById('messagesInput');
    const sendBtn = document.getElementById('messagesSendBtn');
    
    if (!messagesState.currentUser || !input) return;
    
    const message = input.value.trim();
    const hasAttachment = messagesState.pendingFile !== null;
    
    if (!message && !hasAttachment) return;
    
    sendBtn.disabled = true;
    
    try {
        if (hasAttachment) {
            await API.sendFileMessage(messagesState.currentUser.id, messagesState.pendingFile, message, messagesState.replyToMessage?.id);
            clearAttachment();
        } else if (message) {
            await API.sendMessage(messagesState.currentUser.id, message, messagesState.replyToMessage?.id);
        }
        
        input.value = '';
        input.style.height = 'auto';
        updateMessageLength(0);
        clearReply();
        
        await loadMessages(messagesState.currentUser.id);
        await loadMessagesSidebar();
        
        input.focus();
    } catch (error) {
        console.error('Send message error:', error);
        showToast(error.message || 'Failed to send message', 'error');
    } finally {
        sendBtn.disabled = false;
    }
}

// Context menu handlers
document.getElementById('contextEditBtn')?.addEventListener('click', async () => {
    const menu = document.getElementById('messageContextMenu');
    const message = menu.activeMessage;
    if (!message) return;
    
    menu.style.display = 'none';
    
    const result = await showPrompt({
        title: 'Edit message',
        message: 'Update your message below',
        inputValue: message.message,
        confirmText: 'Save'
    });
    
    if (result.isConfirmed && result.value.trim()) {
        try {
            await API.editMessage(message.id, result.value.trim());
            await loadMessages(messagesState.currentUser.id);
            showToast('Message updated', 'success');
        } catch (error) {
            showToast(error.message, 'error');
        }
    }
});

document.getElementById('contextDeleteBtn')?.addEventListener('click', async () => {
    const menu = document.getElementById('messageContextMenu');
    const message = menu.activeMessage;
    if (!message) return;
    
    menu.style.display = 'none';
    
    const confirmed = await showConfirm({
        title: 'Delete message',
        message: 'This message will be removed for you.',
        type: 'warning',
        confirmText: 'Delete'
    });
    
    if (confirmed) {
        try {
            await API.deleteMessage(message.id);
            await loadMessages(messagesState.currentUser.id);
            showToast('Message deleted', 'success');
        } catch (error) {
            showToast(error.message, 'error');
        }
    }
});

document.getElementById('contextReplyBtn')?.addEventListener('click', () => {
    const menu = document.getElementById('messageContextMenu');
    const message = menu.activeMessage;
    if (!message) return;
    
    menu.style.display = 'none';
    setReplyToMessage(message);
});

document.getElementById('contextCopyBtn')?.addEventListener('click', () => {
    const menu = document.getElementById('messageContextMenu');
    const message = menu.activeMessage;
    if (!message) return;
    
    menu.style.display = 'none';
    
    if (message.message) {
        navigator.clipboard.writeText(message.message);
        showToast('Copied to clipboard', 'success');
    }
});

function syncActiveConversation() {
    const avatar = document.getElementById('activeMessageAvatar');
    const title = document.getElementById('activeMessageTitle');
    const subtitle = document.getElementById('activeMessageSubtitle');
    const activeChatName = document.getElementById('activeChatName');
    const activeChatMeta = document.getElementById('activeChatMeta');
    const composerWrapper = document.getElementById('composerWrapper');
    const chatPresencePill = document.getElementById('chatPresencePill');
    const viewVaultBtn = document.getElementById('openVaultFromMessagesBtn');
    const chatAvatarButton = document.getElementById('chatAvatarButton');

    if (!messagesState.currentUser) {
        avatar.innerHTML = 'M';
        title.textContent = 'Select a conversation';
        subtitle.textContent = 'Choose someone from the left to open a thread.';
        activeChatName.textContent = 'No active chat';
        activeChatMeta.textContent = 'Your secure thread history will appear here once a conversation is selected.';
        chatPresencePill.textContent = 'Offline';
        chatPresencePill.className = 'chat-presence-pill offline';
        if (composerWrapper) composerWrapper.style.display = 'none';
        if (chatAvatarButton) chatAvatarButton.disabled = true;
        if (viewVaultBtn) viewVaultBtn.setAttribute('aria-disabled', 'true');
        return;
    }

    if (messagesState.currentUser.avatar) {
        avatar.innerHTML = `<img src="${escapeAttribute(messagesState.currentUser.avatar)}" alt="${escapeAttribute(messagesState.currentUser.username)}">`;
    } else {
        avatar.textContent = (messagesState.currentUser.username || 'U').charAt(0).toUpperCase();
    }

    title.innerHTML = renderVerifiedName(messagesState.currentUser.username, messagesState.currentUser.email_verified);
    subtitle.textContent = messagesState.currentUser.is_online ? 'Online now' : (messagesState.currentUser.last_seen_ago ? `Last seen ${messagesState.currentUser.last_seen_ago}` : 'Offline');
    activeChatName.textContent = `Chatting with ${messagesState.currentUser.username}`;
    activeChatMeta.textContent = messagesState.currentUser.is_online ? 'They are online right now.' : `Messages update automatically. ${messagesState.currentUser.username} was last active ${messagesState.currentUser.last_seen_ago || 'recently'}.`;
    chatPresencePill.textContent = messagesState.currentUser.is_online ? 'Online' : 'Offline';
    chatPresencePill.className = `chat-presence-pill ${messagesState.currentUser.is_online ? 'online' : 'offline'}`;
    if (composerWrapper) composerWrapper.style.display = 'block';
    if (chatAvatarButton) chatAvatarButton.disabled = !messagesState.currentUser.avatar;
    if (viewVaultBtn) viewVaultBtn.setAttribute('aria-disabled', 'false');
}

function enableComposer(enabled) {
    const input = document.getElementById('messagesInput');
    const button = document.getElementById('messagesSendBtn');
    if (input) input.disabled = !enabled;
    if (button) button.disabled = !enabled;
}

function startMessagesPolling(userId) {
    stopMessagesPolling();
    messagesState.pollingTimer = setInterval(async () => {
        if (!messagesState.currentUser || Number(messagesState.currentUser.id) !== Number(userId)) {
            stopMessagesPolling();
            return;
        }
        await loadMessages(userId, true);
    }, 4000);
}

function stopMessagesPolling() {
    if (messagesState.pollingTimer) {
        clearInterval(messagesState.pollingTimer);
        messagesState.pollingTimer = null;
    }
}

function isNearBottom() {
    const container = document.getElementById('messagesThread');
    if (!container) return true;
    const threshold = 100;
    return container.scrollHeight - container.scrollTop - container.clientHeight <= threshold;
}

function scrollToBottom() {
    const container = document.getElementById('messagesThread');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function openMediaViewer(url, type, name) {
    const viewer = document.getElementById('chatMediaViewer');
    const stage = document.getElementById('chatMediaViewerStage');
    const title = document.getElementById('chatMediaViewerTitle');
    const downloadBtn = document.getElementById('downloadAttachmentBtn');

    if (!viewer || !stage || !title) return;
    
    closeMediaViewer();
    
    title.textContent = name || 'Attachment preview';
    window.currentMediaUrl = url;
    window.currentMediaName = name || 'download';
    
    let assetHtml = '';
    if (type === 'pdf') {
        assetHtml = `<iframe class="pdf-viewer" src="${escapeAttribute(url)}" frameborder="0"></iframe>`;
    } else if (type === 'image') {
        assetHtml = `<img class="chat-media-viewer-asset" src="${escapeAttribute(url)}" alt="${escapeAttribute(name || 'Preview')}">`;
    } else {
        assetHtml = `<video class="chat-media-viewer-asset" src="${escapeAttribute(url)}" controls autoplay playsinline></video>`;
    }
    
    stage.innerHTML = assetHtml;
    viewer.hidden = false;
    document.body.classList.add('chat-media-open');
    
    if (downloadBtn) downloadBtn.style.display = 'flex';
}

function closeMediaViewer() {
    const viewer = document.getElementById('chatMediaViewer');
    const stage = document.getElementById('chatMediaViewerStage');
    
    if (!viewer || !stage) return;
    
    stage.innerHTML = '';
    viewer.hidden = true;
    document.body.classList.remove('chat-media-open');
    window.currentMediaUrl = null;
}

function downloadCurrentAttachment() {
    if (window.currentMediaUrl) {
        const a = document.createElement('a');
        a.href = window.currentMediaUrl;
        a.download = window.currentMediaName || 'download';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }
}

function updateSearchStatus(text) {
    const status = document.getElementById('searchStatus');
    if (status) status.textContent = text;
}

function updateConversationSummary(users) {
    const conversationCount = document.getElementById('conversationCount');
    const panelUnreadCount = document.getElementById('panelUnreadCount');
    const totalUnread = users.reduce((sum, user) => sum + (Number(user.unread_count) || 0), 0);
    
    if (conversationCount) conversationCount.textContent = users.length;
    if (panelUnreadCount) panelUnreadCount.textContent = totalUnread;
}

function updateMessageSummary(count) {
    const pill = document.getElementById('messageCountPill');
    if (pill) pill.textContent = `${count} message${count === 1 ? '' : 's'}`;
}

function updateMessageLength(length) {
    const counter = document.getElementById('messageLength');
    if (counter) counter.textContent = `${length} / 2000`;
}

function restoreSelectedConversation() {
    const selectedId = Number(sessionStorage.getItem('selected_message_user_id')) || 0;
    if (!selectedId) return;
    
    const exists = messagesState.conversations.some((user) => Number(user.id) === selectedId);
    if (exists) openConversation(selectedId, false);
}

function openChatOnMobile() {
    document.querySelector('.messages-shell')?.classList.add('chat-open');
}

function closeChatOnMobile() {
    document.querySelector('.messages-shell')?.classList.remove('chat-open');
}

function createSkeletonLoader(count) {
    let skeletons = '';
    for (let i = 0; i < count; i++) {
        skeletons += '<div class="skeleton-loader"><div class="skeleton"></div></div>';
    }
    return `<div class="skeleton-loader">${skeletons}</div>`;
}

function getAvatarColor(username) {
    const colors = ['#0a4d4c', '#14655c', '#1a7d72', '#2e8b7c', '#3e9a88', '#5aab99'];
    let hash = 0;
    for (let i = 0; i < username.length; i++) {
        hash = ((hash << 5) - hash) + username.charCodeAt(i);
        hash |= 0;
    }
    return colors[Math.abs(hash) % colors.length];
}

function formatMessageTime(timestamp) {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    if (isNaN(date.getTime())) return '';
    
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    if (diff < 604800000) return date.toLocaleDateString('en-US', { weekday: 'short' });
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function escapeAttribute(text) {
    return String(text ?? '').replace(/"/g, '&quot;');
}

function renderVerifiedName(username, isVerified) {
    const safeName = escapeHtml(username || 'User');
    return `${safeName}${isVerified ? ' <span class="verified-badge" title="Verified email" aria-label="Verified email"><i class="fas fa-circle-check"></i></span>' : ''}`;
}

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

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
