BBBS Event Finder

/* ----------  INTRO */

The BBBS Event Finder app was developed for Big Brothers Big Sisters of
Central TX as a means to help big + little matches quickly and easily find fun,
cost-effective things to do on their regular outings. The app was developed as
part of the 2014 National Day of Civic Hacking at "Hack4Austin" in Austin, TX.

While it was intended to serve BBBS of Central TX specifically, it's easily
tunable to provide a means to gather, filter and geographically display a
group of events, providing a geographical view of event activities that can
be useful for all sorts of applications.


/* ---------- HOW TO USE */

The BBBS Event Finder app, as originally developed, is available at:

http://bbbsevents.org/

In order to use this app, follow these steps:

1) [optional] choose event filter criteria. You can filter based on the following,
   using the appropriate submenu:
   * category of the event
   * cost of the event
2) select the day to search for events from the 'Date' menu
3) once the search completes, click on the 'Results' menu to view the events
   that were found.

Any events that were found will also be mapped in the main window, relative to
your current location. You can click on the event icons in the map view to get
more information about the event. At any time you may also recenter the map view
based on a location of your choosing by using the 'Find location' address search
field in the lower left corner of the main map window.

In addition, users may view a geographical 'Story Map' listing of
Match Discount Partners, which are area businesses that provide a discount on
goods or services to Big / Little matches. This feature makes use of ESRI's
Story Maps features. Technically speaking, this aspect of the experience is
not part of the app itself, but a separate facility linked to the app. Still,
it's a very interesting additional way to view data geographically.


/* ---------- HOW TO DEPLOY */

In order to deploy this web application, your hosting environment will need
to meet the following criteria, and you will need to modify the base source
files as described below. In order to successfully modify and deploy this
application, you'll need the following skills:

* basic proficiency with HTML, JavaScript, PHP and SQL
* basic knowledge of ESRI ArcGIS and an ArcGIS API key
* basic knowledge of Google's REST APIs and a Google API key'

Additionally, the following back-end and front-end requirements exist:

Back-end Requirements
=====================

The BBBS Event Finder app requires the following to operate:

* PHP 5.4 or later
* SQL database accessible to the PHP installation
* One or more publicly accessible Google calendars to use for event source(s)

Front-end Requirements
======================

The BBBS Event Finder app requires an ECMAScript 5 capable Javascript web
browser in order to operate correctly. This will include all the popular web
browsers available at the time of this application's development.

Source Code Modifications
=========================

In order to deploy the BBBS Event Finder app in your own environment, you will
need to perform the following modifications to the source files below, at a
bare minimum, in order to have any reasonable functionality. You're free of
course to make more extensive modifications for further customization.

.\php\secrets.php
-----------------

* set "GOOGLE_CALENDAR_API_KEY" to a valid Google Calendar API key (used )for
  calling the Google Calendar API)
* set your ArcGIS API keys, specifically "BBBS_ARCGIS_API_CLIENT_ID" and
  "BBBS_ARCGIS_API_CLIENT_SECRET", to valid values. This will require you to
  setup an ArcGIS developer account. Refer to:
  https://developers.arcgis.com/rest/geocode/api-reference/geocoding-authenticate-a-request.htm
* set your SQL database parameters to appropriate values for your
  environment(s), specifically:
  - "MYSQL_DB_HOST": your database system's hostname and port
  - "MYSQL_DB_USERNAME": your database access username
  - "MYSQL_DB_PASSWORD": the password for the above database username
  - "MYSQL_DB_DATABASE_NAME": the name of the database

.\php\universal\universal.php
-----------------------------

* set "LOCAL_DEVELOPMENT_DEPLOYMENT" to TRUE or FALSE, depending on your
  particular deployment environment.
  TRUE is intended to mean "developing / debugging on a local system", while
  FALSE is intended to mean "deployed on a live server"."

.\php\universal\debug.php
-------------------------

* there are various flags that you can use to adjust debug settings as desired

.\index.html
------------

* to adjust functionality such that the app loads events from calendars of your
  choice, adjust the variable "g_event_source_array" and the objects it contains.
* modify the method "initialize_arcgis_map()" to adjust the auto-center location
  for the web map (it defaults to Austin, TX)
* you will likely also want to alter the CSS and HTML content to personalize the
  application for your specific needs as well


/* ---------- CONCLUSION */

Any comments, questions, concerns or general feedback should be directed to:

Stefan Sinclair
mailto: software@ivxiv.com
twitter: @IVxIVSoftware
http://www.ivxiv.com
