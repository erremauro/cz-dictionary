<?php
/**
 * Plugin Name: CZ Dictionary
 * Description: Search dictionary tags with ACF fields and show live results.
 * Version: 0.1.0
 * Author: Roberto Mauro
 */

if (!defined('ABSPATH')) {
    exit;
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
        $short_definition = function_exists('get_field') ? get_field('short_definition', 'term_' . $term_id) : '';

        $short_definition = wp_kses_post($short_definition);
        if (!empty($short_definition)) {
            $short_definition = wpautop($short_definition);
        }

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

function czd_enqueue_assets() {
    wp_enqueue_script('czd-search');
    wp_enqueue_style('czd-style');

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
        padding: 1.5em 1.25em;
    }
    .czd-result {
        padding: 1em;
    }
}
CSS;

    wp_add_inline_style('czd-style', $style);
}

function czd_dictionary_shortcode() {
    czd_enqueue_assets();

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
