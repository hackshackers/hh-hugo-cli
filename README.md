# Hacks/Hackers migration

Migrates WordPress content to Hugo via [WP-CLI](http://wp-cli.org).

## Setup

Place command directory somewhere that WP-CLI can reach it. Then use [`wp package install`](http://wp-cli.org/commands/package/install/) or [the `--require` parameter](http://wp-cli.org/config/#global-parameters) to load the command.

## Usage

```
wp help hh-hugo # More info about commands and arguments
wp hh-hugo migrate post 123 # Migrates specific post with ID 123
wp hh-hugo migrate post 123,456 # Migrates posts 123 and 456
wp hh-hugo migrate posts # Migrate all published items in `post` post type
wp hh-hugo markdown 123 # Convert WP post_content to Markdown and send to STDOUT
wp hh-hugo delete_content_dir # Delete directory where Hugo Markdown files are written
```