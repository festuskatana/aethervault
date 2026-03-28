let allMediaItems = [];
let activeFilter = 'all';
let activeSort = 'newest';
let activeQuery = '';
let currentViewerItem = null;
const vaultViewParams = new URLSearchParams(window.location.search);
const viewedVaultUserId = Number(vaultViewParams.get('user_id')) || null;
let isReadOnlyVault = false;
let currentVaultOwner = null;
const cachedVaultUser = getSelectedChatUser();

document.addEventListener('DOMContentLoaded', async () => {
    if (!isAuthenticated()) {
        redirectToLogin();
        return;
    }

    bindDashboardUI();
    await loadMedia();
});

function bindDashboardUI() {
    setupUploadModal();
    setupMediaViewer();

    const refreshMediaBtn = document.getElementById('refreshMediaBtn');
    if (refreshMediaBtn) {
        refreshMediaBtn.addEventListener('click', async () => {
            await loadMedia();
            showToast('Media library refreshed', 'success');
        });
    }

    const mediaSearch = document.getElementById('mediaSearch');
    if (mediaSearch) {
        mediaSearch.addEventListener('input', debounce((event) => {
            activeQuery = event.target.value.trim().toLowerCase();
            renderMedia();
        }, 180));
    }

    document.querySelectorAll('.filter-pill').forEach((pill) => {
        pill.addEventListener('click', () => {
            activeFilter = pill.dataset.filter;
            document.querySelectorAll('.filter-pill').forEach((button) => button.classList.remove('active'));
            pill.classList.add('active');
            renderMedia();
        });
    });

    const sortMedia = document.getElementById('sortMedia');
    if (sortMedia) {
        sortMedia.addEventListener('change', (event) => {
            activeSort = event.target.value;
            renderMedia();
        });
    }
}

async function loadMedia() {
    const mediaGrid = document.getElementById('mediaGrid');
    mediaGrid.innerHTML = createSkeletonLoader(1);

    try {
        const response = await API.getMedia(viewedVaultUserId);
        if (viewedVaultUserId && Number(response.vault_owner?.id) !== Number(viewedVaultUserId)) {
            throw new Error('Loaded the wrong vault owner');
        }
        allMediaItems = response.media || [];
        currentVaultOwner = response.vault_owner || null;
        isReadOnlyVault = !response.viewer?.is_owner;
        syncVaultMode();
        updateStats(allMediaItems);
        renderMedia();
    } catch (error) {
        console.error('Error loading media:', error);
        if (viewedVaultUserId && cachedVaultUser && Number(cachedVaultUser.id) === Number(viewedVaultUserId)) {
            currentVaultOwner = cachedVaultUser;
            isReadOnlyVault = true;
            syncVaultMode();
        }
        mediaGrid.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>Failed to load media. Please try again.</p>
                <button onclick="loadMedia()" class="retry-btn">Retry</button>
            </div>
        `;
    }
}

function renderMedia() {
    const mediaGrid = document.getElementById('mediaGrid');
    const filteredMedia = getFilteredMedia();
    const resultsCount = document.getElementById('resultsCount');

    if (resultsCount) {
        resultsCount.textContent = `${filteredMedia.length} item${filteredMedia.length === 1 ? '' : 's'}`;
    }

    if (!filteredMedia.length) {
        mediaGrid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-photo-film"></i>
                <p>No media matches your current filters.</p>
            </div>
        `;
        return;
    }

    mediaGrid.innerHTML = filteredMedia.map((item) => {
        const mediaType = item.file_type || item.type;
        const mediaSize = item.file_size || item.size || 0;
        const mediaName = item.original_filename || item.filename || 'Untitled media';
        const mediaUrl = item.url || buildMediaUrl(item);
        const thumb = mediaType === 'image'
            ? `<img class="media-asset" src="${escapeAttribute(mediaUrl)}" alt="${escapeAttribute(mediaName)}" loading="lazy">`
            : `<video class="media-asset media-grid-video" src="${escapeAttribute(mediaUrl)}" preload="metadata" muted playsinline loop autoplay></video>`;

        return `
            <article class="media-card">
                <div class="media-thumb" data-action="preview" data-media-id="${item.id}">
                    <div class="media-thumb-skeleton skeleton shimmer" aria-hidden="true"></div>
                    ${thumb}
                    <span class="media-badge">${mediaType}</span>
                </div>
                <div class="media-card-body">
                    <div class="media-card-top">
                        <h3 class="media-card-title">${escapeHtml(mediaName)}</h3>
                        <span class="media-card-size">${formatFileSize(mediaSize)}</span>
                    </div>
                    <p class="media-card-meta">Added ${item.created_ago || formatMessageTime(item.created_at)}</p>
                    <div class="media-card-footer">
                        <button class="media-link-btn" type="button" data-action="preview" data-media-id="${item.id}">Preview</button>
                        ${isReadOnlyVault ? '' : `<button class="media-delete-btn" type="button" data-action="delete" data-media-id="${item.id}">Delete</button>`}
                    </div>
                </div>
            </article>
        `;
    }).join('');

    mediaGrid.querySelectorAll('[data-action="preview"]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = allMediaItems.find((media) => Number(media.id) === Number(button.dataset.mediaId));
            if (item) {
                openMediaViewer(item);
            }
        });
    });

    if (!isReadOnlyVault) {
        mediaGrid.querySelectorAll('[data-action="delete"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const mediaId = Number(button.dataset.mediaId);
                await deleteMedia(mediaId);
            });
        });
    }

    initMediaAssetSkeletons(mediaGrid);
}

function getFilteredMedia() {
    const items = [...allMediaItems].filter((item) => {
        const matchesFilter = activeFilter === 'all' || (item.file_type || item.type) === activeFilter;
        const mediaName = String(item.original_filename || item.filename || '').toLowerCase();
        const matchesQuery = !activeQuery || mediaName.includes(activeQuery);
        return matchesFilter && matchesQuery;
    });

    return items.sort((a, b) => {
        switch (activeSort) {
            case 'oldest':
                return new Date(a.created_at) - new Date(b.created_at);
            case 'largest':
                return (b.file_size || b.size || 0) - (a.file_size || a.size || 0);
            case 'smallest':
                return (a.file_size || a.size || 0) - (b.file_size || b.size || 0);
            case 'name':
                return a.filename.localeCompare(b.filename);
            case 'newest':
            default:
                return new Date(b.created_at) - new Date(a.created_at);
        }
    });
}

function updateStats(mediaItems) {
    const totalFiles = mediaItems.length;
    const images = mediaItems.filter((item) => (item.file_type || item.type) === 'image').length;
    const videos = mediaItems.filter((item) => (item.file_type || item.type) === 'video').length;
    const totalStorage = mediaItems.reduce((sum, item) => sum + Number(item.file_size || item.size || 0), 0);
    const latestItem = mediaItems[0];

    setText('totalFilesStat', totalFiles);
    setText('imageCountStat', images);
    setText('videoCountStat', videos);
    setText('storageUsedStat', formatFileSize(totalStorage));
    setText('latestUploadStat', latestItem ? `Latest: ${latestItem.original_filename || latestItem.filename}` : 'No uploads yet');
}

function setupUploadModal() {
    const modal = document.getElementById('uploadModal');
    const uploadBtn = document.getElementById('uploadBtn');
    const closeBtn = modal.querySelector('.close');
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');

    if (isReadOnlyVault) {
        uploadBtn.style.display = 'none';
        return;
    }

    uploadBtn.addEventListener('click', () => {
        modal.style.display = 'flex';
    });

    closeBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    uploadArea.addEventListener('click', () => {
        fileInput.click();
    });

    uploadArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        uploadArea.style.borderColor = 'var(--midnight-emerald)';
        uploadArea.style.background = 'var(--soft-mint)';
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.style.borderColor = '#d8e1dc';
        uploadArea.style.background = 'transparent';
    });

    uploadArea.addEventListener('drop', async (event) => {
        event.preventDefault();
        uploadArea.style.borderColor = '#d8e1dc';
        uploadArea.style.background = 'transparent';
        const files = Array.from(event.dataTransfer.files);
        await uploadFiles(files);
    });

    fileInput.addEventListener('change', async (event) => {
        const files = Array.from(event.target.files);
        await uploadFiles(files);
        fileInput.value = '';
    });
}

async function uploadFiles(files) {
    const uploadProgress = document.getElementById('uploadProgress');
    const progressFill = uploadProgress.querySelector('.progress-fill');
    const progressText = document.getElementById('progressText');
    const uploadQueue = document.getElementById('uploadQueue');

    if (!files.length) {
        return;
    }

    uploadQueue.innerHTML = files.map((file) => `
        <div class="upload-queue-item">
            <span>${escapeHtml(file.name)}</span>
            <strong>${formatFileSize(file.size)}</strong>
        </div>
    `).join('');

    uploadProgress.style.display = 'block';

    for (let index = 0; index < files.length; index += 1) {
        const file = files[index];
        const isSupportedMedia = file.type.startsWith('image/') || file.type.startsWith('video/');
        if (!isSupportedMedia) {
            showToast(`Skipped ${file.name}: unsupported file type`, 'error');
            continue;
        }

        const progress = ((index + 1) / files.length) * 100;
        progressFill.style.width = `${progress}%`;
        progressText.textContent = `Uploading ${file.name}...`;

        try {
            await API.uploadMedia(file);
            showToast(`${file.name} uploaded successfully!`, 'success');
        } catch (error) {
            showToast(`Failed to upload ${file.name}: ${error.message}`, 'error');
        }
    }

    setTimeout(async () => {
        uploadProgress.style.display = 'none';
        progressFill.style.width = '0%';
        uploadQueue.innerHTML = '';
        document.getElementById('uploadModal').style.display = 'none';
        await loadMedia();
    }, 700);
}

function setupMediaViewer() {
    const viewer = document.getElementById('mediaViewer');
    const closeBtn = viewer.querySelector('.viewer-close');
    const deleteMediaBtn = document.getElementById('deleteMediaBtn');

    closeBtn.addEventListener('click', closeMediaViewer);

    window.addEventListener('click', (event) => {
        if (event.target === viewer) {
            closeMediaViewer();
        }
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && viewer.style.display !== 'none') {
            closeMediaViewer();
        }
    });

    if (deleteMediaBtn) {
        deleteMediaBtn.addEventListener('click', async () => {
            if (currentViewerItem) {
                await deleteMedia(currentViewerItem.id, true);
            }
        });
    }
}

function openMediaViewer(item) {
    currentViewerItem = item;

    const viewer = document.getElementById('mediaViewer');
    const viewerImage = document.getElementById('viewerImage');
    const viewerVideo = document.getElementById('viewerVideo');
    const viewerTitle = document.getElementById('viewerTitle');
    const viewerMeta = document.getElementById('viewerMeta');
    const downloadBtn = document.getElementById('downloadMediaBtn');
    const deleteMediaBtn = document.getElementById('deleteMediaBtn');

    const mediaType = item.file_type || item.type;
    const mediaUrl = item.url || buildMediaUrl(item);
    const mediaSize = item.file_size || item.size || 0;
    const mediaName = item.original_filename || item.filename || 'Untitled media';

    if (mediaType === 'image') {
        viewerImage.style.display = 'block';
        viewerVideo.style.display = 'none';
        viewerImage.src = mediaUrl;
        viewerVideo.pause();
    } else {
        viewerImage.style.display = 'none';
        viewerVideo.style.display = 'block';
        viewerVideo.src = mediaUrl;
    }

    viewerTitle.textContent = mediaName;
    viewerMeta.innerHTML = `
        <span>Type: ${mediaType}</span>
        <span>Size: ${formatFileSize(mediaSize)}</span>
        <span>Uploaded: ${item.created_at}</span>
    `;
    downloadBtn.href = mediaUrl;
    downloadBtn.download = mediaName;
    if (deleteMediaBtn) {
        deleteMediaBtn.style.display = isReadOnlyVault ? 'none' : 'inline-flex';
    }

    viewer.style.display = 'grid';
    document.body.classList.add('dialog-open');
}

function closeMediaViewer() {
    const viewer = document.getElementById('mediaViewer');
    const viewerVideo = document.getElementById('viewerVideo');
    viewer.style.display = 'none';
    viewerVideo.pause();
    document.body.classList.remove('dialog-open');
    currentViewerItem = null;
}

async function deleteMedia(mediaId, shouldCloseViewer = false) {
    if (isReadOnlyVault) {
        showToast('This vault is read-only', 'error');
        return;
    }

    const item = allMediaItems.find((media) => Number(media.id) === Number(mediaId));
    if (!item) {
        return;
    }

    const confirmed = await showConfirm({
        title: 'Delete media',
        message: `Delete "${item.original_filename || item.filename || 'this file'}" from your vault?`,
        type: 'warning',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    });
    if (!confirmed) {
        return;
    }

    try {
        await API.deleteMedia(mediaId);
        allMediaItems = allMediaItems.filter((media) => Number(media.id) !== Number(mediaId));
        updateStats(allMediaItems);
        renderMedia();
        if (shouldCloseViewer) {
            closeMediaViewer();
        }
        showToast('Media deleted successfully', 'success');
    } catch (error) {
        console.error('Delete media error:', error);
        showToast(error.message || 'Failed to delete media', 'error');
    }
}

function syncVaultMode() {
    const uploadBtn = document.getElementById('uploadBtn');
    const heroTitle = document.getElementById('vaultHeroTitle');
    const heroSubtitle = document.getElementById('vaultHeroSubtitle');
    const sectionTitle = document.getElementById('vaultSectionTitle');
    const viewerBanner = document.getElementById('vaultViewerBanner');
    const ownerName = currentVaultOwner?.full_name || currentVaultOwner?.username || 'this user';

    if (uploadBtn) {
        uploadBtn.style.display = isReadOnlyVault ? 'none' : 'inline-flex';
    }

    if (isReadOnlyVault) {
        heroTitle.textContent = `Browse ${ownerName}'s vault files in read-only mode.`;
        heroSubtitle.textContent = 'You can preview and download files here, but uploads and deletions are disabled.';
        sectionTitle.textContent = `${ownerName}'s media collection`;
        if (viewerBanner) {
            viewerBanner.hidden = false;
            viewerBanner.textContent = `Viewing ${ownerName}'s vault`;
        }
    } else {
        heroTitle.textContent = 'Store, preview, and manage your media in one secure workspace.';
        heroSubtitle.textContent = 'Your library updates in real time with quick filters, storage insights, and direct actions for every file.';
        sectionTitle.textContent = 'Your media collection';
        if (viewerBanner) {
            viewerBanner.hidden = true;
            viewerBanner.textContent = '';
        }
    }
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function buildMediaUrl(item) {
    const normalizedPath = String(item.file_path || '').replace(/^\/+/, '');
    if (normalizedPath) {
        return `${window.location.origin}${PROJECT_BASE_PATH}backend/${normalizedPath}`;
    }
    return `${window.location.origin}${PROJECT_BASE_PATH}backend/uploads/${item.filename || ''}`;
}

function initMediaAssetSkeletons(scope) {
    scope.querySelectorAll('.media-thumb').forEach((thumb) => {
        const asset = thumb.querySelector('.media-asset');
        if (!asset) {
            return;
        }

        const markLoaded = () => {
            thumb.classList.add('loaded');
        };

        const markError = () => {
            thumb.classList.add('loaded', 'load-error');
        };

        if (asset.tagName === 'IMG') {
            if (asset.complete && asset.naturalWidth > 0) {
                markLoaded();
            } else {
                asset.addEventListener('load', markLoaded, { once: true });
                asset.addEventListener('error', markError, { once: true });
            }
            return;
        }

        if (asset.tagName === 'VIDEO') {
            if (asset.readyState >= 2) {
                markLoaded();
            } else {
                asset.addEventListener('loadeddata', markLoaded, { once: true });
                asset.addEventListener('loadedmetadata', markLoaded, { once: true });
                asset.addEventListener('error', markError, { once: true });
            }

            asset.muted = true;
            asset.playsInline = true;
            const playPromise = asset.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {
                    // Ignore autoplay rejections; the preview stays as a static poster frame.
                });
            }
        }
    });
}

function getSelectedChatUser() {
    try {
        const stored = sessionStorage.getItem('selected_chat_user');
        return stored ? JSON.parse(stored) : null;
    } catch (error) {
        return null;
    }
}
