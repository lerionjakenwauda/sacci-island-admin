# SACCI Parish Administration Suite 2.1.5

The approved parish administration interface for St. Augustine's Catholic Church, MaryHill, Ikorodu.

## Approved shell

- Fixed light mode
- WordPress's native top bar spans the full viewport
- The parish identity, top bar and sidebar read as one continuous shell
- No detached navigation island
- No forced dark mode
- Sidebar opens and closes from the header
- Main workspace expands when the sidebar is closed
- Mobile sidebar becomes an overlay drawer
- The supplied parish crest and parish name appear in the header
- WordPress parent menu rows toggle their submenu instead of navigating
- New and profile menus open on click and close outside or with Escape
- Native screens sit inside one large rounded content surface
- Vendor shortcuts are removed from the top bar
- Ctrl+K opens the administration command search

## Parish Overview

WordPress's default dashboard is removed completely, including:

- At a Glance
- Activity
- Quick Draft
- Site Health
- WordPress Events and News
- Welcome panel
- Third-party dashboard widgets

The replacement Parish Overview includes:

- Upcoming parish events
- Published announcements
- Published bulletins
- Published pages
- Quick publishing actions
- Recent parish website activity
- Payment-verification notice
- Parish and developer footer

## Existing management features retained

- Menu reordering
- Menu relabelling
- Dashicon controls
- Menu hiding
- Role-based menu visibility
- Direct-route protection
- Administrator lockout protection

## Install

Upload `sacci-island-admin-v2.1.5.zip` through WordPress and choose **Replace current with uploaded**.

Then clear all cache and hard-refresh the administration area.

## Releases

Every versioned push to `main` is linted, tested, packaged with the
`sacci-island-admin/` root directory and published as a GitHub release.
WordPress reads the public `update.json` manifest first and falls back to the
GitHub Releases API. **Dashboard → Updates → Check again** bypasses SACCI's
cache and discovers a new release during that request.

## Developer

Lerion Jake Nwauda Digital Innovations
https://lerionjakenwauda.com/
