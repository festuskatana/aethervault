document.addEventListener('DOMContentLoaded', () => {
    loadAppBranding();
});

async function loadAppBranding(force = false) {
    try {
        const response = await API.getPublicSettings();
        const settings = response.settings || {};
        applyAppBranding(settings, force);
    } catch (error) {
        console.error('App branding load error:', error);
    }
}

function applyAppBranding(settings, force = false) {
    const appName = settings.app_name || 'Aether Vault';
    const logo = settings.logo || null;

    document.querySelectorAll('[data-app-name]').forEach((node) => {
        node.textContent = appName;
    });

    if (document.title) {
        const currentTitle = document.title;
        if (currentTitle.includes(' - ')) {
            const titleSuffix = currentTitle.split(' - ').slice(1).join(' - ');
            document.title = `${appName} - ${titleSuffix}`;
        } else if (force || !currentTitle.trim() || currentTitle === 'Aether Vault') {
            document.title = appName;
        }
    }

    document.querySelectorAll('[data-app-logo]').forEach((image) => {
        if (logo) {
            image.src = logo;
            image.style.display = 'block';
        } else {
            image.removeAttribute('src');
            image.style.display = 'none';
        }
    });

    document.querySelectorAll('[data-app-logo-fallback]').forEach((fallback) => {
        fallback.style.display = logo ? 'none' : 'inline-flex';
    });

    applyFavicon(logo, appName);
}

function applyFavicon(logo, appName) {
    const head = document.head || document.querySelector('head');
    if (!head) {
        return;
    }

    let favicon = document.querySelector('link[rel="icon"][data-dynamic-favicon]');
    if (!favicon) {
        favicon = document.createElement('link');
        favicon.rel = 'icon';
        favicon.setAttribute('data-dynamic-favicon', 'true');
        head.appendChild(favicon);
    }

    favicon.type = logo ? 'image/png' : 'image/svg+xml';
    favicon.href = logo || buildFallbackFavicon(appName);
}

function buildFallbackFavicon(appName) {
    const letter = String(appName || 'A').trim().charAt(0).toUpperCase() || 'A';
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
            <rect width="64" height="64" rx="16" fill="#0A4D4C"/>
            <text x="32" y="41" text-anchor="middle" font-size="30" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">${letter}</text>
        </svg>
    `;

    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}
