<?php
/*
Plugin Name: HolyPixels Broken Banisher
Plugin URI: https://github.com/ptrsmk/hp-broken-banisher
Description: Banish Broken (404) links from your database
Version: 1.0
Author: HolyPixels (ptrsmk)
Author URI: https://github.com/ptrsmk/
*/

if (!defined('YOURLS_ABSPATH')) {
    die();
}

/**
 * Check if a destination URL is broken (returns 404 status code).
 *
 * @param string $url The URL to check.
 * @return bool Returns false if the URL is broken (404), otherwise returns true.
 */
function check_broken_link_cURL($url) {
    $headers = get_headers($url, 1); // Fetch headers
    $http_code = substr($headers[0], 9, 3); // Extract the HTTP response code

    // Return HTTP code if it's 404, otherwise return false
    if ($http_code == 404) {
        return false; // The link is broken (404)
    } else {
        return true; // The link is not broken
    }
}

/**
 * API action to check and delete broken links.
 *
 * This function is triggered by the 'api_action_check_broken_links' filter.
 * It checks all links in the YOURLS database and deletes those that return a 404 status code.
 */
yourls_add_filter('api_action_check_broken_links', 'check_broken_links_api');
function check_broken_links_api() {
    // Only authorized users can perform this action
    $auth = yourls_is_valid_user();
    if ($auth !== true) {
        $format = isset($_REQUEST['format']) ? $_REQUEST['format'] : 'xml';
        $callback = isset($_REQUEST['callback']) ? $_REQUEST['callback'] : '';
        yourls_api_output($format, array(
            'simple' => $auth,
            'message' => $auth,
            'errorCode' => 403,
            'callback' => $callback,
        ));
        return;
    }

    // Prepare the list of links
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;

    // Domain restriction and pagination handling
    $domain = isset($_REQUEST['domain']) ? $_REQUEST['domain'] : '';
    $where = $domain ? "`url` LIKE '%$domain%'" : "1=1";
    $autoRedirect = isset($_REQUEST['autoredirect']) ? true : false;
    $page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
    $perpage = isset($_REQUEST['perpage']) ? intval($_REQUEST['perpage']) : 150;
    $offset = isset($_REQUEST['offset']) ? intval($_REQUEST['offset']) : ($page - 1) * $perpage;
    $sortby = 'timestamp';
    $sort_order = 'desc';

    // Fetch links from YOURLS database
    if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
        $sql = "SELECT * FROM `$table` WHERE $where ORDER BY `$sortby` $sort_order LIMIT $offset, $perpage";
        $links = $ydb->fetchObjects($sql);
    } else {
        $links = $ydb->get_results("SELECT * FROM `$table` WHERE $where ORDER BY `$sortby` $sort_order LIMIT $offset, $perpage;");
    }

    $final_page = count($links) < $perpage;

    // Check each link and delete broken ones
    if ($links) {
        $d = $s = $f = 0; // Counters for deleted, successful checks, and failed deletions
        foreach ($links as $link) {
            $url = $link->url;
            $is_valid = check_broken_link_cURL($url);

            // If the link is broken (404)
            if (!$is_valid) {
                $keyword = $link->keyword;
                if ($keyword) {
                    $delete = yourls_delete_link_by_keyword($keyword);
                    if ($delete) {
                        $d++; // Increment deleted counter
                    } else {
                        $f++; // Failed to delete
                    }
                }
            } else {
                $s++; // Successful check (not broken)
            }
        }

        $offset += $perpage - $d;
        $next_request_url = yourls_get_yourls_site() . "/yourls-api.php?action=check_broken_links&format=json&signature=$_REQUEST['signature']&offset=$offset";
        if (isset($_REQUEST['perpage'])) {
            $next_request_url .= "&perpage=$perpage";
        }
        $count = count($links);

        $return = array(
            'brokenLinksDeleted' => $d,
            'successfulChecks' => $s,
            'failedDeletions' => $f,
            'totalLinksChecked' => $count,
            'next_request_url' => $next_request_url,
        );

        if ($autoRedirect) {
            $title = "Banishment Complete";
            $httpequiv = "";
            $h1 = "All Broken Links Have Been Banished";
            $msg = "No more redirects. We've reached the end of the database.";
            if (!$final_page) {
                $next_request_url .= "&autoredirect=1";
                $title = "Redirecting...";
                $httpequiv = "refresh";
                $h1 = "You will be redirected in 10 seconds...";
                $msg = "If you are not redirected, <a href='$next_request_url'>click here</a>.";
            }
            echo <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta http-equiv="$httpequiv" content="10;url=$next_request_url">
                <title>$title</title>
            </head>
            <body>
                <h1>$h1</h1>
                <p>$msg</p>
                <p>
                    <ul>
                        <li>brokenLinksDeleted = $d</li>
                        <li>successfulChecks = $s</li>
                        <li>failedDeletions = $f</li>
                        <li>totalLinksChecked = $count</li>
                        <li>next_request_url = $next_request_url</li>
                    </ul>
                </p>
            </body>
            </html>
HTML;
            flush();
            die;
        }
    } else {
        $return = array(
            'error' => 'Database connection failure',
            'code' => 500,
        );
    }

    return $return;
}

/**
 * Index the URL table in YOURLS DB on plugin activation (optional).
 *
 * This function adds an index on the `url` column for better performance.
 */
yourls_add_action('activated_check_broken_links/plugin.php', 'check_broken_links_activated');
function check_broken_links_activated() {
    global $ydb;
    $table = YOURLS_DB_TABLE_URL;
    $index = 'idx_urls';

    // Add index on the `url` column for better performance
    if (version_compare(YOURLS_VERSION, '1.7.3') >= 0) {
        $binds = array('index' => $index);
        $sql = "SHOW INDEX FROM `$table` WHERE Key_name = :index";
        $query = $ydb->fetchAffected($sql, $binds);
        if ($query == null) {
            $sql = "ALTER TABLE `$table` ADD INDEX :index (`url`(30))";
            $query = $ydb->fetchAffected($sql, $binds);
        }
    } else {
        $query = $ydb->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index'");
        if ($query == null) {
            $query = $ydb->query("ALTER TABLE `$table` ADD INDEX `$index` (`url`(30))");
        }
    }

    if ($query === false) {
        echo "Unable to properly index URL table. Please see README";
    }
}
