// Library v2.0 - Wiki Application (PostgreSQL версия)
// @bybyscan 13.07.2025

// Global state
const appState = {
    lastSearchQuery: '',
    currentFile: null,
    baseUrl: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1),
    debug: true
};

// DOM Elements
const dom = {
    searchInput: document.getElementById('searchInput'),
    searchResults: document.getElementById('searchResults'),
    mainContent: document.getElementById('mainContent'),
    navLinks: document.querySelectorAll('.nav-link')
};

// Initialize
function init() {
    log('App initialized (PostgreSQL)');
    setupEventListeners();
    loadInitialState();
}

// Logging
function log(...args) {
    if (appState.debug) {
        console.log('[Wiki App]', ...args);
    }
}

// Setup events
function setupEventListeners() {
    dom.searchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#searchResults') && !e.target.closest('.search-container')) {
            dom.searchResults.style.display = 'none';
        }
    });

    window.addEventListener('popstate', handlePopState);
}

// Load state from URL
function loadInitialState() {
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');
    const searchQuery = urlParams.get('q');

    if (page) {
        log('Loading page from URL:', page);
        openFile(page);
    } else if (searchQuery) {
        log('Loading search from URL:', searchQuery);
        dom.searchInput.value = decodeURIComponent(searchQuery);
        performSearch();
    }
}

// Handle popstate
function handlePopState(event) {
    if (event.state && event.state.file) {
        log('Popstate navigation to:', event.state.file);
        openFile(event.state.file, false);
    }
}

// Open and display a file (load from load_file.php)
function openFile(fileName, updateHistory = true) {
    if (appState.currentFile === fileName) {
        log('File already open:', fileName);
        return;
    }

    log('Opening file:', fileName);
    appState.currentFile = fileName;
    updateActiveNavLink(fileName);

    dom.mainContent.innerHTML = '<div class="loading">Загрузка...</div>';

    fetch('load_file.php?file=' + encodeURIComponent(fileName))
        .then(response => {
            log('File load response:', response.status);
            if (!response.ok) throw new Error('Ошибка загрузки файла (HTTP ' + response.status + ')');
            return response.text();
        })
        .then(data => {
            dom.mainContent.innerHTML = data;
            addCopyButtons();
            applySearchHighlighting();

            if (updateHistory) {
                history.pushState({ file: fileName }, '', `?page=${encodeURIComponent(fileName)}`);
            }
            log('File loaded successfully');
        })
        .catch(error => {
            log('File load error:', error.message);
            dom.mainContent.innerHTML = `
                <div class="error">
                    <h3>Ошибка при загрузке файла</h3>
                    <p>${escapeHtml(error.message)}</p>
                    <p>Файл: ${escapeHtml(fileName)}</p>
                    <button onclick="openFile('${escapeHtml(fileName)}')">Повторить</button>
                </div>
            `;
        });
}

// Update active nav link
function updateActiveNavLink(fileName) {
    dom.navLinks.forEach(link => {
        if (link.dataset.file === fileName) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// --------------------- SEARCH (AJAX версия) ---------------------

// Perform search via AJAX to index.php
function performSearch() {
    const query = dom.searchInput.value.trim();
    if (!query) {
        dom.searchResults.style.display = 'none';
        return;
    }

    log('Performing search via AJAX for:', query);
    appState.lastSearchQuery = query;

    dom.searchResults.innerHTML = '<div class="loading">Поиск...</div>';
    dom.searchResults.style.display = 'block';

    fetch(`?ajax=search&q=${encodeURIComponent(query)}&t=${Date.now()}`)
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(results => {
            log('Search results:', results.length);
            displaySearchResults(results, query);
            // Update URL
            history.pushState({ search: query }, '', `?q=${encodeURIComponent(query)}`);
        })
        .catch(error => {
            log('Search error:', error.message);
            dom.searchResults.innerHTML = `
                <div class="error">
                    <h3>Ошибка при выполнении поиска</h3>
                    <p>${escapeHtml(error.message)}</p>
                    <button onclick="performSearch()">Повторить</button>
                </div>
            `;
        });
}

// Display search results (from DB)
function displaySearchResults(results, query) {
    if (!results || results.length === 0) {
        dom.searchResults.innerHTML = `
            <div class="no-results">
                <h3>Ничего не найдено</h3>
                <p>По запросу "<span class="query">${escapeHtml(query)}</span>"</p>
                <p class="hint">Попробуйте:</p>
                <ul class="hint-list">
                    <li>Ввести номер страницы (например: 0008)</li>
                    <li>Ввести язык (например: php)</li>
                    <li>Ввести тему (например: link, svg)</li>
                    <li>Использовать более короткий запрос</li>
                </ul>
            </div>
        `;
        return;
    }

    let html = `
        <div class="results-header">
            <span class="results-count">Найдено: ${results.length}</span>
            <span class="results-query">"${escapeHtml(query)}"</span>
        </div>
        <div class="results-list">
    `;

    results.forEach(result => {
        const relevanceClass = result.relevance >= 0.5 ? 'high' : (result.relevance >= 0.2 ? 'medium' : 'low');
        const relevancePercent = Math.round(result.relevance * 100);

        html += `
            <div class="result-item" onclick="openFile('${escapeHtml(result.filename)}')">
                <div class="result-header">
                    <h3>${highlightMatches(escapeHtml(result.title || result.filename), query)}</h3>
                    <span class="file-number">#${escapeHtml(result.num || '')}</span>
                </div>
                <div class="file-meta">
                    ${result.lang ? `<span class="file-lang">${escapeHtml(result.lang)}</span>` : ''}
                    ${result.topic ? `<span class="file-topic">${escapeHtml(result.topic)}</span>` : ''}
                </div>
                <div class="relevance-badge ${relevanceClass}">
                    Релевантность: ${relevancePercent}%
                </div>
                <div class="file-name">${escapeHtml(result.filename)}</div>
            </div>
        `;
    });

    html += '</div>';
    dom.searchResults.innerHTML = html;
}

// --------------------- HIGHLIGHTING ---------------------

// Apply search highlighting to content
function applySearchHighlighting() {
    if (!appState.lastSearchQuery || appState.lastSearchQuery.length < 2) return;

    const query = appState.lastSearchQuery;
    const regex = new RegExp(escapeRegExp(query), 'gi');

    function highlightTextNodes(element) {
        if (element.nodeType === Node.TEXT_NODE) {
            const text = element.nodeValue;
            if (text && regex.test(text)) {
                const span = document.createElement('span');
                span.innerHTML = text.replace(regex, match => `<span class="search-highlight">${match}</span>`);
                element.parentNode.replaceChild(span, element);
            }
        } else if (element.nodeType === Node.ELEMENT_NODE) {
            if (element.tagName === 'SCRIPT' ||
                element.tagName === 'STYLE' ||
                element.classList.contains('search-highlight') ||
                element.classList.contains('copy-button')) {
                return;
            }
            const childNodes = Array.from(element.childNodes);
            childNodes.forEach(child => highlightTextNodes(child));
        }
    }

    highlightTextNodes(dom.mainContent);
}

// Highlight matches in a string (for search results)
function highlightMatches(text, query) {
    if (!query || !text) return text;
    try {
        const regex = new RegExp(escapeRegExp(query), 'gi');
        return text.replace(regex, match => `<span class="search-highlight">${match}</span>`);
    } catch (e) {
        return text;
    }
}

// --------------------- COPY BUTTONS ---------------------

function addCopyButtons() {
    document.querySelectorAll('pre code').forEach(code => {
        if (code.closest('pre').querySelector('.copy-button')) return;
        const pre = code.closest('pre');
        const button = document.createElement('button');
        button.className = 'copy-button';
        button.innerHTML = '📋 Копировать';
        button.title = 'Скопировать код в буфер обмена';

        button.onclick = async (e) => {
            e.preventDefault();
            await handleCopyButtonClick(button, code.textContent);
        };

        pre.style.position = 'relative';
        pre.appendChild(button);
    });
}

async function handleCopyButtonClick(button, text) {
    try {
        await copyToClipboard(text);
        button.innerHTML = '✓ Скопировано!';
        button.classList.add('success');
        setTimeout(() => {
            button.innerHTML = '📋 Копировать';
            button.classList.remove('success');
        }, 2000);
    } catch (err) {
        log('Copy error:', err);
        button.innerHTML = '✗ Ошибка';
        button.classList.add('error');
        setTimeout(() => {
            button.innerHTML = '📋 Копировать';
            button.classList.remove('error');
        }, 2000);
    }
}

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (err) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}

// --------------------- HELPERS ---------------------

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// --------------------- INIT ---------------------

document.addEventListener('DOMContentLoaded', () => {
    log('DOM loaded');
    init();
});

// Expose globally for onclick handlers
window.openFile = openFile;
window.performSearch = performSearch;
