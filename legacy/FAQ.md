# Recommended usage

The easiest and more efficient way to enable events search in your grid is to use 2do Directory, the public calendar based on this application. It is the recommended way for hypergrid-enabled regions and grid, as not only it avoids the installation of additional software, but it also allows to share events between grids.

The directory provides places, land for sale and events search in-world from the viewer search feature. Places and land for sale are parsed regularly from your simulator(s).

## Enable in-world search on your region(s)

To enable in-world search in your region without installing the web application:

* Download [OpenSimSearch.Module.dll](https://github.com/GuduleLapointe/flexible_helper_scripts/tree/master/bin) and put it inside your OpenSimulator bin/ folder.
* Add the following settings to each simulator `OpenSim.ini`. (replace "`Your Grid`" and "`yourgrid.org:8002`" with your own values)

    _Note: Even for grids, this config goes in regions OpenSim.ini, not in Robust.ini._


    ```ini
    [Search]
        SearchURL = "http://2do.directory/helpers/query.php?gk=http://yourgrid.org:8002"

    [DataSnapshot]
        index_sims = true
        gridname = "Your Grid"
        snapshot_cache_directory = "./DataSnapshot"
        DATA_SRV_2do = http://2do.directory/helpers/register.php
        ;; You can add multiple search engines
        ;DATA_SRV_OtherEngine = http://example.org/helpers/register.py
    ```

* Restart the simulators

## Add yoor events calendar to 2do Directory

Send us an email at [dev@2do.pm](mailto:dev@2do.pm), with a link to the grid's event calendar! The easier format is iCal, but we can also build a custom scraper and parser for web-based event calendars.

2DO Directory lists only events on hypergrid-enabled grids.

## Put a 2DO teleport board in your region(s)

The in-world 2D0 board can be found in Speculoos Lab region: `speculoos.world:8002/Lab`. It provides a beautiful list of ongoing and upcoming events, with ability to click to teleport to the event location.

You can also find the latest code on GitHub [GuduleLapointe/2do-board](https://github.com/GuduleLapointe/2do-board)


# Standalone usage

If your grid is not hypergrid-enabled or if you want to provide your own calendar, you can install the software on your servers, composed by

* [2DO aggregator](https://github.com/GuduleLapointe/2do-aggregator) the parser, formatting calendars for use by in-world engines
* A web helper required by OpenSimulator
    * [Flexible Helper Scripts](https://github.com/GuduleLapointe/flexible_helper_scripts): helpers only, no web interface
    * or [w4os](https://w4os.org/): a WordPress plugin including the helpers and a web management interface
* [OpenSimSearch module](https://github.com/GuduleLapointe/flexible_helper_scripts/tree/master/bin)

# Potentially Obsolete Questions


This is the initial project FAQ, it needs a review and some information could be outdated, but you might find some answers too. Once the transition from Python PHP is complete, I will check that.

## How do you get the information for the listed events?

2DO events pulls information from the event calendars that are published on the listed grids websites. When a grid publishes an ical feed, it is easy. But many grids have web-based event calendars. We parse those (caching sub-pages to limit load on the grid's webservers) and convert them to ical.

To make it easy to see what's going on right now and provide easy access, 2DO events runs algorithms to extract hypergrid url's and times from the event calendars. This is not always easy, as various grids have different ways of indicating the location of the event. Sometimes the information is in the description, sometimes it is just a region name, sometimes it is a hypergrid url.

## What can a grid or event organizer do to make sure the events are listed correctly?

First of all, make sure the source data (event calendar, website) is correct. It helps if the information is easily parseable by a computer program (see below).

Here are some guidelines to ensure most accurate listings:

* Put the location information in the location field of your calendar software. 2DO events prefers hgurls (of the form 'hg.url.com:8002:Region Name'), but if you list local region names (of the form 'Region Name') 2DO events will be able to tag on the 'hg.url.com:8002:' part itself.
* Use a consistent form for the location information. For example, if you use local region names, make sure they are real region names and not something like 'The far end of the most beautiful Foobar region' but just 'Foobar'. If you use hop url's, use them consistently; don't mix 'Foobar/123/43/22' with 'Foobar (123,43,22)'.
* Be consistent with timezones. 2DO events will parse times and timezones correctly, and represent the event times correctly in whatever timezone the user of 2DO events selects. But if you set the timezone of your calendar to, say, EDT (US/New York) but then put times in it that are grid times, you will confuse the parser and your events will likely be listed incorrectly. Don't do that (it will confuse your users as well!). Set the calendar to 'US/Pacific' (which is grid time) and then put in the events in grid time.
* When the event moves to some other place, make sure to update the event on your grid event calendar. And to be sure that people can find your event even if they have the old time or location, put some kind of beacon that will hand out a landmark to the new location at the old location.

## What is the preferred format for event data?

It helps if the information is easily parseable by a computer program. The explanation of how to accomplish this is a bit technical. Website builders should be able to grasp what is explained below, but if you have any questions do not hesitate to ask at [dev@2do.pm](mailto:dev@2do.pm).

There are a number of options, in order of preference:

* Machine-readable, structured calendar (ical, json, ...)

    Parsing event data works best when it is available in a structured, machine-readable format. The de-facto format for publishing calendar information on the internet is iCalendar (.ics). Many websites with event-support can generate an ical feed. Another easily parseable format is JSON. JSON is often used to convey data from a web server to a browser, but can also be used for sharing information in a structured way to third-party applications.

    An easy way to create an iCalendar is to use Google Calendar. Google calendars can be easily embedded on existing websites, and have an option to export an iCal feed.

* Machine-optimized human-readable websites

    If, for some reason, an ical feed or json provider is not an option (or there is already a website and you don't want to abandon that), there are some tips to make a website more easily parseable by the 2DO events algorithms:
    * Don't put all the event info in one paragraph that is not structured with html tags, that makes it really hard to parse the info accurately and correctly.
    * Use elements (div, span, h1, h2, ...) to structure information. For example, put the title of the  event in it's own element, put the start date in an element, etc..
    * Annotate the html tags to make them easily identifiable by computers. You can do this by adding an id on the tag, or a class. Id's work best for big elements, for example a main div that surrounds all the events, or the table that surrounds all the events. Classes work well for repeating elements, such as titles, times, descriptions, etc..

If you have a web-designer who makes your website, pass along the above info and he/she should be able to structure the information for you.

## Unstructured webpages

While it is possible to parse an unstructered webpage (where the event info is all in one element for example), it usually doesn't work well and breaks easily. For example, some grids have a completely free-form text-field to give all the event info with start and end times written in different forms. The hgurl will be part of the text, and sometimes is just a region name or a specific club without even listing the region name. 2DO events is unable to parse these pages.

## Can I add categories to my event?

Yes, you can. Just include a tag for the form '[[Category:Example]]' somewhere in the event description. Currently the following categories will be recognized:

* [[Category:Music]] - music (live performance, DJ's)
* [[Category:Lecture]] - Lecture or reading
* [[Category:Social]] - Social events (community meetings and such)

More categories will be added in the future. If there is a category you are missing, let us know at [dev@2do.pm](mailto:dev@2do.pm).

## What grids are listed?

The following calendars are imported. The list is not exhaustive.

* (list will be update soon and/or available elsewhere)

## I don't want you to list my events!

That's a pity, but it's your call! Just let us know and we'll work out how to exclude your events.
