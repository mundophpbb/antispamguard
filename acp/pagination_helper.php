<?php
/**
 * AntiSpam Guard — ACP pagination markup helper.
 *
 * @copyright (c) 2026 Mundophpbb
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\antispamguard\acp;

class pagination_helper
{
    public function build_page_number(\phpbb\user $user, $total_logs, $per_page, $start)
    {
        if ($total_logs <= 0)
        {
            return '';
        }

        $current_page = (int) floor($start / $per_page) + 1;
        $total_pages = (int) ceil($total_logs / $per_page);

        return $user->lang('PAGE_OF', $current_page, $total_pages);
    }

    public function build_pagination($base_url, $total_logs, $per_page, $start, $start_param = 'start')
    {
        if ($total_logs <= $per_page)
        {
            return '';
        }

        $total_pages = (int) ceil($total_logs / $per_page);
        $current_page = (int) floor($start / $per_page) + 1;
        $current_page = max(1, min($current_page, $total_pages));
        $links = array();
        $separator = (strpos($base_url, '?') === false) ? '?' : '&amp;';

        $make_url = function ($page) use ($base_url, $separator, $per_page, $start_param) {
            $page_start = ($page - 1) * $per_page;

            return $base_url . $separator . rawurlencode($start_param) . '=' . $page_start;
        };

        if ($current_page > 1)
        {
            $links[] = '<a class="asg-page-prev" href="' . $make_url($current_page - 1) . '">&lsaquo;</a>';
        }

        $pages = array(1, $total_pages, $current_page - 1, $current_page, $current_page + 1);

        if ($current_page <= 3)
        {
            $pages[] = 2;
            $pages[] = 3;
        }

        if ($current_page >= ($total_pages - 2))
        {
            $pages[] = $total_pages - 1;
            $pages[] = $total_pages - 2;
        }

        $pages = array_unique(array_filter($pages, function ($page) use ($total_pages) {
            return $page >= 1 && $page <= $total_pages;
        }));

        sort($pages);

        $previous_page = 0;

        foreach ($pages as $page)
        {
            if ($previous_page && $page > ($previous_page + 1))
            {
                $links[] = '<span class="asg-page-gap">&hellip;</span>';
            }

            if ($page === $current_page)
            {
                $links[] = '<span class="asg-page-current">' . $page . '</span>';
            }
            else
            {
                $links[] = '<a href="' . $make_url($page) . '">' . $page . '</a>';
            }

            $previous_page = $page;
        }

        if ($current_page < $total_pages)
        {
            $links[] = '<a class="asg-page-next" href="' . $make_url($current_page + 1) . '">&rsaquo;</a>';
        }

        return implode(' ', $links);
    }
}