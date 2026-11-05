<?php
/**
 * Order-ready mail with immutable rendered content, recipient scoping and a retry ledger.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Order-ready mail with immutable rendered content, recipient scoping and a retry ledger.
 */
final class communication_service {
    /**
     * Sanitise HTML while preserving only the two documented, subsequently escaped tokens.
     *
     * @param string $template Template.
     * @return string
     */
    public static function clean_template(string $template): string {
        return str_ireplace(
            ['%7Border_reference%7D', '%7Bmanage_url%7D'],
            ['{order_reference}', '{manage_url}'],
            clean_text($template, FORMAT_HTML)
        );
    }
    /**
     * Queue immutable order-ready messages once per recipient and order.
     *
     * @param int $orderid Order ID.
     * @return int
     */
    public static function enqueue_completed(int $orderid): int {
        global $DB;
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock('mail-order:' . $orderid, 10);
        if (!$lock) {
            return 0;
        }
        try {
            $order = $DB->get_record(
                'local_storefront_order',
                [ 'id' => $orderid, 'status' => 'completed' ],
                '*',
                MUST_EXIST
            );
            $user = $DB->get_record(
                'user',
                [ 'id' => $order->userid, 'deleted' => 0, 'suspended' => 0 ],
                '*',
                MUST_EXIST
            );
            $lang = str_starts_with($order->customerlang ?? '', 'de') ? 'de' : 'en';
            $rule = $DB->get_record('local_storefront_comrule', ['eventtype' => 'order_ready', 'language' => $lang]);
            $manager = get_string_manager();
            $subject = $rule->subject ?? $manager->get_string('orderreadysubject', 'local_storefront', null, $lang);
            $template = $rule->body ?? $manager->get_string('orderreadybody', 'local_storefront', null, $lang);
            $template = self::clean_template($template);
            $url = (new \moodle_url('/local/storefront/my.php'))->out(false);
            $body = clean_text(
                strtr(
                    $template,
                    [ '{order_reference}' => s($order->orderkey), '{manage_url}' => s($url) ]
                ),
                FORMAT_HTML
            );
            $recipients = [$user->email => ['customer', (int)$user->id]];
            foreach (preg_split('/[\s,;]+/', trim($rule->adminemails ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $address) {
                if (validate_email($address) && !isset($recipients[$address])) {
                    $recipients[$address] = ['internal', null];
                }
            }
            $count = 0;
            foreach ($recipients as $email => [$type, $userid]) {
                $dedupe = hash('sha256', 'order-ready:' . $order->orderkey . ':' . \core_text::strtolower($email));
                if ($DB->record_exists('local_storefront_comqueue', ['dedupekey' => $dedupe])) {
                    continue;
                }
                $DB->insert_record(
                    'local_storefront_comqueue',
                    (object)[
                    'eventtype' => 'order_ready',
                    'dedupekey' => $dedupe,

                    'orderid' => $orderid,
                    'recipienttype' => $type,
                    'recipientuserid' => $userid,
                    'recipientemail' => $email,

                    'recipienthash' => hash('sha256', \core_text::strtolower($email)), 'language' => $lang,
                    'payload' => json_encode(
                        [ 'subject' => $subject, 'bodyhtml' => $body, 'body' => html_to_text($body) ],
                        JSON_THROW_ON_ERROR
                    ),

                    'availableat' => time() + min(1440, max(0, (int)($rule->delayminutes ?? 0))) * MINSECS,
                    'timecreated' => time(), 'timemodified' => time(),
                    ]
                );
                $count++;
            }
            return $count;
        } finally {
            $lock->release();
        }
    }

    /**
     * Dispatch eligible queued mail and record retry or completion state.
     *
     * @param int $limit Limit.
     * @return void
     */
    public static function dispatch(int $limit = 50): void {
        global $DB;
        $rows = $DB->get_records_select(
            'local_storefront_comqueue',
            'status = :pending AND availableat <= :now',
            ['pending' => 'pending', 'now' => time()],
            'id',
            '*',
            0,
            $limit
        );
        foreach ($rows as $candidate) {
            $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock(
                'mail-row:' . $candidate->id,
                0
            );
            if (!$lock) {
                continue;
            }
            try {
                $row = $DB->get_record('local_storefront_comqueue', ['id' => $candidate->id], '*', MUST_EXIST);
                if ($row->status !== 'pending' || $row->availableat > time()) {
                    continue;
                }
                $order = $DB->get_record('local_storefront_order', ['id' => $row->orderid, 'status' => 'completed']);
                $user = $row->recipientuserid ? $DB->get_record(
                    'user',
                    [ 'id' => $row->recipientuserid, 'deleted' => 0, 'suspended' => 0 ]
                ) : false;
                if (
                    !$order || ($row->recipienttype === 'customer' && (!$user || (int)$order->userid !== (int)$user->id
                        || \core_text::strtolower($user->email) !== \core_text::strtolower($row->recipientemail)))
                ) {
                    $DB->update_record(
                        'local_storefront_comqueue',
                        (object)[
                        'id' => $row->id,
                        'status' => 'cancelled',
                        'payload' => null,

                        'recipientemail' => null, 'timemodified' => time(),
                        ]
                    );
                    continue;
                }
                $recipient = $user ?: (object)[
                'id' => -99,
                'email' => $row->recipientemail,
                'firstname' => '',
                'lastname' => '',

                    'firstnamephonetic' => '', 'lastnamephonetic' => '', 'middlename' => '', 'alternatename' => '',
                    'mailformat' => 1,
                'maildisplay' => 0,
                'emailstop' => 0,
                'deleted' => 0,
                'suspended' => 0,
                'lang' => $row->language,
                ];
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                $sent = false;
                try {
                    $sent = email_to_user(
                        $recipient,
                        \core_user::get_noreply_user(),
                        $payload['subject'],
                        $payload['body'],
                        $payload['bodyhtml']
                    );
                } catch (\Throwable $error) {
                    debugging($error->getMessage(), DEBUG_DEVELOPER);
                }
                $attempts = (int)$row->attempts + 1;
                $DB->update_record('local_storefront_comqueue', (object)['id' => $row->id, 'attempts' => $attempts,
                    'status' => $sent ? 'sent' : ($attempts >= 10 ? 'failed' : 'pending'),
                'timesent' => $sent ? time() : null,

                    'payload' => $sent ? null : $row->payload, 'recipientemail' => $sent ? null : $row->recipientemail,
                    'availableat' => time() + min(DAYSECS, 60 * (2 ** min(10, $attempts))),
                    'lasterror' => $sent ? null : 'mail_delivery_failed', 'timemodified' => time()]);
            } finally {
                $lock->release();
            }
        }
    }
}
