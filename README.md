# Custom Jira PHP

## Summary

This repo contains php scripts that use Jira's REST API

- [src/JiraApi.php](src/JiraApi.php) - PHP Class and adapter for Jira API
- [src/JiraWorklog.php](src/JiraWorklog.php) - PHP Class to handle parsing and summarizing jira worklogs
- [jira-worklog.php](jira-worklog.php) - wrapper for JiraWorklog.php, provides web and command line interface (cli)
- [jira-config.php](jira-config.php) - config file for jira-worklog.php, update this with your Jira API password.
- [tests/data/output1.json](tests/data/output1.json) - example json output for jira-worklog.php
- [tests/data/output1.csv](tests/data/output1.csv) - example csv output for jira-worklog.php

## jira-worklog.php

Outputs a summary of hours logged to Jira issues over a given timespan.  This is a simple free alternative to [Tempo Timesheets](https://tempo.io/products/tempo-timesheets). 

It's highly configurable, with many options and output formats. It works with both web and command line interfaces. Output formats include text summary, csv of worklogs, and complete json that includes totals by Date, by Issues, by Author, as well as a simple list of all matching worklogs.

Options include ability to filter based on 

- From or start date/time - will exclude any worklogs that have start time before this.
- To or end date/time - will exclude any worklogs that have start time after this.
- Authors - will only include worklogs by these authors
- JQL - can further reduce matching workogs to issues that match give JQL.  ex: labels=php

Here's example of help from command line interface (CLI)

```
php jira-worklog.php [options]

  -f str  from date string, anything that can be parsed by strtotime(). REQUIRED.
  -t str  to date string, anything that can be parsed by strtotime(). Optional, default is 'now'.
  -u usrs filter worklogs to only include ones matching jira users. Comma separate usernames in usrs.
  -o out  output format, specify one of: txt, json, csv, html.  Optional, default is txt.
  -k key  jira issue key, will add comment containing worklog summary.  Optional.
  -c fn   config file, where you store apiCredentials. Optional, default is ./jira-config.php
  -j jql  encoded jql to further limit results (beyond dates and users). Should not have "Order by".
  -w      instead of giving daily totals, give totals per week.
  -v      verbose output, including basic timing info with API, written to STDERR.
  -d      debug output - warning - this contains a ton of information, written to STDERR.
  -h      show this help and exit.

Examples:

php jira-worklog.php -f='-7 days'                  # summarize worklogs over the last 7 days
php jira-worklog.php -f='-7 days' -u=chad,jo       # the last 7 days, only users chad and jo
php jira-worklog.php -f='-7 days' -k=CN-12         # the last 7 days, and post comment to CN-12
php jira-worklog.php -f='-7 days' -o=json          # the last 7 days, output in json
php jira-worklog.php -f='-7 days' -o=csv           # the last 7 days, output in csv (open with excel)
php jira-worklog.php -f=2017-1-1  -j=labels%3Dfun  # last 7 days, jql: labels=fun
php jira-worklog.php -f=2017-1-1  -t=2017-1-1      # just new years day 2017
php jira-worklog.php -f=2017-1    -t=2017-3        # Q1 of 2017
php jira-worklog.php -f=2017-1    -t=2017-3  -w    # Q1 of 2017, weekly byDate summary
```

### Examples

Example showing summary all time logged from beginning of current month to now for all users
```
time php custom-jira-php/jira-worklog.php -f='first day of this month'

63.8h Total Time logged, 2017-09-01 Fri - 2017-09-22 Fri
 as of 2017-09-22 Fri 2:36pm CDT

Total logged per issue:
   6h CN-4 Easily Restore, Restart, JIRA (6h 9/6)
   5h CN-8 upgrade Postgres, JIRA, Bitbucket (3h 9/15, 1h 9/19, 1h 9/20)
  15m CN-15 Fix dropbox (15m 9/14)
  16h CN-29 Blog posts (3h 9/5, 1h 9/7, 4h 9/8, 2h 9/9, 6h 9/11)
  30m CN-33 Books to read (30m 9/10)
  10m CN-99 Android Music Player: Pi, Jet Audio (10m 9/21)
   2h CN-100 SPP Financials (1h 9/1, 1h 9/3)
   2h CN-110 One wheel skateboard (2h 9/2)
   9h CN-124 Buy gifts (3h 9/1, 6h 9/15)
 1.5h CN-125 New backup harddrive (1h 9/3, 30m 9/6)
   1h CN-128 Debug git client asking for password (1h 9/8)
  10h CN-133 Random Github work (8h 9/13, 1h 9/19, 1h 9/20)
  45m CN-135 Save only front door images where there is change (45m 9/13)
 1.3h CN-138 Single Speed Brown Bike Maintenance (1h 9/15, 15m 9/19)
   3h CN-139 Fix garage door (1.5h 9/8, 1.5h 9/18)
  20m CN-141 SPP Random Work (20m 9/19)
   1h CN-142 Switch to ATT Fiber (1h 9/19)
  30m CN-143 Cancel credit cards (30m 9/21)
   3h CN-144 Volunteer and Non-profit Work (2h 9/20, 1h 9/21)


Worklogs by Date:
  4h Fri 2017-09-01 -- 1h CN-100, 3h CN-124
  2h Sat 2017-09-02 -- 2h CN-110
  2h Sun 2017-09-03 -- 1h CN-100, 1h CN-125
  3h Tue 2017-09-05 -- 3h CN-29
6.5h Wed 2017-09-06 -- 6h CN-4, 30m CN-125
  1h Thu 2017-09-07 -- 1h CN-29
6.5h Fri 2017-09-08 -- 4h CN-29, 1h CN-128, 1.5h CN-139
  2h Sat 2017-09-09 -- 2h CN-29
 30m Sun 2017-09-10 -- 30m CN-33
  6h Mon 2017-09-11 -- 6h CN-29
8.8h Wed 2017-09-13 -- 8h CN-133, 45m CN-135
 15m Thu 2017-09-14 -- 15m CN-15
 10h Fri 2017-09-15 -- 3h CN-8, 6h CN-124, 1h CN-138
1.5h Mon 2017-09-18 -- 1.5h CN-139
4.1h Tue 2017-09-19 -- 1h CN-8, 30m CN-10, 1h CN-133, 15m CN-138, 20m CN-141, 1h CN-142
  4h Wed 2017-09-20 -- 1h CN-8, 1h CN-133, 2h CN-144
1.7h Thu 2017-09-21 -- 10m CN-99, 30m CN-143, 1h CN-144

real 2.040  user 0.338  sys 0.031 pcpu 18.10
```

Here's an example of checking 2 users over 2 days, verbose output (shows curl calls), and updating CN-146 via POST with worklog summary comment. You can see text output below as well as view the [json output](jira-worklog.json) which was generated with same options except `-o=json` at end.

```
php ./jira-worklog.php  -f='-2 days' -u=chad,jo -k=CN-156 -v

curl: err= 205ms 200 GET 0 36231 https://jira.example.com/rest/api/2/search?maxResults=999&jql=worklogDate%3E%3D2017-10-03+AND+worklogDate%3C%3D2017-10-05+ORDER+BY+key+ASC
curl: err= 165ms 200 GET 0 1537 https://jira.example.com/rest/api/2/issue/CN-117/worklog
curl: err= 150ms 200 GET 0 1509 https://jira.example.com/rest/api/2/issue/CN-130/worklog
curl: err= 149ms 200 GET 0 5972 https://jira.example.com/rest/api/2/issue/CN-133/worklog
curl: err= 139ms 200 GET 0 5897 https://jira.example.com/rest/api/2/issue/CN-146/worklog
curl: err= 160ms 200 GET 0 1487 https://jira.example.com/rest/api/2/issue/CN-153/worklog
curl: err= 230ms 201 POST 0 1944 https://jira.example.com/rest/api/2/issue/CN-146/comment

19h Total Time logged, from 2017-10-03 Tue to 2017-10-05 Thu
only by these users: chad, jo
 as of 2017-10-05 Thu 9:17am CDT

Total logged per issue:
  30m CN-117 Better Frames for art to hang.
  30m CN-130 Print pictures, posters
  15m CN-133 Random Github work
17.5h CN-146 jira-worklog.php
  15m CN-153 Fix dropped SSH connections 

Worklogs by Date:
  6h Tue 2017-10-03 -- 30m CN-117, 30m CN-130, 15m CN-133, 4.5h CN-146, 15m CN-153
 13h Wed 2017-10-04 -- 13h CN-146
```

### Tests

If you plan on doing any development, you'll want to make sure phpunit tests work.  

To setup, just need to run `composer install` after git clone, which will create a vendor folder.

Example of running correctly.
```
[custom-jira-php] > vendor/bin/phpunit
PHPUnit 6.4.3 by Sebastian Bergmann and contributors.

....................                                              20 / 20 (100%)

Time: 46 ms, Memory: 6.00MB

OK (20 tests, 20 assertions)
```

```
[custom-jira-php] > vendor/bin/phpunit --testdox
PHPUnit 6.4.3 by Sebastian Bergmann and contributors.

Norhaus\Tests\JiraApi
 [x] Api call returns curl wrapper
 [x] True is true

Norhaus\Tests\JiraWorklog
 [x] Api call returns cache
 [x] Csv output
 [x] Json output
 [x] Round it
```

Additionally if you want to just understand how this works, You can also test by using hidden option `-a` along with `-v` and `-d` to run it using cached .json files from [tests/data/](tests/data/).  This way you can edit the input files (CN*.json) and see how it changes output (compare against out* files.

```
[11:43 custom-jira-php] > time /usr/local/bin/php jira-worklog.php -avd 

DETECTED -a USING CACHED JSON

apiCall() returning cached result, 43055 bytes, for search?maxResults=999&jql=worklogDate%3E%3D2017-10-03+AND+worklogDate%3C%3D2017-10-05+ORDER+BY+key+ASC
apiCall() returning cached result, 3912 bytes, for issue/CN-117/worklog
parseJiraWorklogs(CN-117) fromEpoch=1507006800 (2017-10-03T00:00:00-05:00) toEpoch=1507265999 (2017-10-05T23:59:59-05:00)
Using worklog 11318 with started=1507083060 2017-10-03T21:11:00.000-0500
Using worklog 11324 with started=1507255380 2017-10-05T21:03:00.000-0500
apiCall() returning cached result, 3825 bytes, for issue/CN-130/worklog
parseJiraWorklogs(CN-130) fromEpoch=1507006800 (2017-10-03T00:00:00-05:00) toEpoch=1507265999 (2017-10-05T23:59:59-05:00)
Using worklog 11317 with started=1507052880 2017-10-03T12:48:00.000-0500
Using worklog 11327 with started=1507256100 2017-10-05T21:15:00.000-0500
apiCall() returning cached result, 7795 bytes, for issue/CN-133/worklog
parseJiraWorklogs(CN-133) fromEpoch=1507006800 (2017-10-03T00:00:00-05:00) toEpoch=1507265999 (2017-10-05T23:59:59-05:00)
Skipping (time, too early) worklog 11005 with started=1505344320 2017-09-13T18:12:00.000-0500
Skipping (time, too early) worklog 11112 with started=1505881620 2017-09-19T23:27:00.000-0500
Skipping (time, too early) worklog 11113 with started=1505923980 2017-09-20T11:13:00.000-0500
Using worklog 11316 with started=1507084020 2017-10-03T21:27:00.000-0500
apiCall() returning cached result, 15364 bytes, for issue/CN-146/worklog
parseJiraWorklogs(CN-146) fromEpoch=1507006800 (2017-10-03T00:00:00-05:00) toEpoch=1507265999 (2017-10-05T23:59:59-05:00)
Skipping (time, too early) worklog 11119 with started=1506090300 2017-09-22T09:25:00.000-0500
Using worklog 11315 with started=1507064940 2017-10-03T16:09:00.000-0500
Using worklog 11319 with started=1507087200 2017-10-03T22:20:00.000-0500
Using worklog 11320 with started=1507173960 2017-10-04T22:26:00.000-0500
Using worklog 11321 with started=1507238880 2017-10-05T16:28:00.000-0500
Skipping (time, too recent) worklog 11336 with started=1507409940 2017-10-07T15:59:00.000-0500
Skipping (time, too recent) worklog 11402 with started=1508000100 2017-10-14T11:55:00.000-0500
Skipping (time, too recent) worklog 11614 with started=1509419760 2017-10-30T22:16:00.000-0500

23.5h Total Time logged, from 2017-10-03 Tue to 2017-10-05 Thu
 as of 2017-11-07 Tue 1:08pm CST

Total logged per issue:
 1.5h CN-117 Better Frames for art to hang. (30m 10/3, 1h 10/5)
 1.3h CN-130 Print pictures, posters (1h 10/3, 15m 10/5)
  15m CN-133 Add to Github: announcementBanner.js (15m 10/3)
20.5h CN-146 Add to Github: jira-worklog.php (4.5h 10/3, 13h 10/4, 3h 10/5)

Worklogs by Date:
6.3h Tue 2017-10-03 -- 30m CN-117, 1h CN-130, 15m CN-133, 4.5h CN-146
 13h Wed 2017-10-04 -- 13h CN-146
4.3h Thu 2017-10-05 -- 1h CN-117, 15m CN-130, 3h CN-146


real 0.076  user 0.066  sys 0.009 pcpu 99.21
```

### Todo

- [x] make sure cmd-line works and web interface work
- [x] support text, html, or json output
- [x] add support for filtering by jira user
- [ ] support case where worklogs are in more than 999 jira issues
- [ ] improve html output
- [x] add csv output
- [x] add basic phpunit tests
- [x] mock Jira API with phpunit
- [ ] async API requests

