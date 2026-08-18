# How to read the application log

Something failed and you need to know why, without shell access to the server.

Smart Connect writes its own log files and shows them in the dashboard. Every failed request lands there with the file, the line, and the stack trace behind it.

## Prerequisites

- An account with the **View Logs** privilege
- Smart Connect 3.3.0 or later

Roles other than admin need the privilege granted. Open **Roles**, edit the role, and tick the privileges under **View Logs**.

## Open the log

1. Open **Admin** in the dashboard menu, then **Logs**.

2. Select the file for the day you are investigating. The list shows the files newest first and keeps 30 days of each.

3. Read the entries. The newest is at the top.

Each entry shows the severity, the time, and the message. A coloured stripe marks the severity. Red is an error, amber a warning, blue an informational entry.

## Choose the right file

Two files cover each day.

| File | Holds |
| --- | --- |
| `app-YYYY-MM-DD.log` | Failures on the server, including every request that returned a 500 and every API error |
| `client-YYYY-MM-DD.log` | Failures in the browser, reported by whoever had the page open |

If a screen returned an error page, read `app`. If a screen looked normal but a button did nothing, read `client`.

## Find the entry you want

To see only failures, set the level filter to `ERROR and above`. The filter includes everything more severe, so `WARNING and above` also shows errors.

To search, type into the search box and select **Search**. The search matches anywhere in the entry, including the stack trace. It is case-insensitive.

To find a specific API failure, search for its `error_id`. The value appears in the 500 response the caller received, and against the call in **API History**.

To move through the results, use **Older** and **Newer**. Each page holds 50 entries.

## Read the detail behind an entry

Select **details** on an entry. The pane shows the context, followed by the stack trace.

A server entry names the URL that failed, the exception class, and the file and line. A browser entry names the page, the script, the line and column, the browser, and the account that hit it.

Entries with no **details** link carry no context beyond their message.

## Read a browser error

Browser errors reach `client-YYYY-MM-DD.log` on their own. Nobody has to report them, and no setting turns them on.

Three kinds are captured: uncaught script errors, unhandled promise rejections, and errors reported by the code itself through `window.reportClientError`.

Repeats are dropped. The same error from the same line is sent once a minute, and one page load never sends more than 20 reports. A broken screen therefore produces a handful of entries rather than thousands.

Failed images and stylesheets are not captured. They are not errors anyone can act on from a log.

## Take a copy of a file

Select **Download** to save the whole file as plain text. Use this to send a log to whoever maintains Smart Connect.

Downloading is a separate privilege from viewing. A role that can read the viewer cannot necessarily download a file.

## Search a large file

The viewer stops searching after 40 MB and says so. Reaching that message means the file is large and the search ran out of budget, not that the file holds nothing.

To search further back, narrow the search term, or select an older file from the list.

## Verify

Confirm the viewer reflects live failures.

1. Open a URL that does not exist, such as `https://dashboard.example.org/no-such-page`.

2. Open **Logs** and select today's file.

3. Confirm the top entry of `app-YYYY-MM-DD.log` reads `WARNING`, with the message `Request could not be dispatched` and the URL you opened.
