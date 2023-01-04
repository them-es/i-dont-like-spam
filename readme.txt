=== I don't like Spam! ===
Contributors: them.es
Donate link: https://them.es
Tags: ninja-forms, caldera-forms, wpforms, contact form, anti-spam, blocklist
Requires at least: 4.9
Tested up to: 6.1
Stable tag: 1.2.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Requires PHP: 7.0

Use the [WordPress Comment Blocklist](https://codex.wordpress.org/Combating_Comment_Spam#Comment_Blacklist) feature to protect you from contact form spam submissions.

= Compatibility =
This plugin is compatible with [Ninja Forms](https://wordpress.org/plugins/ninja-forms), [Caldera Forms](https://wordpress.org/plugins/caldera-forms) and [WPForms](https://wordpress.org/plugins/wpforms-lite).

= Features =
* Not reinventing the wheel since WordPress already has a Comment Blocklist feature.
* Privacy by Design by using a local blocklist.
* No external API.

= Setup =
* Login to the dashboard and open **Settings > Discussion**.
* Scroll down to **Disallowed Comment Keys**.
* Enter some bad words, phrases or weblinks that keep bugging you. **Choose your blocklist wisely!**
* Contact forms that contain any of these words cannot be submitted anymore and will show an error message.
* Optional: Modify the error message output in the Theme Customizer.

= Fun fact =
Spam emails are usually very annoying and affect work - and there is nothing funny about it. But did you know that the term Spam, referring to junk mail, was named after a famous Monty Python sketch?
[https://en.wikipedia.org/wiki/Spam_(Monty_Python)](https://en.wikipedia.org/wiki/Spam_(Monty_Python))

[vimeo https://vimeo.com/19166875]

= Contribution? =

* The Plugin development can be followed via GitHub <3
* We are happy to receive feature suggestions and pull requests: [https://github.com/them-es/i-dont-like-spam](https://github.com/them-es/i-dont-like-spam "GitHub")

= More information =

[https://them.es/plugins/i-dont-like-spam](https://them.es/plugins/i-dont-like-spam)

== Installation ==

1. Upload the Plugin to the `/wp-content/plugins/` directory.
2. Activate it through the 'Plugins' menu in WordPress.
3. Add a list of bad words, phrases or weblinks that should prevent form submissions to the [Comment Blocklist](https://codex.wordpress.org/Combating_Comment_Spam#Comment_Blacklist).

== Changelog ==

= 1.2.6 =
* Code quality
* Documentation

= 1.2.5 =
* Prevent PHP error when checking field values
* Code quality

= 1.2.4 =
* Code quality

= 1.2.3 =
* The option key changed after WordPress 5.5b3. Use option 'disallowed_keys' if WordPress version >=5.5 and implement recommended code snippet for backward compatibility.

= 1.2.2 =
* Use option 'blocklist_keys' if WordPress version >=5.5

= 1.2.1 =
* Improve pluginmissing_admin_notice()

= 1.2 =
* Compatibility with Caldera Forms

= 1.1.1 =
* "Racially neutral" terminology: Rename blacklist to blocklist where possible

= 1.1 =
* Compatibility with WPForms
* Minor changes to translation strings

= 1.0 =
* Initial Release
* Created a GitHub repository with all development sources