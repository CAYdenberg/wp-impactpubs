**Note: ImpactStory has retired their embeddable JavaScript widget, but it looks like they are now providing full profiles in JSON format.

I'll therefore be retiring the ImpactStory portion of this code and building something new using that endpoint. The good news is there shouldn't be a need to make to remote calls to multiple services: ImpactStory can now be the one-stop shop for your publication list.**

Search PubMed or ORCiD. Display the results with shortcode.

== Description ==

This is a WordPress plugin to automatically display a publication profile on a website.

Results can be pulled from PubMed or ORCiD, and will be automatically updated once a week.

One profile can be created per blog user.

Altmetrics badges can be appended to each result by obtaining an API key from ImpactStory (www.impactstory.org).
The Pubmed query and API information can be entered into a special Settings page created in the dashboard. 
The publications can be listed on a particular page (or post!) by entering the shortcode:

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

==Installation==

1. Download the Plugin and activate it.

1. Go to PubMed (http://www.ncbi.nlm.nih.gov/pubmed/) and find a unique string search string to identify
the papers you would like to display.

1. Alternatively, go to ORCiD (orcid.org) and create your profile. Copy down the 16 digit number
on the left-hand side of your profile.

1. Enter your preferred search method, the search string or ORCiD number.

1. Wherever you'd like the publications list to appear, type [publications name=<i>loginname</i>], where *loginname* is your WordPress login name.

== Changelog ==

= 1.0 =

- Initial release

= 2.0 =

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

- made ORCiD parser accept articles without a journal or a date. Now we only require a title and at least one author.

- Tweaked how URLs and DOIs are handled

==
