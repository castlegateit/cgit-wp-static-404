# Castlegate IT WP Static 404

This plugin caches the WordPress 404 page and adjusts the default rewrite rules so that 404 requests can be served by a static HTML fil;e instead of a dynamically generated WordPress page.

This plugin handles page requests and file requests differently:
 - **Files:** Requests for common file extensions are handled by .htaccess rewrite rules. Without .htaccess support, this plugin will not be able to serve a static 404 page when a visitor requests a file that does not exist on your server. The default 404 handler is set in .htaccess and matching filenames are prevented from being routed via WordPress.
 - **Pages:** All other requests will be processed by WordPress. As soon as WordPress has detected that a 404 page should be served, the plugin includes the static 404 page and halts execution.

## Caching the 404 page

The 404 page caching process is triggered by many different WordPress actions, often many are triggered per request and within quick succession.

To avoid regenerating the static 404 page file often, the plugin will create a cron task (if one does not already exist) to perform the cache operation. This cron will cache the 404 page after 30 seconds have passed, meaning that the cache should only be stale for a maximum of 30 seconds.

If you're testing the plugin locally, the static 404 file will not generate unless your cron tasks are successfully executing. Typically this will mean that your development URL needs to resolve on the server, so it needs adding to your hosts file. 

Consider manually running outstanding cron jobs with `wp cron event run cgit_cache_404`. This will return "Invalid cron event" if a re-cache is not due.

## Filters

### 404 file contents

After the 404 page contents has been fetched, filter the contents to modify it prior to saving the static file.

~~~ php
add_filter('cgit_cache_404/response_contents', function($contents) {
    return 'My custom 404 content';
});
~~~

### HTTP request timeout

Adjust the HTTP request timeout for requests made to the 404 page when attempting to cache it.

~~~ php
add_filter('cgit_cache_404/http_timeout', function($timeout) {
    return 1;
});
~~~

### Static 404 file name

Filter the static 404 page file name.

~~~ php
add_filter('cgit_cache_404/404_file_name', function($filename) {
    return 'my-static-four-oh-four.html';
});
~~~

### Static 404 file path

Filter the static 404 page file path.

~~~ php
add_filter('cgit_cache_404/404_file_path', function($path) {
    return '/custom/file/path';
});
~~~

### Static 404 file URL

Filter the static 404 page URL used by the plugin as the ErrorDocument 404 page

~~~ php
add_filter('cgit_cache_404/404_url', function($url) {
    return '/my-custom/url/static-404.html';
});
~~~

### Re-cache actions

Filter the actions that trigger a re-cache of the 404 page.

~~~ php
add_filter('cgit_cache_404/recache_actions', function($actions) {
    $actions[] = 'my_custom_action';
    return $actions;
});
~~~

### Request URL

Filter the URL that is used to trigger a 404 page in order to cache the page.

~~~ php
add_filter('cgit_cache_404/request_url', function($url) {
    return get_home_url(null, 'my-custom-404-url');
});
~~~

### File extensions

Filter the array of file extensions that should use the static 404 page instead of the WordPress one.

~~~ php
add_filter('cgit_cache_404/404_file_extensions', function($extensions) {
    $extensions[] = 'md';
    $extensions[] = 'txt';
    
    return $extensions;
});
~~~


