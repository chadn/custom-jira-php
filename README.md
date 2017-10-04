# Custom Jira PHP

## Summary

This repo contains php scripts that use Jira's REST API

## jira-worklog.php

Outputs a summary of hours logged to Jira issues over a given timespan.  This is a simple free alternative to [Tempo Timesheets](https://tempo.io/products/tempo-timesheets)

Example of summarizing all time logged from beginning of current month to today for all users
```
# on linux:
php custom-jira-php/jira-worklog.php  `date -d "this month" "+%Y-%m"` `date "+%Y-%m-%d"` 

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
```

### Todo

- make sure cmd-line works when called from browser
- add support for filtering by jira user
- improve html output
- add csv output
- add phpunit tests, lint

