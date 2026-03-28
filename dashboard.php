<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Aether Vault - Dashboard</title>
    <?php
    $styleVersion = @filemtime(__DIR__ . '/css/style.css') ?: time();
    $dashboardStyleVersion = @filemtime(__DIR__ . '/css/dashboard.css') ?: time();
    $utilsScriptVersion = @filemtime(__DIR__ . '/js/utils.js') ?: time();
    $apiScriptVersion = @filemtime(__DIR__ . '/js/api.js') ?: time();
    $appSettingsScriptVersion = @filemtime(__DIR__ . '/js/app-settings.js') ?: time();
    $navScriptVersion = @filemtime(__DIR__ . '/js/nav.js') ?: time();
    $dashboardScriptVersion = @filemtime(__DIR__ . '/js/dashboard.js') ?: time();
    ?>
    <link rel="stylesheet" href="css/style.css?v=<?= $styleVersion ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= $dashboardStyleVersion ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php $activePage = 'dashboard'; $showBiometric = true; include __DIR__ . '/includes/navigation.php'; ?>
    
    <main class="vault-shell">
        <section class="vault-hero">
            <div class="vault-hero-copy">
                <p class="hero-kicker">Private Vault</p>
                <h1 id="vaultHeroTitle">Store, preview, and manage your media in one secure workspace.</h1>
                <p class="hero-subtitle" id="vaultHeroSubtitle">Your library updates in real time with quick filters, storage insights, and direct actions for every file.</p>
                <div class="vault-viewer-banner" id="vaultViewerBanner" hidden></div>
                <div class="hero-actions">
                    <button id="uploadBtn" class="upload-fab" type="button">
                        <i class="fas fa-plus"></i>
                        <span>Upload Media</span>
                    </button>
                    <button id="refreshMediaBtn" class="secondary-btn" type="button">
                        <i class="fas fa-rotate-right"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <div class="vault-stats">
                <article class="stat-card accent">
                    <span class="stat-label">Total Files</span>
                    <strong id="totalFilesStat">0</strong>
                    <span class="stat-meta" id="latestUploadStat">No uploads yet</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Storage Used</span>
                    <strong id="storageUsedStat">0 Bytes</strong>
                    <span class="stat-meta">Across images and videos</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Images</span>
                    <strong id="imageCountStat">0</strong>
                    <span class="stat-meta">Photo-ready assets</span>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Videos</span>
                    <strong id="videoCountStat">0</strong>
                    <span class="stat-meta">Motion uploads</span>
                </article>
            </div>
        </section>

        <section class="vault-toolbar">
            <div class="toolbar-search">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="mediaSearch" placeholder="Search by filename">
            </div>
            <div class="toolbar-controls">
                <div class="filter-pills">
                    <button class="filter-pill active" type="button" data-filter="all">All</button>
                    <button class="filter-pill" type="button" data-filter="image">Images</button>
                    <button class="filter-pill" type="button" data-filter="video">Videos</button>
                </div>
                <select id="sortMedia" class="toolbar-select">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                    <option value="largest">Largest size</option>
                    <option value="smallest">Smallest size</option>
                    <option value="name">Filename A-Z</option>
                </select>
            </div>
        </section>

        <section class="vault-content">
            <div class="section-head">
                <div>
                    <p class="section-kicker">Library</p>
                    <h2 id="vaultSectionTitle">Your media collection</h2>
                </div>
                <span class="results-pill" id="resultsCount">0 items</span>
            </div>

            <div class="media-grid" id="mediaGrid">
                <div class="skeleton-loader">
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                    <div class="skeleton shimmer"></div>
                </div>
            </div>
        </section>
        
        <div id="uploadModal" class="modal">
            <div class="modal-content upload-modal-card">
                <div class="modal-header">
                    <div>
                        <h3>Upload Media</h3>
                        <p class="modal-subtitle">Add images or videos to your private vault.</p>
                    </div>
                    <span class="close">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="upload-area" id="uploadArea">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <p>Drag and drop files here or click to browse</p>
                        <small>Supported: JPG, PNG, GIF, WEBP, MP4, MPEG, MOV, WEBM</small>
                        <input type="file" id="fileInput" multiple accept="image/*,video/*">
                    </div>
                    <div id="uploadQueue" class="upload-queue"></div>
                    <div id="uploadProgress" class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <p id="progressText">Uploading...</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="mediaViewer" class="media-viewer" style="display: none;">
            <div class="viewer-shell">
                <div class="viewer-topbar">
                    <div class="viewer-heading">
                        <p class="section-kicker">Fullscreen preview</p>
                        <h3 id="viewerTitle">Media item</h3>
                    </div>
                    <div class="viewer-topbar-actions">
                        <a id="downloadMediaBtn" class="secondary-btn viewer-download-btn" href="#" download>
                            <i class="fas fa-download"></i>
                            <span>Download</span>
                        </a>
                        <button class="viewer-close" type="button" aria-label="Close preview">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="viewer-stage">
                    <img id="viewerImage" src="" alt="">
                    <video id="viewerVideo" controls></video>
                </div>

                <aside class="viewer-dock">
                    <div class="viewer-meta" id="viewerMeta"></div>
                    <div class="viewer-actions">
                        <button id="deleteMediaBtn" class="danger-btn" type="button">
                            <i class="fas fa-trash"></i>
                            <span>Delete</span>
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </main>

    <div id="toast" class="toast"></div>
    
    <script src="js/utils.js?v=<?= $utilsScriptVersion ?>"></script>
    <script src="js/api.js?v=<?= $apiScriptVersion ?>"></script>
    <script src="js/app-settings.js?v=<?= $appSettingsScriptVersion ?>"></script>
    <script src="js/nav.js?v=<?= $navScriptVersion ?>"></script>
    <script src="js/dashboard.js?v=<?= $dashboardScriptVersion ?>"></script>
</body>
</html>
