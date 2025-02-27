<?php

namespace Castlegate\Static404;

use WP_Error;

class Plugin
{
    /**
     * Default HTTP request timeout in seconds
     *
     * @var int
     */
    private const HTTP_TIMEOUT = 5;

    /**
     * HTTP header name used to identify a cache request
     *
     * @var int
     */
    private const HTTP_HEADER_NAME = 'Cgit-404-Cache-Request';

    /**
     * Default Filename for the cached 404 page
     *
     * @var string
     */
    private const FILENAME_404 = 'static-404.html';

    /**
     * Cron job name
     *
     * @var string
     */
    private const WP_CRON_NAME = 'cgit_static_404_recache';

    /**
     * Marker to be used in .htaccess file editing
     *
     * @var string
     */
    private const HTACCESS_MARKER = 'CgitStatic404blog';

    /**
     * Default WordPress actions that should trigger a re-cache of the 404 page
     *
     * @var array
     */
    private const RECACHE_ACTIONS = [
        'trashed_post',
        'untrash_post',
        'delete_post',
        'edit_post',
        'publish_page',
        'publish_post',
        'save_post',
        'publish_future_post',
        'save_post',
        'add_attachment',
        'delete_attachment',
        'edit_attachment',
        'add_category',
        'edit_category',
        'delete_category',
        'created_term',
        'added_term_relationship',
        'edited_terms',
        'edited_term_taxonomy',
        'deleted_term_taxonomy',
        'deleted_term_relationships',
        'deleted_taxonomy',
        'comment_post',
        'deleted_comment',
        'trashed_comment',
        'untrashed_comment',
        'spammed_comment',
        'unspammed_comment',
        'add_link',
        'delete_link',
        'edit_link',
        'activated_plugin',
        'deactivated_plugin',
        'after_switch_theme',
    ];

    /**
     * Requests for the following file extensions will be served the static
     * 404 page when they do not exist
     *
     * @return void
     */
    private const MATCHING_FILE_EXTENSIONS = [
        'asf',
        'asx',
        'avi',
        'bmp',
        'class',
        'css',
        'divx',
        'doc',
        'docx',
        'env',
        'exe',
        'gif',
        'gz',
        'gzip',
        'htm',
        'html',
        'htaccess',
        'ico',
        'jpe',
        'jpeg',
        'jpg',
        'js',
        'json',
        'midi',
        'mid',
        'm4a',
        'm4v',
        'mdb',
        'mov',
        'mp3',
        'mpeg',
        'mpg',
        'mpe',
        'mp4',
        'mpp',
        'odc',
        'odb',
        'odf',
        'odg',
        'odp',
        'ods',
        'odt',
        'ogg',
        'pdf',
        'png',
        'pot',
        'pps',
        'ppt',
        'pptx',
        'qt',
        'ra',
        'ram',
        'rtf',
        'rtx',
        'svg',
        'svgz',
        'swf',
        'tar',
        'tif',
        'tiff',
        'txt',
        'wav',
        'webm',
        'webmanifest',
        'webp',
        'wax',
        'wmv',
        'wmx',
        'wma',
        'wri',
        'xls',
        'xlsx',
        'xla',
        'xlt',
        'xlw',
        'xml',
        'xsd',
        'xsl',
        'yaml',
        'zip',
        'php',
        'woff',
        'woff2',
    ];

    /**
     * Initialise the plugin
     *
     * @return void
     */
    public static function init(): void
    {
        // Define the action to cache the 404 page
        add_action(
            'cgit_cache_404',
            [get_called_class(), 'perform404Cache']
        );

        if (isset($_GET['cache'])) {
            self::perform404Cache();
        }
        // Run cache action on activation
        register_activation_hook(
            CGIT_STATIC_404_PLUGIN_FILE,
            [get_called_class(), 'perform404Cache']
        );

        // Register re-cache actions
        self::performReCacheActionRegistration();

        // Enable htaccess rules
        register_activation_hook(
            CGIT_STATIC_404_PLUGIN_FILE,
            [get_called_class(), 'maybeInstallHtaccess']
        );

        // Handle loading of 404 on 404 requests
        add_action(
            'set_404',
            [get_called_class(), 'perform404PageServing']
        );
    }

    /**
     * Register various actions that should trigger a re-cache of the static 404
     * page
     *
     * @return void
     */
    public static function performReCacheActionRegistration(): void
    {
        foreach (self::getReCacheActions() as $action) {
            add_action($action, function() use ($action) {
                // Do not trigger on auto-saves
                if (self::isAutoSaveRequest()) {
                    return;
                }

                // Check if the event is already scheduled
                if (wp_next_scheduled( self::WP_CRON_NAME) ) {
                    return;
                }

                // Schedule the event for 30 seconds from now
                wp_schedule_single_event(
                    time() + 30, 'cgit_cache_404'
                );
            });
        }
    }

    /**
     * Attempt to cache the 404 page, verify its contents and adjust the
     * .htaccess rules to enable it
     *
     * @return void
     */
    public static function perform404Cache(): void
    {
        // Request the 404 page
        $response = self::performRequest404Page();

        // Verify response
        if (!self::isValid404Response($response)) {
            return;
        }

        // Write to cache
        self::performCache404Page($response);
    }

    /**
     * Request the site's 404 page and return the response string
     *
     * @return array|WP_Error
     */
    private static function performRequest404Page(): WP_Error|array
    {
        return wp_remote_get(
            self::get404RequestUrl(),
            [
                'timeout' => self::getHttpRequestTimeout(),
            ]
        );
    }

    /**
     * Cache the 404 page response to a file
     *
     * @param array $response
     * @return void
     */
    private static function performCache404Page(array $response): void
    {
        $contents = wp_remote_retrieve_body($response);

        $filtered_contents = apply_filters(
            'cgit_cache_404/response_contents',
            $contents
        );

        $file_path = self::get404FilePath();

        // Write to file
        $file = fopen($file_path, "w");

        fwrite($file, $filtered_contents);
        fclose($file);
    }

    /**
     * If we've cached the 404 page and the current request is not a feed
     * request, then send the 404 page contents
     *
     * @param $query
     * @return void
     */
    public static function perform404PageServing($query)
    {
        // Don't serve the static 404 if we're doing a 404 cache request
        if (self::is404CacheRequest()) {
            return;
        }

        if (self::isCached() && !$query->is_feed) {
            status_header(404);
            echo file_get_contents(self::get404FilePath());
            exit;
        }
    }

    /**
     * Write htaccess rules file
     *
     * @return bool
     */
    private static function performHtaccessInstall(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $rules_to_install = self::getHtaccessRules();

        $file = file_get_contents(ABSPATH . '.htaccess');

        $content = $rules_to_install."\n".$file;

        return file_put_contents(ABSPATH . '.htaccess', $content);
    }

    /**
     * Install the htaccess rules if not already installed
     *
     * @return void
     */
    public static function maybeInstallHtaccess(): void
    {
        // Don't install the rules if the 404 is not cached or is already
        // installed
        if (!self::isCached() || self::isHtaccessInstalled()) {
            return;
        }

        self::performHtaccessInstall();
    }

    /**
     * Read lines from htaccess and see if it already contains our rules or not
     *
     * @return bool
     */
    private static function isHtaccessInstalled(): bool
    {
        require_once ABSPATH . 'wp-admin/includes/misc.php';

        $rules = extract_from_markers(ABSPATH . '.htaccess', self::HTACCESS_MARKER . get_current_blog_id());

        // Note this check would fail if the static file name is filtered
        return (bool) strpos(self::getHtaccessRules(), implode("\n", $rules));
    }

    /**
     * Verify that we received a response and that it was indeed a 404 response
     *
     * @param $response
     * @return bool
     */
    private static function isValid404Response($response): bool
    {
        // Check for errors
        if (!is_array($response) || is_wp_error($response)) {
            return false;
        }

        // Check for 404 response
        if (404 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        // Check for some content in the body
        if (empty(wp_remote_retrieve_body($response))) {
            return false;
        }

        return true;
    }

    /**
     * Check if the current request is an auto-save
     *
     * @return bool
     */
    private static function isAutoSaveRequest(): bool
    {
        return defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
    }

    /**
     * Check if the 404 page is already cached
     *
     * @return bool
     */
    private static function isCached(): bool
    {
        return is_file(self::get404FilePath());
    }

    /**
     * Check if the current request is a request for the uncached 404 page
     *
     * @return bool
     */
    private static function is404CacheRequest(): bool
    {
        $current_url = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://";
        $current_url.= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

        if ($current_url === self::get404RequestUrl()) {
            return true;
        }

        return false;
    }

    /**
     * Return the htaccess rules to serve the static 404 when requests are made
     * to missing static files
     *
     * @return string
     */
    private static function getHtaccessRules(): string
    {
        $extensions = self::get404FileExtensions();
        $base_url = parse_url(get_home_url(), PHP_URL_PATH) ?? '/';

        $rules = [
            '# BEGIN '.self::HTACCESS_MARKER  . get_current_blog_id(),
            '# These directives are dynamically generated by the Castlegate IT Static 404 Page plugin.',
            '# Changes to these lines may be overwritten by the plugin. Modifications can be',
            '# made via the plugin\'s filters. Please see the README.md for more information',
            '<IfModule mod_rewrite.c>',
            'ErrorDocument 404 '.self::get404Url(),
            'RewriteEngine On',
            'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
            'RewriteBase /',

            'RewriteCond %{REQUEST_URI} ^' . $base_url . ' [NC]',
            'RewriteCond %{REQUEST_FILENAME} !-f',
            'RewriteCond %{REQUEST_FILENAME} !-d',
            'RewriteCond %{REQUEST_FILENAME} \.('.implode('|', $extensions).')$ [NC]',
            'RewriteRule .* - [L,END]',
            '</IfModule>',
            '# END '.self::HTACCESS_MARKER  . get_current_blog_id(),
        ];

        return implode("\n", $rules)."\n";
    }

    /**
     * Return the filtered HTTP request timeout
     *
     * @return int
     */
    private static function getHttpRequestTimeout(): int
    {
        return apply_filters(
            'cgit_cache_404/http_timeout',
            self::HTTP_TIMEOUT
        );
    }

    /**
     * Return the filtered 404 page file name
     *
     * @return string
     */
    private static function get404FileName(): string
    {
        return apply_filters(
            'cgit_cache_404/404_file_name',
            self::FILENAME_404
        );
    }

    /**
     * Return the filtered re-cache actions
     *
     * @return array
     */
    private static function getReCacheActions(): array
    {
        return apply_filters(
            'cgit_cache_404/recache_actions',
            self::RECACHE_ACTIONS
        );
    }

    /**
     * Return the filtered 404 request URL
     *
     * @return string
     */
    private static function get404RequestUrl(): string
    {
        // Generate a random path to request
        $request_uri = 'cache-404-request-';
        $request_uri.= substr(sha1(CGIT_STATIC_404_NAME), 0, 12);

        // Create the request URL
        $request_url = get_home_url(null, $request_uri);

        return apply_filters(
            'cgit_cache_404/request_url',
            $request_url
        );
    }


    /**
     * Return the filtered file path for the cached 404 page
     *
     * @return string
     */
    private static function get404FilePath(): string
    {
        $uploads_settings = wp_upload_dir();
        $upload_dir = $uploads_settings['basedir'];

        return (string) apply_filters(
            'cgit_cache_404/404_file_path',
            $upload_dir.'/'.self::get404FileName()
        );
    }

    /**
     * Return the URL path for the cached 404 page
     *
     * @return string
     */
    private static function get404Url(): string
    {
        $uploads_settings = wp_upload_dir();
        $upload_url = wp_make_link_relative($uploads_settings['baseurl']);

        return (string) apply_filters(
            'cgit_cache_404/404_url',
            $upload_url.'/'.self::get404FileName()
        );
    }

    /**
     * Get filtered file extensions to be handled by the static 404 handler
     *
     * @return array
     */
    private static function get404FileExtensions(): array
    {
        return (array) apply_filters(
            'cgit_cache_404/404_file_extensions',
            self::MATCHING_FILE_EXTENSIONS
        );
    }
}