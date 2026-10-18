<?php

namespace App\Support\Email;

/**
 * Readable plain-text alternative for HTML emails. Spam filters score HTML-only
 * mail (or a text part that is one run-on line of tag soup) as suspicious.
 */
final class HtmlToText
{
    public static function convert(string $html): string
    {
        $text = preg_replace('#<(head|style|script|title)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        $text = preg_replace_callback(
            '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $label = trim(strip_tags($m[2]));
                $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_starts_with($href, 'mailto:')) {
                    $href = substr($href, 7);
                }

                return ($label === '' || $label === $href) ? $href : $label.' ('.$href.')';
            },
            $text
        ) ?? $text;

        $text = preg_replace('#<img\b[^>]*>#i', '', $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|h[1-6]|tr|li|table)>#i', "\n\n", $text) ?? $text;
        $text = preg_replace('#<li\b[^>]*>#i', '- ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = array_map(static fn (string $line) => trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? $line), explode("\n", $text));
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
