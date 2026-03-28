<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault — Messages</title>
    <?php
    $styleVersion = @filemtime(__DIR__ . '/css/style.css') ?: time();
    $messagesStyleVersion = @filemtime(__DIR__ . '/css/messages.css') ?: time();
    $utilsScriptVersion = @filemtime(__DIR__ . '/js/utils.js') ?: time();
    $apiScriptVersion = @filemtime(__DIR__ . '/js/api.js') ?: time();
    $appSettingsScriptVersion = @filemtime(__DIR__ . '/js/app-settings.js') ?: time();
    $navScriptVersion = @filemtime(__DIR__ . '/js/nav.js') ?: time();
    $messagesScriptVersion = @filemtime(__DIR__ . '/js/messages.js') ?: time();
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/messages.css?v=<?= $messagesStyleVersion ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $activePage = 'messages'; include __DIR__ . '/includes/navigation.php'; ?>
    
    <main class="messages-shell">
        <aside class="conversation-panel" id="conversationPanel">
            <div class="panel-top">
                <div>
                    <p class="eyebrow">Private Inbox</p>
                    <h1>Messages</h1>
                </div>
                <button class="panel-toggle" id="panelToggle" type="button" aria-label="Toggle conversations">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <div class="search-card">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="messageUserSearch" placeholder="Search username or email">
                    <button id="clearSearchBtn" class="search-clear-btn" type="button" aria-label="Clear search">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="search-meta">
                    <span id="searchStatus">Recent conversations</span>
                    <button id="refreshMessagesSidebarBtn" class="text-btn" type="button">Refresh</button>
                </div>
            </div>

            <div class="conversation-summary">
                <div class="summary-card">
                    <span class="summary-label">Total</span>
                    <strong id="conversationCount">0</strong>
                </div>
                <div class="summary-card">
                    <span class="summary-label">Unread</span>
                    <strong id="panelUnreadCount">0</strong>
                </div>
            </div>
            
            <div class="users-list" id="messagesUserList">
                <div class="empty-users">
                    <i class="fas fa-comments"></i>
                    <p>Loading conversations...</p>
                    <p class="hint">Secure threads appear here</p>
                </div>
            </div>
        </aside>
        
        <section class="chat-stage" id="chatArea">
            <header class="chat-header" id="chatHeader">
                <div class="chat-header-main">
                    <button class="icon-btn mobile-only" id="mobileBackBtn" type="button" aria-label="Back to conversations">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button class="chat-avatar-button" id="chatAvatarButton" type="button" aria-label="View profile picture" disabled>
                        <div class="chat-avatar" id="activeMessageAvatar">M</div>
                    </button>
                    <div>
                        <h3 id="activeMessageTitle">Select a conversation</h3>
                        <p id="activeMessageSubtitle">Choose someone from the left to open a thread.</p>
                    </div>
                </div>
                <div class="chat-actions">
                    <span class="chat-presence-pill offline" id="chatPresencePill">Offline</span>
                    <button class="text-btn chat-link-action" id="openVaultFromMessagesBtn" type="button" aria-disabled="true">View vault</button>
                    <button class="icon-btn" id="refreshMessagesBtn" type="button" aria-label="Refresh messages">
                        <i class="fas fa-rotate-right"></i>
                    </button>
                </div>
            </header>

            <div class="chat-banner" id="chatBanner">
                <div>
                    <strong id="activeChatName">No active chat</strong>
                    <p id="activeChatMeta">Your secure thread history will appear here once a conversation is selected.</p>
                </div>
                <span class="message-count-pill" id="messageCountPill">0 messages</span>
            </div>
            
            <div class="messages-container" id="messagesThread">
                <div class="no-chat-selected">
                    <div class="empty-orb">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <h2>No conversation selected</h2>
                    <p>Pick a recent chat or search for a user to start a message.</p>
                </div>
            </div>
            
            <div class="composer-wrapper" id="composerWrapper" style="display: none;">
                <!-- Reply Indicator - Now below the composer, near text box -->
                <div class="reply-indicator" id="replyIndicator" style="display: none;">
                    <div class="reply-indicator-content">
                        <i class="fas fa-reply"></i>
                        <div class="reply-indicator-text">
                            <span class="reply-label">Replying to</span>
                            <span class="reply-message-preview" id="replyMessagePreview"></span>
                        </div>
                        <button class="reply-cancel-btn" id="cancelReplyBtn" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="composer-shell" id="messagesComposer">
                    <div class="composer-toolbar">
                        <span id="composerHint">Press Enter to send • Long press message for actions • Swipe right to reply</span>
                        <span id="messageLength">0 / 2000</span>
                    </div>
                    <div class="message-input">
                        <div class="attachment-preview" id="attachmentPreview" style="display: none;">
                            <div class="attachment-preview-content">
                                <i class="fas fa-file" id="attachmentPreviewIcon"></i>
                                <span id="attachmentPreviewName"></span>
                                <button type="button" id="removeAttachmentBtn" class="remove-attachment">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="input-group">
                            <button id="attachFileBtn" class="icon-btn attach-btn" type="button" aria-label="Attach file">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" id="fileInput" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" hidden>
                            <textarea id="messagesInput" placeholder="Write a secure message..." rows="1" maxlength="2000" disabled></textarea>
                            <button id="messagesSendBtn" class="send-btn" type="button" disabled>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Context Menu for Message Actions -->
    <div class="message-context-menu" id="messageContextMenu" style="display: none;">
        <div class="context-menu-content">
            <button id="contextEditBtn" class="context-menu-item">
                <i class="fas fa-edit"></i>
                <span>Edit message</span>
            </button>
            <button id="contextDeleteBtn" class="context-menu-item">
                <i class="fas fa-trash-alt"></i>
                <span>Delete message</span>
            </button>
            <button id="contextReplyBtn" class="context-menu-item">
                <i class="fas fa-reply"></i>
                <span>Reply</span>
            </button>
            <button id="contextCopyBtn" class="context-menu-item">
                <i class="fas fa-copy"></i>
                <span>Copy text</span>
            </button>
        </div>
    </div>

    <!-- Media/File Viewer -->
    <div class="chat-media-viewer" id="chatMediaViewer" hidden>
        <div class="chat-media-viewer-backdrop" data-media-close="true"></div>
        <div class="chat-media-viewer-dialog" role="dialog" aria-modal="true">
            <div class="chat-media-viewer-topbar">
                <div class="chat-media-viewer-copy">
                    <span class="chat-media-viewer-kicker">Secure Preview</span>
                    <h3 id="chatMediaViewerTitle">Attachment preview</h3>
                </div>
                <div class="chat-media-viewer-actions">
                    <button id="downloadAttachmentBtn" class="chat-media-viewer-download" type="button" aria-label="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="chat-media-viewer-close" id="chatMediaViewerClose" type="button" aria-label="Close preview">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="chat-media-viewer-stage" id="chatMediaViewerStage"></div>
        </div>
    </div>

    <div id="toast" class="toast"></div>
    
    <script src="js/utils.js?v=<?= $utilsScriptVersion ?>"></script>
    <script src="js/api.js?v=<?= $apiScriptVersion ?>"></script>
    <script src="js/app-settings.js?v=<?= $appSettingsScriptVersion ?>"></script>
    <script src="js/nav.js?v=<?= $navScriptVersion ?>"></script>
    <script src="js/messages.js?v=<?= $messagesScriptVersion ?>"></script>
</body>
</html>