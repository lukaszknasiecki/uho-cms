<?php

use Huncwot\UhoFramework\_uho_view;

/**
 * Main view class for the application.
 *
 * Extends the UHO framework's base view class to provide custom HTML rendering
 * with support for template tags and icon shortcodes.
 */
class view_app extends _uho_view
{
    /**
     * Extract content between custom block tags from HTML.
     *
     * Finds and extracts content wrapped in [[id]]...[[/id]] tags (inside <p> elements).
     * Returns the HTML split into three parts: before, inside, and after the block.
     *
     * @param string $html The HTML string to search in.
     * @param string $id   The tag identifier to look for.
     *
     * @return array|null Array with [before, content, after] or null if not found.
     */
    public function cut(string $html, string $id): ?array
    {
        $openTag = '<p>[[' . $id . ']]</p>';
        $closeTag = '<p>[[/' . $id . ']]</p>';

        $startPos = strpos($html, $openTag);
        $endPos = strpos($html, $closeTag, $startPos);

        if ($startPos === false || $endPos <= $startPos) {
            return null;
        }

        $openTagLength = strlen($openTag);
        $closeTagLength = strlen($closeTag);

        return [
            substr($html, 0, $startPos),
            substr($html, $startPos + $openTagLength, $endPos - $startPos - $openTagLength),
            substr($html, $endPos + $closeTagLength)
        ];
    }

    /**
     * Render the final HTML output.
     *
     * Processes the view data through Twig templates and handles custom
     * [[icon::name]] shortcodes, converting them to Material Design Icon spans.
     *
     * @param array $data View data containing 'content' and 'view' keys.
     *
     * @return string The rendered HTML string.
     */
    public function getHtml($data)
    {
        $this->setTemplatePrefix('view_app_');
        $data['content'] = $this->getContentHtml($data['content'], $data['view']);

        // Render full page with layout or content only based on render mode
        if ($this->getRenderHtmlRoot()) {
            $html = $this->getTwig('', $data);
        } else {
            $html = $data['content'];
        }

        // Process [[icon::name]] and [[icon::name,left]] shortcodes
        $html = $this->processIconShortcodes($html);

        return $html;
    }

    /**
     * Replace [[icon::name]] shortcodes with Material Design Icon markup.
     *
     * Supports optional alignment: [[icon::name,left]] adds pull-left class.
     * Some icon names are aliased for convenience (e.g., 'back' -> 'keyboard-backspace').
     *
     * @param string $html The HTML containing icon shortcodes.
     *
     * @return string The HTML with shortcodes replaced by <span> elements.
     */
    private function processIconShortcodes(string $html): string
    {
        $iconAliases = [
            'back' => 'keyboard-backspace',
            'eye'  => 'remove-red-eye'
        ];

        $maxIterations = 100;

        while (strpos($html, '[[icon::') !== false && $maxIterations > 0) {
            $startPos = strpos($html, '[[icon::');
            $endPos = strpos($html, ']]', $startPos);

            if ($endPos <= $startPos) {
                break;
            }

            $iconDef = substr($html, $startPos + 8, $endPos - $startPos - 8);
            $parts = explode(',', $iconDef);

            $iconName = $parts[0];
            $alignClass = ($parts[1] ?? '') === 'left' ? ' pull-left' : '';

            if (isset($iconAliases[$iconName])) {
                $iconName = $iconAliases[$iconName];
            }

            $iconHtml = '<span class="mdi mdi-' . $iconName . $alignClass . '"></span>';
            $html = substr($html, 0, $startPos) . $iconHtml . substr($html, $endPos + 2);

            $maxIterations--;
        }

        return $html;
    }
}
