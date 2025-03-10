# Deck Time Tracking

A complementary app for [Deck](https://github.com/nextcloud/deck), because all the other time tracking (Nextcloud) apps either lack Kanban view, Nextcloud Calendar/CalDAV integration, or are just harder to use (corporate project management style).

## Features

- Adds a start/stop button on each Deck card
- Adds a timesheet tab on each card's details
- Only assigned users and users with the edit/manage permission on the board can add time records
- Time records are also visible in the Calendar and link to the associated Deck card
- Notifications on interested parties for start/end timer and timesheet add/edit/delete
- Reminder notification after 1h in case you forgot your timer

## Requirements

- Nextcloud 27+
- Deck App
- [x] Show card ID badge (checked in Deck settings, refresh after enabling)

## Known Bugs

- Doesn't work on upcoming cards (Deck Index Page) or any Deck widget
- You cannot asign a timesheet record to a group, only a user. There's nothing to fix here, just pointing it out.
- A user can be time tracking multiple cards at once. This is more of a feature, as people multi-task all the time. It could make a neat configuration option.
- If you are removed from a card and have an active timer, you can no longer stop it because the button is removed. A manager must edit the timesheet manually.
- When editing / adding timesheet records from the timesheet table seconds are stripped from `start` and `end` fields due to how `datetime-local` works, but it was way easier than using a datepicker library.
- Editing / adding timesheet records doesn't trigger any state change, the timesheet table and the timer buttons do not sync and you need to reload the state yourself (e.g. change boards or refresh).
- A timer can be left to run indefinitely. Ideally there should be a global setting and a cron to limit timers to a certain limit and/or send a notification to the timer's owner.
- The whole thing runs on the browser, on boards with a lot of cards this can be quite demanding for the client's hardware. This is my first nextcloud app and overriding other app templates is way over my head (if possible at all), plus I'm not familiar with Vue either.

## Todo

- Integrate with Activity app for audit logs & notifications
- Integrate with the Analytics app for reports & charts
- Leverage Deck's PermissionService to allow Timesheet permissions by User Group
- Use l10n in more place than only notifications

## Under consideration

- Implement cache
- Add it to Upcoming Cards (Deck's Index Page) and Deck Widgets
- Show total time recorded on each card / stack / board / user. Needs a configurable date range (this year, this month, this week, today).

## FAQ

### Will this be published in the Nextcloud App Store?

Yes, once it is stable. I'll need some feedback first.

### Why the Calendar events are of type VEVENT and not VTODO?

The Deck cards are already VTODO events, timesheet records are more like standard events. Plus the Nextcloud Calendar uses the Due Date for VTODO events, which doesn't work well for timesheet records.

### Who can see my timesheet records in the calendar?

The board owner, the board managers, the card owner, and the card assignees.

### Can this be used to track employee work hours?

Not in the common 9-5 scenario. This helps time tracking people's actions on tasks listed as Nextcloud Deck Cards. The sum of those timesheet records per user should equal working hours as long as your team creates a card and time tracks everything. For shift tracking take a loot at [Shiftplan](https://apps.nextcloud.com/apps/shifts).

### Can you add the X feature?

Open an issue, if the feature is useful for my team I'll certainly add it.