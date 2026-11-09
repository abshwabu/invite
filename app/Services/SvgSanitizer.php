<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

class SvgSanitizer
{
    public const ALLOWED_TAGS = [
        'svg',
        'g',
        'path',
        'circle',
        'ellipse',
        'rect',
        'line',
        'polyline',
        'polygon',
        'defs',
        'lineargradient',
        'radialgradient',
        'stop',
        'clippath',
    ];

    public const FORBIDDEN_STRINGS = [
        '<script',
        '</script',
        '<foreignobject',
        '<image',
        'javascript:',
        'data:',
        'vbscript:',
    ];

    /**
     * Sanitize SVG content or throw InvalidArgumentException if hostile.
     *
     * @throws InvalidArgumentException
     */
    public static function sanitize(string $svg): string
    {
        $raw = trim($svg);
        if ($raw === '') {
            throw new InvalidArgumentException('Empty SVG content.');
        }

        $lower = strtolower($raw);

        // 1. Fast reject on forbidden tokens
        foreach (self::FORBIDDEN_STRINGS as $token) {
            if (str_contains($lower, $token)) {
                throw new InvalidArgumentException("Hostile SVG token detected: '{$token}'");
            }
        }

        // 2. Reject style tags or attributes with url()
        if (preg_match('/url\s*\(/i', $raw)) {
            throw new InvalidArgumentException('External url() references in SVG styles are prohibited.');
        }

        // 3. Reject any on* event handlers (e.g. onload, onclick, onerror)
        if (preg_match('/\son[a-z]+\s*=/i', $raw)) {
            throw new InvalidArgumentException('Inline event handlers (on*) are prohibited in SVG.');
        }

        // 4. Reject use tag with external href
        if (preg_match('/<use\b[^>]*(href|xlink:href)\s*=\s*["\'](https?:\/\/|\/\/)[^"\']*["\']/i', $raw)) {
            throw new InvalidArgumentException('External <use> references are prohibited.');
        }

        // 5. XML DOM verification
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $previousSetting = libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        $loaded = $dom->loadXML($raw, LIBXML_NONET | LIBXML_NOENT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if (! $loaded) {
            throw new InvalidArgumentException('Malformed XML SVG: ' . ($errors[0]->message ?? 'Parsing error'));
        }

        // Check root is svg
        $root = $dom->documentElement;
        if (! $root || strtolower($root->nodeName) !== 'svg') {
            throw new InvalidArgumentException('Root element of SVG must be <svg>.');
        }

        // Traverse all elements and verify whitelist
        self::validateNode($root);

        return $dom->saveXML($root);
    }

    /**
     * Check if SVG is valid and clean without throwing.
     */
    public static function isValid(string $svg): bool
    {
        try {
            self::sanitize($svg);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function validateNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_ELEMENT_NODE) {
            $tagName = strtolower($node->nodeName);
            if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                throw new InvalidArgumentException("SVG element '<{$tagName}>' is not permitted.");
            }

            /** @var DOMElement $node */
            if ($node->hasAttributes()) {
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $attrName = strtolower($attr->nodeName);
                    $attrValue = strtolower($attr->nodeValue);

                    if (str_starts_with($attrName, 'on')) {
                        throw new InvalidArgumentException("Hostile attribute '{$attrName}' detected.");
                    }

                    if (str_contains($attrValue, 'javascript:') || str_contains($attrValue, 'data:')) {
                        throw new InvalidArgumentException("Hostile URI detected in attribute '{$attrName}'.");
                    }

                    if ($attrName === 'style' && str_contains($attrValue, 'url(')) {
                        throw new InvalidArgumentException("External url() in style attribute is prohibited.");
                    }

                    if (($attrName === 'href' || $attrName === 'xlink:href') && (str_starts_with($attrValue, 'http://') || str_starts_with($attrValue, 'https://') || str_starts_with($attrValue, '//'))) {
                        throw new InvalidArgumentException("External link '{$attrValue}' in '{$attrName}' is prohibited.");
                    }
                }
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                self::validateNode($child);
            }
        }
    }
}
