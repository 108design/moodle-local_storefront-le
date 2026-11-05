<?php
/**
 * Published document revisions and immutable checkout evidence.
 *
 * @package   local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license   108design source-available software license; see LICENSE.md
 */

// This file is part of a 108design source-available software product.
//
// Copyright (C) 2026 Andreas Giesen <andreas@108design.com>.
//
// Use and modification are permitted only under the Software License included
// with this distribution. See LICENSE.md for the full terms.
namespace local_storefront\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Published document revisions and immutable checkout evidence.
 */
final class legal_service {
    /**
     * Return the configured legal document identities.
     *
     * @return array
     */
    public static function documents(): array {
        global $DB;
        return $DB->get_records('local_storefront_legaldoc', null, 'sortorder ASC, title ASC, id ASC');
    }

    /**
     * Load the requested legal document.
     *
     * @param int $id Record ID.
     * @return \stdClass
     */
    public static function get(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_storefront_legaldoc', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Load the legal document using its stable identifier.
     *
     * @param string $identifier Stable identifier.
     * @param bool $visibleonly Whether to omit entries hidden from the viewer.
     * @return \stdClass|null
     */
    public static function get_by_identifier(string $identifier, bool $visibleonly = true): ?\stdClass {
        global $DB;
        $conditions = ['identifier' => $identifier];
        if ($visibleonly) {
            $conditions['visible'] = 1;
        }
        return $DB->get_record('local_storefront_legaldoc', $conditions) ?: null;
    }

    /**
     * Pinned links keep their stable document identity even after an identifier is renamed.
     *
     * @param string $identifier Stable identifier.
     * @param int $documentid Legal document ID.
     * @param int $revisionid Revision ID.
     * @return \stdClass|null
     */
    public static function public_document(string $identifier, int $documentid = 0, int $revisionid = 0): ?\stdClass {
        global $DB, $USER;
        if ($documentid > 0 && $revisionid > 0) {
            $document = $DB->get_record('local_storefront_legaldoc', ['id' => $documentid]);
            if (!$document) {
                return null;
            }
            if (!empty($document->visible)) {
                return $document;
            }
            if (
                isloggedin() && !isguestuser()
                && (
                has_capability('local/storefront:managelegal', \context_system::instance())
                    || has_capability('local/storefront:manageorders', \context_system::instance())
                    || $DB->record_exists_sql(
                        "SELECT ol.id FROM {local_storefront_orderlegal} ol
                        JOIN {local_storefront_order} o ON o.id=ol.orderid
                        WHERE o.userid=:userid AND ol.source=:source AND ol.sourceid=:documentid AND ol.revisionid=:revisionid",
                        [
                        'userid' => $USER->id,
                        'source' => 'storefront',
                        'documentid' => $documentid,
                        'revisionid' => $revisionid,
                        ]
                    )
                )
            ) {
                return $document;
            }
            return null;
        }
        return self::get_by_identifier($identifier);
    }

    /**
     * Authored languages do not require the corresponding Moodle UI pack.
     *
     * @return array
     */
    public static function languages(): array {
        $manager = get_string_manager();
        $languages = $manager->get_list_of_languages();
        foreach ($manager->get_list_of_translations(true) as $code => $name) {
            $languages[$code] = $manager->get_string('thislanguage', 'langconfig', null, $code);
        }
        asort($languages);
        return $languages;
    }

    /**
     * Return the native display name of the language.
     *
     * @param string $language Language code.
     * @return string
     */
    public static function language_name(string $language): string {
        return in_array($language, ['', 'und'], true) ? get_string('legallanguageunassigned', 'local_storefront')
            : (self::languages()[$language] ?? $language);
    }

    /**
     * Return the document's stored language versions.
     *
     * @param int $documentid Legal document ID.
     * @param bool $publishedonly Publishedonly.
     * @return array
     */
    public static function translations(int $documentid, bool $publishedonly = false): array {
        global $DB;
        $conditions = ['documentid' => $documentid];
        if ($publishedonly) {
            $conditions['published'] = 1;
        }
        $result = [];
        foreach ($DB->get_records('local_storefront_legallang', $conditions, 'lang ASC') as $translation) {
            $result[$translation->lang] = $translation;
        }
        return $result;
    }

    /**
     * Remember an explicit legal-language choice without changing Moodle's UI language.
     *
     * @return string
     */
    public static function preferred_language(): string {
        global $SESSION;
        $choice = optional_param('doclang', '', PARAM_SAFEDIR);
        if ($choice === 'auto') {
            unset($SESSION->storefrontlegallang);
        } else if ($choice !== '' && isset(self::languages()[$choice])) {
            $SESSION->storefrontlegallang = $choice;
        }
        return (string) ($SESSION->storefrontlegallang ?? current_language());
    }

    /**
     * Choose an available document language using the configured policy.
     *
     * @param \stdClass $document Validated document data.
     * @param string|null $preferred Preferred.
     * @return \stdClass|null
     */
    public static function resolve_language(\stdClass $document, ?string $preferred = null): ?\stdClass {
        $translations = self::translations((int) $document->id, true);
        $language = $preferred ?? self::preferred_language();
        $candidates = [];
        foreach (array_unique([$language, current_language()]) as $candidate) {
            $candidates[] = $candidate;
            $parent = get_parent_language($candidate);
            if ($parent) {
                $candidates[] = $parent;
            }
            $candidates[] = explode('_', $candidate)[0];
        }
        $candidates[] = (string) $document->defaultlang;
        foreach (array_unique($candidates) as $candidate) {
            if (isset($translations[$candidate])) {
                return $translations[$candidate];
            }
        }
        return null;
    }

    /**
     * Return the current legal-text revision for the requested language.
     *
     * @param \stdClass $document Validated document data.
     * @return \stdClass
     */
    public static function current_revision(\stdClass $document): \stdClass {
        global $DB;
        $translation = self::resolve_language($document);
        if (!$translation) {
            throw new \moodle_exception('legaltranslationunavailable', 'local_storefront');
        }
        return $DB->get_record('local_storefront_legalrev', [
            'id' => (int) $translation->currentrevisionid, 'documentid' => (int) $document->id,
        ], '*', MUST_EXIST);
    }

    /**
     * Draft revisions cannot be exposed through a guessed historical URL.
     *
     * @param \stdClass $document Validated document data.
     * @param int $revisionid Revision ID.
     * @return \stdClass
     */
    public static function public_revision(\stdClass $document, int $revisionid = 0): \stdClass {
        global $DB;
        if (!$revisionid) {
            return self::current_revision($document);
        }
        return $DB->get_record_select(
            'local_storefront_legalrev',
            'id = :id AND documentid = :documentid AND timepublished IS NOT NULL',
            ['id' => $revisionid, 'documentid' => $document->id],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Unsaved preview and saved output use exactly the same local renderer.
     *
     * @param string $content Content to process.
     * @param int $format Format.
     * @return string
     */
    public static function render_content(string $content, int $format): string {
        if (
            !in_array(
                $format,
                [ (int) FORMAT_HTML, (int) FORMAT_MARKDOWN, (int) FORMAT_PLAIN, (int) FORMAT_MOODLE ],
                true
            )
        ) {
            throw new \moodle_exception('invalidparameter');
        }
        return format_text($content, $format, [
            'context' => \context_system::instance(), 'filter' => false, 'noclean' => false, 'overflowdiv' => true,
        ]);
    }

    /**
     * Render the legal-text revision for display or preview.
     *
     * @param \stdClass $revision Stored revision.
     * @return string
     */
    public static function render_revision(\stdClass $revision): string {
        return $revision->renderedcontent !== null ? (string) $revision->renderedcontent
            : format_text((string) $revision->content, (int) $revision->contentformat, [
                'context' => \context_system::instance(), 'overflowdiv' => true,
            ]);
    }

    /**
     * Prepare the current document values for the native editor.
     *
     * @param int $id Record ID.
     * @param string|null $language Language code.
     * @return \stdClass
     */
    public static function edit_data(int $id, ?string $language = null): \stdClass {
        global $DB;
        $document = $id ? self::get($id) : (object) [
            'id' => 0, 'identifier' => '', 'title' => '', 'visible' => 1,
            'showincheckout' => 0, 'requireconfirmation' => 0, 'sortorder' => 0,
            'defaultlang' => '', 'editversion' => 0,
        ];
        $language = $language ?? (string) $document->defaultlang;
        $translations = $id ? self::translations($id) : [];
        $translation = $translations[$language] ?? null;
        $revision = $translation ? $DB->get_record('local_storefront_legalrev', [
            'id' => $translation->draftrevisionid ?: $translation->currentrevisionid, 'documentid' => $id,
        ], '*', MUST_EXIST) : null;
        $document->originlang = $language;
        $document->documentlanguage = $language;
        $document->expectedversion = (int) $document->editversion;
        $document->title = $revision ? $revision->title : ($id ? '' : $document->title);
        $document->contentformat = $revision ? (int) $revision->contentformat : FORMAT_HTML;
        $text = $revision ? (string) $revision->content : '';
        $document->content_editor = ['text' => $document->contentformat === (int) FORMAT_MARKDOWN ? '' : $text,
            'format' => $document->contentformat === (int) FORMAT_MARKDOWN ? FORMAT_HTML : $document->contentformat];
        $document->content_markdown = $document->contentformat === (int) FORMAT_MARKDOWN ? $text : '';
        $document->published = $translation
            ? (int) (!empty($translation->published) && (int) $revision->id === (int) $translation->currentrevisionid)
            : ($id ? 0 : 1);
        $document->isdefault = !$id || $language === (string) $document->defaultlang;
        return $document;
    }

    /**
     * Save a legal document revision while retaining existing order evidence.
     *
     * @param \stdClass $data Submitted or stored data.
     * @param int $userid User ID.
     * @return int
     */
    public static function save(\stdClass $data, int $userid): int {
        global $DB;
        $id = (int) ($data->id ?? 0);
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock('legal-document-' . $id, 10);
        if (!$lock) {
            throw new \moodle_exception('legaleditconflict', 'local_storefront');
        }
        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();
            $document = $id ? self::get($id) : null;
            if (!$id && $DB->count_records('local_storefront_legaldoc') >= 2) {
                throw new \moodle_exception('legaldocumentlimit', 'local_storefront');
            }
            if ($document && (int) $document->editversion !== (int) ($data->expectedversion ?? -1)) {
                throw new \moodle_exception('legaleditconflict', 'local_storefront');
            }
            $language = (string) $data->documentlanguage;
            $origin = (string) ($data->originlang ?? $language);
            if (!isset(self::languages()[$language])) {
                throw new \moodle_exception('invalidparameter');
            }
            $translations = $id ? self::translations($id) : [];
            if ($origin !== $language && (!in_array($origin, ['', 'und'], true) || isset($translations[$language]))) {
                throw new \moodle_exception('invalidparameter');
            }
            $ismarkdown = (int) $data->contentformat === (int) FORMAT_MARKDOWN;
            $format = $ismarkdown ? (int) FORMAT_MARKDOWN
                : (int) ($data->content_editor['format'] ?? $data->contentformat);
            if (
                !in_array(
                    $format,
                    [ (int) FORMAT_HTML, (int) FORMAT_MARKDOWN, (int) FORMAT_PLAIN, (int) FORMAT_MOODLE ],
                    true
                )
            ) {
                throw new \moodle_exception('invalidparameter');
            }
            $text = $ismarkdown ? (string) $data->content_markdown
                : (string) ($data->content_editor['text'] ?? '');
            $title = trim((string) $data->title);
            if ($title === '' || trim($text) === '') {
                throw new \moodle_exception('required');
            }
            $default = !$document
            || (string) $document->defaultlang === $origin ? $language : (string) $document->defaultlang;
            $published = !empty($data->published);
            $visible = $document ? !empty($document->visible) : !empty($data->visible);
            if (
                $visible && (($default === $language && !$published && empty($translations[$origin]->published))
                    || ($default !== $language && empty($translations[$default]->published)))
            ) {
                throw new \moodle_exception('legaldefaultrequired', 'local_storefront');
            }
            $now = time();
            $record = (object) [
                'defaultlang' => $default, 'timemodified' => $now,
                'editversion' => $document ? (int) $document->editversion + 1 : 1,
                'title' => $default === $language && $published ? $title : (string) ($document->title ?? $title),
            ];
            if (!$document) {
                $record->identifier = clean_param((string) $data->identifier, PARAM_ALPHANUMEXT);
                $record->visible = $visible;
                $record->showincheckout = !empty($data->showincheckout);
                $record->requireconfirmation = $record->showincheckout && !empty($data->requireconfirmation);
                $record->sortorder = (int) $data->sortorder;
            }
            if ($document) {
                $record->id = $id;
                $DB->update_record('local_storefront_legaldoc', $record);
            } else {
                $record->currentrevisionid = null;
                $record->timecreated = $now;
                $id = (int) $DB->insert_record('local_storefront_legaldoc', $record);
            }
            $revisionnumber = (int) $DB->get_field_sql(
                'SELECT COALESCE(MAX(revisionnumber), 0) FROM {local_storefront_legalrev} WHERE documentid = ?',
                [$id]
            ) + 1;
            $revisionid = (int) $DB->insert_record('local_storefront_legalrev', (object) [
                'documentid' => $id, 'revisionnumber' => $revisionnumber, 'title' => $title,
                'lang' => $language, 'content' => $text, 'contentformat' => $format,
                'renderedcontent' => self::render_content($text, $format),
                'contenthash' => hash('sha256', $title . "\n" . $format . "\n" . $text),
                'createdby' => $userid, 'timecreated' => $now, 'timepublished' => $published ? $now : null,
            ]);
            $translation = $translations[$origin] ?? null;
            $row = (object) ['documentid' => $id, 'lang' => $language, 'draftrevisionid' => $revisionid,
                'currentrevisionid' => $published ? $revisionid : ($translation->currentrevisionid ?? $revisionid),
                'published' => $published || !empty($translation->published)];
            if ($translation) {
                $row->id = $translation->id;
                $DB->update_record('local_storefront_legallang', $row);
            } else {
                $DB->insert_record('local_storefront_legallang', $row);
            }
            if ($default === $language && $row->published) {
                $DB->set_field(
                    'local_storefront_legaldoc',
                    'currentrevisionid',
                    $row->currentrevisionid,
                    [ 'id' => $id ]
                );
            }
            $transaction->allow_commit();
            return $id;
        } catch (\Throwable $error) {
            if ($transaction) {
                $transaction->rollback($error);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }

    /**
     * Save document-wide settings without creating or changing a language revision.
     *
     * @param \stdClass $data Submitted or stored data.
     * @return void
     */
    public static function save_settings(\stdClass $data): void {
        global $DB;
        $id = (int) $data->id;
        $lock = \core\lock\lock_config::get_lock_factory('local_storefront')->get_lock('legal-document-' . $id, 10);
        if (!$lock) {
            throw new \moodle_exception('legaleditconflict', 'local_storefront');
        }
        $transaction = null;
        try {
            $transaction = $DB->start_delegated_transaction();
            $document = self::get($id);
            if ((int) $document->editversion !== (int) ($data->expectedversion ?? -1)) {
                throw new \moodle_exception('legaleditconflict', 'local_storefront');
            }
            $identifier = clean_param((string) $data->identifier, PARAM_ALPHANUMEXT);
            if ($identifier === '') {
                throw new \moodle_exception('required');
            }
            if (
                $DB->record_exists_select(
                    'local_storefront_legaldoc',
                    'identifier = :identifier AND id <> :id',
                    ['identifier' => $identifier, 'id' => $id]
                )
            ) {
                throw new \moodle_exception('legalidentifierexists', 'local_storefront');
            }
            $translations = self::translations($id);
            $default = (string) $data->defaultlang;
            if (!isset($translations[$default])) {
                throw new \moodle_exception('invalidparameter');
            }
            $translation = $translations[$default];
            if (!empty($data->visible) && empty($translation->published)) {
                throw new \moodle_exception('legaldefaultrequired', 'local_storefront');
            }
            $record = (object) [
                'id' => $id, 'identifier' => $identifier, 'visible' => !empty($data->visible),
                'showincheckout' => !empty($data->showincheckout),
                'requireconfirmation' => !empty($data->showincheckout) && !empty($data->requireconfirmation),
                'sortorder' => (int) $data->sortorder, 'defaultlang' => $default,
                'editversion' => (int) $document->editversion + 1, 'timemodified' => time(),
            ];
            if (!empty($translation->published)) {
                $revision = $DB->get_record(
                    'local_storefront_legalrev',
                    ['id' => $translation->currentrevisionid, 'documentid' => $id],
                    '*',
                    MUST_EXIST
                );
                $record->title = $revision->title;
                $record->currentrevisionid = $revision->id;
            }
            $DB->update_record('local_storefront_legaldoc', $record);
            $transaction->allow_commit();
        } catch (\Throwable $error) {
            if ($transaction) {
                $transaction->rollback($error);
            }
            throw $error;
        } finally {
            $lock->release();
        }
    }

    /**
     * Delete the record subject to its retained references.
     *
     * @param int $id Record ID.
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        self::get($id);
        if ($DB->record_exists('local_storefront_orderlegal', ['source' => 'storefront', 'sourceid' => $id])) {
            throw new \moodle_exception('legaldocumentinuse', 'local_storefront');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_storefront_legallang', ['documentid' => $id]);
        $DB->delete_records('local_storefront_legalrev', ['documentid' => $id]);
        $DB->delete_records('local_storefront_legaldoc', ['id' => $id]);
        $transaction->allow_commit();
    }

    /**
     * Checkout uses one selected translation per document.
     *
     * @return array
     */
    public static function checkout_documents(): array {
        $result = [];
        foreach (self::documents() as $document) {
            if (empty($document->visible) || empty($document->showincheckout)) {
                continue;
            }
            $translation = self::resolve_language($document);
            if (!$translation) {
                throw new \moodle_exception('legaltranslationunavailable', 'local_storefront');
            }
            $revision = self::public_revision($document, (int) $translation->currentrevisionid);
            $result[] = (object) [
                'source' => 'storefront', 'sourceid' => (int) $document->id,
                'revisionid' => (int) $revision->id, 'lang' => (string) $translation->lang,
                'identifier' => (string) $document->identifier, 'title' => (string) $revision->title,
                'contenthash' => (string) $revision->contenthash,
                'required' => !empty($document->requireconfirmation), 'sortorder' => (int) $document->sortorder,
                'url' => new \moodle_url('/local/storefront/legal.php', [
                    'document' => $document->identifier, 'documentid' => $document->id, 'revision' => $revision->id,
                ]),
            ];
        }
        usort($result, static fn(\stdClass $left, \stdClass $right): int =>
            [(int) $left->sortorder, \core_text::strtolower((string) $left->title), (int) $left->sourceid]
                <=> [(int) $right->sortorder, \core_text::strtolower((string) $right->title), (int) $right->sourceid]);
        foreach ($result as $document) {
            $document->token = self::confirmation_token($document);
        }
        return $result;
    }

    /**
     * Recheck the displayed set, including linked documents without confirmation boxes.
     *
     * @param int $orderid Order ID.
     * @param array $acceptedtokens Acceptedtokens.
     * @param array|null $presented Values presented to the customer.
     * @return void
     */
    public static function record_order_documents(int $orderid, array $acceptedtokens, ?array $presented = null): void {
        global $DB;
        $DB->get_record('local_storefront_order', ['id' => $orderid], 'id', MUST_EXIST);
        if ($DB->record_exists('local_storefront_orderlegal', ['orderid' => $orderid])) {
            throw new \coding_exception('Legal-document evidence has already been recorded for this order.');
        }
        $accepted = array_fill_keys(array_map('strval', $acceptedtokens), true);
        $documents = self::checkout_documents();
        $expected = array_map(static fn($d): string => $d->token, $documents);
        if ($presented !== null) {
            sort($expected);
            $actual = array_map('strval', $presented);
            sort($actual);
            if ($expected !== $actual) {
                throw new \moodle_exception('legalcheckoutchanged', 'local_storefront');
            }
        }
        foreach ($documents as $document) {
            if (!empty($document->required) && !isset($accepted[$document->token])) {
                throw new \moodle_exception('legalconfirmationmissing', 'local_storefront');
            }
        }
        $transaction = $DB->start_delegated_transaction();
        foreach ($documents as $document) {
            $DB->insert_record('local_storefront_orderlegal', (object) [
                'orderid' => $orderid, 'source' => (string) $document->source, 'sourceid' => (int) $document->sourceid,
                'revisionid' => (int) $document->revisionid, 'lang' => (string) ($document->lang ?? ''),
                'identifier' => (string) $document->identifier, 'title' => (string) $document->title,
                'contenthash' => (string) $document->contenthash, 'required' => !empty($document->required),
                'timeaccepted' => !empty($document->required) && isset($accepted[$document->token]) ? time() : null,
            ]);
        }
        $transaction->allow_commit();
    }

    /**
     * Return the token binding checkout to the displayed legal revisions.
     *
     * @param \stdClass $document Validated document data.
     * @return string
     */
    private static function confirmation_token(\stdClass $document): string {
        return implode(':', [$document->source, (int) $document->sourceid, (int) $document->revisionid]);
    }
}
