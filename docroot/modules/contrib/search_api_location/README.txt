CONTENTS OF THIS FILE
---------------------
 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Developers

INTRODUCTION
------------
This module adds support for indexing location values provided by the geofield
module and subsequently filtering and/or sorting on them, provided the service
class supports this.

The module adds two new data types, Latitude/longitude and Spatial Recursive
Prefix Tree to the Fields form of Search API indexes. You can use these to index
the LatLong Pair property of fields of type geofield. To make real use of this
module, you will need to install additional sub-modules. Three of them are
included in this project:

[1] search_api_location_views
[2] search_api_location_geocoder
[3] search_api_location_facets

Please refer to their own README.txt files for details.

REQUIREMENTS
------------
For this module to have any effect, You need to enable search_api module. Keep
in mind the backend service class of the search_api has to support those data
type as well. Currently, only the Search API Solr module [1] is known to support
this feature.

[1] http://drupal.org/project/search_api_solr

INSTALLATION
------------
Install as you would normally install a contributed Drupal module. For further
information, see [1]:

[1] https://www.drupal.org/docs/8/extending-drupal-8/installing-modules

CONFIGURATION
-------------
After enabling search_api_location go to
   /admin/config/search/search-api/index/YOUR_INDEX_NAME/fields

Click on 'add fields' and select the geofield which you want to index.
Note the geofield must have stored a lat/lon pair value.
Then select type as Latitude/longitude to work with search_api_location_views
and/or Recursive Prefix Tree to work with fcets_map_widget.

DEVELOPERS
----------
This module provides two data_type plugins for search_api i.e LocationDataType
and RptDataType. By supporting those data type with your service class,
you indicate that you can index the location data type in a useful manner.
The LocationDataType is defined as a latitude and longitude, in decimal degrees,
separated by a comma, and has string format.
E.g., Dries place of birth would be represented as: 51.16831,4.394287 .

Similarly RptDataType is defined as a latitude and longitude without comma and
a space in between latitude and Longitude.
E,g, Dries place of birth would be represented as: 51.16831 4.394287

The modules also provides two location input plugin named as Raw and Map to
allow your site users to enter the location in various format.
