<?php
/**
 * Apply a sesskey-protected cart selection action.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

define('AJAX_SCRIPT', isset($_POST['ajax']) && $_POST['ajax'] === '1');
// Central store_access enforces the configured public or authenticated audience.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require_once(__DIR__ . '/../../config.php');
use local_storefront\local\{product_selection, store_access};
store_access::require_access();
store_access::require_catalogue_capability();
require_sesskey();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidparameter');
}
$action = required_param('action', PARAM_ALPHA);
switch ($action) {
    case 'add':
        product_selection::add(required_param('offerid', PARAM_INT));
        break;
    case 'remove':
        product_selection::remove(required_param('offerid', PARAM_INT));
        break;
    case 'clear':
        product_selection::clear();
        break;
    default:
        throw new moodle_exception('invalidparameter');
}
if (AJAX_SCRIPT) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['html' => \local_storefront\output\cart_drawer::body(),
        'count' => count(product_selection::snapshot()), 'label' => \local_storefront\output\cart_drawer::label()],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    );
    exit;
}
redirect(new moodle_url('/local/storefront/cart.php'));
