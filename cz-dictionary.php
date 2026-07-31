<?php
/**
 * Plugin Name: CZ Dictionary
 * Description: Search dictionary tags with ACF fields and show live results.
 * Version: 0.2.5
 * Author: Roberto Mauro
 */

if (!defined('ABSPATH')) {
    exit;
}

function czd_format_short_definition($term_id) {
    $short_definition = function_exists('get_field') ? get_field('short_definition', 'term_' . $term_id) : '';
    $short_definition = wp_kses_post($short_definition);
    if (!empty($short_definition)) {
        $short_definition = wpautop($short_definition);
    }
    return $short_definition;
}

function czd_get_terms_data() {
    $terms = get_terms([
        'taxonomy' => 'post_tag',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $data = [];

    foreach ($terms as $term) {
        $term_id = $term->term_id;

        $show_as = function_exists('get_field') ? get_field('show_as', 'term_' . $term_id) : '';
        $synonyms_csv = function_exists('get_field') ? get_field('synonyms_csv', 'term_' . $term_id) : '';
        $readings = function_exists('get_field') ? get_field('readings', 'term_' . $term_id) : '';
        $short_definition = czd_format_short_definition($term_id);

        $data[] = [
            'id' => $term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'show_as' => is_string($show_as) ? $show_as : '',
            'synonyms_csv' => is_string($synonyms_csv) ? $synonyms_csv : '',
            'readings' => is_string($readings) ? $readings : '',
            'short_definition_html' => $short_definition,
            'definition_url' => home_url('/lemma/' . $term->slug),
        ];
    }

    return $data;
}

function czd_register_assets() {
    wp_register_script('czd-search', '', [], '0.1.0', true);
    wp_register_style('czd-style', false, [], '0.1.0');
}
add_action('init', 'czd_register_assets');

function czd_enqueue_common_assets() {
    wp_enqueue_script('czd-search');
    wp_enqueue_style('czd-style');
}

function czd_enqueue_dictionary_assets() {
    static $did_enqueue = false;
    if ($did_enqueue) {
        czd_enqueue_common_assets();
        return;
    }
    $did_enqueue = true;

    czd_enqueue_common_assets();

    $terms_data = czd_get_terms_data();
    wp_add_inline_script(
        'czd-search',
        'window.CZD_DictionaryData = ' . wp_json_encode($terms_data) . ';',
        'before'
    );

    $script = <<<JS
(function () {
    function normalize(value) {
        return (value || '').toString().toLowerCase();
    }

    function buildSearchHaystack(item) {
        return [
            item.show_as,
            item.synonyms_csv,
            item.readings,
            item.name,
            item.slug,
            item.description
        ].map(normalize).join(' ');
    }

    function renderResults(wrapper, items, query, limit) {
        var resultsEl = wrapper.querySelector('.czd-results');
        var emptyEl = wrapper.querySelector('.czd-empty');
        var shownCountEl = wrapper.querySelector('.czd-count');
        resultsEl.innerHTML = '';

        if (!items.length) {
            emptyEl.textContent = query ? 'Nessun risultato.' : '';
            if (shownCountEl) {
                shownCountEl.textContent = '';
            }
            return;
        }

        emptyEl.textContent = '';
        var sliced = items.slice(0, limit);
        if (shownCountEl) {
            shownCountEl.textContent = 'Mostrati ' + sliced.length + ' di ' + items.length;
        }
        sliced.forEach(function (item) {
            var card = document.createElement('div');
            card.className = 'czd-result';

            var name = document.createElement('a');
            name.className = 'czd-result__name';
            name.href = item.definition_url;
            name.textContent = item.name;

            var def = document.createElement('div');
            def.className = 'czd-result__def';
            def.innerHTML = item.short_definition_html || '';

            var link = document.createElement('a');
            link.className = 'czd-result__button';
            link.href = item.definition_url + '?show_all=1';
            link.textContent = 'Vai alla definizione';

            card.appendChild(name);
            if (item.readings) {
                var readings = document.createElement('div');
                readings.className = 'czd-result__readings';
                readings.textContent = item.readings;
                card.appendChild(readings);
            }
            card.appendChild(def);
            card.appendChild(link);
            resultsEl.appendChild(card);
        });
    }

    function initSearch(wrapper) {
        var input = wrapper.querySelector('.czd-input');
        var sentinel = wrapper.querySelector('.czd-sentinel');
        var pageSize = 20;
        var visibleLimit = pageSize;
        var filteredData = [];
        var data = (window.CZD_DictionaryData || []).map(function (item) {
            item._haystack = buildSearchHaystack(item);
            return item;
        });

        function applyQuery() {
            var query = normalize(input.value);
            visibleLimit = pageSize;
            if (!query) {
                filteredData = data.slice();
                renderResults(wrapper, filteredData, '', visibleLimit);
                return;
            }

            filteredData = data.filter(function (item) {
                return item._haystack.indexOf(query) !== -1;
            });
            renderResults(wrapper, filteredData, query, visibleLimit);
        }

        function loadMoreIfNeeded() {
            if (visibleLimit >= filteredData.length) {
                return;
            }
            visibleLimit = Math.min(filteredData.length, visibleLimit + pageSize);
            var query = normalize(input.value);
            renderResults(wrapper, filteredData, query, visibleLimit);
        }

        if ('IntersectionObserver' in window && sentinel) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreIfNeeded();
                    }
                });
            }, { rootMargin: '120px' });
            observer.observe(sentinel);
        }

        input.addEventListener('input', applyQuery);
        applyQuery();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var wrappers = document.querySelectorAll('.czd-dictionary');
        wrappers.forEach(initSearch);
    });
})();
JS;

    wp_add_inline_script('czd-search', $script, 'after');

    $style = <<<CSS
.czd-dictionary {
    max-width: 900px;
    margin: 2em auto;
    box-sizing: border-box;
    width: 100%;
}
.czd-input {
    width: 100%;
    padding: 0.9em 1em;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text);
    font-family: var(--font-body);
    font-size: 1rem;
    margin-bottom: 1.25em;
}
.czd-input:focus {
    outline: none;
    border-color: var(--link);
}
.czd-results {
    display: grid;
    gap: 1.25em;
}
.czd-result {
    border: var(--card-border);
    padding: 1.25em;
    background: var(--card-bg);
    border-radius: var(--radius-md);
    box-shadow: var(--card-hover-shadow);
}
.czd-result__name {
    display: inline-block;
    font-weight: 600;
    font-size: 1.1em;
    margin-bottom: 0.6em;
    color: var(--link);
}
.czd-result__name:hover {
    color: var(--link);
}
.czd-result__def {
    margin-bottom: 0.9em;
}
.czd-result__def p {
    text-indent: 0;
    text-align: justify;
    hyphens: auto;
    word-break: break-word;
    line-height: 1.68;
    font-size: 1em;
    padding: 0.25em 0;
}
.czd-result__readings {
    font-size: 0.85em;
    font-style: italic;
    margin-bottom: 0.75em;
}
.czd-result__button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem 1rem;
    border: 1px solid var(--link);
    color: var(--link);
    text-decoration: none;
    font-size: 0.9rem;
    border-radius: 999px;
    background: transparent;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.czd-result__button:hover {
    background-color: rgba(var(--link-rgb), 0.15);
}
.czd-empty {
    color: var(--text);
    opacity: 0.7;
    font-size: 0.95em;
}
.czd-count {
    color: var(--text);
    opacity: 0.7;
    font-size: 0.9em;
    margin-bottom: 0.75em;
}
.czd-sentinel {
    height: 1px;
}
@media (max-width: 640px) {
    .czd-dictionary {
        /* padding: 1.5em 1.25em; */
    }
    .czd-results {
        display: block;
    }
    .czd-result {
        margin-bottom: 1.25em;
    }
    .czd-result {
        padding: 1em;
    }
}
CSS;

    wp_add_inline_style('czd-style', $style);
}

function czd_normalize_lemma_slug($name) {
    $value = is_string($name) ? $name : '';
    $value = strtolower($value);

    // Mappa diacritici/sanscrito verso un set ASCII semplice.
    $map = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'æ' => 'ae',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ď' => 'd', 'ḍ' => 'd', 'ḏ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'ñ' => 'n', 'ń' => 'n', 'ṅ' => 'n', 'ṇ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ś' => 's', 'ṣ' => 's', 'š' => 's', 'ș' => 's',
        'ż' => 'z', 'ź' => 'z', 'ž' => 'z',
        'ṛ' => 'r', 'ṝ' => 'r',
        'ḷ' => 'l', 'ḹ' => 'l', 'ḻ' => 'l',
        'ṃ' => 'm', 'ṁ' => 'm',
        'ṭ' => 't', 'ṯ' => 't',
        'ḥ' => 'h', 'ḫ' => 'h',
    ];

    $value = strtr($value, $map);

    // Tieni solo caratteri compatibili con uno slug "italiano" semplice.
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
    $value = preg_replace('/-+/', '-', $value);
    $value = trim((string) $value, '-');

    return $value;
}

function czd_enqueue_lemma_assets() {
    static $did_enqueue = false;
    if ($did_enqueue) {
        czd_enqueue_common_assets();
        return;
    }
    $did_enqueue = true;

    czd_enqueue_common_assets();

    $endpoint_base = rest_url('czd/v1/lemma/');
    wp_add_inline_script(
        'czd-search',
        'window.CZD_LemmaEndpointBase = ' . wp_json_encode($endpoint_base) . ';',
        'before'
    );

    $script = <<<JS
(function () {
    var endpointBase = window.CZD_LemmaEndpointBase || '';
    if (!endpointBase) return;

    var MOBILE_BREAKPOINT = 680;
    var current = { anchor: null, popup: null, overlay: null };
    var cache = Object.create(null);
    var LEMMA_PAGINATION_THRESHOLD = 256;

    function shouldIgnoreClick(e) {
        return e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1;
    }

    function closePopup() {
        if (current.popup) current.popup.remove();
        if (current.overlay) current.overlay.remove();
        if (current.anchor && current.anchor.focus) {
            current.anchor.focus({ preventScroll: true });
        }
        current = { anchor: null, popup: null, overlay: null };
        document.removeEventListener('keydown', onKeydown);
        window.removeEventListener('resize', onReflow);
        window.removeEventListener('scroll', onReflow, true);
    }

    function onKeydown(e) {
        if (e.key === 'Escape') closePopup();
    }

    function onReflow() {
        if (current.popup && current.anchor) {
            positionPopup(current.popup, current.anchor);
        }
    }

    function positionPopup(popup, anchor) {
        popup.style.minWidth = '240px';
        popup.style.visibility = 'hidden';
        popup.style.left = '0px';
        popup.style.top = '0px';
        document.body.appendChild(popup);

        var rect = anchor.getBoundingClientRect();
        var vw = document.documentElement.clientWidth;
        var vh = document.documentElement.clientHeight;
        var margin = 8;

        var pr = popup.getBoundingClientRect();
        var top = window.scrollY + rect.bottom + margin;
        if (rect.bottom + pr.height + margin > vh) {
            top = window.scrollY + rect.top - pr.height - margin;
            if (top < window.scrollY + margin) top = window.scrollY + margin;
        }

        var left = window.scrollX + rect.left + (rect.width - pr.width) / 2;
        left = Math.max(
            window.scrollX + margin,
            Math.min(left, window.scrollX + vw - pr.width - margin)
        );

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';
        popup.style.visibility = 'visible';

        if (vw <= MOBILE_BREAKPOINT) {
            popup.style.right = left + 'px';
        }
    }

    function buildBasePopup(titleText) {
        var overlay = document.createElement('div');
        overlay.className = 'lemma-overlay';
        overlay.addEventListener('click', closePopup, { passive: true });

        var popup = document.createElement('div');
        popup.className = 'lemma-popup';
        popup.setAttribute('role', 'dialog');
        popup.setAttribute('aria-modal', 'true');

        var titleId = 'lemma-popup-title-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 7);
        popup.setAttribute('aria-labelledby', titleId);

        var btn = document.createElement('button');
        btn.className = 'lemma-popup-close';
        btn.type = 'button';
        btn.setAttribute('aria-label', 'Chiudi lemma');
        btn.textContent = '×';
        btn.addEventListener('click', closePopup);

        var title = document.createElement('div');
        title.className = 'lemma-popup-title';
        title.id = titleId;
        title.textContent = titleText || '';

        var content = document.createElement('div');
        content.className = 'lemma-popup-content';

        popup.appendChild(btn);
        popup.appendChild(title);
        popup.appendChild(content);

        return { popup: popup, overlay: overlay, content: content, title: title };
    }

    function buildLoadingPopup(anchor) {
        closePopup();
        var built = buildBasePopup(anchor.getAttribute('data-lemma-name') || anchor.textContent || '');
        built.content.innerHTML = '<p>Caricamento…</p>';
        document.body.appendChild(built.overlay);
        current = { anchor: anchor, popup: built.popup, overlay: built.overlay };
        positionPopup(built.popup, anchor);
        document.addEventListener('keydown', onKeydown);
        window.addEventListener('resize', onReflow);
        window.addEventListener('scroll', onReflow, true);
        (built.popup.querySelector('button') || built.popup).focus({ preventScroll: true });
        return built;
    }

    function lemmaUrlFromSlug(slug) {
        try {
            var u = new URL(endpointBase);
            var basePath = u.pathname.replace(/\\/+$/, '');
            var lemmaPath = basePath.replace(/\\/wp-json\\/czd\\/v1\\/lemma$/, '') + '/lemma/' + encodeURIComponent(slug);
            return u.origin + lemmaPath;
        } catch (e) {
            return '/lemma/' + encodeURIComponent(slug);
        }
    }

    function fetchLemma(slug) {
        if (cache[slug]) return Promise.resolve(cache[slug]);
        var url = endpointBase.replace(/\\/+$/, '') + '/' + encodeURIComponent(slug);
        return fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(function (data) {
                cache[slug] = data;
                return data;
            });
    }

    function paginateLemmaHTML(html, threshold) {
        var container = document.createElement('div');
        container.innerHTML = html || '';
        var nodes = Array.from(container.childNodes);
        var pages = [];
        var page = document.createElement('div');
        page.className = 'lemma-page';
        var remaining = threshold;

        function getNodeTextLength(node) {
            return (node.textContent || '').length;
        }

        function splitTextNode(node, maxChars) {
            if (maxChars <= 0) return [null, node];
            var text = node.nodeValue || '';
            if (text.length <= maxChars) return [node, null];

            var cut = -1;
            for (var i = maxChars; i > 0; i -= 1) {
                if (/\s/.test(text.charAt(i - 1))) {
                    cut = i;
                    break;
                }
            }

            if (cut <= 0) {
                var hasWhitespace = /\s/.test(text);
                // Se c'è whitespace ma non prima del limite, sposta l'intera parola
                // alla pagina successiva per evitare tronchi a metà.
                if (hasWhitespace) return [null, node];
                // Caso estremo: singola parola più lunga della soglia.
                // Se siamo all'inizio di una pagina, la mettiamo intera (overflow controllato)
                // per evitare loop infinito; altrimenti la rimandiamo alla pagina successiva.
                if (maxChars >= threshold) return [node, null];
                return [null, node];
            }

            var head = document.createTextNode(text.slice(0, cut));
            var tailText = text.slice(cut).replace(/^\s+/, '');
            var tail = tailText ? document.createTextNode(tailText) : null;
            return [head, tail];
        }

        function splitNodeByChars(node, maxChars) {
            if (maxChars <= 0) return [null, node];
            if (node.nodeType === 3) return splitTextNode(node, maxChars);
            if (node.nodeType !== 1) return [node, null];

            var head = node.cloneNode(false);
            var tail = node.cloneNode(false);
            var remainingChars = maxChars;
            var children = Array.from(node.childNodes);

            for (var i = 0; i < children.length; i += 1) {
                var child = children[i];
                var childLen = getNodeTextLength(child);

                if (childLen <= remainingChars) {
                    head.appendChild(child);
                    remainingChars -= childLen;
                    continue;
                }

                if (remainingChars > 0) {
                    var parts = splitNodeByChars(child, remainingChars);
                    var childHead = parts[0];
                    var childTail = parts[1];
                    if (childHead) head.appendChild(childHead);
                    if (childTail) tail.appendChild(childTail);
                } else {
                    tail.appendChild(child);
                }

                for (var j = i + 1; j < children.length; j += 1) {
                    tail.appendChild(children[j]);
                }
                break;
            }

            return [
                head.childNodes.length ? head : null,
                tail.childNodes.length ? tail : null
            ];
        }

        function pushPage() {
            if (page.childNodes.length) pages.push(page);
            page = document.createElement('div');
            page.className = 'lemma-page';
            remaining = threshold;
        }

        var idx = 0;
        while (idx < nodes.length) {
            var node = nodes[idx];
            var textLen = getNodeTextLength(node);

            if (textLen <= remaining || textLen === 0) {
                page.appendChild(node);
                remaining -= textLen;
                idx += 1;
                continue;
            }

            var split = splitNodeByChars(node, remaining);
            var head = split[0];
            var tail = split[1];
            if (head) page.appendChild(head);
            pushPage();
            if (!tail) {
                idx += 1;
                continue;
            }

            node = tail;
            while (node) {
                var len = getNodeTextLength(node);
                if (len <= remaining || len === 0) {
                    page.appendChild(node);
                    remaining -= len;
                    node = null;
                    idx += 1;
                    break;
                }
                var splitMore = splitNodeByChars(node, remaining);
                var h = splitMore[0];
                var t = splitMore[1];
                if (h) page.appendChild(h);
                pushPage();
                node = t;
            }
        }
        if (page.childNodes.length) pages.push(page);

        return pages.length ? pages : [page];
    }

    function buildLemmaPaginationControls(pages) {
        var index = 0;
        var nav = document.createElement('div');
        nav.className = 'lemma-pagination';

        var prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'lemma-pagination-prev';
        prev.setAttribute('aria-label', 'Pagina precedente');
        prev.textContent = '←';

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'lemma-pagination-next';
        next.setAttribute('aria-label', 'Pagina successiva');
        next.textContent = '→';

        var indicator = document.createElement('span');
        indicator.className = 'lemma-pagination-indicator';

        function update() {
            pages.forEach(function (page, i) {
                page.hidden = i !== index;
            });
            prev.disabled = index === 0;
            next.disabled = index === pages.length - 1;
            indicator.textContent = (index + 1) + '/' + pages.length;
        }

        prev.addEventListener('click', function () {
            if (index > 0) {
                index -= 1;
                update();
            }
        });

        next.addEventListener('click', function () {
            if (index < pages.length - 1) {
                index += 1;
                update();
            }
        });

        nav.appendChild(prev);
        nav.appendChild(indicator);
        nav.appendChild(next);
        update();

        return nav;
    }

    function renderLemmaIntoPopup(built, lemma) {
        var name = lemma && lemma.name ? lemma.name : '';
        var html = lemma && lemma.short_definition_html ? lemma.short_definition_html : '';
        var slug = lemma && lemma.slug ? lemma.slug : '';
        var definitionUrl = (lemma && lemma.definition_url) ? lemma.definition_url : lemmaUrlFromSlug(slug);

        built.title.textContent = name;

        var footer = document.createElement('p');
        footer.className = 'lemma-popup-footer';
        var link = document.createElement('a');
        link.className = 'lemma-popup-link';
        link.href = definitionUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'Apri la definizione completa';
        footer.appendChild(link);

        built.content.innerHTML = '';

        var pages = paginateLemmaHTML(html, LEMMA_PAGINATION_THRESHOLD);
        var pagination = null;
        if (pages.length > 1) {
            built.content.classList.add('lemma-popup-content--paged');
            built.content.style.overflow = 'hidden';
            built.content.style.maxHeight = 'none';
            pages.forEach(function (page, index) {
                if (index !== 0) page.hidden = true;
                built.content.appendChild(page);
            });
            // Mostra il link alla definizione completa solo nell'ultima pagina.
            pages[pages.length - 1].appendChild(footer);
            pagination = buildLemmaPaginationControls(pages);
        } else {
            built.content.innerHTML = html || '<p>Nessuna descrizione breve disponibile.</p>';
            built.content.appendChild(footer);
        }
        if (pagination) built.popup.appendChild(pagination);
    }

    function renderErrorIntoPopup(built, slug) {
        var fallbackUrl = lemmaUrlFromSlug(slug);
        built.content.innerHTML = '';
        var p = document.createElement('p');
        p.textContent = 'Impossibile caricare il lemma.';
        var link = document.createElement('a');
        link.href = fallbackUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = 'Apri la pagina del lemma';
        p.appendChild(document.createTextNode(' '));
        p.appendChild(link);
        built.content.appendChild(p);
    }

    function getSlugFromAnchor(anchor) {
        var slug = (anchor.getAttribute('data-lemma-slug') || '').trim();
        if (slug) return slug;
        var href = anchor.getAttribute('href') || '';
        var m = href.match(/\\/lemma\\/([^\\/?#]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    function openLemma(anchor) {
        var slug = getSlugFromAnchor(anchor);
        if (!slug) return;

        var built = buildLoadingPopup(anchor);
        fetchLemma(slug)
            .then(function (lemma) {
                renderLemmaIntoPopup(built, lemma);
                positionPopup(built.popup, anchor);
            })
            .catch(function () {
                renderErrorIntoPopup(built, slug);
                positionPopup(built.popup, anchor);
            });
    }

    function initLemmaPopups() {
        document.addEventListener('click', function (e) {
            if (shouldIgnoreClick(e)) return;
            var a = e.target.closest('a.lemma');
            if (!a) return;

            var slug = getSlugFromAnchor(a);
            if (!slug) return;

            e.preventDefault();
            openLemma(a);
        });
    }

    if (document.readyState !== 'loading') initLemmaPopups();
    else document.addEventListener('DOMContentLoaded', initLemmaPopups);
})();
JS;

    wp_add_inline_script('czd-search', $script, 'after');

    $style = <<<CSS
:root {
    /* footnote-popup parity */
    --fn-bg: var(--pt-bg);
    --fn-overlay-bg: rgba(0, 0, 0, 0.5);
    --fn-elev-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    --fn-border: 1px solid rgba(255, 255, 255, 0.15);
}

a.lemma {
    color: var(--text);
    text-decoration: none;
    border-bottom: 1px dotted var(--border, rgba(20, 20, 20, 1));
}

.lemma-overlay {
    position: fixed;
    inset: 0;
    background: var(--fn-overlay-bg);
    z-index: 9998;
}

.lemma-popup {
    position: absolute;
    max-width: min(36rem, calc(100vw - 2rem));
    box-sizing: border-box;
    border-radius: 12px;
    box-shadow: var(--fn-elev-shadow);
    padding: 1rem 1rem 1.25rem;
    z-index: 9999;
    border: var(--fn-border);
    background: var(--fn-bg);
    color: var(--text);
}

.lemma-popup-close {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    margin: 0;
    border: 0;
    background: transparent;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    color: var(--text);
}

.lemma-popup-title {
    font-size: 1.4em;
    font-weight: 600;
    margin-bottom: 1em;
}

.lemma-popup-content {
    margin-top: 0.25rem;
    max-height: 60vh;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
    scrollbar-gutter: stable both-edges;
}

.lemma-popup-content p {
    text-indent: 0;
    text-align: justify;
    hyphens: auto;
    word-break: break-word;
    line-height: 1.68;
    font-size: 1em;
    padding: 0.25em 0;
}

.lemma-popup-footer {
    margin: 0.75rem 0 0;
}

.lemma-pagination {
    margin-top: 1em;
    text-align: center;
}

.lemma-pagination-indicator {
    padding: 0 1em;
}

.lemma-popup-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem 1rem;
    border: 1px solid var(--link, currentColor);
    color: var(--link, currentColor);
    text-decoration: none;
    font-size: 0.9rem;
    border-radius: 999px;
    background: transparent;
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.lemma-popup-link:hover {
    background-color: rgba(var(--link-rgb, 0, 0, 0), 0.12);
}

@media (max-width: 680px) {
    .lemma-popup {
        max-width: calc(100vw - 1rem);
    }
    .lemma-popup-content {
        max-height: 65vh;
    }
}

@media print {
    .lemma-popup,
    .lemma-overlay {
        display: none !important;
    }
}
CSS;

    wp_add_inline_style('czd-style', $style);
}

function czd_dictionary_shortcode() {
    czd_enqueue_dictionary_assets();

    ob_start();
    ?>
    <div class="czd-dictionary">
        <input class="czd-input" type="search" placeholder="Cerca un termine..." aria-label="Cerca un termine">
        <div class="czd-empty"></div>
        <div class="czd-count"></div>
        <div class="czd-results"></div>
        <div class="czd-sentinel" aria-hidden="true"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('cz_dictionary', 'czd_dictionary_shortcode');

function czd_rest_get_lemma($request) {
    $slug = (string) $request->get_param('slug');
    $slug = sanitize_title($slug);

    if ($slug === '') {
        return new WP_Error('czd_invalid_slug', 'Slug non valido.', ['status' => 400]);
    }

    $term = get_term_by('slug', $slug, 'post_tag');
    if (!$term || is_wp_error($term)) {
        return new WP_Error('czd_not_found', 'Lemma non trovato.', ['status' => 404]);
    }

    $term_id = (int) $term->term_id;
    $show_as = function_exists('get_field') ? get_field('show_as', 'term_' . $term_id) : '';
    $short_definition = czd_format_short_definition($term_id);

    return [
        'id' => $term_id,
        'slug' => $term->slug,
        'name' => $term->name,
        'show_as' => is_string($show_as) ? $show_as : '',
        'short_definition_html' => $short_definition,
        'definition_url' => home_url('/lemma/' . $term->slug),
    ];
}

function czd_register_rest_routes() {
    register_rest_route('czd/v1', '/lemma/(?P<slug>[^/]+)', [
        'methods' => 'GET',
        'callback' => 'czd_rest_get_lemma',
        'permission_callback' => '__return_true',
        'args' => [
            'slug' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_title',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'czd_register_rest_routes');

function czd_lemma_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'slug' => '',
        'name' => '',
        'popup' => 'true',
    ], $atts, 'lemma');

    $name = isset($atts['name']) ? (string) $atts['name'] : '';

    $slug = isset($atts['slug']) ? (string) $atts['slug'] : '';
    if ($slug === '') {
        if ($name === '') {
            return '';
        }
        $slug = czd_normalize_lemma_slug($name);
    }
    $slug = sanitize_title($slug);

    if ($slug === '') {
        return '';
    }

    if ($name === '') {
        $name = $slug;
    }

    $popup_raw = isset($atts['popup']) ? (string) $atts['popup'] : 'true';
    $popup_value = strtolower(trim($popup_raw));
    $popup_enabled = !in_array($popup_value, ['0', 'false', 'no', 'off'], true);

    $show_as = is_string($content) && trim($content) !== ''
        ? $content
        : $name;
    $show_as = wp_kses_post(do_shortcode($show_as));

    $href = trailingslashit(home_url('/lemma/' . $slug));
    $title = sprintf("Leggi la definizione di '%s' su Cigno Zen", $name);

    if ($popup_enabled) {
        czd_enqueue_lemma_assets();
        $html = sprintf(
            '<a class="lemma" href="%1$s" title="%2$s" target="_blank" rel="noopener" data-lemma-slug="%3$s" data-lemma-name="%4$s">%5$s</a>',
            esc_url($href),
            esc_attr($title),
            esc_attr($slug),
            esc_attr($name),
            $show_as
        );
    } else {
        $html = sprintf(
            '<a href="%1$s" title="%2$s" target="_blank" rel="noopener">%3$s</a>',
            esc_url($href),
            esc_attr($title),
            $show_as
        );
    }

    return $html;
}
add_shortcode('lemma', 'czd_lemma_shortcode');
