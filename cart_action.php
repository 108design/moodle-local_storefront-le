<?php
/** @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */
require_once(__DIR__.'/../../config.php');
use local_storefront\local\{product_selection,store_access};
store_access::require_access();
store_access::require_catalogue_capability();
require_sesskey();
if ($_SERVER['REQUEST_METHOD']!=='POST') { throw new moodle_exception('invalidparameter'); }
$action=required_param('action',PARAM_ALPHA);
switch ($action) {
    case 'add': product_selection::add(required_param('offerid',PARAM_INT)); break;
    case 'remove': product_selection::remove(required_param('offerid',PARAM_INT)); break;
    case 'clear': product_selection::clear(); break;
    default: throw new moodle_exception('invalidparameter');
}
redirect(new moodle_url('/local/storefront/cart.php'));
