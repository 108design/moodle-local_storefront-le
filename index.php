<?php
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
page::setup('index', 'storefront');
$filters = catalogue_filter::from_request();
$products = catalogue::products($filters);
page::header('storefront');
$groups = [
    'search' => ['label' => get_string('search'), 'fields' => []],
    'filter' => ['label' => get_string('filter'), 'fields' => []],
    'display' => ['label' => get_string('uicataloguedisplay', 'local_storefront'), 'fields' => []],
];
$field = static function (string $label, string $id, string $control, string $group = 'filter') use (&$groups): void {
    $groups[$group]['fields'][] = \local_storefront\output\search_bar::field(
        \local_storefront\output\search_bar::labelled_control($control, $label, $id),
        $id,
        '',
        $label
    );
};
$field(get_string('search'), 'storefront-search', html_writer::empty_tag('input', [
    'id' => 'storefront-search', 'name' => 'q', 'value' => $filters['q'], 'class' => 'form-control']), 'search');
$choices = ['' => get_string('all')];
foreach (category_service::all(true) as $term) {
    $choices[$term->identifier] = format_string($term->name);
}
$field(get_string('categories', 'local_storefront'), 'storefront-category', html_writer::select(
    $choices,
    'category',
    $filters['category'],
    null,
    ['id' => 'storefront-category', 'class' => 'form-control form-select custom-select']
));
$tags = '';
foreach (tag_service::all(true) as $term) {
    $tags .= html_writer::div(
        html_writer::checkbox(
            'tagselect[]',
            $term->identifier,
            in_array($term->identifier, $filters['tags'], true),
            format_string($term->name)
        ),
        'mb-1'
    );
}
$groups['filter']['fields'][] = html_writer::div(
    html_writer::tag(
        'fieldset',
        html_writer::tag(
            'legend',
            get_string('tags', 'local_storefront'),
            [ 'class' => 'small mb-1' ]
        )

            . html_writer::tag(
                'details',
                html_writer::tag(
                    'summary',
                    get_string('choosetags', 'local_storefront'),
                    [ 'class' => 'form-control' ]
                )
                . html_writer::div($tags, 'border rounded p-2')
            )
    ),
    'local-plugin-search-bar-field'
);
$field(get_string('grouping', 'local_storefront'), 'cataloguegroup', html_writer::select(
    [
    '' => get_string('none'),
         'category' => get_string('categories', 'local_storefront'),
         'tag' => get_string('tags', 'local_storefront')],
    'group',
    $filters['group'],
    null,
    ['id' => 'cataloguegroup', 'class' => 'form-control form-select custom-select']
), 'display');
$field(get_string('sorting', 'local_storefront'), 'cataloguesort', html_writer::select(
    [
    '' => get_string('sortorder', 'local_storefront'), 'name' => get_string('name', 'local_storefront')],
    'sort',
    $filters['sort'],
    null,
    ['id' => 'cataloguesort', 'class' => 'form-control form-select custom-select']
), 'display');
$actions = html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo \local_storefront\output\search_bar::render(
    $groups,
    ['method' => 'get', 'action' => $PAGE->url->out(false), 'role' => 'search',
    'class' => 'local-storefront-catalogue-filters'],
    $actions
);
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
echo $OUTPUT->footer();
