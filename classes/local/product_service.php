<?php
/**
 * Native course product editing; existing orders never depend on mutable editorial fields.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Native course product editing; existing orders never depend on mutable editorial fields.
 */
final class product_service {
    /**
     * Validate and save the course product.
     *
     * @param \stdClass $data Submitted or stored data.
     * @return int
     */
    public static function save(\stdClass $data): int {
        global $DB;
        $id = (int)($data->id ?? 0);
        $identifier = clean_param(trim($data->identifier ?? ''), PARAM_ALPHANUMEXT);
        if ($id) {
            $old = $DB->get_record('local_storefront_product', ['id' => $id], '*', MUST_EXIST);
            // Stable product identity is referenced by backup hints and immutable orders.
            $identifier = $old->identifier;
        }
        if ($identifier === '' || trim($data->name) === '') {
            throw new \moodle_exception('required');
        }
        $tx = $DB->start_delegated_transaction();
        $record = (object)[ 'identifier' => $identifier, 'producttype' => 'course',
            'name' => trim($data->name),
         'shortdescription' => $data->shortdescription_editor['text'] ?? $data->shortdescription ?? '',

            'shortdescriptionformat' => (int)($data->shortdescription_editor['format'] ?? FORMAT_HTML),
            'description' => $data->description_editor['text'] ?? '',
            'descriptionformat' => (int)($data->description_editor['format'] ?? FORMAT_HTML),
            'visible' => !empty($data->visible), 'sortorder' => (int)($data->sortorder ?? 0),
            'timemodified' => time() ];
        if ($id) {
            $record->id = $id;
            $DB->update_record('local_storefront_product', $record);
        } else {
            $record->timecreated = time();
            $id = (int)$DB->insert_record('local_storefront_product', $record);
        }
        if (!empty($data->description_editor['itemid']) && !empty($data->shortdescription_editor['itemid'])) {
            $updated = product_content::postupdate_editors($data, $id);
            $DB->update_record('local_storefront_product', (object)['id' => $id,
                'description' => $updated->description, 'descriptionformat' => $updated->descriptionformat,
                'descriptiontrust' => $updated->descriptiontrust, 'shortdescription' => $updated->shortdescription,
                'shortdescriptionformat' => $updated->shortdescriptionformat,
            'shortdescriptiontrust' => $updated->shortdescriptiontrust]);
        }
        if (isset($data->productimage)) {
            file_save_draft_area_files(
                (int)$data->productimage,
                \context_system::instance()->id,
                'local_storefront',
                'productimage',
                $id,
                product_image::options()
            );
        }
        category_service::sync_product($id, (array)($data->categories ?? []));
        tag_service::sync_product($id, (array)($data->tags ?? []));
        $tx->allow_commit();
        return $id;
    }

    /**
     * Validate and save the product offer.
     *
     * @param \stdClass $data Submitted or stored data.
     * @return int
     */
    public static function save_offer(\stdClass $data): int {
        global $DB;
        $productid = (int)$data->productid;
        $DB->get_record('local_storefront_product', ['id' => $productid, 'producttype' => 'course'], 'id', MUST_EXIST);
        $currency = strtoupper(trim($data->currency));
        if (
            !preg_match('/^[A-Z]{3}$/D', $currency) || !is_numeric($data->amount) || (float)$data->amount <= 0
                || !preg_match('/^\d{1,13}(?:\.\d{1,6})?$/D', (string)$data->amount)
                || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $data->identifier)
        ) {
            throw new \moodle_exception('invalidoffer', 'local_storefront');
        }
        $rounded = \core_payment\helper::get_rounded_cost((float)$data->amount, $currency);
        if ($rounded <= 0 || abs($rounded - (float)$data->amount) > 0.000001) {
            throw new \moodle_exception('invalidoffer', 'local_storefront');
        }
        $record = (object)['productid' => $productid, 'identifier' => $data->identifier,
            'displayname' => trim($data->displayname), 'amount' => $data->amount, 'currency' => $currency,
            'channel' => 'direct', 'billingtype' => 'oneoff', 'stripesourcemode' => 'moodle',
            'entitlementsjson' => '[]', 'enabled' => !empty($data->enabled),
            'sortorder' => (int)($data->sortorder ?? 0), 'timemodified' => time()];
        if (!empty($data->id)) {
            $DB->get_record('local_storefront_offer', ['id' => $data->id, 'productid' => $productid], 'id', MUST_EXIST);
            $record->id = (int)$data->id;
            $DB->update_record('local_storefront_offer', $record);
            return $record->id;
        }
        $record->timecreated = time();
        return (int)$DB->insert_record('local_storefront_offer', $record);
    }
}
