# Custom Jira PHP

## Summary

This repo contains php scripts that use Jira's REST API

- JiraApi.php - PHP Class and adapter for Jira API
- JiraWorklog.php - PHP Class to handle parsing and summarizing jira worklogs
- jira-worklog.php - wrapper for JiraWorklog.php, provides web and command line interface (cli)

## jira-worklog.php

Outputs a summary of hours logged to Jira issues over a given timespan.  This is a simple free alternative to [Tempo Timesheets](https://tempo.io/products/tempo-timesheets).

```
php ./jira-worklog.php [options]

  -f str  from date string, anything that can be parsed by strtotime(). REQUIRED.
  -t str  to date string, anything that can be parsed by strtotime(). Optional, default is 'today'.
  -u usrs filter worklogs to only include ones matching jira users. Comma separate usernames in usrs.
  -o out  output format, specify one of: txt, json, html.  Optional, default is txt.
  -k key  jira issue key, will add comment containing worklog summary.  Optional.
  -c fn   config file, where you store apiCredentials. Optional, default is ./jira-config.php
  -v      verbose output, including basic timing info with API.
  -d      debug output - warning - this contains a ton of information.
  -h      show this help and exit.

Examples:

php ./jira-worklog.php -f='-7 days'                # the last 7 days
php ./jira-worklog.php -f='-7 days' -u=chad,jo     # the last 7 days, only users chad and jo
php ./jira-worklog.php -f='-7 days' -k=CN-12       # the last 7 days, and post comment to CN-12
php ./jira-worklog.php -f='-7 days' -o=json        # the last 7 days, output in json
php ./jira-worklog.php -f=2017-1-1  -t=2017-1-1    # just new years day 2017
php ./jira-worklog.php -f=2017-1    -t=2017-3      # Q1 of 2017
```

### Example outputs

Here's an example of checking 2 users over 2 days - text output below, or you can see [json output](jira-worklog.json).

```
php ./jira-worklog.php  -f='-2 days' -u=chad,jo -o=txt

19h Total Time logged, from 2017-10-03 Tue to 2017-10-05 Thu
only by these users: chad, jo
 as of 2017-10-05 Thu 9:17am CDT

Total logged per issue:
  30m CN-117 Better Frames for art to hang.
  30m CN-130 Print pictures, posters
  15m CN-133 Random Github work
17.5h CN-146 jira-worklog.php
  15m CN-153 Fix dropped SSH connections to Tatanka

Daily Worklogs:
  6h Tue 2017-10-03 -- 30m CN-117, 30m CN-130, 15m CN-133, 4.5h CN-146, 15m CN-153
 13h Wed 2017-10-04 -- 13h CN-146
```

Example of summarizing all time logged from beginning of current month to today for all users
```
time php custom-jira-php/jira-worklog.php -f='first day of this month'

63.8h Total Time logged, 2017-09-01 Fri - 2017-09-22 Fri
 as of 2017-09-22 Fri 2:36pm CDT

Total logged per issue:
   6h CN-4 Easily Restore, Restart, JIRA
   5h CN-8 upgrade Postgres, JIRA, Bitbucket
  15m CN-15 Fix dropbox
  16h CN-29 Blog posts
  30m CN-33 Books to read
  10m CN-99 Android Music Player: Pi, Jet Audio
   2h CN-100 SPP Financials
   2h CN-110 One wheel skateboard
   9h CN-124 Buy gifts
 1.5h CN-125 New backup harddrive 
   1h CN-128 Debug git client asking for password
  10h CN-133 Random Github work
  45m CN-135 Save only front door images where there is change
 1.3h CN-138 Single Speed Brown Bike Maintenance
   3h CN-139 Fix garage door
  20m CN-141 SPP Random Work
   1h CN-142 Switch to ATT Fiber
  30m CN-143 Cancel credit cards
   3h CN-144 Volunteer and Non-profit Work

Daily Worklogs:
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

### Todo

- [x] make sure cmd-line works and web interface work
- [x] support text, html, or json output
- [x] add support for filtering by jira user
- [ ] support case where worklogs are in more than 999 jira issues
- [ ] improve html output
- [ ] add csv output
- [ ] add phpunit tests, lint

