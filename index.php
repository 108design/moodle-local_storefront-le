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
echo html_writer::start_tag('form', ['method' => 'get', 'class' => 'row align-items-end mb-4']);
$field = static function (string $label, string $id, string $control, string $width = 'col-12 col-md-3'): void {
    echo html_writer::div(
        html_writer::tag('label', $label, [ 'for' => $id, 'class' => 'd-block mb-1' ])
        . $control,
        $width . ' mb-3'
    );
};
$field(get_string('search'), 'storefront-search', html_writer::empty_tag('input', [
    'id' => 'storefront-search', 'name' => 'q', 'value' => $filters['q'], 'class' => 'form-control']));
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
echo html_writer::div(
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
    'col-12 col-md-3 mb-3'
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
));
$field(get_string('sorting', 'local_storefront'), 'cataloguesort', html_writer::select(
    [
    '' => get_string('sortorder', 'local_storefront'), 'name' => get_string('name', 'local_storefront')],
    'sort',
    $filters['sort'],
    null,
    ['id' => 'cataloguesort', 'class' => 'form-control form-select custom-select']
));
echo html_writer::div(
    html_writer::tag(
        'button',
        get_string('search'),
        [ 'type' => 'submit', 'class' => 'btn btn-secondary' ]
    ),
    'col-12 col-md-3 mb-3'
);
echo html_writer::end_tag('form');
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
