<?php

namespace App\Rules;

use Closure;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMProcessingInstruction;
use Illuminate\Contracts\Validation\ValidationRule;
use RuntimeException;

/**
 * Allow-list HTML sanitiser for the operator-authored rich-text fields
 * (`popups.custom_html`, `pages.content`, `products.description`).
 *
 * composer.json contains no HTMLPurifier, DOMPurify or mews/purifier and no
 * dependency may be added, so this is a hand-rolled DOMDocument cleaner with
 * the same shape HTMLPurifier has: parse, keep only allow-listed elements and
 * attributes, drop everything else.
 *
 * The storefront views previously ran a regex block-list over these fields.
 * That shape is bypassable — every attribute pattern there required a quoted
 * value, so `<img src=x onerror=alert(1)>` and `href=javascript:alert(1)`
 * survived intact. An allow-list cannot be bypassed that way, because an
 * attribute that is not named in the list is removed whatever its value is.
 *
 * Sanitise at the request boundary (prepareForValidation), never in the view.
 * The views still call sanitize() on render so rows written before this rule
 * existed are neutralised too.
 */
final class SafeHtml implements ValidationRule
{
    /**
     * Long-form CMS content: pages.content, products.description.
     */
    public const PROFILE_CONTENT = 'content';

    /**
     * Popup bodies, which render inside a small modal.
     */
    public const PROFILE_POPUP = 'popup';

    /**
     * Attributes allowed on every permitted element.
     *
     * Deliberately absent: `style` (CSS injection — `position:fixed` overlays,
     * `background:url()` exfiltration), `id` and `name` (DOM clobbering of the
     * ids Alpine and the checkout forms look up), and every `on*` handler.
     *
     * @var list<string>
     */
    private const GLOBAL_ATTRIBUTES = ['class', 'title', 'dir', 'lang'];

    /**
     * Additional attributes allowed per element.
     *
     * @var array<string, list<string>>
     */
    private const ELEMENT_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'blockquote' => ['cite'],
        'col' => ['span', 'width'],
        'colgroup' => ['span', 'width'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'li' => ['value'],
        'ol' => ['start', 'type', 'reversed'],
        'table' => ['width', 'summary'],
        'td' => ['colspan', 'rowspan', 'align', 'valign'],
        'th' => ['colspan', 'rowspan', 'align', 'valign', 'scope'],
        'time' => ['datetime'],
    ];

    /**
     * Elements kept for the long-form CMS profile.
     *
     * @var list<string>
     */
    private const CONTENT_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'col',
        'colgroup', 'dd', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'h1',
        'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'mark', 'ol', 'p',
        'pre', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody',
        'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'u', 'ul',
    ];

    /**
     * Elements kept for popup bodies. Deliberately narrower: no table markup and
     * no heading above h4, because a popup renders inside a modal.
     *
     * @var list<string>
     */
    private const POPUP_TAGS = [
        'a', 'b', 'blockquote', 'br', 'div', 'em', 'h2', 'h3', 'h4', 'hr', 'i',
        'img', 'li', 'ol', 'p', 'small', 'span', 'strong', 'u', 'ul',
    ];

    /**
     * Elements removed together with everything inside them, because unwrapping
     * them would either leave their text behind as visible copy (script, style)
     * or smuggle the payload past the allow-list (svg, math, template).
     *
     * `html` and `body` are deliberately absent: libxml produces them around a
     * fragment that carries its own document structure, and removing them would
     * delete the operator's content along with the wrapper.
     *
     * @var list<string>
     */
    private const DROPPED_SUBTREES = [
        'applet', 'audio', 'base', 'basefont', 'button', 'canvas', 'embed',
        'fieldset', 'form', 'frame', 'frameset', 'head', 'iframe', 'input',
        'isindex', 'keygen', 'label', 'link', 'listing', 'map', 'marquee',
        'math', 'meta', 'noembed', 'noframes', 'noscript', 'object',
        'optgroup', 'option', 'plaintext', 'portal', 'script', 'select', 'slot',
        'style', 'svg', 'template', 'textarea', 'title', 'track', 'video', 'xmp',
    ];

    /**
     * URL schemes permitted in href/src/cite. Everything else — javascript:,
     * data:, vbscript: and any scheme smuggled past the decoder — is dropped.
     *
     * @var list<string>
     */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Attributes whose value is scheme checked.
     *
     * @var list<string>
     */
    private const URL_ATTRIBUTES = ['cite', 'href', 'src'];

    public function __construct(protected string $profile = self::PROFILE_CONTENT) {}

    /**
     * Fail closed if the value reaching the validator is not already sanitised.
     *
     * prepareForValidation() normalises the value before the validator is built,
     * so this only fires when that hook has been removed or bypassed. Failing
     * loudly is deliberate: silently rewriting here would be too late, because
     * Validator::validated() reads the snapshot taken when the validator was
     * constructed and would still hand the controller the raw string.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (self::sanitize($value, $this->profile) !== $value) {
            $fail('The :attribute field contains HTML that is not allowed. Script tags, event handler attributes and javascript: URLs are removed.');
        }
    }

    /**
     * Reduce arbitrary operator markup to the profile's allow-list.
     *
     * Idempotent: sanitize(sanitize($html)) === sanitize($html).
     */
    public static function sanitize(?string $html, string $profile = self::PROFILE_CONTENT): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (! class_exists(DOMDocument::class)) {
            throw new RuntimeException('ext-dom is required to sanitise rich HTML content.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;
        $document->formatOutput = false;

        $internalErrors = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<div>'.mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8').'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (! $loaded) {
            return '';
        }

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMNode) {
            return '';
        }

        self::cleanChildren($root, $profile);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim(mb_decode_numericentity($output, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'));
    }

    /**
     * @return list<string>
     */
    private static function allowedTags(string $profile): array
    {
        return $profile === self::PROFILE_POPUP ? self::POPUP_TAGS : self::CONTENT_TAGS;
    }

    /**
     * @return list<string>
     */
    private static function allowedAttributes(string $tag): array
    {
        return array_merge(self::GLOBAL_ATTRIBUTES, self::ELEMENT_ATTRIBUTES[$tag] ?? []);
    }

    private static function cleanChildren(DOMNode $parent, string $profile): void
    {
        $allowedTags = self::allowedTags($profile);

        // Snapshot: this loop removes and re-parents nodes as it goes.
        foreach (iterator_to_array($parent->childNodes, false) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $parent->removeChild($child);

                continue;
            }

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DROPPED_SUBTREES, true)) {
                    $parent->removeChild($child);

                    continue;
                }

                if (! in_array($tag, $allowedTags, true)) {
                    // Clean the subtree while it is still attached, then lift
                    // its children up: the snapshot this loop walks will not
                    // contain them once they have been re-parented.
                    self::cleanChildren($child, $profile);
                    self::unwrap($parent, $child);

                    continue;
                }

                self::cleanAttributes($child, $tag);
            }

            self::cleanChildren($child, $profile);
        }
    }

    /**
     * Replace a disallowed-but-harmless element with its own children, so
     * <marquee>Hello</marquee> keeps the copy instead of vanishing.
     */
    private static function unwrap(DOMNode $parent, DOMNode $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::allowedAttributes($tag);

        foreach (iterator_to_array($element->attributes, false) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl((string) $attribute->nodeValue)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /**
     * Only relative URLs and the schemes in ALLOWED_URL_SCHEMES survive.
     *
     * The comparison runs against a stripped copy — control characters and
     * whitespace removed, then decoded again — so `java\tscript:`,
     * ` javascript:` and `&#106;avascript:` all resolve to the same
     * `javascript:` the browser would execute. The original value is what gets
     * written back out, so a legitimate URL is not mangled.
     */
    private static function isSafeUrl(string $url): bool
    {
        $normalised = self::stripControlCharacters($url);
        $normalised = self::stripControlCharacters(html_entity_decode($normalised, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $normalised = strtolower($normalised);

        if ($normalised === '') {
            return false;
        }

        if (! preg_match('/^([a-z][a-z0-9+.\-]*):/', $normalised, $matches)) {
            return true;
        }

        return in_array($matches[1], self::ALLOWED_URL_SCHEMES, true);
    }

    private static function stripControlCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x20\x7F]+/', '', $value) ?? $value;
    }
}
