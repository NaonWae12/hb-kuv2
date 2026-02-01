<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class HtmlService
{
    /**
     * Clean and sanitize HTML for storage and display.
     * Includes XSS protection and optimization.
     */
    public function sanitize($html): string
    {
        if (empty($html)) {
            return '';
        }

        try {
            // Use DOMDocument to parse and clean HTML
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);

            // Wrap in a container div to handle fragments
            $wrappedHtml = '<div>' . $html . '</div>';
            $dom->loadHTML('<?xml encoding="UTF-8">' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // 1. Remove dangerous elements and attributes (XSS Protection)
            $this->removeDangerousTags($dom);

            // 2. Remove empty tags recursively
            $this->removeEmptyNodes($dom);

            // 3. Flatten nested spans with same style attributes (run multiple times to handle deeply nested)
            for ($i = 0; $i < 5; $i++) {
                $this->flattenNestedSpans($dom);
            }

            // Get cleaned HTML from the wrapper div
            $wrapper = $dom->getElementsByTagName('div')->item(0);
            if ($wrapper) {
                $cleaned = '';
                foreach ($wrapper->childNodes as $child) {
                    $cleaned .= $dom->saveHTML($child);
                }
                return trim($cleaned);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to sanitize HTML', ['error' => $e->getMessage()]);
        }

        return $html;
    }

    /**
     * Remove dangerous tags and attributes for XSS protection.
     */
    private function removeDangerousTags(DOMDocument $dom): void
    {
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'style', 'base'];
        foreach ($dangerousTags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            while ($elements->length > 0) {
                $p = $elements->item(0);
                $p->parentNode->removeChild($p);
            }
        }

        $xpath = new DOMXPath($dom);
        
        // Remove on* attributes (onclick, onmouseover, etc)
        $nodes = $xpath->query('//@*[starts-with(name(), "on")]');
        foreach ($nodes as $node) {
            $node->parentNode->removeAttribute($node->nodeName);
        }

        // Remove javascript: and data: links in href/src
        $attributesToCheck = ['href', 'src', 'formaction'];
        foreach ($attributesToCheck as $attr) {
            $nodes = $xpath->query("//@{$attr}[starts-with(normalize-space(.), 'javascript:')] | //@{$attr}[starts-with(normalize-space(.), 'data:')]");
            foreach ($nodes as $node) {
                $node->parentNode->removeAttribute($node->nodeName);
            }
        }
    }

    /**
     * Flatten nested spans - remove unnecessary nesting.
     */
    public function flattenNestedSpans(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $spans = $xpath->query('//span');

        for ($i = $spans->length - 1; $i >= 0; $i--) {
            $span = $spans->item($i);
            if (!$span || !($span instanceof DOMElement) || !$span->parentNode) {
                continue;
            }

            $style = $span->getAttribute('style');
            $textContent = trim($span->textContent);

            // Remove empty spans
            if (empty($style) && empty($textContent)) {
                while ($span->firstChild) {
                    $span->parentNode->insertBefore($span->firstChild, $span);
                }
                $span->parentNode->removeChild($span);
                continue;
            }

            // Handle nested spans
            if ($span->parentNode instanceof DOMElement && $span->parentNode->nodeName === 'span') {
                $parentSpan = $span->parentNode;
                $parentStyle = $parentSpan->getAttribute('style');

                if ($parentStyle === $style) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->removeChild($span);
                } elseif (!empty($style) && !empty($parentStyle)) {
                    $mergedStyle = $this->mergeStyles($parentStyle, $style);
                    $span->setAttribute('style', $mergedStyle);
                    $parentSpan->parentNode->insertBefore($span, $parentSpan);
                    while ($parentSpan->firstChild) {
                        $span->parentNode->insertBefore($parentSpan->firstChild, $span->nextSibling);
                    }
                    $parentSpan->parentNode->removeChild($parentSpan);
                } elseif (empty($parentStyle)) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->parentNode->insertBefore($span, $parentSpan);
                    while ($parentSpan->firstChild) {
                        $span->parentNode->insertBefore($parentSpan->firstChild, $span->nextSibling);
                    }
                    $parentSpan->parentNode->removeChild($parentSpan);
                } elseif (empty($style) && !empty($parentStyle)) {
                    while ($span->firstChild) {
                        $parentSpan->insertBefore($span->firstChild, $span);
                    }
                    $parentSpan->removeChild($span);
                }
            }
        }
    }

    /**
     * Merge two CSS style strings, with inner style taking precedence.
     */
    public function mergeStyles(string $parentStyle, string $innerStyle): string
    {
        $parentStyles = $this->parseStyleString($parentStyle);
        $innerStyles = $this->parseStyleString($innerStyle);
        $merged = array_merge($parentStyles, $innerStyles);

        $styleParts = [];
        foreach ($merged as $property => $value) {
            $styleParts[] = $property . ': ' . $value;
        }

        return implode('; ', $styleParts);
    }

    /**
     * Parse CSS style string into an associative array.
     */
    private function parseStyleString(string $style): array
    {
        $styles = [];
        foreach (explode(';', $style) as $rule) {
            $rule = trim($rule);
            if (empty($rule)) continue;
            $parts = explode(':', $rule, 2);
            if (count($parts) === 2) {
                $styles[trim($parts[0])] = trim($parts[1]);
            }
        }
        return $styles;
    }

    /**
     * Recursively remove empty nodes from DOM.
     */
    public function removeEmptyNodes(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof DOMElement) {
                $this->removeEmptyNodes($child);

                $textContent = trim($child->textContent);
                if (empty($textContent) && $child->childNodes->length === 0) {
                    $child->parentNode->removeChild($child);
                }
            }
        }
    }

    /**
     * Helper method to strip HTML tags and normalize content.
     */
    public function stripHtml(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(['•', '·', '▪', '▫'], '-', $text);
        $text = str_replace("\t", ' ', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}]/u', '', $text);
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\xD7FF\xE000-\xFFFD]/u', '', $text);
        $text = trim($text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\r\n|\r/', "\n", $text);

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return $text;
    }
}
