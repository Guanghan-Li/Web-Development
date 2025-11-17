const inputs = document.querySelectorAll('input');
console.log('Inputs found without defer:', inputs.length);

// Exploring defer
// 1. With the script tag in the head and no defer, this query runs before the DOM has parsed the <input> elements, so it finds 0 inputs.
// 2. The defer attribute downloads the script while the page parses, but waits to execute it until after the HTML is fully parsed.
// 3. The browser must finish building the DOM (right before the DOMContentLoaded event fires) before a deferred script runs.
// 4. With defer: Request page -> Start parsing HTML + fetch script -> Finish DOM construction -> Execute deferred script -> Fire DOMContentLoaded -> Fire load.
// 5. Without defer: Request page -> Start parsing HTML -> Hit script tag (parsing pauses) -> Fetch & run script -> Resume parsing HTML -> Fire DOMContentLoaded -> Fire load.

const includedTags = [];

function createTag(label) {
    const button = document.createElement('button');
    button.classList.add('tag');
    button.textContent = label;
    button.addEventListener('click', () => {
        button.remove();

        const tagIndex = includedTags.indexOf(label);
        if (tagIndex !== -1) {
            includedTags.splice(tagIndex, 1);
        }

        hideArticles();
    });
    return button;
}

function sanitizeTag(term) {
    return typeof term === 'string' ? term.trim().toLowerCase() : '';
}

function hideArticles() {
    const articles = Array.from(document.querySelectorAll('main > a'));

    if (!includedTags.length) {
        articles.forEach((article) => article.classList.remove('hidden'));
        return;
    }

    const includedArticles = new Set();

    includedTags.forEach((tag) => {
        const normalizedTag = tag.trim().toLowerCase();

        articles.forEach((article) => {
            const articleTags = Array.from(article.querySelectorAll('.tags li'));

            articleTags.forEach((tagElement) => {
                const articleTagText = tagElement.textContent.trim().toLowerCase();

                if (articleTagText.includes(normalizedTag)) {
                    includedArticles.add(article);
                }
            });
        });
    });

    articles.forEach((article) => {
        if (includedArticles.has(article)) {
            article.classList.remove('hidden');
        } else {
            article.classList.add('hidden');
        }
    });
}

function ensureTagContainer() {
    const header = document.querySelector('main > header');
    if (!header) {
        return null;
    }

    let container = header.querySelector('.tag-container');
    if (!container) {
        container = document.createElement('div');
        container.classList.add('tag-container');
        header.appendChild(container);
    }

    return container;
}

function addSearchTerm(term) {
    const sanitized = sanitizeTag(term);
    if (!sanitized || includedTags.includes(sanitized)) {
        return;
    }

    includedTags.push(sanitized);

    const tagButton = createTag(sanitized);
    const container = ensureTagContainer();
    if (container) {
        container.appendChild(tagButton);
    }

    hideArticles();
}

function initialize() {
    const header = document.querySelector('main > header');
    ensureTagContainer();

    const params = new URLSearchParams(window.location.search);
    const tagParams = params.getAll('tag').map(sanitizeTag).filter(Boolean);

    console.log('URL tags:', tagParams);

    tagParams.forEach((tag) => {
        addSearchTerm(tag);
    });

    if (!header) {
        return;
    }

    const input = header.querySelector('input');
    const addButton = header.querySelector('button');

    if (addButton && input) {
        addButton.addEventListener('click', () => {
            addSearchTerm(input.value);
            input.value = '';
        });
    }

    if (input) {
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addSearchTerm(input.value);
                input.value = '';
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', initialize);
