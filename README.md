# XACUBE Live Chat for WordPress

Add live chat to your WordPress site and answer visitors from your XACUBE inbox, in English or Persian.

[XACUBE](https://xacube.ir) is a hosted live chat and shared inbox. This plugin connects a WordPress site to it.

## What it does

- Shows the chat on your site, already set up for that site
- Lets administrators answer conversations inside WordPress admin
- Recognises signed-in WordPress users, if you turn that on
- Updates from the WordPress Plugins screen

## Install

1. Sign in to [XACUBE](https://app.xacube.ir) and add your website under **Websites**.
2. Download the WordPress plugin from that website's page. This copy is set up for your site.
3. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip and activate it.

The zips on the [Releases](../../releases) page are plain packages with no site attached. On its own, a plain package does not show a chat, so use the copy from your panel for a new install.

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer
- A XACUBE account

## What it sends to XACUBE

The plugin relies on the XACUBE service, so visitors' browsers load the chat from it and conversations are stored there. WordPress also contacts XACUBE to check for plugin updates, sending the site's public key and plugin version. See the [privacy page](https://xacube.ir/en/privacy).

## Changes

Every version is listed in [changelog.json](xacube/changelog.json) and on the [changelog page](https://xacube.ir/en/docs/releases).

## Security

Please don't report security problems in a public issue. Use GitHub's private vulnerability reporting on this repository.

## License

GPL v2 or later. See [LICENSE](LICENSE). The bundled Inter and Vazirmatn fonts are under the SIL Open Font License; their licences are in [xacube/assets/fonts](xacube/assets/fonts).

---

## فارسی

با این افزونه، گفتگوی آنلاین XACUBE را به سایت وردپرسی خود اضافه می‌کنید و از داخل پیشخوان وردپرس به بازدیدکنندگان پاسخ می‌دهید.

برای نصب، در [پنل XACUBE](https://app.xacube.ir) سایت خود را در بخش **وب‌سایت‌ها** اضافه کنید، افزونهٔ مخصوص آن را دریافت کنید و در وردپرس از مسیر **افزونه‌ها ← افزودن ← بارگذاری افزونه** نصب و فعال کنید.
