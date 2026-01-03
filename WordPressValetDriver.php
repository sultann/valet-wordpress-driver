<?php
/**
 * WordPress Valet Driver.
 *
 * Proxies missing uploads to production and handles multisite subdirectory routing.
 *
 * @see https://github.com/sultann/valet-wordpress-driver
 */

namespace Valet\Drivers\Custom;

use Valet\Drivers\Specific\WordPressValetDriver as BaseWordPressValetDriver;

class WordPressValetDriver extends BaseWordPressValetDriver
{
    /**
     * URI patterns that identify upload requests.
     */
    private const UPLOAD_PATTERNS = [
        '#^/wp-content/uploads/#',
        '#^/files/#',
        '#^/[^/]+/files/#',
    ];

    /**
     * Local TLD to replace when building remote URL.
     */
    private const LOCAL_TLD = '.test';

    /**
     * Production TLD to use as default fallback.
     */
    private const REMOTE_TLD = '.com';

    /**
     * Default protocol for remote URL.
     */
    private const REMOTE_PROTOCOL = 'https://';

    /**
     * Optional config file name for custom remote URL.
     */
    private const CONFIG_FILE = '.valet-proxy';

    /**
     * Flag indicating whether current request should be proxied to remote.
     */
    private static bool $isRemoteRequest = false;

    /**
     * Whether this is a multisite installation.
     */
    private bool $isMultisite = false;

    /**
     * Determine if the driver serves the request.
     *
     * @param string $sitePath The site's root path.
     * @param string $siteName The site name.
     * @param string $uri      The request URI.
     *
     * @return bool True if this driver should handle the request.
     */
    public function serves(string $sitePath, string $siteName, string $uri): bool
    {
        if (!parent::serves($sitePath, $siteName, $uri)) {
            return false;
        }

        $this->isMultisite = $this->detectMultisite($sitePath);

        return true;
    }

    /**
     * Determine if the incoming request is for a static file.
     *
     * @param string $sitePath The site's root path.
     * @param string $siteName The site name.
     * @param string $uri      The request URI.
     *
     * @return string|false Local file path, remote URL, or false.
     */
    public function isStaticFile(string $sitePath, string $siteName, string $uri): string|false
    {
        self::$isRemoteRequest = false;

        if ($this->isMultisite) {
            $staticPath = $this->handleMultisiteStaticFile($sitePath, $uri);
            if ($staticPath) {
                return $staticPath;
            }
        }

        $localPath = parent::isStaticFile($sitePath, $siteName, $uri);

        if ($localPath) {
            return $localPath;
        }

        if ($this->isUploadRequest($uri)) {
            self::$isRemoteRequest = true;

            return $this->getRemoteHost($sitePath, $siteName) . $uri;
        }

        return false;
    }

    /**
     * Serve the static file or redirect to remote host.
     *
     * @param string $staticFilePath The file path or remote URL.
     * @param string $sitePath       The site's root path.
     * @param string $siteName       The site name.
     * @param string $uri            The request URI.
     */
    public function serveStaticFile(string $staticFilePath, string $sitePath, string $siteName, string $uri): void
    {
        if (self::$isRemoteRequest) {
            header("Location: $staticFilePath", true, 302);
            exit;
        }

        parent::serveStaticFile($staticFilePath, $sitePath, $siteName, $uri);
    }

    /**
     * Get the fully resolved path to the application's front controller.
     *
     * @param string $sitePath The site's root path.
     * @param string $siteName The site name.
     * @param string $uri      The request URI.
     *
     * @return string|null The front controller path.
     */
    public function frontControllerPath(string $sitePath, string $siteName, string $uri): ?string
    {
        $_SERVER['PHP_SELF'] = $uri;
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];

        if ($this->isMultisite) {
            $uri = $this->handleMultisiteUri($sitePath, $uri);
        }

        return parent::frontControllerPath($sitePath, $siteName, $uri);
    }

    /**
     * Detect if this is a multisite installation.
     *
     * @param string $sitePath The site's root path.
     *
     * @return bool True if multisite.
     */
    private function detectMultisite(string $sitePath): bool
    {
        $wpConfigPath = $sitePath . '/wp-config.php';

        if (!file_exists($wpConfigPath)) {
            return false;
        }

        $wpConfig = file_get_contents($wpConfigPath);

        return strpos($wpConfig, 'MULTISITE') !== false
            || strpos($wpConfig, 'WP_ALLOW_MULTISITE') !== false;
    }

    /**
     * Handle static file requests for multisite subdirectory installations.
     *
     * @param string $sitePath The site's root path.
     * @param string $uri      The request URI.
     *
     * @return string|false The static file path or false.
     */
    private function handleMultisiteStaticFile(string $sitePath, string $uri): string|false
    {
        if (!$this->isWordPressDirectory($uri)) {
            return false;
        }

        if (str_ends_with($uri, '/')) {
            return false;
        }

        $newUri = substr($uri, stripos($uri, '/wp-'));

        if (file_exists($sitePath . $newUri)) {
            return $sitePath . $newUri;
        }

        return false;
    }

    /**
     * Handle URI rewriting for multisite subdirectory installations.
     *
     * @param string $sitePath The site's root path.
     * @param string $uri      The request URI.
     *
     * @return string The modified URI.
     */
    private function handleMultisiteUri(string $sitePath, string $uri): string
    {
        if (!$this->isWordPressDirectory($uri)) {
            return $uri;
        }

        if (stripos($uri, 'wp-admin/network') !== false) {
            return $uri;
        }

        if (stripos($uri, 'wp-cron.php') !== false) {
            $newUri = substr($uri, stripos($uri, '/wp-'));
            if (file_exists($sitePath . $newUri)) {
                return $newUri;
            }
        }

        return substr($uri, stripos($uri, '/wp-'));
    }

    /**
     * Check if the URI contains a WordPress directory.
     *
     * @param string $uri The request URI.
     *
     * @return bool True if URI contains wp-admin, wp-content, or wp-includes.
     */
    private function isWordPressDirectory(string $uri): bool
    {
        return stripos($uri, 'wp-admin') !== false
            || stripos($uri, 'wp-content') !== false
            || stripos($uri, 'wp-includes') !== false;
    }

    /**
     * Check if the URI is an upload request.
     *
     * @param string $uri The request URI.
     *
     * @return bool True if this is an upload request.
     */
    private function isUploadRequest(string $uri): bool
    {
        foreach (self::UPLOAD_PATTERNS as $pattern) {
            if (preg_match($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the remote host URL for proxying.
     *
     * @param string $sitePath The site's root path.
     * @param string $siteName The site name.
     *
     * @return string The remote host URL.
     */
    private function getRemoteHost(string $sitePath, string $siteName): string
    {
        $configFile = $sitePath . '/' . self::CONFIG_FILE;

        if (file_exists($configFile)) {
            return rtrim(trim(file_get_contents($configFile)), '/');
        }

        $remoteDomain = str_replace(self::LOCAL_TLD, self::REMOTE_TLD, $siteName . self::LOCAL_TLD);

        return self::REMOTE_PROTOCOL . $remoteDomain;
    }
}