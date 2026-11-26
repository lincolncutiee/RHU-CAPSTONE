<?php
/**
 * Clean guided encoding layouts (Admin form_display_style = clean).
 * Same field names / permissions as paper forms. Print still uses paper templates.
 */

require_once __DIR__ . '/PaperFormRenderer.php';
require_once __DIR__ . '/FormTemplate.php';

class CleanFormRenderer
{
    public static function renderPage(string $pageId, string $role, bool $lockRecords = false): string
    {
        $role = Permissions::normalizeRole($role);
        $path = __DIR__ . '/../forms/clean/' . $pageId . '.php';
        if (!is_file($path)) {
            // Fallback: paper content inside a single clean step card
            $paper = PaperFormRenderer::renderPage($pageId, $role, $lockRecords);
            return '<div class="clean-guided" data-page-id="' . htmlspecialchars($pageId) . '">'
                . '<div class="clean-step-rail" aria-hidden="true"></div>'
                . '<div class="clean-step is-active" data-clean-step="0">'
                . '<h2 class="clean-step-title">' . htmlspecialchars($pageId) . '</h2>'
                . '<p class="clean-step-help">Same official fields — cleaner encoding. Print stays official paper.</p>'
                . '<div class="clean-fallback-paper">' . $paper . '</div>'
                . '</div></div>';
        }

        ob_start();
        $fg = 'it';
        include $path;
        $html = ob_get_clean();
        if (FormTemplate::isInlineEdit()) {
            $html = FormTemplate::augmentInlineEditableHtml($html, $pageId);
        } else {
            $html = FormTemplate::applyStaticTemplateCopy($html, $pageId);
        }
        return $html;
    }
}
