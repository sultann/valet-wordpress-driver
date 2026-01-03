# Valet WordPress Driver

A Laravel Valet driver for WordPress that proxies missing uploads to production and handles multisite subdirectory routing.

If you've ever pulled a production database locally and faced broken images everywhere, this fixes that.

## Features

- Proxies missing uploads to your live site automatically
- Handles WordPress multisite subdirectory routing
- Works out of the box - no config needed for most setups
- Built for Laravel Valet 4.x

## Installation

```bash
curl -sL https://raw.githubusercontent.com/sultann/valet-wordpress-driver/main/WordPressValetDriver.php \
  -o ~/.config/valet/Drivers/WordPressValetDriver.php && valet restart
```

Or download `WordPressValetDriver.php` manually to `~/.config/valet/Drivers/` and restart Valet.

## How It Works

When you request an image that doesn't exist locally (like `/wp-content/uploads/2024/photo.jpg`), the driver redirects to your production site instead of showing a 404.

By default, it converts `.test` to `.com`:
- `mysite.test` → `https://mysite.com`
- `shop.test` → `https://shop.com`

### Custom Production URL

If your production site uses a different domain, create a `.valet-proxy` file in your site root:

```bash
echo "https://staging.mysite.com" > .valet-proxy
```

That's it. The driver will use that URL instead.

## Multisite Support

The driver auto-detects multisite installations by checking for `MULTISITE` in your `wp-config.php`. It then handles subdirectory routing properly, so `/blog1/wp-admin/` works as expected.

## Supported Upload Paths

- `/wp-content/uploads/*`
- `/wp-content/uploads/sites/*` (multisite)
- `/files/*` (legacy multisite)

## Requirements

- Laravel Valet 4.x
- PHP 8.0+
- macOS

## Troubleshooting

**Images still broken?** Clear your browser cache. It might have cached the old 404s.

**Wrong production URL?** Create a `.valet-proxy` file with the correct URL.

**Multisite not working?** Make sure `MULTISITE` constant exists in your `wp-config.php`.

## Contributing

Found a bug or have an idea? [Open an issue](https://github.com/sultann/valet-wordpress-driver/issues).

## License

MIT

## Credits

Inspired by [Phil Kurth's proxy concept](https://philkurth.com.au/proxying-images-to-remote-host-on-laravel-valet/) and [lushkant's multisite driver](https://github.com/lushkant/wordpress-multisite-subdirectory-valet-driver).