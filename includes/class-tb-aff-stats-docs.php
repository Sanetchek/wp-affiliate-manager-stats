<?php
/**
 * Developer documentation admin page + Plugins screen link.
 *
 * @package WPAM_Affiliate_Stats
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class TB_Aff_Stats_Docs
{
    private const MENU_SLUG = 'wpam-aff-stats-docs';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 60);
        add_filter(
            'plugin_action_links_' . plugin_basename(TB_AFF_STATS_PLUGIN_FILE),
            [self::class, 'plugin_action_links']
        );
        add_filter(
            'network_admin_plugin_action_links_' . plugin_basename(TB_AFF_STATS_PLUGIN_FILE),
            [self::class, 'plugin_action_links']
        );
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public static function plugin_action_links(array $links): array
    {
        $url = self::docs_url();
        $links['documentation'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Documentation', 'wpam-aff-stats')
        );

        return $links;
    }

    public static function docs_url(): string
    {
        return admin_url('admin.php?page=' . self::MENU_SLUG);
    }

    public static function register_menu(): void
    {
        $cap = 'manage_options';
        if (!current_user_can('manage_options') && current_user_can('wpam_admin')) {
            $cap = 'wpam_admin';
        } elseif (!current_user_can('manage_options') && current_user_can('activate_plugins')) {
            $cap = 'activate_plugins';
        } elseif (!current_user_can('manage_options')) {
            return;
        }

        $page_title = __('Developer documentation', 'wpam-aff-stats');
        $menu_title = __('WPAM Stats Docs', 'wpam-aff-stats');

        if (defined('WPAM_VERSION') || class_exists('WPAM_Plugin', false)) {
            add_submenu_page(
                'wpam-affiliates',
                $page_title,
                $menu_title,
                $cap,
                self::MENU_SLUG,
                [self::class, 'render_page']
            );

            return;
        }

        add_management_page(
            __('WP Affiliate Manager Stats — Docs', 'wpam-aff-stats'),
            $menu_title,
            $cap,
            self::MENU_SLUG,
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options') && !current_user_can('wpam_admin') && !current_user_can('activate_plugins')) {
            wp_die(esc_html__('You do not have permission to view this page.', 'wpam-aff-stats'));
        }

        $path = TB_AFF_STATS_PLUGIN_DIR . 'docs/developers.md';
        $markdown = is_readable($path) ? (string) file_get_contents($path) : '';
        $html = $markdown !== ''
            ? self::markdown_to_html($markdown)
            : '<p>' . esc_html__('Documentation file is missing.', 'wpam-aff-stats') . '</p>';

        $github = 'https://github.com/Sanetchek/wp-affiliate-manager-stats';

        echo '<div class="wrap tb-aff-stats-docs">';
        echo '<h1>' . esc_html__('WP Affiliate Manager Stats — Developer documentation', 'wpam-aff-stats') . '</h1>';
        echo '<p class="description">';
        echo esc_html__('Integration guide for themes and custom checkouts.', 'wpam-aff-stats');
        echo ' <a href="' . esc_url($github) . '" target="_blank" rel="noopener noreferrer">';
        echo esc_html__('GitHub repository', 'wpam-aff-stats');
        echo '</a></p>';
        echo '<div class="tb-aff-stats-docs__body card" style="max-width:960px;padding:16px 20px;margin-top:12px;">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from plugin-owned markdown via esc_* in converter.
        echo $html;
        echo '</div></div>';

        echo '<style>
            .tb-aff-stats-docs__body h1{font-size:1.6em;margin:1.2em 0 .5em}
            .tb-aff-stats-docs__body h2{font-size:1.3em;margin:1.4em 0 .4em;border-bottom:1px solid #dcdcde;padding-bottom:4px}
            .tb-aff-stats-docs__body h3{font-size:1.1em;margin:1.1em 0 .35em}
            .tb-aff-stats-docs__body pre{background:#1d2327;color:#f0f0f1;padding:12px 14px;overflow:auto;border-radius:4px}
            .tb-aff-stats-docs__body code{font-size:12px}
            .tb-aff-stats-docs__body :not(pre)>code{background:#f0f0f1;padding:2px 5px;border-radius:3px}
            .tb-aff-stats-docs__body table{border-collapse:collapse;width:100%;margin:10px 0 16px}
            .tb-aff-stats-docs__body th,.tb-aff-stats-docs__body td{border:1px solid #c3c4c7;padding:8px 10px;text-align:left}
            .tb-aff-stats-docs__body th{background:#f6f7f7}
            .tb-aff-stats-docs__body ul{list-style:disc;padding-left:1.4em}
        </style>';
    }

    /**
     * Minimal Markdown → HTML for plugin-owned docs (no external deps).
     */
    public static function markdown_to_html(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $parts = preg_split('/(```[\s\S]*?```)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return '<p>' . esc_html($markdown) . '</p>';
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '```') && str_ends_with($part, '```')) {
                $inner = preg_replace('/^```[a-zA-Z0-9_-]*\n?/', '', $part) ?? $part;
                $inner = preg_replace('/\n?```$/', '', $inner) ?? $inner;
                $html .= '<pre><code>' . esc_html(rtrim($inner)) . '</code></pre>';
                continue;
            }
            $html .= self::markdown_blocks_to_html($part);
        }

        return $html;
    }

    private static function markdown_blocks_to_html(string $text): string
    {
        $lines = explode("\n", $text);
        $out = '';
        $in_ul = false;
        $in_table = false;
        $table_buf = [];

        $flush_ul = static function () use (&$in_ul, &$out): void {
            if ($in_ul) {
                $out .= '</ul>';
                $in_ul = false;
            }
        };
        $flush_table = function () use (&$in_table, &$table_buf, &$out): void {
            if (!$in_table) {
                return;
            }
            $out .= self::table_to_html($table_buf);
            $table_buf = [];
            $in_table = false;
        };

        foreach ($lines as $line) {
            $trim = rtrim($line);

            if (preg_match('/^\|(.+)\|$/', $trim)) {
                $flush_ul();
                $in_table = true;
                $table_buf[] = $trim;
                continue;
            }
            $flush_table();

            if (preg_match('/^### (.+)$/', $trim, $m)) {
                $flush_ul();
                $out .= '<h3>' . self::inline($m[1]) . '</h3>';
                continue;
            }
            if (preg_match('/^## (.+)$/', $trim, $m)) {
                $flush_ul();
                $out .= '<h2>' . self::inline($m[1]) . '</h2>';
                continue;
            }
            if (preg_match('/^# (.+)$/', $trim, $m)) {
                $flush_ul();
                $out .= '<h1>' . self::inline($m[1]) . '</h1>';
                continue;
            }
            if (preg_match('/^[-*] (.+)$/', $trim, $m)) {
                if (!$in_ul) {
                    $out .= '<ul>';
                    $in_ul = true;
                }
                $out .= '<li>' . self::inline($m[1]) . '</li>';
                continue;
            }
            if ($trim === '') {
                $flush_ul();
                continue;
            }
            $flush_ul();
            $out .= '<p>' . self::inline($trim) . '</p>';
        }

        $flush_ul();
        $flush_table();

        return $out;
    }

    /**
     * @param list<string> $rows
     */
    private static function table_to_html(array $rows): string
    {
        $parsed = [];
        foreach ($rows as $row) {
            if (preg_match('/^\|\s*:?-{3,}/', $row)) {
                continue; // separator
            }
            $cells = array_map('trim', explode('|', trim($row, '|')));
            $parsed[] = $cells;
        }
        if ($parsed === []) {
            return '';
        }

        $html = '<table><thead><tr>';
        foreach ($parsed[0] as $cell) {
            $html .= '<th>' . self::inline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        for ($i = 1, $n = count($parsed); $i < $n; $i++) {
            $html .= '<tr>';
            foreach ($parsed[$i] as $cell) {
                $html .= '<td>' . self::inline($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    private static function inline(string $text): string
    {
        $text = esc_html($text);
        $text = preg_replace(
            '/\[([^\]]+)\]\((https?:\/\/[^)]+)\)/',
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>',
            $text
        ) ?? $text;
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;

        return $text;
    }
}
