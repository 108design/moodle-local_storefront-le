<?php
// This file is part of Moodle - https://moodle.org/
/**
 * German language strings for Storefront.
 *
 * @package local_storefront
 * @copyright 2026 Andreas Giesen <andreas@108design.com>
 * @license 108design source-available software license; see LICENSE.md
 */

// Copyright (C) 2026 Andreas Giesen. Use and modification under LICENSE.md.
defined('MOODLE_INTERNAL') || die();

$string['coursemappingnopermission'] = 'Du darfst die Einschreibemethoden dieses Kurses nicht konfigurieren.';
$string['coursemissing'] = 'Wähle einen verfügbaren Moodle-Kurs.';
$string['courseroleinvalid'] = 'Wähle eine Rolle, die über diese Einschreibemethode im Kurs zugewiesen werden darf.';
$string['coursepluginenablehelp'] = 'Aktiviere Storefront-Kurskauf unter Website-Administration → Plugins → Einschreibung, bevor du das Produkt veröffentlichst.';
$string['coursepluginmissing'] = 'Installiere zuerst das begleitende Einschreibe-Plugin Storefront-Kurskauf, um Kursprodukte zu verknüpfen.';
$string['moodlepaymentchoosegateway'] = 'Wähle eine der für dieses Moodle-Zahlungskonto verfügbaren Zahlungsmethoden.';
$string['legalidentifier_help'] = 'Diese Kennung gilt für alle Sprachfassungen des Rechtstexts, zum Beispiel terms oder privacy. Du bearbeitest sie unter „Gemeinsame Einstellungen“. Eine Änderung gilt für den gesamten Rechtstext und kann bestehende Links mit der bisherigen Kennung ungültig machen.';
$string['legaldocumentinuse'] = 'Dieser Rechtstext ist bereits mit einer Bestellung verknüpft und kann nicht gelöscht werden. Blende ihn stattdessen aus.';
$string['legallanguage_help'] = 'Die Sprache gilt für diese Fassung. Ordne übernommenen Texten ihre tatsächliche Sprache zu. Weitere Sprachfassungen legst du beim selben Rechtstext an.';
$string['legalinputformat_help'] = 'Wähle den visuellen Editor oder Markdown. Markdown bleibt als Rohtext gespeichert. Bearbeiten und Vorschau nutzen denselben Bereich. Beim Formatwechsel wird der Inhalt nicht automatisch konvertiert; füge den Text im gewählten Format ein.';
$string['legalpublished_help'] = 'Beim Speichern wird diese Fassung für Leser und Checkout verfügbar. Ohne Häkchen speicherst du einen Entwurf; eine bisher veröffentlichte Fassung bleibt dabei verfügbar. Bereits veröffentlichte Revisionen bleiben über ihre festen Links erreichbar.';
$string['legalpreviewfailed'] = 'Die Vorschau konnte nicht geladen werden. Dein Rohtext ist erhalten. Wechsle zu Bearbeiten und versuche es erneut.';
$string['legaleditingintro'] = 'Hier bearbeitest du Bezeichnung, Inhalt und Veröffentlichung dieser Sprachfassung. Kennung und weitere dokumentweite Angaben findest du unter „Gemeinsame Einstellungen“.';
$string['legaleditconflict'] = 'Der Rechtstext wurde inzwischen geändert. Öffne die aktuelle Fassung und übertrage deine Änderungen.';
$string['legalcheckoutchanged'] = 'Die angezeigten Rechtstexte haben sich geändert. Öffne den Checkout erneut und prüfe die aktuellen Fassungen.';
$string['legalrevisionnotice'] = 'Du liest die fest verlinkte Revision {$a}. Über das Sprachmenü erreichst du die aktuellen Sprachfassungen.';
$string['legalsettingsintro'] = 'Kennung, Standardsprache, Sichtbarkeit, Reihenfolge und Checkout-Regeln gelten für alle Sprachfassungen dieses Rechtstexts. Sprachbezogene Bezeichnungen und Inhalte bearbeitest du über die Sprachlinks.';
$string['legaldefaultlanguage_help'] = 'Wähle die Fassung, die bei einer fehlenden Übersetzung angezeigt wird. Ein sichtbarer Rechtstext benötigt eine veröffentlichte Standardfassung.';
$string['legalcreatingintro'] = 'Lege die erste Sprachfassung und die gemeinsamen Einstellungen des Rechtstexts an. Weitere Sprachen fügst du danach hinzu. Die erste Fassung wird als Standardsprache verwendet.';
$string['adminhelp_legal_customer_article'] = '<p>Der Checkout verlinkt die Rechtstexte für deine Bestellung. Lies sie vor der Bestätigung. Du kannst eine andere veröffentlichte Dokumentensprache wählen, ohne die Sprache der Moodle-Oberfläche zu ändern.</p><p>Meine Käufe verlinkt die mit deiner Bestellung gespeicherten Fassungen und erforderlichen Bestätigungen. Spätere Textänderungen ersetzen diese Bestellnachweise nicht.</p>';
$string['moodlecourses_help'] = 'Wähle alle Kurse, die das Produkt bereitstellt. Für jeden betroffenen Kurs werden die nativen Rechte zur Einschreibungskonfiguration benötigt. Bereits von Käufen referenzierte Zuordnungen lassen sich nicht entfernen.';
$string['orderingintro'] = 'Verschiebe Einträge global oder innerhalb einer gewählten Kategorie beziehungsweise eines Tags. Ausgeblendete Produkte bleiben Teil der Reihenfolge. Die Gruppenreihenfolge lässt sich auf die globale Reihenfolge zurücksetzen.';
$string['conversionintro'] = 'Wandele {$a} in einen Tag um. Produktzuordnungen gehen auf den passenden Tag über; die Kategorie wird entfernt. Andere Zuordnungen bleiben erhalten.';
$string['amount_help'] = 'Einmalpreis für dieses Kursprodukt. Verwende einen positiven Betrag mit Dezimalpunkt. Die Kurszuordnung bestimmt die Zugangsdauer für alle Preisoptionen.';
$string['currency_help'] = 'Verwende eine dreistellige Währung, die ein aktiviertes Gateway des gewählten Moodle-Zahlungskontos unterstützt. Alle Produkte einer Bestellung müssen dieselbe Währung verwenden.';
$string['invalidoffer'] = 'Gib einen gültigen positiven Preis, eine Währung und eine Kennung ein.';
$string['badgecolor_help'] = 'Verwende einen sechsstelligen HEX-Wert oder eine unterstützte OKLCH-Farbe. Ein ungültiger Wert verwendet den neutralen Standard.';
$string['storevisibility_help'] = 'Wähle, wer den Katalog ansehen darf. Vor der Zahlung müssen Käufer/innen angemeldet sein. Eigene Bestellungen und Kurszugänge bleiben auf das zugehörige Konto beschränkt.';
$string['paymentaccount_help'] = 'Wähle ein aktiviertes Konto aus den Moodle-Zahlungskonten. Richte sein Gateway ein und prüfe dessen Sandbox vor der Annahme von Zahlungen.';
$string['cartempty'] = 'Dein Warenkorb ist leer.';
$string['selectionchanged'] = 'Deine Auswahl oder ihr Preis hat sich geändert. Prüfe Warenkorb und Bestellübersicht erneut.';
$string['selectionexpired'] = 'Dieser Auswahllink ist abgelaufen oder wurde bereits verwendet. Wähle deine Kurse erneut im Katalog.';
$string['selectionunavailable'] = 'Ein ausgewähltes Angebot ist derzeit nicht verfügbar. Entferne es vor der Bestellung.';
$string['coursefulfilmentunavailable'] = 'Der Kurszugang ist derzeit nicht verfügbar. Bitte wende dich an die Shop-Verwaltung.';
$string['noproducts'] = 'Keine Kurse passen zu deiner Auswahl.';
$string['checkoutmixedcurrency'] = 'Verwende für alle Produkte einer Bestellung dieselbe Währung.';
$string['deliverypending'] = 'Die Zahlung ist erfasst. Die Kursauslieferung wartet oder benötigt einen weiteren Versuch. Du kannst sie hier wiederholen; wende dich an die Shop-Verwaltung, falls der Zugang weiter fehlt.';
$string['legaldocumentlimit'] = 'Du kannst bis zu zwei Rechtstexte anlegen. Ergänze eine Sprachfassung oder Revision eines bestehenden Dokuments.';
$string['distributionmismatch'] = 'Diese Installation kann dieses Paket nicht verwenden. Stelle vor dem Fortsetzen das zur installierten Ausgabe passende Paket wieder her.';
$string['orderreadysubject'] = 'Dein Kurszugang ist bereit';
$string['orderreadybody'] = '<p>Deine Bestellung {order_reference} ist bereit.</p><p><a href="{manage_url}">Käufe und Kurszugänge ansehen</a></p>';
$string['mailplaceholders'] = 'Verwende {order_reference} für die Bestellkennung und {manage_url} für Meine Käufe. Der Inhalt wird beim Vormerken gespeichert; spätere Änderungen gelten für neue Nachrichten.';
$string['attempts'] = 'Versuche';
$string['adminhelp_shopping_article'] = '<p>Der Katalog aktualisiert sich bei der Eingabe und bei Filteränderungen. Ohne JavaScript übernimmst du deine Auswahl mit Suchen.</p><p>Bild und Titel führen zu den Produktdetails. Ein einzelnes Angebot legst du direkt in den Warenkorb. Bei mehreren Preisen führt Angebot wählen zur Auswahl. Ein anderes Angebot für dasselbe Produkt ersetzt die Auswahl im Warenkorb. Ein Produkt kann mehrere Kurse enthalten.</p><p>Melde dich vor der Bestellung an. Die Zahlung erfolgt über die vom Betreiber eingerichteten Gateways. Bewahre die Bestellkennung für Rückfragen auf. Offene Zahlungen findest du unter Meine Käufe.</p><p>Das Warenkorb-Icon neben dem Nutzermenü öffnet die Seitenleiste mit deiner Auswahl und den Gesamtbeträgen. Dort kannst du Positionen entfernen oder zum vollständigen Warenkorb und zur Kasse wechseln. Ohne JavaScript öffnet das Icon die Warenkorbseite.</p>';
$string['adminhelp_purchases_article'] = '<p>Meine Käufe zeigt deine eigenen Bestellungen mit getrenntem Zahlungs- und Auslieferungsstatus. Auf eine erfasste Zahlung kann noch eine ausstehende Kursauslieferung folgen. Kursauslieferung wiederholen verwendet die erfasste Zahlung und bucht nicht erneut ab.</p><p>Jeder Kurskauf behält seinen ursprünglichen Zeitraum. Andere aktive Käufe können deinen Zugang nach dem Ende eines Kaufs erhalten. Wende dich bei Erstattungen oder Streitfällen an die Shop-Verwaltung.</p>';
$string['adminhelp_legal_article'] = '<p>Bis zu zwei frei benennbare Dokumente können Sprachfassungen und Revisionen enthalten. Ein bearbeiteter Entwurf lässt die veröffentlichte Fassung bestehen, bis du den neuen Stand veröffentlichst.</p><p>Die Dokumenteinstellungen legen Sichtbarkeit, Standardsprache und erforderliche Bestätigung an der Kasse fest. Die Bestellung hält die angezeigte Fassung und eine geforderte Bestätigung fest. Frühere Nachweise bleiben erhalten. Leser können die Dokumentsprache unabhängig von der Moodle-Oberfläche wählen.</p>';
$string['adminhelp_products_article'] = '<p>Lege ein Kursprodukt an und speichere es. Unter Kursauslieferung wählst du seine Kurse, unter Angebote die Preise. Aktiviere ein Angebot und mache das Produkt sichtbar, wenn Kurse und Zahlungskonto bereit sind.</p><p>Die Kennung bleibt nach dem ersten Speichern fest. Ausblenden verhindert neue Käufe; frühere Bestellungen behalten ihre gespeicherten Produkt- und Kursangaben.</p>';
$string['adminhelp_offers_article'] = '<p>Jedes Angebot hat einen Einmalpreis in einer Währung. Richte ein Moodle-Zahlungskonto mit einem aktivierten Gateway ein, das diese Währung unterstützt. Mehrere Währungen lassen sich nicht in einer Bestellung bezahlen.</p><p>Rolle und Zugangsdauer kommen aus der Kurszuordnung. Preisoptionen legen keine eigenen Zugangszeiträume fest. Angebotsänderungen gelten für neue Bestellungen.</p>';
$string['adminhelp_taxonomy_article'] = '<p>Kategorien gliedern den Katalog; Tags beschreiben überlappende Themen. Beides ordnest du im Produkteditor zu. Besucher/innen können Suche, Kategorie und Tags verbinden. Bei mehreren ausgewählten Tags genügt die Zuordnung zu einem davon.</p><p>Kleinere Sortierwerte erscheinen zuerst. Ausgeblendete Kategorien und Tags bleiben zugeordnet, erscheinen aber nicht in den Kundenfiltern.</p>';
$string['adminhelp_delivery_article'] = '<p>Aktiviere Storefront Enrolment in Moodle. Kursauslieferung ordnet dem Produkt einen oder mehrere Kurse mit Rolle und Dauer zu. Die Bearbeitung erfordert die nativen Rechte zur Einschreibungskonfiguration in jedem betroffenen Kurs.</p><p>Bestellungen behalten die gespeicherte Rolle und Dauer. Befristeter Zugang beginnt bei der tatsächlichen Auslieferung. Wiederholen erhält bereits ausgelieferte Positionen. Ein weiterer aktiver Kauf schützt den von ihm gewährten Zugang. Eine Kurswiederherstellung übernimmt einen deaktivierten Produkthinweis und verlangt bewusste Neuzuordnung.</p>';
$string['adminhelp_operations_article'] = '<p>Bestellungen und Zugänge trennt Zahlung, Positionsauslieferung und Kurszugang. Käufer/innen können offene Zahlungen fortsetzen. Kursauslieferung wiederholen benötigt einen gespeicherten Zahlungsnachweis und verwendet die ursprünglichen Kursangaben.</p><p>Reparieren du eine deaktivierte oder fehlende Kurszuordnung vor dem Wiederholen. Die Rückkehr von einer Bezahlseite setzt keine Bestellung auf bezahlt. Vereinbaren du Erstattungs- und Streitfallverfahren passend zum eingesetzten Gateway.</p><p>Mit <strong>Kurszugang sperren</strong> pausierst du den Zugang aus diesem Kauf. Andere Käufe können den Kurs weiterhin zugänglich machen. <strong>Kurszugang wieder freigeben</strong> hebt deine Sperre auf. Laufzeit und Sperren aus dem Zahlungsstatus gelten weiter. Erstattungen bearbeitest du beim Zahlungsanbieter.</p>';
$string['operations_help'] = 'Prüfe je Bestellung Zahlung, Auslieferung und Kurszugang. Einen ausgelieferten Kurszugang kannst du direkt am Kurs verwalten.';
$string['shopping_help'] = 'Wähle Kurse im Katalog, prüfe deinen Warenkorb und melde dich vor der Zahlung an.';
$string['adminemails_help'] = 'Optionale interne Kopien der Nachricht zum Kurszugang. Trenne gültige Adressen durch Kommas, Semikolons oder Leerzeichen.';
$string['purchases_help'] = 'Unter Meine Käufe findest du den Zahlungs- und Kursauslieferungsstatus.';
$string['legal_help'] = 'Verwalte veröffentlichte Sprachfassungen und Checkout-Bestätigungen für bis zu zwei Rechtstexte.';
$string['cartupdatefailed'] = 'Der Warenkorb konnte nicht aktualisiert werden. Bitte versuche es erneut.';
$string['moodlepaymentnoaccounts'] = 'Lege unter Website-Administration → Zahlungen → Zahlungskonten ein aktives Moodle-Zahlungskonto an und wähle es anschließend hier aus.';
$string['paymentaccountrequired'] = 'Wähle ein aktives Moodle-Zahlungskonto aus.';
$string['updateguard_hint'] = 'Verwende ein freigegebenes Paket für die installierte Ausgabe. Du kannst den Vorgang erneut prüfen.';
$string['updateguard_dependency'] = 'Eine benötigte Plugin-Version fehlt. Aktualisiere die Abhängigkeit gemeinsam mit diesem Plugin oder vorher.';
$string['updateguard_approval'] = 'Die Pakete oder der installierte Stand haben sich seit der Vorschau geändert. Prüfe den Vorgang erneut.';
$string['updateguard_busy'] = 'Eine andere Installation läuft. Versuche es nach deren Abschluss erneut.';
$string['updateguard_installed'] = 'Der installierte Updateschutz ist unvollständig. Stelle die Dateien der zuletzt installierten freigegebenen Version wieder her.';
$string['updateguard_operation'] = 'Diese Kombination von Installationsaktionen wird nicht unterstützt. Starte einen einzelnen Updatevorgang erneut.';
$string['updateinfo_choice'] = 'Kontrolliere vor dem Fortfahren die Paketquelle und Moodles Prüfergebnisse. Mit „{$a}“ unten kannst du abbrechen.';
$string['deliveryconfigurationunavailable'] = 'Der Kurszugang ist nicht eingerichtet. Bitte kontaktiere die Website-Administration.';
