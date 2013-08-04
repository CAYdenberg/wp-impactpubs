=== ImpactPubs ===
Contributors: Casey A. Ydenberg
Tags: List of publications, PubMed
Requires at least: 3.0.1
Tested up to: 3.5.2
Stable tag: 4.3
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Search PubMed or ORCiD and attach ImpactStory badges. Display the results with shortcode.

== Description ==
This is a WordPress plugin to automatically display a publication profile on a website.

Results can be pulled from PubMed or ORCiD, and will be automatically updated once a week.

One profile can be create per blog user.

Altmetrics badges can be appended to each result by obtaining an API key from ImpactStory (www.impactstory.org)
The Pubmed query and API information can be entered into a special Settings page created in the dashboard. 
The publications can be listed on a particular page (or post!) by entering the shortcode:

[publications]

If your site has multiple users, each user can create their own publications list on the dashboard. The
shortcode is then changed into:

[publications name=<i>login_name</i>]

To retrieve results from PubMed:
Because PubMed does not use unique identifiers for a authors,
you must come up with a way to search for yourself that distinguishes
you from other people in the database.

For example:
* "mcclintock"
* "mcclintock B"
* "mcclintock B AND Cold Spring Harbor[affiliation]"

To retrieve results from ORCiD:
First, go to orcid.org and create your publication profile (very easy to do). 
On the left side of your profile, you should see a 16 digit number. Copy this
number down to access your profile from ImpactPubs.

To use badges from ImpactStory, go to http://impactstory.org/api-docs and request an API key. The key is
simply entered into the dashboard as a second parameter.

NOTE: This Plugin works by making a remote call to the National Library of Medicine's E-Utilities.
(http://eutils.ncbi.nlm.nih.gov/entrez/eutils/), to ORCiD (http://pub.orcid.org/) and to
and to ImpactStory (http://impactstory.org/embed/).

The creator(s) of the Plugin assume no responsibility for the accuracy or cleanliness of the data retrieved 
from these remote services. Use at your own risk.

==Installation==

1. Download the Plugin and activate it.

1. Go to PubMed (http://www.ncbi.nlm.nih.gov/pubmed/) and find a unique string search string to identify
the papers you would like to display. Some examples:
* "mcclintock"
* "mcclintock B"
* "mcclintock B AND Cold Spring Harbor[affiliation]"

1. Alternatively, go to ORCiD (orcid.org) and create your profile. Copy down the 16 digit number
on the left-hand side of your profile.

1. To add Altmetrics, write to team@impactstory.org and ask for a free API key.

1. Enter your preferred search method, the search string or ORCiD number, and the API key into the settings page on the dashboard.

1. Wherever you'd like the publications list to appear, type [publications name=<i>login_name</i>], where login_name is your WordPress login name.

== Changelog ==

= 1.0 =
Initial release

= 2.0=
Added ORCiD search functionality
Added multi-user functionality
Added weekly automatic updates

==