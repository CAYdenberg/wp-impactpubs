=== ImpactPubs ===
Contributors: CAYdenberg
Tags: academia, science, publications, CV, citation
Requires at least: 2.8
Tested up to: 3.7
Stable tag: tags
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Search PubMed, ORCiD, or ImpactStory and display publication information on your blog or website. Altmetric information from ImpactStory can be included.

== Description ==

This is a WordPress plugin to automatically display a publication profile on a website.

Results can be pulled from PubMed, ORCiD, or ImpactStory, and will be automatically updated daily.

One profile can be created per blog user.

[publications]

If your site has multiple users, each user can create their own publications list on the dashboard. The
shortcode is then changed into:

[publications name=<i>login_name</i>]

**To retrieve results from PubMed**:
Because PubMed does not use unique identifiers for a authors,
you must come up with a way to search for yourself that distinguishes
you from other people in the database.

For example:

- "mcclintock"

- "mcclintock B"

- "mcclintock B AND Cold Spring Harbor[affiliation]"

**To retrieve results from ORCiD**:
First, go to orcid.org and create your publication profile (very easy to do). 
On the left side of your profile, you should see a 16 digit number. Copy this
number down to access your profile from ImpactPubs.

**To retrieve results from ImpactStory**: go to http://impactstory.org and create a profile. Your name in URL format 
(eg http://www.impactstory.org/users/YourName/) is entered as the identifier.

NOTE: This Plugin works by making a remote call to the National Library of Medicine's E-Utilities.
(http://eutils.ncbi.nlm.nih.gov/entrez/eutils/), to ORCiD (http://feed.labs.orcid-eu.org/) and to ImpactStory (http://impactstory.org/embed/).

The creator(s) of the Plugin assume no responsibility for the accuracy or cleanliness of the data retrieved 
from these remote services. Use at your own risk.

==Installation==

1. Download the Plugin and activate it.

1. Go to PubMed (http://www.ncbi.nlm.nih.gov/pubmed/) and find a unique string search string to identify
the papers you would like to display.

1. Alternatively, go to ORCiD (orcid.org) and create your profile. Copy down the 16 digit number
on the left-hand side of your profile.

1. Alternatively, go to ImpactStory (www.impactstory.org) and create a profile. Copy down your username.

1. Enter your preferred search method, the search string, ORCiD number or your name at ImpactStory as the "identifier".

1. Wherever you'd like the publications list to appear, type [publications name=<i>loginname</i>], where *loginname* is your WordPress login name.

== Screenshots ==

1. Example of required user input. This is a form on the Wordpress dashboard.

2. Example of output the end-user sees upon requesting the page. Style may vary depending on your WordPress theme.

== Changelog ==

= 1.0 =

- Initial release

= 2.0=

- Added ORCiD search functionality

- Added multi-user functionality

- Added weekly automatic updates

= 2.1 =

- orcid id's are now validated if they include letters, numbers, or dashes

- Up to 1000 records can now be retrieved from PubMed (this should be large enough to include anyone, but not so large so as to invite a near-infinite loop if an inappropriate search is done.

= 2.2 =

- simplified author formatting function

- added css and js to adjust the line height to fix a spacing issue with very small fonts

= 2.3 =

- changed ORCiD retrieval to now retrieve from feed.labs.orcid-eu.org. As this service is also an ongoing project this may require some tweaks in the future.

= 2.4 =

- changed remote requests to utilize WordPress HTTP API to support a wider variety of hosting sites

= 2.5 =

- fixed automatic updates, changed to daily cron

= 2.6 =

- Changed naming of span classes to avoid conflicts

- made ORCiD parser accept articles without a journal or a date. Now we only require a title and at least one author

= 2.7 =

- Retired all the ImpactStory code.

- Improved error handling (when remote service can't be contacted) a bit.
 
= 3.0 = 

- Put ImpactStory back in, as a completely separate 3rd party service.

==
