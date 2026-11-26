<?php

require_once __DIR__ . '/AdminInstall.php';

class FormTemplate
{
    private static ?array $stringCache = null;
    private static ?array $brandingCache = null;
    private static bool $inlineEdit = false;

    public static function setInlineEdit(bool $enabled): void
    {
        self::$inlineEdit = $enabled;
    }

    public static function isInlineEdit(): bool
    {
        return self::$inlineEdit;
    }

    public static function resetCache(): void
    {
        self::$stringCache = null;
        self::$brandingCache = null;
    }

    /** Build default keys from wizard config + paper catalog. */
    public static function collectDefaultStrings(): array
    {
        $out = require __DIR__ . '/../config/admin-template-catalog.php';
        $static = require __DIR__ . '/../config/admin-form-static-text.php';
        if (is_array($static)) {
            $out = array_merge($out, $static);
        }
        $wizard = require __DIR__ . '/../config/form-wizard.php';

        foreach ($wizard['pages'] ?? [] as $page) {
            $pid = $page['id'] ?? '';
            if ($pid === '') {
                continue;
            }
            if (!empty($page['title'])) {
                $out['page.' . $pid . '.title'] = (string) $page['title'];
            }
            if (!empty($page['subtitle'])) {
                $out['page.' . $pid . '.subtitle'] = (string) $page['subtitle'];
            }
            if (!empty($page['short_title'])) {
                $out['page.' . $pid . '.short_title'] = (string) $page['short_title'];
            }
            foreach ($page['sections'] ?? [] as $sec) {
                if (!empty($sec['title'])) {
                    $key = 'page.' . $pid . '.section.' . self::slugKey($sec['title']);
                    $out[$key] = (string) $sec['title'];
                }
                foreach ($sec['fields'] ?? [] as $field) {
                    $name = $field['name'] ?? '';
                    $label = $field['label'] ?? '';
                    if ($name !== '' && $label !== '') {
                        $out['field.' . $pid . '.' . $name . '.label'] = (string) $label;
                        $out['label.' . $name] = (string) $label;
                    }
                }
            }
        }

        return $out;
    }

    public static function slugKey(string $text): string
    {
        $s = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $text));
        return trim($s, '_') ?: 'section';
    }

    public static function fieldLabelKey(string $pageId, string $fieldName): string
    {
        return 'field.' . $pageId . '.' . $fieldName . '.label';
    }

    public static function fieldOptionKey(string $pageId, string $fieldName, string $value): string
    {
        return 'field.' . $pageId . '.option.' . $fieldName . '.' . self::slugKey($value);
    }

    public static function sectionTitleKey(string $pageId, string $title): string
    {
        return 'page.' . $pageId . '.section.' . self::slugKey($title);
    }

    /** Resolved template text; HTML-safe unless inline edit wraps the span. */
    public static function html(string $key, string $fallback): string
    {
        $text = self::get($key, $fallback);
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        if (!self::$inlineEdit) {
            return $escaped;
        }
        return self::wrapInlineEditable($key, $escaped);
    }

    public static function wrapInlineEditable(string $key, string $innerHtml): string
    {
        return '<span class="admin-tpl-editable" data-template-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
            . '" contenteditable="true" spellcheck="false" title="Click to edit label">' . $innerHtml . '</span>';
    }

    public static function hasTemplateStringsTable(mysqli $conn): bool
    {
        $check = $conn->query("SHOW TABLES LIKE 'form_template_strings'");
        if (!$check) {
            return false;
        }
        $ok = $check->num_rows > 0;
        $check->free();
        return $ok;
    }

    public static function syncDefaults(mysqli $conn): bool
    {
        AdminInstall::ensureSchema($conn);
        if (!self::hasTemplateStringsTable($conn)) {
            error_log('[RHU FormTemplate] form_template_strings missing; sync skipped.');
            return false;
        }

        $defaults = self::collectDefaultStrings();
        $stmt = $conn->prepare(
            'INSERT INTO form_template_strings (template_key, default_text)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE default_text = VALUES(default_text)'
        );
        if (!$stmt) {
            error_log('[RHU FormTemplate] sync prepare failed: ' . $conn->error);
            return false;
        }
        foreach ($defaults as $key => $text) {
            $stmt->bind_param('ss', $key, $text);
            if (!$stmt->execute()) {
                error_log('[RHU FormTemplate] sync insert failed for ' . $key . ': ' . $stmt->error);
            }
        }
        $stmt->close();
        return true;
    }

    private static function loadStrings(mysqli $conn): array
    {
        if (self::$stringCache !== null) {
            return self::$stringCache;
        }
        AdminInstall::ensureSchema($conn);
        self::syncDefaults($conn);
        $map = [];
        if (!self::hasTemplateStringsTable($conn)) {
            self::$stringCache = $map;
            return $map;
        }
        $res = $conn->query('SELECT template_key, default_text, custom_text FROM form_template_strings');
        if (!$res) {
            error_log('[RHU FormTemplate] load strings query failed: ' . $conn->error);
            self::$stringCache = $map;
            return $map;
        }
        while ($row = $res->fetch_assoc()) {
            $map[$row['template_key']] = $row['custom_text'] !== null && $row['custom_text'] !== ''
                ? $row['custom_text']
                : $row['default_text'];
        }
        self::$stringCache = $map;
        return $map;
    }

    public static function get(string $key, string $fallback = ''): string
    {
        try {
            $conn = getDBConnection();
            $map = self::loadStrings($conn);
            $conn->close();
            return $map[$key] ?? $fallback;
        } catch (Throwable $e) {
            return $fallback;
        }
    }

    /** Resolve label: try field-specific, then global field name, then default text. */
    public static function fieldLabel(string $defaultText, ?string $fieldName = null, ?string $pageId = null): string
    {
        if ($pageId && $fieldName) {
            $v = self::get('field.' . $pageId . '.' . $fieldName . '.label', '');
            if ($v !== '') {
                return $v;
            }
        }
        if ($fieldName) {
            $v = self::get('label.' . $fieldName, '');
            if ($v !== '') {
                return $v;
            }
        }
        return $defaultText;
    }

    public static function branding(): array
    {
        if (self::$brandingCache !== null) {
            return self::$brandingCache;
        }
        $defaults = [
            'logo_path'           => '/assets/img/rhu-magallanes-logo.png',
            'site_title'          => 'RHU Magallanes',
            'site_subtitle'       => 'Agusan del Norte',
            'facility_name'       => 'Rural Health Unit — Magallanes',
            'form_display_style'  => 'official',
        ];
        try {
            $conn = getDBConnection();
            // Schema is ensured once in header/auth — skip heavy ensureSchema here for speed
            $brRes = @$conn->query('SELECT * FROM system_branding WHERE id = 1 LIMIT 1');
            if (!$brRes) {
                require_once __DIR__ . '/AdminInstall.php';
                AdminInstall::ensureSchema($conn);
                $brRes = $conn->query('SELECT * FROM system_branding WHERE id = 1 LIMIT 1');
            }
            $row = $brRes ? $brRes->fetch_assoc() : null;
            $conn->close();
            self::$brandingCache = $row ? array_merge($defaults, $row) : $defaults;
        } catch (Throwable $e) {
            self::$brandingCache = $defaults;
        }
        return self::$brandingCache;
    }

    public static function logoUrl(): string
    {
        $b = self::branding();
        $path = (string) ($b['logo_path'] ?? '/assets/img/rhu-magallanes-logo.png');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }

    /** Encoding screen style: official (paper-like) or clean (guided). Print always official. */
    public static function formDisplayStyle(): string
    {
        $style = strtolower(trim((string) (self::branding()['form_display_style'] ?? 'official')));
        return $style === 'clean' ? 'clean' : 'official';
    }

    public static function isCleanFormDisplay(): bool
    {
        return self::formDisplayStyle() === 'clean';
    }

    /** Official RHU / wizard forms for admin catalog (includes Review & Submit). */
    public static function formCatalog(): array
    {
        $wizard = require __DIR__ . '/../config/form-wizard.php';
        $forms = [];
        $seq = 0;
        foreach ($wizard['pages'] ?? [] as $page) {
            $id = (string) ($page['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $seq++;
            $paper = is_array($page['paper'] ?? null) ? $page['paper'] : [];
            $paperPath = __DIR__ . '/../forms/paper/' . $id . '.php';
            $fieldCount = 0;
            foreach ($page['sections'] ?? [] as $sec) {
                $fieldCount += count($sec['fields'] ?? []);
            }
            $type = (string) ($paper['type'] ?? '');
            $forms[] = [
                'id'                 => $id,
                'sequence'           => $seq,
                'title'              => (string) ($page['title'] ?? $id),
                'short_title'        => (string) ($page['short_title'] ?? $page['title'] ?? $id),
                'subtitle'           => (string) ($page['subtitle'] ?? ''),
                'paper_type'         => $type,
                'paper_type_label'   => self::paperTypeLabel($type, $paper['size'] ?? null, $paper['pair'] ?? null),
                'paper_size'         => $paper['size'] ?? null,
                'paper_pair'         => $paper['pair'] ?? null,
                'has_paper_template' => is_file($paperPath),
                'field_count'        => $fieldCount,
                'is_system'          => $type === 'system',
            ];
        }
        return $forms;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupStringsByForm(array $rows, ?array $forms = null): array
    {
        $forms = $forms ?? self::formCatalog();
        $grouped = [];
        foreach ($forms as $f) {
            $grouped[(string) $f['id']] = [];
        }
        $grouped['_global'] = [];

        foreach ($rows as $row) {
            $key = (string) ($row['template_key'] ?? '');
            $formId = self::templateKeyFormId($key);
            if ($formId !== null && array_key_exists($formId, $grouped)) {
                $grouped[$formId][] = $row;
            } else {
                $grouped['_global'][] = $row;
            }
        }

        return $grouped;
    }

    public static function templateKeyFormId(string $key): ?string
    {
        if (preg_match('/^(?:page|field)\.([a-z0-9_-]+)\./', $key, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function paperTypeLabel(string $type, ?string $size = null, ?string $pair = null): string
    {
        return match ($type) {
            'independent' => 'Independent' . ($size ? ' · ' . str_replace('-', ' ', (string) $size) : ''),
            'front'       => 'Paper front' . ($pair ? ' (pairs with ' . $pair . ')' : ''),
            'back'        => 'Paper back' . ($pair ? ' (pairs with ' . $pair . ')' : ''),
            'system'      => 'System page (no print layout)',
            default       => $type !== '' ? $type : 'Form',
        };
    }

    /** @return list<array<string, mixed>> */
    public static function listStrings(mysqli $conn, ?string $search = null, ?string $prefix = null): array
    {
        AdminInstall::ensureSchema($conn);
        self::syncDefaults($conn);
        $search = trim((string) $search);
        $prefix = trim((string) $prefix);

        $sql = 'SELECT template_key, default_text, custom_text, updated_at FROM form_template_strings WHERE 1=1';
        $params = [];
        $types = '';

        if ($prefix !== '') {
            $sql .= ' AND template_key LIKE ?';
            $params[] = $prefix . '%';
            $types .= 's';
        }
        if ($search !== '') {
            $sql .= ' AND (template_key LIKE ? OR default_text LIKE ? OR custom_text LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }
        $sql .= ' ORDER BY template_key ASC LIMIT 5000';

        if (!self::hasTemplateStringsTable($conn)) {
            return [];
        }

        if ($params) {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log('[RHU FormTemplate] list prepare failed: ' . $conn->error);
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        return $rows;
    }

    public static function saveString(mysqli $conn, int $actorId, string $key, ?string $customText): void
    {
        AdminInstall::ensureSchema($conn);
        self::syncDefaults($conn);
        $customText = $customText !== null && trim($customText) === '' ? null : $customText;

        $defaults = self::collectDefaultStrings();
        $defaultText = $defaults[$key] ?? ($customText ?? '');

        $stmt = $conn->prepare(
            'INSERT INTO form_template_strings (template_key, default_text, custom_text, updated_at, updated_by)
             VALUES (?, ?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE custom_text = VALUES(custom_text), updated_at = NOW(), updated_by = VALUES(updated_by)'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not save template string: ' . $conn->error);
        }
        $stmt->bind_param('sssi', $key, $defaultText, $customText, $actorId);
        $stmt->execute();
        $stmt->close();
        self::resetCache();

        require_once __DIR__ . '/AdminAudit.php';
        AdminAudit::log($conn, $actorId, 'template.update', 'template_key', $key, [
            'custom_text' => $customText,
        ]);
    }

    public static function saveBranding(mysqli $conn, int $actorId, array $data): void
    {
        AdminInstall::ensureSchema($conn);
        $title = trim($data['site_title'] ?? '');
        $sub = trim($data['site_subtitle'] ?? '');
        $facility = trim($data['facility_name'] ?? '');
        $logo = trim($data['logo_path'] ?? '');
        $style = strtolower(trim((string) ($data['form_display_style'] ?? 'official')));
        if ($style !== 'clean') {
            $style = 'official';
        }

        $stmt = $conn->prepare(
            'UPDATE system_branding SET site_title = ?, site_subtitle = ?, facility_name = ?, logo_path = ?, form_display_style = ?, updated_at = NOW(), updated_by = ? WHERE id = 1'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not save branding: ' . $conn->error);
        }
        $stmt->bind_param('sssssi', $title, $sub, $facility, $logo, $style, $actorId);
        $stmt->execute();
        $stmt->close();
        self::resetCache();

        require_once __DIR__ . '/AdminAudit.php';
        AdminAudit::log($conn, $actorId, 'branding.update', 'system_branding', '1', $data);
    }

    /** Apply saved template copy on live/print forms (no contenteditable). */
    public static function applyStaticTemplateCopy(string $html, string $pageId): string
    {
        return self::processStaticCopyHtml($html, $pageId, false);
    }

    /**
     * Admin preview: wrap remaining static copy so every visible label can be edited and saved.
     */
    public static function augmentInlineEditableHtml(string $html, string $pageId): string
    {
        if (!self::$inlineEdit || trim($html) === '' || $pageId === '') {
            return $html;
        }

        return self::processStaticCopyHtml($html, $pageId, true);
    }

    private static function processStaticCopyHtml(string $html, string $pageId, bool $wrapEditable): string
    {
        if (trim($html) === '' || $pageId === '') {
            return $html;
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="augment-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $doc->getElementById('augment-root');
        if (!$root) {
            return $html;
        }

        $usedKeys = [];
        $xpath = new DOMXPath($doc);

        $candidates = [];
        foreach ($xpath->query('//*[contains(@class,"paper-sheet")]//*') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if (!self::augmentIsTextCarrier($node)) {
                continue;
            }
            if (self::augmentShouldSkip($node)) {
                continue;
            }
            $candidates[] = $node;
        }

        foreach ($candidates as $el) {
            if (!$el->parentNode) {
                continue;
            }
            if ($el->getElementsByTagName('span')->length) {
                $hasEditable = false;
                foreach ($el->getElementsByTagName('span') as $span) {
                    if ($span instanceof DOMElement && str_contains(' ' . $span->getAttribute('class') . ' ', ' admin-tpl-editable ')) {
                        $hasEditable = true;
                        break;
                    }
                }
                if ($hasEditable) {
                    continue;
                }
            }

            $key = self::augmentResolveKey($el, $pageId, $usedKeys);
            if ($key === '') {
                continue;
            }

            $plain = self::augmentPlainText($el);
            if ($plain === '') {
                continue;
            }

            $resolved = self::get($key, $plain);
            if ($wrapEditable) {
                self::augmentReplaceWithEditable($doc, $el, $key, $resolved);
            } else {
                self::augmentReplacePlain($doc, $el, $resolved);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function augmentIsTextCarrier(DOMElement $el): bool
    {
        $class = ' ' . $el->getAttribute('class') . ' ';
        $tag = strtolower($el->tagName);

        if (str_contains($class, ' admin-tpl-editable ')) {
            return false;
        }

        if ($tag === 'span' && str_contains($class, ' pf-sublabel ')) {
            return true;
        }
        if ($tag === 'div' && (
            str_contains($class, ' pf-section-title-center ')
            || str_contains($class, ' pf-sig-label ')
            || str_contains($class, ' pf-pcsf-copy-label ')
            || str_contains($class, ' pf-title-main ')
            || str_contains($class, ' pf-form-footer ')
        )) {
            return true;
        }
        if ($tag === 'p' && (
            str_contains($class, ' pf-pcsf-certify ')
            || str_contains($class, ' pf-pcsf-privacy ')
        )) {
            return true;
        }
        if ($tag === 'th') {
            return true;
        }
        if ($tag === 'li' && self::augmentAncestorHasClass($el, 'pf-instructions')) {
            return true;
        }
        if ($tag === 'td' && self::augmentAncestorHasClass($el, 'ekas-services-table')) {
            $row = $el->parentNode;
            if ($row instanceof DOMElement && $el === $row->firstElementChild) {
                return true;
            }
        }
        if (($tag === 'strong' || $tag === 'em') && !self::augmentContainsFormControl($el)) {
            return trim($el->textContent) !== '';
        }
        if ($tag === 'span' && self::augmentIsOptionLabelSpan($el)) {
            return true;
        }
        if ($tag === 'p' && !self::augmentContainsFormControl($el)) {
            return trim($el->textContent) !== '';
        }

        return false;
    }

    private static function augmentShouldSkip(DOMElement $el): bool
    {
        if (self::augmentHasEditableAncestor($el)) {
            return true;
        }
        if (self::augmentContainsFormControl($el) && !self::augmentIsOptionLabelSpan($el)) {
            return true;
        }
        if (str_contains(' ' . $el->getAttribute('class') . ' ', ' pf-label ')) {
            return true;
        }
        if (str_contains(' ' . $el->getAttribute('class') . ' ', ' pf-section-bar ')) {
            return true;
        }
        if (str_contains(' ' . $el->getAttribute('class') . ' ', ' pf-req ')
            || str_contains(' ' . $el->getAttribute('class') . ' ', ' pf-opt ')) {
            return true;
        }

        return false;
    }

    private static function augmentIsOptionLabelSpan(DOMElement $el): bool
    {
        if (strtolower($el->tagName) !== 'span') {
            return false;
        }
        $label = $el->parentNode;
        if (!$label instanceof DOMElement || strtolower($label->tagName) !== 'label') {
            return false;
        }
        $class = ' ' . $label->getAttribute('class') . ' ';
        return str_contains($class, ' pf-check ') || str_contains($class, ' pf-radio ');
    }

    private static function augmentHasEditableAncestor(DOMElement $el): bool
    {
        for ($node = $el->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (str_contains(' ' . $node->getAttribute('class') . ' ', ' admin-tpl-editable ')) {
                return true;
            }
        }

        return false;
    }

    private static function augmentAncestorHasClass(DOMElement $el, string $needle): bool
    {
        for ($node = $el->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (str_contains(' ' . $node->getAttribute('class') . ' ', ' ' . $needle . ' ')) {
                return true;
            }
        }

        return false;
    }

    private static function augmentContainsFormControl(DOMElement $el): bool
    {
        foreach (['input', 'textarea', 'select', 'button'] as $tag) {
            if ($el->getElementsByTagName($tag)->length > 0) {
                return true;
            }
        }

        return false;
    }

    private static function augmentPlainText(DOMElement $el): string
    {
        $text = '';
        foreach ($el->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->data;
            } elseif ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'br') {
                    $text .= "\n";
                } elseif (str_contains(' ' . $child->getAttribute('class') . ' ', ' admin-tpl-editable ')) {
                    $text .= $child->textContent;
                } else {
                    $text .= $child->textContent;
                }
            }
        }
        if ($text === '' && $el->childNodes->length === 0) {
            $text = $el->textContent;
        }

        return trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)) ?? '');
    }

    /** @param array<string, true> $usedKeys */
    private static function augmentResolveKey(DOMElement $el, string $pageId, array &$usedKeys): string
    {
        if (self::augmentIsOptionLabelSpan($el)) {
            $label = $el->parentNode;
            if ($label instanceof DOMElement) {
                foreach ($label->getElementsByTagName('input') as $input) {
                    if (!$input instanceof DOMElement) {
                        continue;
                    }
                    $name = preg_replace('/\[\]$/', '', (string) $input->getAttribute('name'));
                    $value = (string) $input->getAttribute('value');
                    if ($name !== '' && $value !== '') {
                        return self::fieldOptionKey($pageId, $name, $value);
                    }
                }
            }
        }

        $plain = self::augmentPlainText($el);
        $base = 'page.' . $pageId . '.auto.' . self::slugKey(mb_substr($plain, 0, 80));
        $key = $base;
        $n = 2;
        while (isset($usedKeys[$key])) {
            $key = $base . '_' . $n;
            $n++;
        }
        $usedKeys[$key] = true;

        return $key;
    }

    private static function augmentReplaceWithEditable(DOMDocument $doc, DOMElement $el, string $key, string $resolved): void
    {
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }

        $span = $doc->createElement('span');
        $span->setAttribute('class', 'admin-tpl-editable');
        $span->setAttribute('data-template-key', $key);
        $span->setAttribute('contenteditable', 'true');
        $span->setAttribute('spellcheck', 'false');
        $span->setAttribute('title', 'Click to edit label');
        $span->appendChild($doc->createTextNode($resolved));
        $el->appendChild($span);
    }

    private static function augmentReplacePlain(DOMDocument $doc, DOMElement $el, string $resolved): void
    {
        while ($el->firstChild) {
            $el->removeChild($el->firstChild);
        }
        $el->appendChild($doc->createTextNode($resolved));
    }
}
