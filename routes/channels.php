<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('election-results.{courseName}', function ($user, $courseName) {
    $userCourse = $user->student?->block?->course?->name;
    return strtoupper((string) $userCourse) === strtoupper((string) $courseName);
});

Broadcast::channel('admin.audit-trail', function ($user) {
    return $user->hasRole('admin');
});
