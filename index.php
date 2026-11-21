<?php
// This file is part of Moodle - https://moodle.org/
/**
 * Display the searchable public course catalogue.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Central page::setup() enforces the store audience or the requested administration capability.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{page, catalogue, category_service, tag_service, catalogue_filter, catalogue_order};
use local_storefront\output\search_bar;
page::setup('index', 'storefront');
$filters = catalogue_filter::from_request();
$products = catalogue::products($filters);
$PAGE->requires->js_call_amd('local_storefront/catalogue_controls', 'init');
page::header('storefront');
$groups = [
    'search' => ['label' => get_string('search'), 'fields' => []],
    'filter' => ['label' => get_string('filter'), 'fields' => []],
    'display' => ['label' => get_string('uicataloguedisplay', 'local_storefront'), 'fields' => []],
];
$field = static function (string $label, string $id, string $control, string $group = 'filter') use (&$groups): void {
    $groups[$group]['fields'][] = search_bar::field(
        search_bar::labelled_control($control, $label, $id),
        $id,
        '',
        $label
    );
};
$groups['search']['fields'][] = search_bar::field(
    html_writer::label(get_string('search'), 'storefront-search', false, ['class' => 'sr-only visually-hidden'])
    . html_writer::empty_tag('input', [
        'type' => 'search', 'id' => 'storefront-search', 'name' => 'q', 'value' => $filters['q'],
        'placeholder' => get_string('productsearchplaceholder', 'local_storefront'), 'class' => 'form-control',
    ]),
    'q'
);
$choices = ['' => get_string('all')];
foreach (category_service::all(true) as $term) {
    $choices[$term->identifier] = format_string($term->name);
}
$field(get_string('category'), 'storefront-category', html_writer::select(
    $choices,
    'category',
    $filters['category'],
    null,
    ['id' => 'storefront-category', 'class' => 'form-control form-select custom-select']
));
$tags = '';
foreach (tag_service::all(true) as $term) {
    $tags .= html_writer::tag('label',
        html_writer::checkbox(
            'tagselect[]',
            $term->identifier,
            in_array($term->identifier, $filters['tags'], true)
        ) . ' ' . format_string($term->name),
        ['class' => 'form-check-label']
    );
}
if ($tags !== '') {
    $taglabel = get_string('tags', 'local_storefront');
    $all = get_string('all');
    $count = count($filters['tags']);
    $summary = html_writer::span($count ? get_string('cataloguetagsselected', 'local_storefront', $count) : $all, '', [
        'id' => 'storefront-tags-count',
        'data-search-bar-tag-count' => get_string('cataloguetagsselected', 'local_storefront', '{count}'),
        'data-search-bar-tag-empty' => $all,
    ]);
    $details = html_writer::tag('details', html_writer::tag('summary', $summary, [
        'class' => 'form-control', 'aria-labelledby' => 'storefront-tags-label storefront-tags-count',
    ]) . html_writer::div($tags, 'local-plugin-search-bar-tag-options'), ['class' => 'local-plugin-search-bar-tags']);
    $groups['filter']['fields'][] = search_bar::field(
        search_bar::labelled_control($details, $taglabel, 'storefront-tags', false), 'tags', '', $taglabel
    );
}
$field(get_string('grouping', 'local_storefront'), 'cataloguegroup', html_writer::select(
    [
    '' => get_string('groupnone', 'local_storefront'),
         'category' => get_string('categories', 'local_storefront'),
         'tag' => get_string('tags', 'local_storefront')],
    'group',
    $filters['group'],
    null,
    ['id' => 'cataloguegroup', 'class' => 'form-control form-select custom-select']
), 'display');
$field(get_string('sorting', 'local_storefront'), 'cataloguesort', html_writer::select(
    [
    '' => get_string('sortrecommended', 'local_storefront'), 'name' => get_string('name', 'local_storefront')],
    'sort',
    $filters['sort'],
    null,
    ['id' => 'cataloguesort', 'class' => 'form-control form-select custom-select']
), 'display');
$actions = html_writer::tag('button', get_string('search'), [
    'type' => 'submit', 'class' => 'btn btn-secondary', 'data-search-bar-submit' => '1',
]);
$hidden = '';
foreach (['type', 'availability'] as $name) {
    if ($filters[$name] !== '') {
        $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $filters[$name]]);
    }
}
echo html_writer::start_div('', ['data-search-bar-scope' => 'local-storefront-catalogue']);
echo search_bar::render(
    $groups,
    ['method' => 'get', 'action' => $PAGE->url->out(false), 'role' => 'search',
    'class' => 'local-storefront-catalogue-filters', 'data-search-bar' => '1',
    'data-search-bar-loading' => get_string('searchbarloading', 'local_storefront'),
    'data-search-bar-error' => get_string('searchbarerror', 'local_storefront')],
    $actions,
    $hidden
);
echo html_writer::div('', 'local-plugin-search-bar-status small', [
    'data-search-bar-status' => '1', 'role' => 'status', 'aria-live' => 'polite',
]);
echo html_writer::start_div('', ['data-search-bar-results' => '1']);
foreach (catalogue_order::sections($products, $filters) as $section) {
    if ($section['name'] !== '') {
        echo $OUTPUT->heading(format_string($section['name']), 3);
    }
    echo html_writer::start_div('local-storefront-product-grid mb-4');
    foreach ($section['products'] as $product) {
        echo \local_storefront\output\catalogue_view::card($product);
    }
    echo html_writer::end_div();
}
if (!$products) {
    echo $OUTPUT->notification(get_string('noproducts', 'local_storefront'), 'info');
}
echo html_writer::end_div() . html_writer::end_div();
echo $OUTPUT->footer();
