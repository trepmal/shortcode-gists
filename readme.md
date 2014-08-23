# Shortcode Gists

Turn `[gist]...[/gist]` into an embedded gist. Why? get the benefits of Gists while keeping the code in your post to benefit site-search.

This:

```
[gist filename='my-plugin.php' description='a plugin that does stuff']&lt;?php
//Plugin Name: My Plugin
[/gist]
```
becomes:

![screenshot](screenshot.png)

---

## First, configure your application

You'll need to register an application on Github and enter your client information General Settings

![general-settings](general-settings.png)


## Second, authorize your user

Go to your profile and authorize. 

![authorize](authorize.png)

Now, when you author a post, any `[gist]`s will be associated with your github/gist acount.