<?php

namespace EventTicketsElementor\Email;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sanitizes custom email templates saved in plugin settings.
 */
class Email_Template_Sanitizer
{
    public static function sanitize_html(string $html): string
    {
        $html = (string) $html;
        if ('' === trim($html)) {
            return '';
        }

        $allowed = [
            'html'   => ['lang' => true, 'dir' => true],
            'head'   => [],
            'body'   => ['style' => true, 'class' => true],
            'meta'   => ['charset' => true, 'name' => true, 'content' => true, 'http-equiv' => true],
            'title'  => [],
            'style'  => ['type' => true, 'media' => true],
            'a'      => ['href' => true, 'title' => true, 'target' => true, 'rel' => true, 'style' => true],
            'br'     => [],
            'div'    => ['style' => true, 'class' => true],
            'span'   => ['style' => true, 'class' => true],
            'p'      => ['style' => true, 'class' => true],
            'strong' => ['style' => true, 'class' => true],
            'b'      => ['style' => true, 'class' => true],
            'em'     => ['style' => true, 'class' => true],
            'i'      => ['style' => true, 'class' => true],
            'ul'     => ['style' => true, 'class' => true],
            'ol'     => ['style' => true, 'class' => true],
            'li'     => ['style' => true, 'class' => true],
            'table'  => ['style' => true, 'class' => true, 'cellpadding' => true, 'cellspacing' => true, 'border' => true, 'width' => true, 'role' => true, 'align' => true],
            'thead'  => ['style' => true, 'class' => true],
            'tbody'  => ['style' => true, 'class' => true],
            'tr'     => ['style' => true, 'class' => true],
            'td'     => ['style' => true, 'class' => true, 'colspan' => true, 'rowspan' => true, 'width' => true, 'align' => true, 'valign' => true],
            'th'     => ['style' => true, 'class' => true, 'colspan' => true, 'rowspan' => true, 'width' => true, 'align' => true, 'valign' => true],
            'img'    => ['src' => true, 'alt' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true],
            'h1'     => ['style' => true, 'class' => true],
            'h2'     => ['style' => true, 'class' => true],
            'h3'     => ['style' => true, 'class' => true],
            'h4'     => ['style' => true, 'class' => true],
        ];

        return wp_kses($html, $allowed);
    }
}
