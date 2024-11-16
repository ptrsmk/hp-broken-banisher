# HolyPixels Broken Banisher
YOURLS plugin to eliminate broken (404) links from the database.

> This plugin requires YOURLS 1.7+
> Be aware, there's no sanitization going on here. Obviously, the code shouldn't run unless the API key is correct though. 

## Purpose

This plugin helps you audit your links and banish them from the database.


## How to

* Backup your database! Only you are responsible for what happens when using this plugin.
* In `/user/plugins`, create a new folder named `hp-broken-banisher`.
* Drop these files in that directory.
* Go to the Plugins administration page, and activate the plugin .
* In your browser, enter something like: `https://sho.rt/yourls-api.php?action=check_broken_links&signature=YOUR_API_KEY&perpage=50&autoredirect=1`. Hit Enter and wait.


## FAQ

1. "I have a question."
* Please, ask it in the Discussion area.

2. "I found a problem."
* Cool. Please start an Issue or submit a Pull Request.
