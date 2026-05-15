<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class PublicHtmlSanitizer
{
    private const ROOT_ID = '__public_html_sanitizer_root__';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'code',
        'div',
        'em',
        'figcaption',
        'figure',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'hr',
        'i',
        'img',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'section',
        'span',
        'strong',
        'table',
        'tbody',
        'td',
        'th',
        'thead',
        'tr',
        'u',
        'ul',
    ];

    /**
     * @var array<int, string>
     */
    private const DROP_WITH_CONTENT_TAGS = [
        'applet',
        'base',
        'embed',
        'form',
        'frame',
        'frameset',
        'iframe',
        'input',
        'link',
        'meta',
        'object',
        'script',
        'select',
        'style',
        'textarea',
    ];

    /**
     * @var array<int, string>
     */
    private const GLOBAL_ATTRIBUTES = [
        'aria-hidden',
        'aria-label',
        'class',
        'dir',
        'lang',
        'role',
        'title',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'rel', 'target'],
        'img' => ['alt', 'height', 'loading', 'src', 'width'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
    ];

    public function sanitize(?string $html): string
    {
        $value = trim((string) $html);
        if ($value === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?>' . sprintf('<div id="%s">%s</div>', self::ROOT_ID, $value),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $root = $this->findRootNode($document);
        $this->sanitizeChildren($root);

        return trim($this->innerHtml($root));
    }

    private function findRootNode(DOMDocument $document): DOMElement
    {
        $xpath = new DOMXPath($document);
        $nodes = $xpath->query(sprintf('//*[@id="%s"]', self::ROOT_ID));

        if ($nodes !== false && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
            return $nodes->item(0);
        }

        return $document->documentElement;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null; $node = $next) {
            $next = $node->nextSibling;

            if ($node instanceof DOMComment) {
                $parent->removeChild($node);
                continue;
            }

            if (!$node instanceof DOMElement) {
                continue;
            }

            $tagName = strtolower($node->tagName);

            if (in_array($tagName, self::DROP_WITH_CONTENT_TAGS, true)) {
                $parent->removeChild($node);
                continue;
            }

            if (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                $this->unwrapElement($node);
                continue;
            }

            $this->sanitizeAttributes($node, $tagName);
            $this->sanitizeChildren($node);

            if ($tagName === 'img' && !$node->hasAttribute('src')) {
                $parent->removeChild($node);
            }
        }
    }

    private function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $allowedAttributes = array_merge(
            self::GLOBAL_ATTRIBUTES,
            self::TAG_ATTRIBUTES[$tagName] ?? []
        );

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim((string) $attribute->value);

            if (str_starts_with($name, 'on') || !in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'href') {
                $sanitizedHref = $this->sanitizeUrl($value, ['http', 'https', 'mailto', 'tel']);
                if ($sanitizedHref === null) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }

                $element->setAttribute('href', $sanitizedHref);
                continue;
            }

            if ($name === 'src') {
                $sanitizedSrc = $this->sanitizeUrl($value, ['http', 'https']);
                if ($sanitizedSrc === null) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }

                $element->setAttribute('src', $sanitizedSrc);
                continue;
            }

            if ($name === 'target') {
                $normalizedTarget = strtolower($value);
                if (!in_array($normalizedTarget, ['_blank', '_self', '_parent', '_top'], true)) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }

                $element->setAttribute('target', $normalizedTarget);
                if ($normalizedTarget === '_blank') {
                    $existingRel = trim((string) $element->getAttribute('rel'));
                    $relValues = array_filter(array_unique(array_merge(
                        preg_split('/\s+/', $existingRel) ?: [],
                        ['noopener', 'noreferrer']
                    )));

                    $element->setAttribute('rel', implode(' ', $relValues));
                }

                continue;
            }

            if ($name === 'rel') {
                $relValues = array_filter(array_map(
                    fn (string $token): string => strtolower(trim($token)),
                    preg_split('/\s+/', $value) ?: []
                ));

                $safeRelValues = array_values(array_intersect($relValues, [
                    'nofollow',
                    'noopener',
                    'noreferrer',
                    'ugc',
                ]));

                if ($safeRelValues === []) {
                    $element->removeAttribute($attribute->name);
                    continue;
                }

                $element->setAttribute('rel', implode(' ', array_unique($safeRelValues)));
                continue;
            }

            $element->setAttribute($attribute->name, $value);
        }
    }

    /**
     * @param array<int, string> $allowedSchemes
     */
    private function sanitizeUrl(string $value, array $allowedSchemes): ?string
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $trimmed = trim($decoded);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $trimmed) === 1) {
            return null;
        }

        $collapsed = strtolower((string) preg_replace('/\s+/', '', $trimmed));
        foreach (['javascript:', 'vbscript:', 'data:'] as $blockedScheme) {
            if (str_starts_with($collapsed, $blockedScheme)) {
                return null;
            }
        }

        if (
            str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '/')
            || str_starts_with($trimmed, './')
            || str_starts_with($trimmed, '../')
        ) {
            return $trimmed;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if ($scheme === null) {
            return $trimmed;
        }

        return in_array(strtolower($scheme), $allowedSchemes, true) ? $trimmed : null;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $node->ownerDocument?->saveHTML($childNode) ?? '';
        }

        return $html;
    }
}
