# Hacks/Hackers migration

Migrates WordPress content from the old (pre-2017) WordPress site to the [new Hugo site](https://github.com/hackshackers/hackshackers-hugo) via [WP-CLI](http://wp-cli.org).

## Setup

Place command directory somewhere that WP-CLI can reach it. Then use [`wp package install`](http://wp-cli.org/commands/package/install/) or [the `--require` parameter](http://wp-cli.org/config/#global-parameters) to load the command.

## Usage

```
wp hh-hugo migrate_post         Migrate a single item from WP `post` post type to Hugo `blog` section
wp hh-hugo migrate_all_posts    Migrate all items from WP `post` post type to Hugo `blog` section
wp hh-hugo migrate_group        Migrate a single group
wp hh-hugo migrate_all_groups   Migrate all groups
wp hh-hugo delete_content_dirs  Delete hugo-content and hugo-images directories
wp hh-hugo markdown             Print post_content transformed to Markdown
```