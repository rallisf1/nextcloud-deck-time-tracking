<?php

return [
    'routes' => [
        ['name' => 'timesheet#getCardTimesheets', 'url' => '/card/{cardId}', 'verb' => 'GET'],
        ['name' => 'timesheet#startTracking', 'url' => '/card/{cardId}', 'verb' => 'POST'],
        ['name' => 'timesheet#stopTracking', 'url' => '/card/{id}', 'verb' => 'PUT'],
        ['name' => 'timesheet#createTimesheet', 'url' => '/timesheet', 'verb' => 'POST'],
        ['name' => 'timesheet#editTimesheet', 'url' => '/timesheet/{id}', 'verb' => 'PUT'],
        ['name' => 'timesheet#deleteTimesheet', 'url' => '/timesheet/{id}', 'verb' => 'DELETE'],
        ['name' => 'timesheet#getAssignedUsers', 'url' => '/card/{cardId}/assignees', 'verb' => 'GET'],
    ],
];